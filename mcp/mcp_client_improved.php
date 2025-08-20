<?php

require_once __DIR__ . '/../vendor/autoload.php';

use VehicleLoanAssistant\VehicleLoanMcpServer;

/**
 * Enhanced MCP Client with improved error handling and better integration
 */
class MCPClientImproved {
    private $serverUrl;
    private $timeout;
    private $mcpServerInstance;
    private $retryAttempts;
    private $retryDelay;
    
    public function __construct($serverUrl = 'http://localhost:9081', $timeout = 30, $retryAttempts = 3, $retryDelay = 1) {
        $this->serverUrl = $serverUrl;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
        
        // Initialize MCP server instance
        global $mongodb_con;
        try {
            $this->mcpServerInstance = new VehicleLoanMcpServer($mongodb_con);
        } catch (Exception $e) {
            error_log("Failed to initialize MCP server: " . $e->getMessage());
            throw new RuntimeException("MCP server initialization failed");
        }
    }
    
    /**
     * Make HTTP request to MCP server with retry logic
     */
    private function makeRequest($tool, $params = []) {
        $data = [
            'tool' => $tool,
            'params' => $params,
            'timestamp' => time(),
            'request_id' => uniqid('mcp_', true)
        ];
        
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $this->serverUrl . '/mcp-tools',
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'User-Agent: VehicleLoanMCP/1.0'
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    $lastError = 'cURL Error: ' . $error;
                    if ($attempt < $this->retryAttempts) {
                        sleep($this->retryDelay * $attempt);
                        continue;
                    }
                    break;
                }
                
                if ($httpCode !== 200) {
                    $lastError = "HTTP Error: {$httpCode}";
                    if ($attempt < $this->retryAttempts && $httpCode >= 500) {
                        sleep($this->retryDelay * $attempt);
                        continue;
                    }
                    break;
                }
                
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = 'JSON Decode Error: ' . json_last_error_msg();
                    if ($attempt < $this->retryAttempts) {
                        sleep($this->retryDelay * $attempt);
                        continue;
                    }
                    break;
                }
                
                return $decoded;
                
            } catch (Exception $e) {
                $lastError = 'Request Exception: ' . $e->getMessage();
                if ($attempt < $this->retryAttempts) {
                    sleep($this->retryDelay * $attempt);
                    continue;
                }
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError ?? 'Unknown error occurred',
            'attempts' => $this->retryAttempts
        ];
    }
    
    /**
     * Validate parameters before making requests
     */
    private function validateParams($tool, $params) {
        $validations = [
            'send_otp' => [
                'required' => ['mobile_number'],
                'patterns' => ['mobile_number' => '/^\d{10}$/']
            ],
            'verify_otp' => [
                'required' => ['mobile_number', 'otp'],
                'patterns' => [
                    'mobile_number' => '/^\d{10}$/',
                    'otp' => '/^\d{6}$/'
                ]
            ],
            'request_pan' => [
                'required' => ['session_id']
            ],
            'search_brands' => [
                'required' => [],
                'optional' => ['query']
            ],
            'search_models' => [
                'required' => ['make'],
                'optional' => ['query']
            ],
            'save_user' => [
                'required' => ['session_id', 'name', 'email', 'mobile_number'],
                'patterns' => [
                    'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
                    'mobile_number' => '/^\d{10}$/'
                ]
            ],
            'fetch_offers' => [
                'required' => ['make', 'model'],
                'optional' => ['loan_amount', 'tenure']
            ]
        ];
        
        if (!isset($validations[$tool])) {
            return ['valid' => false, 'error' => "Unknown tool: {$tool}"];
        }
        
        $validation = $validations[$tool];
        
        // Check required parameters
        foreach ($validation['required'] as $required) {
            if (!isset($params[$required]) || empty($params[$required])) {
                return ['valid' => false, 'error' => "Missing required parameter: {$required}"];
            }
        }
        
        // Check patterns
        if (isset($validation['patterns'])) {
            foreach ($validation['patterns'] as $param => $pattern) {
                if (isset($params[$param]) && !preg_match($pattern, $params[$param])) {
                    return ['valid' => false, 'error' => "Invalid format for parameter: {$param}"];
                }
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Execute MCP tool with validation and error handling
     */
    private function executeTool($tool, $params = []) {
        // Validate parameters
        $validation = $this->validateParams($tool, $params);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }
        
        try {
            // Try direct method call first (for local testing)
            if ($this->mcpServerInstance && method_exists($this->mcpServerInstance, $tool)) {
                return call_user_func_array([$this->mcpServerInstance, $tool], array_values($params));
            }
            
            // Fallback to HTTP request
            return $this->makeRequest($tool, $params);
            
        } catch (Exception $e) {
            error_log("MCP tool execution error ({$tool}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Tool execution failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send OTP to mobile number
     */
    public function sendOtp($mobileNumber) {
        return $this->executeTool('sendOtp', [$mobileNumber]);
    }
    
    /**
     * Verify OTP for mobile number
     */
    public function verifyOtp($mobileNumber, $otp) {
        return $this->executeTool('verifyOtp', [$mobileNumber, $otp]);
    }
    
    /**
     * Request PAN card upload
     */
    public function requestPan($sessionId) {
        return $this->executeTool('requestPan', [$sessionId]);
    }
    
    /**
     * Search vehicle brands
     */
    public function searchBrands($query = '') {
        return $this->executeTool('searchBrands', [$query]);
    }
    
    /**
     * Search vehicle models by brand
     */
    public function searchModels($make, $query = '') {
        return $this->executeTool('searchModels', [$make, $query]);
    }
    
    /**
     * Save user information
     */
    public function saveUser($userData) {
        if (!is_array($userData)) {
            return [
                'success' => false,
                'error' => 'User data must be an array'
            ];
        }
        
        return $this->executeTool('saveUser', [$userData]);
    }
    
    /**
     * Fetch loan offers
     */
    public function fetchOffers($loanData) {
        if (!is_array($loanData)) {
            return [
                'success' => false,
                'error' => 'Loan data must be an array'
            ];
        }
        
        return $this->executeTool('fetchOffers', [$loanData]);
    }
    
    /**
     * Get MCP server capabilities
     */
    public function getCapabilities() {
        try {
            if ($this->mcpServerInstance && method_exists($this->mcpServerInstance, 'getCapabilities')) {
                return $this->mcpServerInstance->getCapabilities();
            }
            
            return $this->makeRequest('get_capabilities', []);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get capabilities: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Health check for MCP server
     */
    public function healthCheck() {
        try {
            $startTime = microtime(true);
            
            if ($this->mcpServerInstance) {
                $response = $this->searchBrands('test');
                $responseTime = (microtime(true) - $startTime) * 1000;
                
                return [
                    'success' => true,
                    'status' => 'healthy',
                    'response_time_ms' => round($responseTime, 2),
                    'server_type' => 'direct'
                ];
            }
            
            $response = $this->makeRequest('health', []);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => isset($response['success']) ? $response['success'] : false,
                'status' => $response['status'] ?? 'unknown',
                'response_time_ms' => round($responseTime, 2),
                'server_type' => 'http'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Batch execute multiple tools
     */
    public function batchExecute($operations) {
        if (!is_array($operations)) {
            return [
                'success' => false,
                'error' => 'Operations must be an array'
            ];
        }
        
        $results = [];
        $hasErrors = false;
        
        foreach ($operations as $index => $operation) {
            if (!isset($operation['tool']) || !isset($operation['params'])) {
                $results[$index] = [
                    'success' => false,
                    'error' => 'Invalid operation format'
                ];
                $hasErrors = true;
                continue;
            }
            
            $results[$index] = $this->executeTool($operation['tool'], $operation['params']);
            if (!$results[$index]['success']) {
                $hasErrors = true;
            }
        }
        
        return [
            'success' => !$hasErrors,
            'results' => $results,
            'total_operations' => count($operations),
            'failed_operations' => array_sum(array_map(fn($r) => !$r['success'] ? 1 : 0, $results))
        ];
    }
}

?>
