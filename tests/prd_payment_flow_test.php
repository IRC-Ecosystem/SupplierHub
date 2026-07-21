<?php
require_once __DIR__ . '/../controllers/OrderController.php';

function assertFlow($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$db = getDB();
$orderId = null;
$paymentRef = null;
$stockWasReduced = false;
$key = 'test-checkout-' . bin2hex(random_bytes(12));

try {
    $before = (int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn();
    $created = OrderController::directCheckout([
        'supplier_id' => 1,
        'items' => [['material_id' => 1, 'qty' => 1]],
        'idempotency_key' => $key
    ], 2);
    assertFlow($created['status'] === 'success', 'Checkout gagal.');
    $orderId = (int)$created['data']['order_id'];
    $order = Order::findById($orderId);
    assertFlow($order['status'] === 'submitted' && $order['payment_status'] === 'unpaid', 'Checkout harus SUBMITTED/UNPAID.');
    assertFlow((int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn() === $before, 'Checkout tidak boleh mengurangi stok.');

    $accepted = OrderController::approve($orderId, 1);
    assertFlow($accepted['status'] === 'success', 'Supplier gagal menerima order.');
    $order = Order::findById($orderId);
    assertFlow($order['status'] === 'pending_payment' && $order['payment_status'] === 'unpaid', 'Accept harus PENDING_PAYMENT/UNPAID.');

    $request = OrderController::requestPayment($orderId, 2, 'test-request-' . $orderId);
    assertFlow($request['status'] === 'success', 'Payment request gagal dibuat.');
    assertFlow((int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn() === $before, 'Payment request tidak boleh mengurangi stok.');
    $requestReplay = OrderController::requestPayment($orderId, 2, 'test-request-other-' . $orderId);
    assertFlow(!empty($requestReplay['data']['idempotent_replay']), 'Pending payment request harus dideduplikasi.');

    $failed = OrderController::verifySmartBankPayment($orderId, 'failed');
    assertFlow($failed['status'] === 'success', 'Verifikasi gagal tidak tercatat.');
    assertFlow((int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn() === $before, 'Verifikasi gagal tidak boleh mengurangi stok.');

    $retry = OrderController::requestPayment($orderId, 2, 'test-retry-' . $orderId);
    assertFlow($retry['status'] === 'success', 'Retry payment request gagal.');
    $paymentRef = 'TEST-SB-' . $orderId;
    $paid = OrderController::verifySmartBankPayment($orderId, 'succeeded', $paymentRef);
    assertFlow($paid['status'] === 'success', 'Verifikasi sukses gagal.');
    $paymentRef = $paid['data']['reference'];
    $stockAfter = (int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn();
    assertFlow($stockAfter === $before - 1, 'Payment sukses harus mengurangi stok tepat satu.');
    $stockWasReduced = true;

    $replay = OrderController::verifySmartBankPayment($orderId, 'succeeded', $paymentRef);
    assertFlow($replay['status'] === 'success' && !empty($replay['data']['idempotent_replay']), 'Retry harus idempotent.');
    assertFlow((int)$db->query("SELECT stock FROM materials WHERE id=1")->fetchColumn() === $stockAfter, 'Retry tidak boleh mengurangi stok lagi.');
    assertFlow(count(Order::getStatusHistory($orderId)) >= 4, 'Histori status tidak lengkap.');

    echo "PRD_PAYMENT_FLOW_OK\n";
} finally {
    if ($paymentRef) {
        $stmt = $db->prepare("DELETE FROM payments WHERE reference_id=:ref");
        $stmt->execute(['ref'=>$paymentRef]);
    }
    if ($orderId) {
        $stmt = $db->prepare("DELETE FROM orders WHERE id=:id AND idempotency_key=:ikey");
        $stmt->execute(['id'=>$orderId,'ikey'=>$key]);
    }
    if ($stockWasReduced) $db->exec("UPDATE materials SET stock=stock+1 WHERE id=1");
}
