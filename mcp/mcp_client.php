<?php

require_once __DIR__ . '/../vendor/autoload.php';
use VehicleLoanAssistant\VehicleLoanMcpServer;

class MCPClient {
    private $mcpServerInstance;
    private $toolSchemas;
    
    public function __construct() {
        try {
            $this->mcpServerInstance = new VehicleLoanMcpServer();
            
            if ($this->mcpServerInstance) {
                $this->initializeToolSchemas();
            } else {
                throw new Exception("VehicleLoanMcpServer instantiation returned null");
            }
            
        } catch (Error $e) {
            error_log("MCPClient Debug - Error initializing MCP server instance (Error): " . $e->getMessage());
            error_log("MCPClient Debug - Error file: " . $e->getFile() . " line: " . $e->getLine());
            throw $e;
        }
        
        if ($this->mcpServerInstance) {
            error_log("MCPClient Debug - Initialization: SUCCESS");
        } else {
            error_log("MCPClient Debug - Initialization: FAILED");
        }
    }

    public function getCapabilities(): array
    {
        try {
            return $this->mcpServerInstance->getCapabilities();
        } catch (\Throwable $e) {
            error_log("MCPClient Error getting capabilities: " . $e->getMessage());
            throw new Exception('Failed to get server capabilities: ' . $e->getMessage());
        }
    }

    public function healthCheck(): array
    {
        return ['ok' => true, 'message' => 'Server instance is alive'];
    }

    public function getStatus(): array
    {
        return [
            'mcp_server_available' => true,
            'tool_schemas_count' => count($this->toolSchemas ?? []),
            'available_methods' => array_keys($this->toolSchemas ?? [])
        ];
    }

    private function initializeToolSchemas() {
        try {
            
            $capabilities = $this->mcpServerInstance->callTool('get_all_mcp_tools');

            if (isset($capabilities['tools']) && is_array($capabilities['tools'])) {
                $this->toolSchemas = $capabilities['tools'];
                error_log("MCPClient Debug - Loaded " . count($this->toolSchemas) . " tool schemas.");
            } else {
                error_log("MCPClient Debug - 'get_all_mcp_tools' did not return expected format.");
            }
        } catch (\Throwable $e) {
            error_log("MCPClient Debug - Error fetching tool schemas: " . $e->getMessage());
            throw new Exception("Failed to fetch tool schemas: " . $e->getMessage());
        }
    }
    
    private function getMethodNameForTool($toolName) {
        return lcfirst(str_replace('_', '', ucwords($toolName, '_')));
    }

    public function call(string $toolName, array $payload): array
    {
        try {
            $result = $this->mcpServerInstance->callTool($toolName, $payload);
            error_log("MCPClient Debug - Tool '{$toolName}' called successfully. Result: " . json_encode($result));
            return $result;

        } catch (\Throwable $e) {
            error_log("MCPClient Error calling tool '{$toolName}': " . $e->getMessage());
            throw new Exception("Tool execution failed for '{$toolName}': " . $e->getMessage());
        }
    }

    public function getToolSchemas() {
        return $this->toolSchemas;
    }

    public function listTools(): array
    {
        $toolsList = [];
        foreach ($this->toolSchemas as $name => $schemaInfo) {
            $toolsList[$name] = $schemaInfo['description'] ?? 'No description';
        }
        return $toolsList;
    }

    public function sendOtp(array $data): array
    {
        return $this->call('send_otp', ['data' => $data]);
    }

    public function verifyOtp(array $data): array
    {
        return $this->call('verify_otp', ['data' => $data]);
    }

    public function requestPanDetails(array $data): array
    {
        return $this->call('request_pan_details', ['data' => $data]);
    }

    public function searchBrands(array $data): array
    {
        return $this->call('search_brands', ['data' => $data]);
    }

    public function searchModels(array $data): array
    {
        return $this->call('search_models', ['data' => $data]);
    }

    public function saveUser(array $data): array
    {
        return $this->call('save_user', ['data' => $data]);
    }

    public function fetchOffers(array $data): array
    {
        return $this->call('fetch_offers', ['data' => $data]);
    }

    public function compareVehicles(array $data): array
    {
        return $this->call('compare_vehicles', ['data' => $data]);
    }

    public function assessLoanEligibility(array $data): array
    {
        return $this->call('assess_loan_eligibility', ['data' => $data]);
    }
}

