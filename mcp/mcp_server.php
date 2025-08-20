<?php

require_once 'mcp_tools.php';

class MCPServer {
    private $tools;
    private $port;
    
    public function __construct($port = 8080) {
        $this->port = $port;
        $this->tools = new MCPTools();
    }
    
    public function start() {
        if (php_sapi_name() !== 'cli') {
            die('MCP Server must be run from command line');
        }
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            die('Could not create socket: ' . socket_strerror(socket_last_error()));
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!socket_bind($socket, '0.0.0.0', $this->port)) {
            die('Could not bind to socket: ' . socket_strerror(socket_last_error()));
        }
        
        if (!socket_listen($socket, 5)) {
            die('Could not listen on socket: ' . socket_strerror(socket_last_error()));
        }
        
        echo "MCP Server started on port {$this->port}\n";
        
        while (true) {
            $clientSocket = socket_accept($socket);
            if (!$clientSocket) {
                continue;
            }
            
            $this->handleClient($clientSocket);
            socket_close($clientSocket);
        }
        
        socket_close($socket);
    }
    
    private function handleClient($clientSocket) {
        $request = '';
        while (true) {
            $data = socket_read($clientSocket, 1024);
            if ($data === false || $data === '') {
                break;
            }
            $request .= $data;
            if (strpos($request, "\r\n\r\n") !== false) {
                break;
            }
        }
        
        if (empty($request)) {
            return;
        }
        
        $response = $this->processRequest($request);
        socket_write($clientSocket, $response);
    }
    
    private function processRequest($request) {
        $lines = explode("\r\n", $request);
        $firstLine = $lines[0];
        
        if (!preg_match('/^POST \/mcp-tools HTTP\/1\.[01]$/', $firstLine)) {
            return $this->createHttpResponse(404, 'Not Found');
        }
        
        $contentLength = 0;
        $headerEnd = false;
        $bodyStart = 0;
        
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '') {
                $headerEnd = true;
                $bodyStart = $i + 1;
                break;
            }
            if (preg_match('/^Content-Length:\s*(\d+)$/i', $lines[$i], $matches)) {
                $contentLength = (int)$matches[1];
            }
        }
        
        if (!$headerEnd) {
            return $this->createHttpResponse(400, 'Bad Request');
        }
        
        $body = implode("\r\n", array_slice($lines, $bodyStart));
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->createHttpResponse(400, 'Invalid JSON', [
                'error' => 'JSON Parse Error: ' . json_last_error_msg()
            ]);
        }
        
        if (!isset($data['tool'])) {
            return $this->createHttpResponse(400, 'Bad Request', [
                'error' => 'Tool parameter is required'
            ]);
        }
        
        $result = $this->executeTool($data['tool'], $data['params'] ?? []);
        
        return $this->createHttpResponse(200, 'OK', $result);
    }
    
    private function executeTool($tool, $params) {
        try {
            switch ($tool) {
                case 'SEND_OTP':
                    return $this->tools->sendOtp($params);
                case 'VERIFY_OTP':
                    return $this->tools->verifyOtp($params);
                case 'REQUEST_PAN':
                    return $this->tools->requestPan($params);
                case 'SEARCH_BRANDS':
                    return $this->tools->searchBrands($params);
                case 'SEARCH_MODELS':
                    return $this->tools->searchModels($params);
                case 'SAVE_USER':
                    return $this->tools->saveUser($params);
                case 'FETCH_OFFERS':
                    return $this->tools->fetchOffers($params);
                default:
                    return [
                        'success' => false,
                        'error' => 'Unknown tool: ' . $tool
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Tool execution error: ' . $e->getMessage()
            ];
        }
    }
    
    private function createHttpResponse($statusCode, $statusText, $data = null) {
        $body = '';
        if ($data !== null) {
            $body = json_encode($data, JSON_UNESCAPED_SLASHES);
        }
        
        $response = "HTTP/1.1 {$statusCode} {$statusText}\r\n";
        $response .= "Content-Type: application/json\r\n";
        $response .= "Content-Length: " . strlen($body) . "\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Access-Control-Allow-Methods: POST, OPTIONS\r\n";
        $response .= "Access-Control-Allow-Headers: Content-Type\r\n";
        $response .= "\r\n";
        $response .= $body;
        
        return $response;
    }
}

if (php_sapi_name() === 'cli') {
    // $server = new MCPServer();
    $server = new MCPServer(9081);
    $server->start();
}

?>