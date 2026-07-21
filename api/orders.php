<?php
/**
 * Orders API Endpoint
 * POST /api/orders.php?action=checkout      (UMKM)
 * GET  /api/orders.php?action=pending       (Supplier)
 * GET  /api/orders.php?action=detail&id=X   (Supplier)
 * POST /api/orders.php?action=approve       (Supplier)
 * POST /api/orders.php?action=reject        (Supplier)
 * GET  /api/orders.php?action=completed     (Supplier)
 * GET  /api/orders.php?action=history       (UMKM)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../helpers/ApiResponse.php';
require_once __DIR__ . '/../models/Procurement.php';

GatewayMiddleware::addResponseHeaders();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') CsrfMiddleware::verify();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$requestData = LoggerMiddleware::getRequestData();
$userId = null;

$response = ['status' => 'error', 'message' => 'Action tidak valid.'];

switch ($action) {
    case 'add_cart':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $matId = $input['material_id'] ?? 0;
        $qty = $input['qty'] ?? 1;
        $found = false;
        foreach ($_SESSION['cart'] as &$ci) {
            if ($ci['material_id'] == $matId) { $ci['qty'] += $qty; $found = true; break; }
        }
        unset($ci);
        if (!$found) $_SESSION['cart'][] = ['material_id' => $matId, 'qty' => $qty];
        
        // Clear bundle discount since user modified the cart manually
        unset($_SESSION['bundle_discount']);
        unset($_SESSION['bundle_name']);
        
        $response = ['status' => 'success', 'message' => 'Ditambahkan ke keranjang.'];
        break;

    case 'add_bundle':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        
        // Clear existing cart to hold only bundle items
        $_SESSION['cart'] = [];
        $items = $input['items'] ?? [];
        foreach ($items as $item) {
            $_SESSION['cart'][] = [
                'material_id' => (int)$item['material_id'],
                'qty' => (int)$item['qty']
            ];
        }
        $_SESSION['bundle_discount'] = (int)($input['discount'] ?? 0);
        $_SESSION['bundle_name'] = $input['bundle_name'] ?? '';
        
        $response = ['status' => 'success', 'message' => 'Paket bundling berhasil dipindahkan ke keranjang belanja Anda.'];
        break;

    case 'subscribe':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        $type = $input['subscription_type'] ?? '';
        $price = (int)($input['price'] ?? 0);
        
        if ($type !== 'vip' && $type !== 'gold') {
            $response = ['status' => 'error', 'message' => 'Tipe langganan tidak valid.'];
            break;
        }
        
        $_SESSION['subscription'] = $type;
        
        $response = [
            'status' => 'success', 
            'message' => 'Langganan B2B ' . strtoupper($type) . ' Berhasil Diaktifkan!',
            'data' => [
                'type' => $type,
                'price' => $price,
                'ref' => 'SB-SUB-' . date('Ymd') . '-' . rand(1000, 9999)
            ]
        ];
        break;

    case 'cancel_subscription':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        
        if (isset($_SESSION['subscription'])) {
            $oldType = $_SESSION['subscription'];
            unset($_SESSION['subscription']);
            $response = [
                'status' => 'success',
                'message' => 'Langganan B2B ' . strtoupper($oldType) . ' Berhasil Dibatalkan.'
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Anda tidak memiliki langganan aktif saat ini.'
            ];
        }
        break;

    case 'checkout':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        // If checkout from cart session, use directCheckout (SmartBank + 3% fee)
        if (!empty($input['from_cart']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $input['items'] = $_SESSION['cart'];
            $input['idempotency_key'] = $_SESSION['checkout_idempotency_key'] ?? ($input['idempotency_key'] ?? null);
            // Resolve supplier_id from the first item in cart if not provided
            if (empty($input['supplier_id'])) {
                $db = getDB();
                $firstMatId = $_SESSION['cart'][0]['material_id'] ?? 0;
                $stmtSupp = $db->prepare("SELECT supplier_id FROM materials WHERE id = :id");
                $stmtSupp->execute(['id' => $firstMatId]);
                $matRow = $stmtSupp->fetch();
                $input['supplier_id'] = $matRow['supplier_id'] ?? 1;
            }
        }
        // Use directCheckout to trigger SmartBank payment + 3% margin + stock gating
        $response = OrderController::directCheckout($input, $userId);
        if ($response['status'] === 'success') {
            $_SESSION['cart'] = [];
            unset($_SESSION['bundle_discount']);
            unset($_SESSION['bundle_name']);
            unset($_SESSION['checkout_idempotency_key']);
        }
        break;

    case 'direct_checkout':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        $response = OrderController::directCheckout($input, $userId);
        break;

    case 'pending':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = $user['user_id'];
        $response = OrderController::listPending($userId);
        break;

    case 'detail':
        $user = AuthMiddleware::requireAuth();
        $userId = $user['user_id'];
        $orderId = $_GET['id'] ?? 0;
        $response = OrderController::detail($orderId, $userId, $user['role']);
        break;

    case 'detail_by_ref':
        $user = AuthMiddleware::requireAuth();
        $userId = $user['user_id'];
        $ref = $_GET['ref'] ?? '';
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM orders WHERE smartbank_ref = :ref OR order_code = :ref LIMIT 1");
        $stmt->execute(['ref' => $ref]);
        $row = $stmt->fetch();
        if ($row) {
            $response = OrderController::detail($row['id'], $userId, $user['role']);
        } else {
            $response = ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }
        break;

    case 'approve':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = $user['user_id'];
        $orderId = $input['order_id'] ?? 0;
        $resiPengiriman = $input['resi_pengiriman'] ?? null;
        $response = OrderController::approve($orderId, $userId, $resiPengiriman);
        break;

    case 'cart_update':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $cartAction = $input['cart_action'] ?? '';
        $idx = (int)($input['idx'] ?? -1);
        if ($cartAction === 'clear') {
            $_SESSION['cart'] = [];
        } elseif ($idx >= 0 && isset($_SESSION['cart'][$idx])) {
            if ($cartAction === 'increase') $_SESSION['cart'][$idx]['qty']++;
            elseif ($cartAction === 'decrease') $_SESSION['cart'][$idx]['qty']--;
            elseif ($cartAction === 'update') $_SESSION['cart'][$idx]['qty'] = (int)($input['qty'] ?? 0);
            else { $response = ['status'=>'error','message'=>'Aksi keranjang tidak valid.']; break; }
            if ($_SESSION['cart'][$idx]['qty'] <= 0) array_splice($_SESSION['cart'], $idx, 1);
        } else {
            $response = ['status'=>'error','message'=>'Item keranjang tidak ditemukan.'];
            break;
        }
        unset($_SESSION['bundle_discount'], $_SESSION['bundle_name'], $_SESSION['checkout_idempotency_key']);
        $response = ['status'=>'success','message'=>'Keranjang diperbarui.'];
        break;

    case 'request_payment':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        $orderId = (int)($input['order_id'] ?? 0);
        $paymentKey = trim((string)($input['idempotency_key'] ?? ''));
        $response = OrderController::requestPayment($orderId, $userId, $paymentKey);
        break;

    case 'supplier_fulfillment':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = (int)$user['user_id'];
        $response = Procurement::supplierUpdate((int)($input['order_id'] ?? 0), $userId, (string)($input['fulfillment_action'] ?? ''), $input);
        break;

    case 'receive_goods':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = (int)$user['user_id'];
        $response = Procurement::receive((int)($input['order_id'] ?? 0), $userId, is_array($input['items'] ?? null) ? $input['items'] : [], trim((string)($input['idempotency_key'] ?? '')), $input['note'] ?? null);
        break;

    case 'cancel_order':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = (int)$user['user_id'];
        $response = Procurement::cancel((int)($input['order_id'] ?? 0), $userId, (string)($input['reason'] ?? ''));
        break;

    case 'open_dispute':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = (int)$user['user_id'];
        $response = Procurement::openDispute((int)($input['order_id'] ?? 0), $userId, (string)($input['category'] ?? ''), (string)($input['description'] ?? ''));
        break;

    case 'resolve_dispute':
        $user = AuthMiddleware::requireAuth('supplier');
        $response = Procurement::resolveDispute((int)($input['dispute_id'] ?? 0), (int)$user['user_id'], (string)($input['resolution'] ?? ''), (string)($input['resolution_note'] ?? ''));
        break;

    case 'request_refund':
        $user = AuthMiddleware::requireAuth('umkm');
        $response = Procurement::requestRefund((int)($input['order_id'] ?? 0), (int)$user['user_id'], trim((string)($input['idempotency_key'] ?? '')), (string)($input['reason'] ?? ''));
        break;

    case 'supplier_performance':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = (int)$user['user_id'];
        $response = ['status'=>'success','data'=>Procurement::supplierPerformance($userId)];
        break;

    case 'reject':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = $user['user_id'];
        $orderId = $input['order_id'] ?? 0;
        $response = OrderController::reject($orderId, $userId);
        break;

    case 'completed':
        $user = AuthMiddleware::requireAuth('supplier');
        $userId = $user['user_id'];
        $response = OrderController::listCompleted($userId);
        break;

    case 'history':
        $user = AuthMiddleware::requireAuth('umkm');
        $userId = $user['user_id'];
        $response = OrderController::history($userId);
        break;

    default:
        $response = ['status' => 'error', 'message' => 'Action tidak dikenali.'];
}

LoggerMiddleware::log('/api/orders.php?action=' . $action, $userId, $requestData, $response);

$response = ApiResponse::normalize($response);
http_response_code(ApiResponse::codeFor($response));
echo json_encode($response);
