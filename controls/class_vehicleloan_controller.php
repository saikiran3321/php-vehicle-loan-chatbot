<?php 

use VehicleLoanAssistant\VehicleLoanMcpServer;

class VehicleLoanController {
    private $mongodb_con;
    private $sessionId;
    private $mcpClient;
    private $aiClient;
    private $toolSchemas;
    private $config;

    public function __construct($sessionId) {
        global $mongodb_con;
        $this->mongodb_con = $mongodb_con;
        $this->sessionId = $sessionId;
        
        $this->initializeClients();
        $this->initializeConfig();
        $this->initializeToolSchemas();
    }

    private function initializeClients() {
        $this->mcpClient = class_exists('MCPClient') ? new MCPClient() : null;
        $this->aiClient  = class_exists('AIClient')  ? new AIClient()  : null;
    }

    private function initializeConfig() {
        $this->config = [
            'flow_steps' => [
                'MOBILE_NUMBER' => ['next' => 'OTP_VERIFICATION', 'component' => 'mobile-input', 'action_keys' => []],
                'OTP_VERIFICATION' => ['next' => 'PAN_DETAILS', 'component' => 'otp-input', 'action_keys' => ['send_otp']],
                'PAN_DETAILS' => ['next' => 'BRAND_SELECTION', 'component' => 'pan-upload', 'action_keys' => ['verify_otp']],
                'BRAND_SELECTION' => ['next' => 'MODEL_SELECTION', 'component' => 'brand-selection',  'action_keys' => ['request_pan_details','search_brands']],
                'MODEL_SELECTION' => ['next' => 'USER_DETAILS', 'component' => 'model-selection',  'action_keys' => ['search_models']],
                'USER_DETAILS' => ['next' => 'OFFERS', 'component' => 'user-info-form', 'action_keys' => ['save_user','fetch_offers']],
                'OFFERS' => ['next' => 'COMPLETED', 'component' => 'offers', 'action_keys' => []],
                'COMPLETED' => ['next' => 'COMPLETED', 'component' => 'done', 'action_keys' => []],
            ],
            'validation_patterns' => [
                'mobile' => '/^\d{10}$/',
                'otp' => '/^\d{6}$/',
                'pan' => '/^[A-Z]{5}[0-9]{4}[A-Z]$/',
                'email' => '/^[^\s]+@[^\s]+$/'
            ]
        ];
    }

    private function initializeToolSchemas() {
        $this->toolSchemas = [];
        
        if (!$this->mcpClient) { 
            error_log("MCPClient not available for tool schema initialization");
            return; 
        }
        
        $this->toolSchemas = $this->mcpClient->getToolSchemas();
        
        if (empty($this->toolSchemas)) {
            error_log("WARNING: No tool schemas found in MCPClient");
        }
    }

    private function extractJsonFromText($text) {
        $patterns = [
            '/```json\s*({.*?})\s*```/s',
            '/({.*})/s'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $json = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return ['message' => trim(str_replace($matches[0], '', $text)), 'json' => $json];
                }
            }
        }
        
        return ['message' => $text, 'json' => null];
    }

    private function convertToJson($data) {
        $textFields = [
            'candidates.0.content.parts.0.text',
            'output.0.content.0.text'
        ];
        
        foreach ($textFields as $field) {
            $value = $this->getNestedValue($data, $field);
            if ($value) {
                return $this->extractJsonFromText($value);
            }
        }
        
        return ['message' => $data, 'json' => null];
    }

    private function getNestedValue($array, $path) {
        $keys = explode('.', $path);
        $current = $array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    public function getOrCreateUserState() {
        $s = $this->mongodb_con->find_one('ai_user_states', ['session_id' => $this->sessionId]);
        if ($s) { return $s; }
        $s = [
            'session_id' => $this->sessionId,
            'mobile_number' => null,
            'otp_verified' => false,
            'pan_uploaded' => false,
            'user_info' => [],
            'vehicle_preferences' => ['budget'=>null,'type'=>null,'brands'=>[],'models'=>[]],
            'vehicles' => [],
            'current_vehicle_index' => 0,
            'loan_eligibility' => ['assessed'=>false,'eligible'=>false,'max_loan_amount'=>null,'interest_rate'=>null,'tenure_options'=>[]],
            'current_step' => 'MOBILE_NUMBER',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->mongodb_con->insert('ai_user_states', $s);
        return $s;
    }

    private function updateUserState(array $updates) {
        if (!$updates) return false;
        $updates['updated_at'] = date('Y-m-d H:i:s');
        return $this->mongodb_con->update_one('ai_user_states', ['$set' => $updates], ['session_id' => $this->sessionId]);
    }

    private function buildStateFromInput($userInput, $state) {
        $updates = [];
        $patterns = [
            'mobile_number' => ['pattern' => '/\b(\d{10})\b/', 'field' => 'mobile_number'],
            'otp_submitted' => ['pattern' => '/(?i)\bOTP[:\s-]*([0-9]{6})\b/', 'field' => 'otp_submitted'],
            'name' => ['pattern' => '/(?i)\bname[:\s-]*([a-zA-Z\s]+)/i', 'field' => 'user_info.name'],
            'email' => ['pattern' => '/(?i)\bemail[:\s-]*([^\s]+@[^\s]+)/i', 'field' => 'user_info.email'],
            'income' => ['pattern' => '/(?i)\bincome[:\s-]*([0-9,]+)/i', 'field' => 'user_info.income', 'type' => 'int'],
            'residence_type' => ['field' => 'user_info.residence_type'],
            'employment_type' => ['field' => 'user_info.employment_type'],
            'down_payment' => ['pattern' => '/(?i)\b(?:down.?payment|downpayment)[:\s-]*([0-9,]+)/i', 'field' => 'user_info.down_payment', 'type' => 'int']
        ];

        foreach ($patterns as $key => $config) {
            if($config['pattern']) {
                if(gettype($userInput) == "string") {
                    if (preg_match($config['pattern'], $userInput, $matches)) {
                        $value = trim($matches[1]);
                        if (isset($config['type']) && $config['type'] === 'int') {
                            $value = (int)str_replace(',', '', $value);
                        }
                        $this->setNestedValue($updates, $config['field'], $value);
                    }
                }
            }
        }

        // Special handling for mobile number - also check for common variations
        if (empty($updates['mobile_number'])) {
            $mobilePatterns = [
                '/\b(\d{10})\b/',
                '/\b(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})\b/',
                '/\b\+?91[-.\s]?(\d{10})\b/',
                '/\b(\d{5}[-.\s]?\d{5})\b/'
            ];
            
            foreach ($mobilePatterns as $pattern) {
                if(gettype($userInput) == "string") {
                    if (preg_match($pattern, $userInput, $matches)) {
                        $mobile = preg_replace('/[^0-9]/', '', $matches[1]);
                        if (strlen($mobile) === 10) {
                            $updates['mobile_number'] = $mobile;
                            break;
                        }
                    }
                }
            }
        }

        return $updates;
    }

    private function setNestedValue(&$array, $path, $value) {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
    }

    private function buildPrompt($userInput, $state, $currentVehicleData) {
        $currentStep = $state['current_step'] ?? 'MOBILE_NUMBER';
        $nextStep = $this->config['flow_steps'][$currentStep]['next'] ?? 'COMPLETED';
        $component = $this->config['flow_steps'][$currentStep]['component'] ?? null;

        $prompt = $this->buildBasePrompt($userInput, $currentStep, $nextStep, $component, json_encode($state));
        $prompt .= $this->buildToolSchemas();
        $prompt.= $this->raceDirective();
        /*$prompt .= $this->buildStepSpecificPrompt($currentStep);
        $prompt .= $this->buildResponseFormatPrompt($component, $nextStep);*/

        return $prompt;
    }

    private function buildBasePrompt($userInput, $currentStep, $nextStep, $component, $state) {
        return "User: {$userInput}\nPrevious message: {$state}\nState: {$currentStep}\nNext: {$nextStep}\nComponent: {$component}\n\n";
    }

    private function buildToolSchemas() {
        if (!$this->toolSchemas) { return "No MCP tools available. Work with basic functionality."; }
        $out = ["Available MCP Tools and Schemas:"];
        foreach ($this->toolSchemas as $name => $info) {
            $out[] = "**{$name}**: " . ($info['description'] ?? '');
            $schema = $info['schema'];
            $props  = $schema['properties']['data']['properties'] ?? [];
            $req    = $schema['properties']['data']['required'] ?? [];
            if ($props) {
                $out[] = "Required fields:";
                foreach ($props as $field => $fInfo) {
                    $isReq = in_array($field, $req) ? ' (REQUIRED)' : '';
                    $type  = $fInfo['type'] ?? 'any';
                    $desc  = $fInfo['description'] ?? '';
                    $out[] = "- {$field}: {$type}{$isReq}" . ($desc ? " - {$desc}" : '');
                }
            }
        }
        return implode("\n", $out);
    }

    private function raceDirective() {
    return <<<PROMPT
        ROLE
        - You are a vehicle loan assistant. Keep ALL conversation focused on vehicles and loans. If the user goes off-topic, reply with a brief, polite redirection back to the current step.

        FLOW
        - Steps (in order): MOBILE_NUMBER → OTP_VERIFICATION → PAN_DETAILS → BRAND_SELECTION → MODEL_SELECTION → USER_DETAILS → OFFERS → COMPLETED
        - For the CURRENT step, you may call ONLY the tools listed in that step’s action_keys. Never call tools outside action_keys for that step.
        - If no tool call is needed, return "actions": [] and ask the precise next question needed to proceed.

        STEP SPECS (authoritative mapping)
        - MOBILE_NUMBER       => next: OTP_VERIFICATION, component: mobile-input,      action_keys: [send_otp]
        - OTP_VERIFICATION    => next: PAN_DETAILS,     component: otp-input,          action_keys: [verify_otp]
        - PAN_DETAILS         => next: BRAND_SELECTION, component: pan-upload,         action_keys: [request_pan_details]
        - BRAND_SELECTION     => next: MODEL_SELECTION, component: brand-selection,    action_keys: [search_brands]
        - MODEL_SELECTION     => next: USER_DETAILS,    component: model-selection,    action_keys: [search_models]
        - USER_DETAILS        => next: OFFERS,          component: user-info-form,     action_keys: [save_user]
        - OFFERS              => next: COMPLETED,       component: offers,             action_keys: [fetch_offers]
        - COMPLETED           => next: COMPLETED,       component: done,               action_keys: []

        CONTEXT INPUTS
        - You receive: user_input (free text), current_step, next_step, component, and tool_schemas (a dict keyed by tool name, each with description + JSON schema: properties/types/required/patterns).
        - Always read the schema for the tool you intend to call. Build "data" EXACTLY to that schema (lowercase keys, correct types, include required fields only, omit extraneous).

        OUTPUT (STRICT)
        - Return ONLY valid JSON (no extra text) in this exact shape:
        {
          "actions": [
            { "action": "tool_name", "data": { /* strictly per the tool's schema; lowercase keys */ } }
          ],
          "message": "user-friendly message",
          "component": "<component>",
          "next_step": "<next_step>"
        }
        - Keys MUST be lowercase.
        - "actions" can be an empty array when no tool is needed.
        - Include multiple actions only if the step truly requires more than one (rare; otherwise 0 or 1).

        SCHEMA & VALIDATION RULES
        - Use only tool names exactly as provided in tool_schemas: send_otp, verify_otp, request_pan_details, search_brands, search_models, save_user, fetch_offers.
        - Validate and coerce user input to match schema types/patterns. If data is missing/invalid, do NOT call the tool; return actions: [] and ask for the specific missing field in "message".
        - Examples of constraints you must respect (commonly present in these tools’ schemas):
          - send_otp.data.mobile_number: string matching ^\\d{10}$
          - verify_otp.data.mobile_number: string ^\\d{10}$; verify_otp.data.otp: string ^\\d{6}$
          - request_pan_details.data: { name: string (letters/spaces), dob: string DD-MM-YYYY, pan_number: string ^[A-Z]{5}[0-9]{4}[A-Z]$ }
          - search_brands.data.make: string (search query; allow partial)
          - search_models.data: { make: string, model: string (partial allowed) }
          - save_user.data: { session_id: string, name: string, email: string (email), mobile_number: string ^\\d{10}$, pan: string ^[A-Z]{5}[0-9]{4}[A-Z]$ (optional if not collected), otp_verified: boolean }
          - fetch_offers.data: { loan_amount: integer, tenure_months: integer, interest_rate: number (optional), user_id: string, vehicle_details: { make: string, model: string } }
        - Do NOT echo sensitive values (e.g., full OTP) back to the user in the message; acknowledge safely instead.
        - If the step’s required upstream state is clearly unmet (e.g., trying to select model before brand), ask for that prerequisite and set actions: [].

        MESSAGING
        - "message" should be short, actionable, and specific to what’s needed next (e.g., exact format hints).
        - If a tool is called, "message" should confirm what is happening and what the user should expect or provide next.

        EXAMPLES

        // MOBILE_NUMBER → send_otp
        Input: user provides "9876543210" at MOBILE_NUMBER
        Output:
        {
          "actions": [
            { "action": "send_otp", "data": { "mobile_number": "9876543210" } }
          ],
          "message": "Great! I’ve sent an OTP to 9876543210. Please enter the 6-digit code.",
          "component": "otp-input",
          "next_step": "OTP_VERIFICATION"
        }

        // PAN_DETAILS → request_pan_details
        Input: {name: "Sai", dob: "1998-12-08", pan: "FTRPM0129J"}
        Output:
        {
          "actions": [
            { "action": "request_pan_details", "data": { "name": "RAVI KUMAR", "dob": "05-07-1994", "pan_number": "ABCDE1234F" } }
            { "action": "search_brands", "data": { "make": "" } }
          ],
          "message": "PAN details captured. Next, pick a brand you’re interested in.",
          "component": "brand-selection",
          "next_step": "BRAND_SELECTION"
        }

        GUARDS
        - Never include unknown keys or wrong types in "data".
        - Never call tools not listed in the current step’s action_keys.
        - Never leak internal reasoning; output only the strict JSON.
        - Be conservative: if any required field is missing/invalid per schema, do not call the tool—ask for that field instead.

    PROMPT;
    }

    private function buildStepSpecificPrompt($currentStep) {
        $stepPrompts = [
            'MOBILE_NUMBER' => "\n- Collect mobile number for verification\n- Explain this is for loan application security\n- Use 'send_otp' tool when mobile number is provided",
            'OTP_VERIFICATION' => "\n- Verify OTP sent to mobile\n- Confirm user identity for loan processing\n- Use 'verify_otp' tool when OTP is provided",
            'PAN_DETAILS' => "\n- Collect PAN card details (name, dob, pan_number)\n- Explain this is required for loan eligibility\n- Use 'request_pan_details' tool when PAN information is provided",
            'BRAND_SELECTION' => "\n- IMMEDIATELY use 'search_brands' tool with empty make field to get all available brands\n- Display available brands for user selection\n- Ask user to select their preferred vehicle brand\n- Once user selects a brand, proceed to model selection",
            'MODEL_SELECTION' => "\n- Use 'search_models' tool to get available models for selected brand\n- Help select specific model from chosen brand\n- Consider budget, features, and loan terms\n- Suggest variants that fit requirements",
            // 'VEHICLE_COMPARISON' => "\n- Compare selected vehicles side by side\n- Use 'compare_vehicles' tool for detailed comparison\n- Highlight price, features, and loan differences\n- Help user make informed decision",
            'USER_DETAILS' => "\n- Collect income and employment details\n- Ask for credit score if available\n- Determine down payment capacity\n- Use 'save_user' tool to store user information",
            'LOAN_ELIGIBILITY' => "\n- Assess loan eligibility for selected vehicle\n- Use 'assess_loan_eligibility' tool for calculation\n- Calculate EMI and interest rates\n- Provide personalized loan recommendations",
            'OFFERS' => "\n- Present loan offers based on eligibility\n- Use 'fetch_offers' tool to get available offers\n- Show different tenure and EMI options\n- Guide user to select best offer"
        ];

        return $stepPrompts[$currentStep] ?? '';
    }

    private function buildResponseFormatPrompt($component, $nextStep) {
        return "\n\nRespond with valid JSON in this exact format:\n{\"action\":\"tool_name\",\"data\":{tool_data},\"message\":\"user_message\",\"component\":\"{$component}\",\"next_step\":\"{$nextStep}\"}\n\nIMPORTANT: The 'data' field must contain the exact field names and types as specified in the tool schema above. All data keys should be lowercase.";
    }

    private function systemInstruction() {
        return <<<SYS
        You are a specialized vehicle loan eligibility assistant. Your ONLY focus is vehicle selection and loan eligibility assessment. DO NOT discuss other topics or provide general advice. Your core responsibilities are:

        RESTRICTIONS:
        - ONLY discuss vehicles and loan-related topics
        - If user asks about other subjects, politely redirect to vehicle/loan discussion
        - Focus on practical vehicle selection and financing options

        Always respond with valid JSON only. Follow the flow: MOBILE_NUMBER→OTP_VERIFICATION→PAN_DETAILS→BRAND_SELECTION→MODEL_SELECTION→USER_DETAILS→OFFERS.

        CRITICAL: When using MCP tools, ensure the 'data' field exactly matches the tool's schema requirements - use the exact field names, types, and patterns specified. All data keys must be lowercase.
        SYS;
    }

    private function processAIResponse($userInput, $state, $currentVehicleData) {
        $prompt = $this->buildPrompt($userInput, $state, $currentVehicleData);
        $systemInstruction = $this->systemInstruction();

        $aiResponse = $this->aiClient->generate([
            'prompt' => $prompt,
            'system_instruction' => $systemInstruction
        ]);

        if (!isset($aiResponse['api_status']) || !$aiResponse['api_status'] || $aiResponse['status'] !== 200) {
            return ['success' => false, 'error' => 'AI service unavailable.'];
        }

        $parsed = $this->convertToJson($aiResponse['content']);
        $response = $parsed['json'] ?? $parsed;

        if (!is_array($response)) {
            return ['success' => false, 'error' => 'Invalid response format.'];
        }

        // Ensure data keys are lowercase
        if (isset($response['data']) && is_array($response['data'])) {
            $response['data'] = $this->convertKeysToLowercase($response['data']);
        }

        return ['success' => true, 'response' => $response];
    }

    private function convertKeysToLowercase($array) {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = strtolower($key);
            if (is_array($value)) {
                $result[$newKey] = $this->convertKeysToLowercase($value);
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    public function processMessage($userInput) {
        if (empty($userInput)) {
            return ['status' =>'fail', 'error' => "Hi! I'm your Vehicle Loan Assistant. How can I help you?"];
        }

        $state = $this->getOrCreateUserState();
        if (!$state) {
            return ['status' =>'fail', 'error' => 'Unable to initialize session. Please try again.'];
        }

        $this->logUserInput($userInput);

        if ($this->isGreetingMessage($userInput)) {
            return $this->handleGreetingMessage($state);
        }

        $inputUpdates = $this->buildStateFromInput($userInput, $state);
        if (!empty($inputUpdates)) {
            $this->updateUserState($inputUpdates);
            $state = array_merge($state, $inputUpdates);
        }

        $currentVehicleData = $this->getCurrentVehicleData($state);
        $aiResult = $this->processAIResponse($userInput, $state, $currentVehicleData);
        
        if (!$aiResult['success']) {
            return ['status' =>'fail', 'error' => $aiResult['error']];
        }

        $aiResponse = $aiResult['response'];

        $mcp_ai_response = $aiResponse['actions'][0];

        $actionKey = $mcp_ai_response['action'];
        $actionData = $mcp_ai_response['data'] ?? [];
        $mcpResult = $this->callMCP($actionKey,$actionData,$state);
        // $mcpResult = $this->processMCPAction($aiResponse, $state);

        if (!empty($mcpResult['success'])) {
            $targetNext = $aiResponse['next_step'] 
                ?? ($this->config['flow_steps'][$currentStep]['next'] ?? $currentStep);
            if (!empty($targetNext) && $targetNext !== $currentStep) {
                $this->updateUserState(['current_step' => $targetNext]);
                $state['current_step'] = $targetNext;
            }
        }
        
        $finalResponse = $this->buildFinalResponse($aiResponse, $mcpResult, $state);
        
        return [
            'status' => 'Ok',
            'details' => $finalResponse,
            'state' => $state
        ];
    }

    private function callMCP($tool, $data, $state): array {
        if (!$this->mcpClient) { return ['success'=>false, 'error'=>'MCP client not available']; }
        if (!isset($this->toolSchemas[$tool])) { return ['success'=>false, 'error'=>'Unknown tool: '.$tool]; }

       
        $schema = $this->toolSchemas[$tool]['schema'];
        $req    = $schema['properties']['data']['required'] ?? [];
        foreach ($req as $field) {
            if (!array_key_exists($field, $data)) {
                return ['success'=>false, 'error'=>"Missing required field '{$field}' for {$tool}"];
            }
        }

        if (method_exists($this->mcpClient, 'call')) {
            $res = $this->mcpClient->call($tool, ['data'=>$data]);
        } elseif (method_exists($this->mcpClient, $tool)) {
            $res = $this->mcpClient->{$tool}(['data'=>$data]);
        } else {
            return ['success'=>false, 'error'=>'MCP client cannot invoke tool: '.$tool];
        }
       
        $this->logMCPAction($tool, $data, $res);
        return is_array($res) ? $res : ['success'=>true, 'data'=>$res];
    }

    private function isGreetingMessage($userInput) {
        $greetings = [
            '/^(hi|hello|hey|good morning|good afternoon|good evening)$/i',
            '/^(how are you|how\'s it going|what\'s up)$/i',
            '/^(start|begin|help|guide)$/i',
            '/^(vehicle loan|car loan|bike loan)$/i'
        ];

        foreach ($greetings as $pattern) {
            if(gettype($userInput) == "string") {
                if (preg_match($pattern, $userInput)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function handleGreetingMessage($state) {
        $stepInfo = $this->getCurrentStepInfo($state);
        $component = $stepInfo['component'];
        $nextStep = $stepInfo['next_step'];
        
        return [
            'status' => 'Ok',
            'details' => [
                'from' => 'bot',
                'type' => 'text',
                'content' => "Hello! I'm your Vehicle Loan Assistant. I'll help you get a vehicle loan step by step.\n\nTo begin, please provide your 10-digit mobile number. I'll send you an OTP for verification to ensure your security.",
                'component' => $component,
                'show' => true,
                'next_step' => $nextStep,
                'success' => true,
                'error_message' => null,
                'data' => [
                    'instruction' => 'Please enter your 10-digit mobile number',
                    'examples' => ['9876543210', '987-654-3210', '+91 9876543210']
                ]
            ],
            'state' => $state
        ];
    }

    private function getCurrentStepInfo($state) {
        $currentStep = $state['current_step'] ?? 'MOBILE_NUMBER';
        $stepInfo = $this->config['flow_steps'][$currentStep] ?? ['next' => 'COMPLETED', 'component' => 'general'];
        
        return [
            'current_step' => $currentStep,
            'next_step' => $stepInfo['next'] ?? 'COMPLETED',
            'component' => $stepInfo['component'] ?? 'general',
            'step_info' => $stepInfo
        ];
    }

    private function getFlowDebugInfo($state) {
        $stepInfo = $this->getCurrentStepInfo($state);
        $currentStep = $stepInfo['current_step'];
        
        $flowDebug = [
            'current_step' => $currentStep,
            'all_steps' => $this->config['flow_steps'],
            'step_progression' => [
                'MOBILE_NUMBER' => 'OTP_VERIFICATION',
                'OTP_VERIFICATION' => 'PAN_DETAILS',
                'PAN_DETAILS' => 'BRAND_SELECTION',
                'BRAND_SELECTION' => 'MODEL_SELECTION',
                'MODEL_SELECTION' => 'VEHICLE_COMPARISON',
                'VEHICLE_COMPARISON' => 'USER_DETAILS',
                'USER_DETAILS' => 'LOAN_ELIGIBILITY',
                'LOAN_ELIGIBILITY' => 'OFFERS',
                'OFFERS' => 'COMPLETED'
            ],
            'component_mapping' => []
        ];
        
        foreach ($this->config['flow_steps'] as $step => $info) {
            $flowDebug['component_mapping'][$step] = [
                'component' => $info['component'],
                'next' => $info['next']
            ];
        }
        
        return $flowDebug;
    }

    private function buildFinalResponse($aiResponse, $mcpResult, $state) {
        $latestState = $this->getOrCreateUserState();
        $currentStep = $latestState['current_step'] ?? $state['current_step'] ?? 'MOBILE_NUMBER';

        $flowConfig  = $this->config['flow_steps'];
        $stepInfo    = $flowConfig[$currentStep] ?? ['next' => 'COMPLETED', 'component' => 'general'];

        // Prefer AI-proposed component/next_step if available (and MCP succeeded)
        $component = $aiResponse['component'] ?? $stepInfo['component'];
        $nextStep  = $aiResponse['next_step']  ?? $stepInfo['next'];

        if (empty($mcpResult['success'])) {
            // On failure, keep the UI aligned with current step, not the AI proposal
            $component = $stepInfo['component'];
            $nextStep  = $stepInfo['next'];
        }

        return [
            'from' => 'bot',
            'type' => 'text',
            'content' => $aiResponse['message'] ?? 'Processing your request...',
            'component' => $component,
            'show' => true,
            'next_step' => $nextStep,
            'success' => !empty($mcpResult['success']),
            'error_message' => !empty($mcpResult['success']) ? null : ($mcpResult['error'] ?? 'Action failed'),
            'debug_info' => [
                'ai_response' => $aiResponse,
                'mcp_result' => $mcpResult,
                'current_step' => $currentStep,
                'component_mapped' => $stepInfo['component'],
                'state_current_step' => $state['current_step'] ?? 'NOT_SET',
                'latest_state_step' => $latestState['current_step'] ?? 'NOT_SET',
                'component_source' => 'flow_config_' . $currentStep
            ]
        ];
    }

    private function getCurrentVehicleData($state) {
        if (empty($state['vehicles']) || !is_array($state['vehicles'])) {
            return ['condition' => []];
        }

        $currentIndex = $state['current_vehicle_index'] ?? 0;
        if (isset($state['vehicles'][$currentIndex])) {
            $vehicle = $state['vehicles'][$currentIndex];
            return $vehicle instanceof MongoDB\Model\BSONDocument ? 
                   $vehicle->getArrayCopy() : $vehicle;
        }

        return ['condition' => []];
    }

    private function logUserInput($userInput) {
        $dataToInsert = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_input' => $userInput,
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'user_input'
        ];
        $this->mongodb_con->insert('ai_user_inputs', $dataToInsert);
    }

    private function logMCPAction($actionKey, $data, $result) {
        $dataToInsert = [
            'session_id' => $this->sessionId,
            'action_key' => $actionKey,
            'action_data' => $data,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'mcp_action'
        ];
        $this->mongodb_con->insert('ai_user_inputs', $dataToInsert);
    }
}