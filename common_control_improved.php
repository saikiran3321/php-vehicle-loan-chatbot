<?php
require_once("controls/class_ai_client.php");
require_once("mcp/mcp_client.php");

/**
 * Enhanced Vehicle Loan Assistant Controller
 * Improved version with better error handling, cleaner code, and enhanced MCP integration
 */

class VehicleLoanController {
    private $mongodb_con;
    private $sessionId;
    private $mcpClient;
    private $aiClient;

    public function __construct($mongodb_connection, $sessionId) {
        $this->mongodb_con = $mongodb_connection;
        $this->sessionId = $sessionId;
        $this->mcpClient = new MCPClient();
        $this->aiClient = new AIClient();
    }

    /**
     * Extract JSON from AI response text
     */
    private function extractJsonFromText($text) {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*({(?:[^{}]|(?R))*})\s*```/s', $text, $matches)) {
            $innerJson = $matches[1];
            $decodedInnerJson = json_decode($innerJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'message' => trim(str_replace($matches[0], '', $text)),
                    'json' => $decodedInnerJson
                ];
            }
        }

        // Try direct JSON decode
        $decodedJson = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
            return ['message' => '', 'json' => $decodedJson];
        }

        // Try to find JSON within text
        if (preg_match('/({(?:[^{}]|(?R))*})/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [
                    'message' => trim(str_replace($matches[1], '', $text)),
                    'json' => $decoded
                ];
            }
        }

        return ['message' => $text, 'json' => null];
    }

    /**
     * Convert AI response to structured format
     */
    private function convertToJson($data) {
        $result = ['json' => null, 'message' => null];

        // Handle Gemini response format
        if (isset($data['candidates'])) {
            foreach ($data['candidates'] as &$candidate) {
                if (!isset($candidate['content']['parts'])) continue;
                foreach ($candidate['content']['parts'] as &$part) {
                    if (isset($part['text'])) {
                        $parsed = $this->extractJsonFromText($part['text']);
                        $part['text'] = $parsed;
                        if (is_array($parsed) && isset($parsed['json'])) {
                            $result['json'] = $parsed['json'];
                            $result['message'] = $parsed['message'];
                        }
                    }
                }
            }
        }

        // Handle ChatGPT response format
        if (isset($data['output'])) {
            foreach ($data['output'] as &$outputItem) {
                if (!isset($outputItem['content'])) continue;
                foreach ($outputItem['content'] as &$contentPart) {
                    if (isset($contentPart['text'])) {
                        $parsed = $this->extractJsonFromText($contentPart['text']);
                        $contentPart['text'] = $parsed;
                        if (is_array($parsed) && isset($parsed['json'])) {
                            $result['json'] = $parsed['json'];
                            $result['message'] = $parsed['message'];
                        }
                    }
                }
            }
        }

        // Handle string response
        if ($result['json'] === null && is_string($data)) {
            $parsed = $this->extractJsonFromText($data);
            if (is_array($parsed) && isset($parsed['json'])) {
                $result['json'] = $parsed['json'];
                $result['message'] = $parsed['message'];
            }
        }

        return $result;
    }

    /**
     * Get or create user state with improved error handling
     */
    public function getOrCreateUserState() {
        try {
            $existingState = $this->mongodb_con->find_one("ai_user_states", ['session_id' => $this->sessionId]);

            if ($existingState) {
                return $existingState instanceof MongoDB\Model\BSONDocument ? 
                       $existingState->getArrayCopy() : $existingState;
            }

            $defaultState = [
                'session_id' => $this->sessionId,
                'mobile_number' => null,
                'otp_submitted' => null,
                'otp_verified' => false,
                'pan_uploaded' => false,
                'user_info' => [],
                'vehicles' => [],
                'current_vehicle_index' => 0,
                'current_step' => 'MOBILE_NUMBER',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->mongodb_con->insert('ai_user_states', $defaultState);
            return $defaultState;
        } catch (Exception $e) {
            error_log("Error in getOrCreateUserState: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user state with validation
     */
    public function updateUserState($updates) {
        try {
            if (!is_array($updates) || empty($updates)) {
                return false;
            }

            $updates['updated_at'] = date('Y-m-d H:i:s');
            return $this->mongodb_con->update_one(
                'ai_user_states',
                ['$set' => $updates],
                ['session_id' => $this->sessionId]
            );
        } catch (Exception $e) {
            error_log("Error in updateUserState: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current vehicle data with safety checks
     */
    public function getCurrentVehicleData($state) {
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

    /**
     * Update current vehicle data
     */
    public function updateCurrentVehicleData($vehicleData) {
        $state = $this->getOrCreateUserState();
        if (!$state) return false;

        $currentIndex = $state['current_vehicle_index'] ?? 0;

        if (empty($state['vehicles'])) {
            $state['vehicles'] = [['condition' => []]];
        }

        $currentVehicle = $state['vehicles'][$currentIndex] ?? ['condition' => []];
        if ($currentVehicle instanceof MongoDB\Model\BSONDocument) {
            $currentVehicle = $currentVehicle->getArrayCopy();
        }

        $state['vehicles'][$currentIndex] = array_merge($currentVehicle, $vehicleData);
        return $this->updateUserState(['vehicles' => $state['vehicles']]);
    }

    /**
     * Build state updates from user input
     */
    public function buildStateFromInput($userInput, $state) {
        $updates = [];

        // Extract mobile number
        if (preg_match('/\b(\d{10})\b/', $userInput, $matches)) {
            if (empty($state['mobile_number'])) {
                $updates['mobile_number'] = $matches[1];
            }
        }

        // Extract OTP
        if (preg_match('/(?i)\bOTP[:\s-]*([0-9]{6})\b/', $userInput, $matches)) {
            $updates['otp_submitted'] = $matches[1];
        }

        // Extract user information patterns
        if (preg_match('/(?i)\bname[:\s-]*([a-zA-Z\s]+)/i', $userInput, $matches)) {
            $updates['user_info']['name'] = trim($matches[1]);
        }

        if (preg_match('/(?i)\bemail[:\s-]*([^\s]+@[^\s]+)/i', $userInput, $matches)) {
            $updates['user_info']['email'] = trim($matches[1]);
        }

        return $updates;
    }

    /**
     * Enhanced MCP action validation
     */
    public function shouldExecuteMCPAction($actionKey, $data) {
        $validations = [
            'SEND_OTP' => fn($d) => !empty($d['mobile_number']) && preg_match('/^\d{10}$/', $d['mobile_number']),
            'VERIFY_OTP' => fn($d) => !empty($d['mobile_number']) && !empty($d['otp_submitted']) && preg_match('/^\d{6}$/', $d['otp_submitted']),
            'REQUEST_PAN' => fn($d) => isset($d['otp_verified']) && $d['otp_verified'] === true,
            'SEARCH_BRANDS' => fn($d) => true,
            'SEARCH_MODELS' => fn($d) => !empty($d['make']),
            'SAVE_USER' => fn($d) => !empty($d['name']) && !empty($d['email']) && filter_var($d['email'], FILTER_VALIDATE_EMAIL),
            'FETCH_OFFERS' => fn($d) => !empty($d['make']) && !empty($d['model']) && isset($d['user_info_saved'])
        ];

        return isset($validations[$actionKey]) ? $validations[$actionKey]($data) : false;
    }

    /**
     * Execute MCP action with error handling
     */
    public function executeMCPAction($actionKey, $data) {
        try {
            $actions = [
                'SEND_OTP' => fn($d) => $this->mcpClient->sendOtp($d['mobile_number']),
                'VERIFY_OTP' => fn($d) => $this->mcpClient->verifyOtp($d['mobile_number'], $d['otp_submitted']),
                'REQUEST_PAN' => fn($d) => $this->mcpClient->requestPan($d['session_id'] ?? $this->sessionId),
                'SEARCH_BRANDS' => fn($d) => $this->mcpClient->searchBrands($d['query'] ?? ''),
                'SEARCH_MODELS' => fn($d) => $this->mcpClient->searchModels($d['make'] ?? '', $d['query'] ?? ''),
                'SAVE_USER' => fn($d) => $this->mcpClient->saveUser($d),
                'FETCH_OFFERS' => fn($d) => $this->mcpClient->fetchOffers($d)
            ];

            if (!isset($actions[$actionKey])) {
                return ['success' => false, 'error' => 'Unknown action: ' . $actionKey];
            }

            return $actions[$actionKey]($data);
        } catch (Exception $e) {
            error_log("Error executing MCP action {$actionKey}: " . $e->getMessage());
            return ['success' => false, 'error' => 'Action execution failed: ' . $e->getMessage()];
        }
    }

    /**
     * Enhanced system instruction for better AI responses
     */
    public function getSystemInstruction() {
        return "You are an intelligent Vehicle Loan Assistant that helps users through a structured loan application process. 

CORE PRINCIPLES:
- Always respond with valid JSON only
- Guide users step-by-step through the loan process
- Be conversational yet professional
- Handle multiple vehicle inquiries seamlessly
- Provide clear, actionable responses

FLOW STEPS:
1. MOBILE_NUMBER: Collect and validate 10-digit mobile number
2. OTP_VERIFICATION: Send and verify OTP
3. PAN_UPLOAD: Request PAN card upload
4. BRAND_SELECTION: Help select vehicle brand
5. MODEL_SELECTION: Help select vehicle model
6. USER_DETAILS: Collect personal information
7. OFFERS: Present loan offers

RESPONSE FORMAT:
Always return JSON with these fields:
- from: 'bot'
- type: 'text' or 'component'
- content: Clear, helpful message
- component: Component name if needed
- show: true
- action_keys: MCP action to execute
- vehicle_data: Vehicle information updates
- state_updates: Session state updates
- mcp_data: Additional MCP data

BEHAVIOR:
- Ask for one piece of information at a time
- Validate input before proceeding
- Handle errors gracefully
- Support multiple vehicle inquiries
- Provide helpful guidance and explanations";
    }

    /**
     * Generate enhanced AI prompt
     */
    public function generatePrompt($userInput, $state, $currentVehicleData) {
        $prompt = "You are a Vehicle Loan Assistant. Respond with JSON only.

CURRENT CONTEXT:
- User Input: {$userInput}
- Session State: " . json_encode($state, JSON_UNESCAPED_SLASHES) . "
- Current Vehicle: " . json_encode($currentVehicleData, JSON_UNESCAPED_SLASHES) . "

INSTRUCTIONS:
1. Analyze the user input and current state
2. Determine the next appropriate step in the loan process
3. Generate a helpful response with the correct component
4. Include necessary MCP actions
5. Update state as needed

RESPONSE SCHEMA:
{
  \"from\": \"bot\",
  \"type\": \"text|component\",
  \"content\": \"Your helpful message here\",
  \"component\": \"component-name or null\",
  \"show\": true,
  \"action_keys\": \"MCP_ACTION_NAME or null\",
  \"vehicle_data\": {\"condition\": {}},
  \"state_updates\": {},
  \"mcp_data\": {}
}

Generate appropriate response based on current context.";

        return $prompt;
    }

    /**
     * Process user message with enhanced error handling
     */
    public function processMessage($userInput) {
        try {
            // Validate input
            if (empty(trim($userInput))) {
                return [
                    'status' => 'fail',
                    'error' => ['error' => "Hi! I'm your Vehicle Loan Assistant. How can I help you?"]
                ];
            }

            // Get current state
            $state = $this->getOrCreateUserState();
            if (!$state) {
                return [
                    'status' => 'fail',
                    'error' => ['error' => 'Unable to initialize session. Please try again.']
                ];
            }

            // Log user input
            $this->logUserInput($userInput);

            // Build state updates from input
            $inputUpdates = $this->buildStateFromInput($userInput, $state);
            if (!empty($inputUpdates)) {
                $this->updateUserState($inputUpdates);
                $state = array_merge($state, $inputUpdates);
            }

            // Handle new vehicle requests
            if (preg_match('/(?i)(new|another|different|more)\s+(vehicle|car|bike|loan)/i', $userInput)) {
                $state = $this->addNewVehicleToState($state);
            }

            $currentVehicleData = $this->getCurrentVehicleData($state);

            // Generate AI prompt
            $prompt = $this->generatePrompt($userInput, $state, $currentVehicleData);
            $systemInstruction = $this->getSystemInstruction();

            // Get AI response
            $aiResponse = $this->aiClient->generate([
                'prompt' => $prompt,
                'system_instruction' => $systemInstruction
            ]);

            if (!isset($aiResponse['api_status']) || !$aiResponse['api_status'] || $aiResponse['status'] !== 200) {
                return [
                    'status' => 'fail',
                    'error' => ['error' => 'AI service temporarily unavailable. Please try again.']
                ];
            }

            // Parse AI response
            $parsed = $this->convertToJson($aiResponse['content']);
            $apiResponse = $parsed['json'] ?? $parsed;

            if (!is_array($apiResponse)) {
                return [
                    'status' => 'fail',
                    'error' => ['error' => 'Invalid response format. Please try again.']
                ];
            }

            // Execute MCP actions if needed
            if (isset($apiResponse['action_keys']) && !empty($apiResponse['action_keys'])) {
                $mcpData = array_merge($state, [
                    'session_id' => $this->sessionId,
                    'user_input' => $userInput
                ]);

                if (isset($apiResponse['vehicle_data'])) {
                    $mcpData = array_merge($mcpData, $apiResponse['vehicle_data']);
                }

                if ($this->shouldExecuteMCPAction($apiResponse['action_keys'], $mcpData)) {
                    $mcpResult = $this->executeMCPAction($apiResponse['action_keys'], $mcpData);
                    $apiResponse['mcp_result'] = $mcpResult;

                    // Apply MCP state updates
                    if (isset($mcpResult['state_updates'])) {
                        $this->updateUserState($mcpResult['state_updates']);
                        $state = array_merge($state, $mcpResult['state_updates']);
                    }
                } else {
                    $apiResponse['mcp_result'] = [
                        'success' => false,
                        'error' => 'Required data not available for action: ' . $apiResponse['action_keys']
                    ];
                }
            }

            // Apply AI response state updates
            if (isset($apiResponse['state_updates']) && !empty($apiResponse['state_updates'])) {
                $this->updateUserState($apiResponse['state_updates']);
            }

            // Update vehicle data
            if (isset($apiResponse['vehicle_data']) && !empty($apiResponse['vehicle_data'])) {
                $this->updateCurrentVehicleData($apiResponse['vehicle_data']);
            }

            return [
                'status' => 'Ok',
                'details' => $apiResponse,
                'state' => $state
            ];

        } catch (Exception $e) {
            error_log("Error in processMessage: " . $e->getMessage());
            return [
                'status' => 'fail',
                'error' => ['error' => 'An unexpected error occurred. Please try again.']
            ];
        }
    }

    /**
     * Log user input for analytics
     */
    private function logUserInput($userInput) {
        try {
            $dataToInsert = [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_input' => $userInput,
                'session_id' => $this->sessionId,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->mongodb_con->insert('ai_user_inputs', $dataToInsert);
        } catch (Exception $e) {
            error_log("Error logging user input: " . $e->getMessage());
        }
    }

    /**
     * Add new vehicle to state
     */
    private function addNewVehicleToState($state) {
        $newVehicle = ['condition' => []];
        $state['vehicles'][] = $newVehicle;
        $state['current_vehicle_index'] = count($state['vehicles']) - 1;

        $this->updateUserState([
            'vehicles' => $state['vehicles'],
            'current_vehicle_index' => $state['current_vehicle_index']
        ]);

        return $state;
    }
}

// Main request handler
if (isset($_POST['action']) && $_POST['action'] === "sendMessage") {
    header('Content-Type: application/json');
    
    try {
        $sessionId = $_POST['sessionId'] ?? session_id();
        $userInput = trim($_POST['chatInput'] ?? '');
        
        $controller = new VehicleLoanController($mongodb_con, $sessionId);
        $response = $controller->processMessage($userInput);
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        error_log("Fatal error in main handler: " . $e->getMessage());
        echo json_encode([
            'status' => 'fail',
            'error' => ['error' => 'System error. Please try again later.']
        ], JSON_PRETTY_PRINT);
    }
    exit;
}
?>
