<?php
/**
 * Auth API Endpoint
 * POST /api/auth.php?action=login
 * POST /api/auth.php?action=register
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../helpers/ApiResponse.php';

GatewayMiddleware::addResponseHeaders();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$requestData = LoggerMiddleware::getRequestData();

$response = ['status' => 'error', 'message' => 'Action tidak valid.'];

switch ($action) {
    case 'login':
        $response = AuthController::login($input);
        break;
    
    case 'register':
        $response = AuthController::register($input);
        break;
    
    default:
        $response = ['status' => 'error', 'message' => 'Action tidak dikenali. Gunakan: login, register'];
}

// Log request
LoggerMiddleware::log(
    '/api/auth.php?action=' . $action,
    $response['data']['user_id'] ?? null,
    $requestData,
    $response
);

$response = ApiResponse::normalize($response);
http_response_code(ApiResponse::codeFor($response, $action === 'register' ? 201 : 200));
echo json_encode($response);
