<?php
require_once $_SERVER['DOCUMENT_ROOT']."/AI_Chatbot/v1/controls/class_ai_client.php";
require_once $_SERVER['DOCUMENT_ROOT']."/AI_Chatbot/v1/mcp/mcp_client.php";
require_once $_SERVER['DOCUMENT_ROOT']."/AI_Chatbot/v1/controls/class_vehicleloan_controller.php";

if (isset($_POST['action']) && $_POST['action'] === "sendMessage") {
    header('Content-Type: application/json');
    
    try {
        $sessionId = $_POST['sessionId'] ?? session_id();
        $userInput = $_POST['chatInput'] ?? '';
        
        $controller = new VehicleLoanController($sessionId);
        $response = $controller->processMessage($userInput);
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        error_log("Fatal error in main handler: " . $e->getMessage());
        echo json_encode([
            'status' => 'fail',
            'error' => ['error' => 'System error. Please try again later.'.$e->getMessage()]
        ], JSON_PRETTY_PRINT);
    }
    exit;
}
