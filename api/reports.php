<?php
/**
 * Dashboard/Reports API Endpoint
 * GET /api/reports.php?action=supplier_stats
 * GET /api/reports.php?action=umkm_stats
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';

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

echo json_encode($response);
