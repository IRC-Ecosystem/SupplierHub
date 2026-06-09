<?php
/**
 * Order Controller
 * Handles order lifecycle: create → review → approve/reject → payment
 * 
 * Sesuai Aplikasi.docx:
 * - Semua transaksi → payment request ke SmartBank
 * - SupplierHub tidak mengelola saldo langsung
 * - Fee supplier 3%
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../services/SmartBankService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';
require_once __DIR__ . '/../models/Payment.php';

class OrderController {

    /**
     * UMKM: Create new order (checkout)
     * IPO: items[] → validate stock → create order (pending)
     */
    public static function checkout($data, $umkm_id) {
        // Validate
        if (empty($data['items']) || !is_array($data['items'])) {
            return ['status' => 'error', 'message' => 'Items pesanan wajib diisi.'];
        }

        if (empty($data['supplier_id'])) {
            return ['status' => 'error', 'message' => 'Supplier ID wajib diisi.'];
        }

        // Validate each item
        foreach ($data['items'] as $item) {
            if (empty($item['material_id']) || empty($item['qty']) || $item['qty'] <= 0) {
                return ['status' => 'error', 'message' => 'Data item tidak valid.'];
            }
        }

        try {
            $discount = isset($data['discount']) ? (int)$data['discount'] : 0;
            $result = Order::create($umkm_id, $data['supplier_id'], $data['items'], $discount);
            return [
                'status'  => 'success',
                'message' => 'Pesanan berhasil dibuat. Menunggu konfirmasi supplier.',
                'data'    => $result
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Gagal membuat pesanan: ' . $e->getMessage()];
        }
    }

    /**
     * UMKM: Create and pay bundle order directly (Shopee/Gojek flow)
     */
    public static function directCheckout($data, $umkm_id) {
        if (empty($data['items']) || !is_array($data['items'])) {
            return ['status' => 'error', 'message' => 'Items pesanan wajib diisi.'];
        }

        if (empty($data['supplier_id'])) {
            return ['status' => 'error', 'message' => 'Supplier ID wajib diisi.'];
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $subtotal = 0;
            // Validate stock and price for each item
            foreach ($data['items'] as $item) {
                $matId = $item['material_id'];
                $qty = $item['qty'];

                $stmt = $db->prepare("SELECT stock, price, name FROM materials WHERE id = :id");
                $stmt->execute(['id' => $matId]);
                $mat = $stmt->fetch();

                if (!$mat) {
                    throw new Exception("Bahan baku tidak ditemukan.");
                }

                if ($mat['stock'] < $qty) {
                    throw new Exception("Stok untuk " . $mat['name'] . " tidak mencukupi. Tersedia: " . $mat['stock'] . ", Dibutuhkan: " . $qty);
                }

                $subtotal += $mat['price'] * $qty;
            }

            // Calculate 3% Margin
            $fee = (int) round($subtotal * FEE_SUPPLIER);
            $discount = isset($data['discount']) ? (int)$data['discount'] : 0;
            $total = ($subtotal + $fee) - $discount;
            if ($total < 0) $total = 0;

            // Generate order code
            $orderCode = 'ORD-B2B-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT) . '-PMP';

            // DEPENDENCY INVERSION (SOLID): Gunakan PaymentGatewayInterface
            /** @var PaymentGatewayInterface $gateway */
            $gateway = 'SmartBankService';
            $paymentResponse = $gateway::pay(
                $umkm_id,
                $subtotal - $discount,
                $fee,
                "Direct payment for order {$orderCode}"
            );

            // EVALUASI STOK BERDASARKAN RESPONS SMARTBANK
            if ($paymentResponse['status'] !== 'success') {
                throw new Exception('Pembayaran ditolak oleh SmartBank: ' . ($paymentResponse['message'] ?? 'Unknown error'));
            }

            // Deduct stock for each item ONLY after success
            foreach ($data['items'] as $item) {
                Material::reduceStock($item['material_id'], $item['qty']);
            }

            $ref = $paymentResponse['data']['payment_id'] ?? ('SB-REF-' . date('Ymd') . '-' . rand(1000, 9999));

            // Log ke tabel payments lokal (Buku Kas)
            Payment::create($umkm_id, 'debit', $total, "Pembayaran pesanan {$orderCode}", $ref);
            Payment::create($data['supplier_id'], 'credit', $subtotal - $discount, "Penerimaan dana pesanan {$orderCode}", $ref);

            // Insert completed order directly
            $stmt = $db->prepare("
                INSERT INTO orders (order_code, umkm_id, supplier_id, status, subtotal, fee_supplier, total, smartbank_ref, completed_at)
                VALUES (:code, :umkm, :supplier, 'completed', :subtotal, :fee, :total, :ref, NOW())
            ");
            $stmt->execute([
                'code'     => $orderCode,
                'umkm'     => $umkm_id,
                'supplier' => $data['supplier_id'],
                'subtotal' => $subtotal,
                'fee'      => $fee,
                'total'    => $total,
                'ref'      => $ref
            ]);
            $orderId = $db->lastInsertId();

            // Insert order items
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, material_id, qty, price_at_order)
                VALUES (:order_id, :material_id, :qty, :price)
            ");
            foreach ($data['items'] as $item) {
                // Fetch price again just to be secure
                $stmtPrice = $db->prepare("SELECT price FROM materials WHERE id = :id");
                $stmtPrice->execute(['id' => $item['material_id']]);
                $price = $stmtPrice->fetch()['price'];

                $stmt->execute([
                    'order_id'    => $orderId,
                    'material_id' => $item['material_id'],
                    'qty'         => $item['qty'],
                    'price'       => $price
                ]);
            }

            $db->commit();

            return [
                'status'  => 'success',
                'message' => 'Pembayaran Berhasil! Transaksi selesai via SmartBank.',
                'data'    => [
                    'order_id'   => $orderId,
                    'order_code' => $orderCode,
                    'total'      => $total,
                    'ref'        => $ref
                ]
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return ['status' => 'error', 'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()];
        }
    }

    /**
     * Supplier: Get pending orders
     */
    public static function listPending($supplier_id) {
        $orders = Order::getPending($supplier_id);
        return [
            'status'  => 'success',
            'message' => 'Pesanan masuk berhasil diambil.',
            'data'    => $orders
        ];
    }

    /**
     * Supplier: Get order detail with items
     */
    public static function detail($order_id) {
        $order = Order::getWithItems($order_id);
        if (!$order) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        // Check stock for each item
        $allSufficient = true;
        foreach ($order['items'] as &$item) {
            $item['sufficient'] = $item['current_stock'] >= $item['qty'];
            if (!$item['sufficient']) $allSufficient = false;
        }
        $order['stock_sufficient'] = $allSufficient;

        return [
            'status' => 'success',
            'data'   => $order
        ];
    }

    /**
     * Supplier: Approve order and trigger payment
     * IPO: order_id → validate stock → reduce stock → payment request → completed
     */
    public static function approve($order_id, $supplier_id, $resi = null) {
        // Get order
        $order = Order::getWithItems($order_id);
        if (!$order) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        if ($order['supplier_id'] != $supplier_id) {
            return ['status' => 'error', 'message' => 'Akses ditolak.'];
        }

        if ($order['status'] !== 'pending') {
            return ['status' => 'error', 'message' => 'Pesanan sudah diproses sebelumnya.'];
        }

        // Check stock
        foreach ($order['items'] as $item) {
            if ($item['current_stock'] < $item['qty']) {
                return [
                    'status'  => 'error',
                    'message' => "Stok {$item['material_name']} tidak mencukupi. Tersedia: {$item['current_stock']}, Dibutuhkan: {$item['qty']}"
                ];
            }
        }

        // DEPENDENCY INVERSION (SOLID): Gunakan PaymentGatewayInterface
        /** @var PaymentGatewayInterface $gateway */
        $gateway = 'SmartBankService';
        $paymentResponse = $gateway::pay(
            $order['umkm_id'],
            $order['subtotal'],
            $order['fee_supplier'], // which is exactly 3% calculated when order was created
            "Payment for order {$order['order_code']} from {$order['umkm_name']}"
        );

        // EVALUASI STOK BERDASARKAN RESPONS SMARTBANK
        if ($paymentResponse['status'] !== 'success') {
            return [
                'status'  => 'error',
                'message' => 'Pembayaran ditolak oleh SmartBank: ' . ($paymentResponse['message'] ?? 'Unknown error')
            ];
        }

        // Reduce stock ONLY after payment success
        foreach ($order['items'] as $item) {
            Material::reduceStock($item['material_id'], $item['qty']);
        }

        // Update order status
        $smartbankRef = $paymentResponse['data']['payment_id'] ?? null;
        Order::approve($order_id, $smartbankRef, $resi);

        // Log ke tabel payments lokal (Buku Kas)
        Payment::create($order['umkm_id'], 'debit', $order['total'], "Pembayaran pesanan {$order['order_code']}", $smartbankRef);
        Payment::create($order['supplier_id'], 'credit', $order['subtotal'], "Penerimaan dana pesanan {$order['order_code']}", $smartbankRef);

        return [
            'status'  => 'success',
            'message' => "Pesanan {$order['order_code']} berhasil diapprove. Payment request telah dikirim ke SmartBank.",
            'data'    => [
                'order_code'    => $order['order_code'],
                'subtotal'      => $order['subtotal'],
                'fee_supplier'  => $order['fee_supplier'],
                'total'         => $order['total'],
                'smartbank_ref' => $smartbankRef,
                'payment_status' => $paymentResponse['status']
            ]
        ];
    }

    /**
     * Supplier: Reject order
     */
    public static function reject($order_id, $supplier_id) {
        $order = Order::findById($order_id);
        if (!$order || $order['supplier_id'] != $supplier_id) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        Order::reject($order_id);
        return [
            'status'  => 'success',
            'message' => 'Pesanan berhasil ditolak.'
        ];
    }

    /**
     * Supplier: Get completed orders (laporan tagihan)
     */
    public static function listCompleted($supplier_id) {
        $orders = Order::getCompleted($supplier_id);
        return [
            'status'  => 'success',
            'message' => 'Laporan tagihan berhasil diambil.',
            'data'    => $orders
        ];
    }

    /**
     * UMKM: Get order history
     */
    public static function history($umkm_id) {
        $orders = Order::getByUmkm($umkm_id);
        return [
            'status'  => 'success',
            'message' => 'Riwayat pesanan berhasil diambil.',
            'data'    => $orders
        ];
    }
}
