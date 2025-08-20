<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/AI_Chatbot/v1//vendor/autoload.php';

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

try {
    $server = Server::make()
        ->withServerInfo('Vehicle Loan MCP Server', '1.0.0')
        ->build();

    $server->discover(
        basePath: __DIR__,
        scanDirs: ['src']
    );

    $transport = new StdioServerTransport();

    echo "Starting Vehicle Loan MCP Server...\n";
    $server->listen($transport);
} catch (\Throwable $e) {
    error_log('MCP Server Error: ' . $e->getMessage());
    echo "Error starting MCP server: " . $e->getMessage() . "\n";
    exit(1);
}