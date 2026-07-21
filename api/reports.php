<?php
/**
 * Dashboard/Reports API Endpoint
 * GET /api/reports.php?action=supplier_stats
 * GET /api/reports.php?action=umkm_stats
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../helpers/ApiResponse.php';

GatewayMiddleware::addResponseHeaders();

$action = $_GET['action'] ?? '';
$userId = null;

$response = ['status' => 'error', 'message' => 'Action tidak valid.'];

switch ($action) {
    case 'supplier_stats':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = $user['user_id'];
        $response = DashboardController::supplierStats($userId);
        break;

    case 'umkm_stats':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        $response = DashboardController::umkmStats($userId);
        break;

    default:
        $response = ['status' => 'error', 'message' => 'Action tidak dikenali. Gunakan: supplier_stats, umkm_stats'];
}

LoggerMiddleware::log('/api/reports.php?action=' . $action, $userId, null, $response);

$response = ApiResponse::normalize($response);
http_response_code(ApiResponse::codeFor($response));
echo json_encode($response);
