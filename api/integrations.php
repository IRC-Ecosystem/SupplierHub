<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../services/IntegrationService.php';
require_once __DIR__ . '/../services/ReliabilityService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../helpers/ApiResponse.php';

$action = (string) ($_GET['action'] ?? 'status');
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?: [];
$webhooks = ['smartbank_payment_callback', 'logistics_shipment_event'];

if (in_array($action, $webhooks, true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
        exit;
    }

    if (!IntegrationService::verifySignature($raw)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Signature webhook tidak valid atau secret belum dikonfigurasi.']);
        exit;
    }

    if ($action === 'smartbank_payment_callback') {
        $response = IntegrationService::smartBankCallback($input);
        $provider = 'smartbank';
    } else {
        $response = IntegrationService::logisticsEvent($input);
        $provider = 'logistikita';
    }

    ReliabilityService::recordWebhook(
        $provider,
        IntegrationService::eventId(),
        $raw,
        (string) ($_SERVER['HTTP_X_B2BLINK_SIGNATURE'] ?? ''),
        ($response['status'] ?? 'error') === 'success' ? 'accepted' : 'failed',
        $response['message'] ?? null
    );
} else {
    $user = AuthMiddleware::requireAuth('integrator');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        CsrfMiddleware::verify();
    }

    switch ($action) {
        case 'status':
            $response = ['status' => 'success', 'data' => IntegrationService::configuration()];
            break;

        case 'outbox':
            $db = getDB();
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
            $query = $db->prepare("SELECT event_id, aggregate_type, aggregate_id, event_type, event_version, status, attempts, created_at FROM outbox_events ORDER BY id DESC LIMIT {$limit}");
            $query->execute();
            $response = ['status' => 'success', 'data' => $query->fetchAll()];
            break;

        case 'worker':
            $response = ['status' => 'success', 'data' => ReliabilityService::runWorker((int) ($input['limit'] ?? 25))];
            break;

        case 'reconcile':
            $response = ReliabilityService::reconcile();
            break;

        case 'issues':
            $response = ['status' => 'success', 'data' => ReliabilityService::issues((int) ($_GET['limit'] ?? 50))];
            break;

        case 'resolve_issue':
            $response = ReliabilityService::resolveIssue((int) ($input['issue_id'] ?? 0), (int) $user['user_id']);
            break;

        case 'replay_event':
            $response = ReliabilityService::replay(trim((string) ($input['event_id'] ?? '')));
            break;

        case 'mock_refund':
            $response = Procurement::completeMockRefund((int) ($input['refund_id'] ?? 0), !empty($input['success']), trim((string) ($input['reference'] ?? '')));
            break;

        case 'insight_procurement_summary':
            $response = ['status' => 'success', 'data' => IntegrationService::insightSummary()];
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Action integrasi tidak dikenali.'];
    }
}

$response = ApiResponse::normalize($response);
http_response_code(ApiResponse::codeFor($response));
echo json_encode($response);
