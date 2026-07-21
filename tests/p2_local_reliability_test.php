<?php
require_once __DIR__ . '/../services/ReliabilityService.php';
require_once __DIR__ . '/../models/Procurement.php';

function ok2($condition, $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = getDB();
$orderIds = [];
$eventIds = [];
$inboxId = 'p2-test-' . bin2hex(random_bytes(4));

try {
    $supplier = (int) $db->query("SELECT id FROM users WHERE role = 'supplier' ORDER BY id LIMIT 1")->fetchColumn();
    $umkm = (int) $db->query("SELECT id FROM users WHERE role = 'umkm' ORDER BY id LIMIT 1")->fetchColumn();
    $materialQuery = $db->prepare('SELECT id FROM materials WHERE supplier_id = :supplier_id ORDER BY id LIMIT 1');
    $materialQuery->execute(['supplier_id' => $supplier]);
    $material = (int) $materialQuery->fetchColumn();

    ok2($supplier && $umkm && $material, 'Seed P2 tidak tersedia.');

    $code = 'TEST-P2-' . strtoupper(bin2hex(random_bytes(4)));
    $query = $db->prepare("INSERT INTO orders(order_code, umkm_id, supplier_id, status, payment_status, subtotal, fee_supplier, total, paid_at) VALUES(:code, :umkm, :supplier, 'paid', 'paid', 10000, 300, 10300, NOW())");
    $query->execute(['code' => $code, 'umkm' => $umkm, 'supplier' => $supplier]);
    $order = (int) $db->lastInsertId();
    $orderIds[] = $order;

    $db->prepare('INSERT INTO order_items(order_id, material_id, qty, price_at_order) VALUES(:order_id, :material_id, 1, 10000)')
        ->execute(['order_id' => $order, 'material_id' => $material]);

    $eventId = 'p2-worker-' . bin2hex(random_bytes(4));
    $eventIds[] = $eventId;
    $db->prepare("INSERT INTO outbox_events(event_id, event_type, aggregate_type, aggregate_id, payload, status, max_attempts, available_at) VALUES(:event_id, 'P2_TEST_EVENT', 'supplier_order', :aggregate_id, :payload, 'pending', 2, NOW())")
        ->execute(['event_id' => $eventId, 'aggregate_id' => (string) $order, 'payload' => json_encode(['test' => true])]);

    $run = ReliabilityService::runWorker(10);
    ok2($run['published'] >= 1, 'Worker mock tidak mempublikasikan event.');

    $status = $db->prepare('SELECT status FROM outbox_events WHERE event_id = :event_id');
    $status->execute(['event_id' => $eventId]);
    ok2($status->fetchColumn() === 'published', 'Status outbox bukan published.');

    $firstInbox = ReliabilityService::recordInbox('p2-test', $inboxId, 'P2_TEST', ['ok' => 1]);
    $secondInbox = ReliabilityService::recordInbox('p2-test', $inboxId, 'P2_TEST', ['ok' => 1]);
    ok2(empty($firstInbox['idempotent_replay']) && !empty($secondInbox['idempotent_replay']), 'Inbox dedup gagal.');

    $reconciliation = ReliabilityService::reconcile();
    ok2($reconciliation['status'] === 'success', 'Rekonsiliasi gagal.');

    $refundKey = 'p2-refund-' . bin2hex(random_bytes(4));
    $refund = Procurement::requestRefund($order, $umkm, $refundKey, 'Barang tidak sesuai dan perlu pengembalian dana');
    ok2($refund['status'] === 'success', 'Refund request lokal gagal: ' . json_encode($refund));

    $refundCode = $refund['data']['refund_code'];
    $refundQuery = $db->prepare('SELECT id FROM refund_requests WHERE refund_code = :code');
    $refundQuery->execute(['code' => $refundCode]);
    $refundId = (int) $refundQuery->fetchColumn();
    $done = Procurement::completeMockRefund($refundId, true, 'MOCK-P2-REF');
    ok2($done['status'] === 'success', 'Mock refund gagal.');

    echo "P2_LOCAL_RELIABILITY_OK\n";
} finally {
    $db->prepare('DELETE FROM inbox_events WHERE event_id = :event_id')->execute(['event_id' => $inboxId]);

    foreach ($eventIds as $eventId) {
        $db->prepare('DELETE FROM outbox_events WHERE event_id = :event_id')->execute(['event_id' => $eventId]);
    }

    foreach ($orderIds as $orderId) {
        $db->prepare('DELETE FROM refund_requests WHERE order_id = :order_id')->execute(['order_id' => $orderId]);
        $db->prepare('DELETE FROM outbox_events WHERE aggregate_id = :order_id')->execute(['order_id' => (string) $orderId]);
        $db->prepare('DELETE FROM order_items WHERE order_id = :order_id')->execute(['order_id' => $orderId]);
        $db->prepare('DELETE FROM orders WHERE id = :order_id')->execute(['order_id' => $orderId]);
    }
}
