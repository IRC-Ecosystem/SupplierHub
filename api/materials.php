<?php
/**
 * Materials API Endpoint
 * GET  /api/materials.php?action=list
 * GET  /api/materials.php?action=catalog
 * GET  /api/materials.php?action=detail&id=X
 * POST /api/materials.php?action=create
 * POST /api/materials.php?action=update
 * POST /api/materials.php?action=delete
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../helpers/ApiResponse.php';

GatewayMiddleware::addResponseHeaders();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') CsrfMiddleware::verify();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$requestData = LoggerMiddleware::getRequestData();

$response = ['status' => 'error', 'message' => 'Action tidak valid.'];

switch ($action) {
    case 'list':
        $user = AuthMiddleware::requireAuth('supplier');
        $response = MaterialController::list($user['user_id']);
        break;

    case 'catalog':
        // UMKM can view catalog (or any authenticated user)
        $user = AuthMiddleware::requireAuth();
        $response = MaterialController::catalog();
        break;

    case 'detail':
        $user = AuthMiddleware::requireAuth();
        $id = $_GET['id'] ?? 0;
        $response = MaterialController::detail($id);
        break;

    case 'create':
        $user = AuthMiddleware::requireAuth('supplier');
        $response = MaterialController::create($input, $user['user_id']);
        break;

    case 'update':
        $user = AuthMiddleware::requireAuth('supplier');
        $id = $input['id'] ?? 0;
        $response = MaterialController::update($id, $input);
        break;

    case 'delete':
        $user = AuthMiddleware::requireAuth('supplier');
        $id = $input['id'] ?? ($_GET['id'] ?? 0);
        $response = MaterialController::delete($id);
        break;

    default:
        $response = ['status' => 'error', 'message' => 'Action tidak dikenali. Gunakan: list, catalog, detail, create, update, delete'];
}

// Log
$userId = $user['user_id'] ?? null;
LoggerMiddleware::log('/api/materials.php?action=' . $action, $userId, $requestData, $response);

$response = ApiResponse::normalize($response);
http_response_code(ApiResponse::codeFor($response));
echo json_encode($response);
