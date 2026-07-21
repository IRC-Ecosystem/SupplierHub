<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Order.php';

class Procurement {
    private static function addStatusHistory(int $orderId, ?string $from, string $to, ?int $actor, string $reason, PDO $db): void {
        Order::addStatusHistory($orderId, $from, $to, $actor, $reason, $db);
    }

    private static function uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function outbox(PDO $db, int $orderId, string $type, array $payload): void {
        $stmt = $db->prepare("INSERT INTO outbox_events(event_id, aggregate_type, aggregate_id, event_type, payload) VALUES(:eid, 'supplier_order', :aid, :type, :payload)");
        $stmt->execute([
            'eid' => self::uuid(),
            'aid' => (string) $orderId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public static function enqueue(PDO $db, int $orderId, string $type, array $payload): void {
        self::outbox($db, $orderId, $type, $payload);
    }

    private static function transition(PDO $db, array $order, string $to, int $actor, string $reason, array $allowed): void {
        if (!in_array($order['status'], $allowed, true)) {
            throw new DomainException('Transisi status tidak valid dari ' . $order['status'] . ' ke ' . $to . '.');
        }

        $stmt = $db->prepare('UPDATE orders SET status = :to WHERE id = :id AND status = :from');
        $stmt->execute(['to' => $to, 'id' => $order['id'], 'from' => $order['status']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Status pesanan berubah. Muat ulang halaman.');
        }

        Order::addStatusHistory($order['id'], $order['status'], $to, $actor, $reason, $db);
    }

    public static function supplierUpdate(int $orderId, int $supplierId, string $action, array $data): array {
        $db = getDB();
        $db->beginTransaction();

        try {
            $query = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || (int) $order['supplier_id'] !== $supplierId) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }

            if ($action === 'processing') {
                self::transition($db, $order, 'processing', $supplierId, 'Supplier mulai menyiapkan pesanan', ['paid']);
                self::outbox($db, $orderId, 'SUPPLIER_ORDER_PROCESSING', ['order_id' => $orderId, 'order_code' => $order['order_code']]);
            } elseif ($action === 'shipped') {
                $tracking = trim((string) ($data['tracking_reference'] ?? ''));
                if ($tracking === '' || strlen($tracking) > 100) {
                    throw new DomainException('Referensi pengiriman wajib diisi.');
                }
                self::transition($db, $order, 'shipped', $supplierId, 'Barang dikirim oleh supplier', ['paid', 'processing']);
                $db->prepare('UPDATE orders SET resi_pengiriman = :ref, shipped_at = NOW() WHERE id = :id')->execute(['ref' => $tracking, 'id' => $orderId]);
                self::outbox($db, $orderId, 'SUPPLIER_ORDER_SHIPPED', [
                    'order_id' => $orderId,
                    'order_code' => $order['order_code'],
                    'tracking_reference' => $tracking,
                    'integration_status' => 'local_only',
                ]);
            } elseif ($action === 'estimate') {
                if ($order['status'] !== 'submitted') {
                    throw new DomainException('Estimasi hanya dapat diubah sebelum pesanan dikonfirmasi.');
                }
                $eta = trim((string) ($data['fulfillment_eta'] ?? ''));
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $eta) ?: DateTime::createFromFormat('Y-m-d H:i:s', $eta);
                if (!$date || $date <= new DateTime()) {
                    throw new DomainException('Estimasi pemenuhan harus berada di masa depan.');
                }
                $db->prepare('UPDATE orders SET fulfillment_eta = :eta WHERE id = :id')->execute(['eta' => $date->format('Y-m-d H:i:s'), 'id' => $orderId]);
                self::outbox($db, $orderId, 'SUPPLIER_FULFILLMENT_ESTIMATED', ['order_id' => $orderId, 'fulfillment_eta' => $date->format(DATE_ATOM)]);
            } else {
                throw new DomainException('Aksi supplier tidak dikenal.');
            }

            $db->commit();
            return ['status' => 'success', 'message' => 'Pesanan berhasil diperbarui.'];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function receive(int $orderId, int $umkmId, array $items, string $key, ?string $note = null): array {
        if ($key === '' || strlen($key) > 100) {
            return ['status' => 'error', 'message' => 'Idempotency key receipt wajib diisi.'];
        }
        if (!$items) {
            return ['status' => 'error', 'message' => 'Item penerimaan wajib diisi.'];
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $old = $db->prepare('SELECT gr.*, o.status AS order_status FROM goods_receipts gr JOIN orders o ON o.id = gr.order_id WHERE gr.idempotency_key = :key');
            $old->execute(['key' => $key]);
            $prior = $old->fetch();
            if ($prior) {
                $db->rollBack();
                return ['status' => 'success', 'message' => 'Penerimaan sebelumnya dikembalikan.', 'data' => ['receipt_code' => $prior['receipt_code'], 'order_status' => $prior['order_status'], 'idempotent_replay' => true]];
            }

            $query = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || (int) $order['umkm_id'] !== $umkmId) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            if (!in_array($order['status'], ['shipped', 'partially_received'], true)) {
                throw new DomainException('Barang hanya dapat diterima setelah dikirim.');
            }

            $lines = $db->prepare('SELECT id, qty FROM order_items WHERE order_id = :order_id FOR UPDATE');
            $lines->execute(['order_id' => $orderId]);
            $sum = $db->prepare('SELECT COALESCE(SUM(accepted_qty + rejected_qty), 0) processed FROM goods_receipt_items WHERE order_item_id = :item_id');

            $available = [];
            foreach ($lines->fetchAll() as $line) {
                $sum->execute(['item_id' => $line['id']]);
                $available[(int) $line['id']] = ['qty' => (int) $line['qty'], 'processed' => (int) $sum->fetchColumn()];
            }

            $normalized = [];
            foreach ($items as $item) {
                $id = (int) ($item['order_item_id'] ?? 0);
                $accepted = (int) ($item['accepted_qty'] ?? 0);
                $rejected = (int) ($item['rejected_qty'] ?? 0);
                if (!isset($available[$id]) || $accepted < 0 || $rejected < 0 || $accepted + $rejected < 1) {
                    throw new DomainException('Data item penerimaan tidak valid.');
                }
                if ($available[$id]['processed'] + $accepted + $rejected > $available[$id]['qty']) {
                    throw new DomainException('Jumlah penerimaan melebihi quantity pesanan.');
                }
                $normalized[] = ['id' => $id, 'accepted' => $accepted, 'rejected' => $rejected, 'reason' => trim((string) ($item['rejection_reason'] ?? ''))];
                $available[$id]['processed'] += $accepted + $rejected;
            }

            $full = true;
            foreach ($available as $stock) {
                if ($stock['processed'] < $stock['qty']) {
                    $full = false;
                    break;
                }
            }

            $receiptCode = 'GR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $receiptStatus = $full ? 'full' : 'partial';
            $db->prepare('INSERT INTO goods_receipts(receipt_code, order_id, idempotency_key, received_by, note, receipt_status) VALUES(:code, :order_id, :key, :user_id, :note, :status)')
                ->execute(['code' => $receiptCode, 'order_id' => $orderId, 'key' => $key, 'user_id' => $umkmId, 'note' => $note, 'status' => $receiptStatus]);
            $receiptId = (int) $db->lastInsertId();

            $insert = $db->prepare('INSERT INTO goods_receipt_items(goods_receipt_id, order_item_id, accepted_qty, rejected_qty, rejection_reason) VALUES(:receipt_id, :item_id, :accepted, :rejected, :reason)');
            foreach ($normalized as $item) {
                $insert->execute(['receipt_id' => $receiptId, 'item_id' => $item['id'], 'accepted' => $item['accepted'], 'rejected' => $item['rejected'], 'reason' => $item['reason'] ?: null]);
            }

            $to = $full ? 'received' : 'partially_received';
            $historyReason = $full ? 'Seluruh quantity telah diterima' : 'Sebagian quantity telah diterima';
            self::transition($db, $order, $to, $umkmId, $historyReason, ['shipped', 'partially_received']);
            if ($full) {
                $db->prepare('UPDATE orders SET received_at = NOW(), completed_at = NOW() WHERE id = :id')->execute(['id' => $orderId]);
            }

            self::outbox($db, $orderId, $full ? 'RESTOCK_COMPLETED' : 'GOODS_PARTIALLY_RECEIVED', ['order_id' => $orderId, 'receipt_id' => $receiptId, 'receipt_code' => $receiptCode, 'inventory_sync_status' => 'pending']);
            $db->commit();

            $message = $full ? 'Penerimaan penuh tercatat. Menunggu sinkronisasi Inventory.' : 'Penerimaan sebagian tercatat.';
            return ['status' => 'success', 'message' => $message, 'data' => ['receipt_code' => $receiptCode, 'order_status' => $to, 'inventory_sync_status' => 'pending']];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function cancel(int $orderId, int $umkmId, string $reason): array {
        $trimmedReason = trim($reason);
        if (strlen($trimmedReason) < 5) {
            return ['status' => 'error', 'message' => 'Alasan pembatalan minimal 5 karakter.'];
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $query = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || (int) $order['umkm_id'] !== $umkmId) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            if ($order['payment_status'] === 'paid') {
                throw new DomainException('Order yang sudah dibayar memerlukan refund SmartBank dan belum dapat dibatalkan lokal.');
            }
            if ($order['payment_status'] === 'pending') {
                throw new DomainException('Payment request sedang diverifikasi SmartBank dan belum dapat dibatalkan.');
            }

            self::transition($db, $order, 'cancelled', $umkmId, 'Dibatalkan UMKM: ' . $trimmedReason, ['submitted', 'pending_payment', 'payment_failed']);
            $db->prepare('UPDATE orders SET cancellation_reason = :reason WHERE id = :id')->execute(['reason' => $trimmedReason, 'id' => $orderId]);
            self::outbox($db, $orderId, 'SUPPLIER_ORDER_CANCELLED', ['order_id' => $orderId, 'reason' => $trimmedReason, 'refund_required' => false]);

            $db->commit();
            return ['status' => 'success', 'message' => 'Pesanan dibatalkan.'];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function openDispute(int $orderId, int $umkmId, string $category, string $description): array {
        $allowed = ['shortage', 'damaged', 'quality', 'late', 'other'];
        $trimmedDescription = trim($description);
        if (!in_array($category, $allowed, true) || strlen($trimmedDescription) < 10) {
            return ['status' => 'error', 'message' => 'Kategori dan uraian sengketa minimal 10 karakter wajib diisi.'];
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $query = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || (int) $order['umkm_id'] !== $umkmId) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            if (!in_array($order['status'], ['shipped', 'partially_received', 'received'], true)) {
                throw new DomainException('Sengketa hanya dapat dibuat setelah barang dikirim.');
            }

            $check = $db->prepare("SELECT id FROM procurement_disputes WHERE order_id = :order_id AND status = 'open'");
            $check->execute(['order_id' => $orderId]);
            if ($check->fetch()) {
                throw new DomainException('Masih ada sengketa terbuka untuk pesanan ini.');
            }

            $code = 'DSP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $db->prepare('INSERT INTO procurement_disputes(dispute_code, order_id, opened_by, category, description) VALUES(:code, :order_id, :user_id, :category, :description)')
                ->execute(['code' => $code, 'order_id' => $orderId, 'user_id' => $umkmId, 'category' => $category, 'description' => $trimmedDescription]);
            self::outbox($db, $orderId, 'PROCUREMENT_DISPUTE_OPENED', ['order_id' => $orderId, 'dispute_code' => $code, 'category' => $category]);

            $db->commit();
            return ['status' => 'success', 'message' => 'Sengketa berhasil dibuat.', 'data' => ['dispute_code' => $code]];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function supplierPerformance(int $supplierId): array {
        $db = getDB();
        $query = $db->prepare("SELECT COUNT(*) total_orders, SUM(status IN ('received', 'completed')) completed_orders, SUM(status = 'rejected') rejected_orders, SUM(status = 'cancelled') cancelled_orders, AVG(CASE WHEN shipped_at IS NOT NULL AND paid_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, paid_at, shipped_at) END) avg_fulfillment_hours FROM orders WHERE supplier_id = :supplier_id");
        $query->execute(['supplier_id' => $supplierId]);
        $orders = $query->fetch();

        $disputeQuery = $db->prepare("SELECT COUNT(*) disputes, SUM(d.status = 'open') open_disputes FROM procurement_disputes d JOIN orders o ON o.id = d.order_id WHERE o.supplier_id = :supplier_id");
        $disputeQuery->execute(['supplier_id' => $supplierId]);

        return array_merge($orders ?: [], $disputeQuery->fetch() ?: []);
    }

    public static function resolveDispute(int $disputeId, int $supplierId, string $resolution, string $note): array {
        $allowed = ['resolved', 'rejected'];
        $trimmedNote = trim($note);
        if (!in_array($resolution, $allowed, true) || strlen($trimmedNote) < 10) {
            return ['status' => 'error', 'message' => 'Resolusi dan catatan minimal 10 karakter wajib diisi.'];
        }

        $db = getDB();
        $query = $db->prepare('SELECT d.*, o.order_code, o.supplier_id FROM procurement_disputes d JOIN orders o ON o.id = d.order_id WHERE d.id = :id FOR UPDATE');
        $query->execute(['id' => $disputeId]);
        $dispute = $query->fetch();
        if (!$dispute || (int) $dispute['supplier_id'] !== $supplierId) {
            return ['status' => 'error', 'message' => 'Dispute tidak ditemukan.'];
        }
        if ($dispute['status'] !== 'open') {
            return ['status' => 'error', 'message' => 'Dispute sudah diproses.'];
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE procurement_disputes SET status = :status, resolution_note = :note, resolved_by = :user_id, resolved_at = NOW() WHERE id = :id AND status = 'open'")
                ->execute(['status' => $resolution, 'note' => $trimmedNote, 'user_id' => $supplierId, 'id' => $disputeId]);
            self::outbox($db, (int) $dispute['order_id'], 'PROCUREMENT_DISPUTE_' . strtoupper($resolution), ['order_id' => (int) $dispute['order_id'], 'dispute_id' => $disputeId, 'resolution' => $resolution]);

            $db->commit();
            return ['status' => 'success', 'message' => 'Dispute berhasil diperbarui.'];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function requestRefund(int $orderId, int $umkmId, string $key, string $reason): array {
        $db = getDB();
        $db->beginTransaction();

        try {
            $existing = $db->prepare('SELECT * FROM refund_requests WHERE idempotency_key = :key');
            $existing->execute(['key' => $key]);
            $refund = $existing->fetch();
            if ($refund) {
                $db->rollBack();
                return ['status' => 'success', 'message' => 'Refund request sebelumnya dikembalikan.', 'data' => ['refund_code' => $refund['refund_code'], 'status' => $refund['status'], 'idempotent_replay' => true]];
            }

            $query = $db->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $order = $query->fetch();
            if (!$order || (int) $order['umkm_id'] !== $umkmId) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            if ($order['payment_status'] !== 'paid' || !in_array($order['status'], ['paid', 'processing', 'shipped', 'partially_received', 'received'], true)) {
                throw new DomainException('Order belum memenuhi syarat refund.');
            }
            if (strlen(trim($reason)) < 10) {
                throw new DomainException('Alasan refund minimal 10 karakter.');
            }

            $code = 'RF-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $db->prepare('INSERT INTO refund_requests(refund_code, order_id, requested_by, idempotency_key, amount, reason) VALUES(:code, :order_id, :user_id, :key, :amount, :reason)')
                ->execute(['code' => $code, 'order_id' => $orderId, 'user_id' => $umkmId, 'key' => $key, 'amount' => $order['total'], 'reason' => trim($reason)]);
            $db->prepare("UPDATE orders SET status = 'refund_pending' WHERE id = :id")->execute(['id' => $orderId]);
            self::addStatusHistory($orderId, $order['status'], 'refund_pending', $umkmId, 'Refund request lokal menunggu SmartBank', $db);
            self::outbox($db, $orderId, 'REFUND_REQUESTED', ['order_id' => $orderId, 'refund_code' => $code, 'amount' => $order['total'], 'integration_status' => 'pending_smartbank']);

            $db->commit();
            return ['status' => 'success', 'message' => 'Refund request tercatat dan menunggu SmartBank.', 'data' => ['refund_code' => $code, 'status' => 'pending']];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    public static function completeMockRefund(int $refundId, bool $success, string $reference = ''): array {
        $db = getDB();
        $db->beginTransaction();

        try {
            $query = $db->prepare('SELECT r.*, o.status order_status, o.order_code FROM refund_requests r JOIN orders o ON o.id = r.order_id WHERE r.id = :id FOR UPDATE');
            $query->execute(['id' => $refundId]);
            $refund = $query->fetch();
            if (!$refund) {
                throw new DomainException('Refund tidak ditemukan.');
            }
            if ($refund['status'] !== 'pending') {
                $db->rollBack();
                return ['status' => 'success', 'message' => 'Refund sudah diproses.', 'data' => ['status' => $refund['status'], 'idempotent_replay' => true]];
            }

            $newOrder = $success ? 'refunded' : 'refund_failed';
            $refundStatus = $success ? 'succeeded' : 'failed';
            $paymentStatus = $success ? 'refunded' : 'paid';
            $failureReason = $success ? null : 'Mock SmartBank menolak refund';
            $db->prepare('UPDATE refund_requests SET status = :status, external_reference = :ref, failure_reason = :error WHERE id = :id')
                ->execute(['status' => $refundStatus, 'ref' => $reference ?: null, 'error' => $failureReason, 'id' => $refundId]);
            $db->prepare('UPDATE orders SET status = :status, payment_status = :payment WHERE id = :id')
                ->execute(['status' => $newOrder, 'payment' => $paymentStatus, 'id' => $refund['order_id']]);
            self::addStatusHistory($refund['order_id'], 'refund_pending', $newOrder, null, $success ? 'Refund mock berhasil' : 'Refund mock gagal', $db);
            self::outbox($db, (int) $refund['order_id'], $success ? 'REFUND_SUCCEEDED' : 'REFUND_FAILED', ['order_id' => (int) $refund['order_id'], 'refund_code' => $refund['refund_code'], 'external_reference' => $reference ?: null, 'delivery_mode' => 'local_mock']);

            $db->commit();
            return ['status' => 'success', 'message' => $success ? 'Mock refund berhasil diproses.' : 'Mock refund gagal diproses.', 'data' => ['status' => $refundStatus]];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }
}
