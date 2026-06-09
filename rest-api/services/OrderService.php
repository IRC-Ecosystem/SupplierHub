<?php
/**
 * Order Service
 * LAYER: Business Logic (Service Layer)
 * 
 * Arsitektur: Service berisi LOGIKA BISNIS murni.
 * Akses database didelegasikan ke OrderRepository & MaterialRepository.
 * Integrasi pembayaran didelegasikan ke SmartBankService (Payment Microservice).
 * 
 * Microservice Domain: Order Service
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: supplier_id, items[] (material_id, qty)
 *   Proses: Validasi → hitung subtotal → fee supplier 3% → create order → log
 *   Output: order data (order_code, subtotal, fee, total)
 * 
 * Aturan #3: Semua output transaksi = payment request
 * Aturan #7: Fee Supplier = 3%
 */

require_once __DIR__ . '/../models/OrderRepository.php';
require_once __DIR__ . '/../models/MaterialRepository.php';
require_once __DIR__ . '/SmartBankService.php';
require_once __DIR__ . '/MaterialService.php';

class OrderService {

    /**
     * List orders dengan filter berdasarkan role
     */
    public static function list($userId, $role, $status = null, $page = 1, $limit = 10) {
        return OrderRepository::findAll($userId, $role, $status, $page, $limit);
    }

    /**
     * Cari order berdasarkan ID
     */
    public static function findById($id) {
        return OrderRepository::findById($id);
    }

    /**
     * Cari order dengan items detail + cek stok
     * LOGIKA BISNIS: Pengecekan stok per item dilakukan di sini
     */
    public static function findWithItems($id) {
        $order = OrderRepository::findWithItems($id);
        if (!$order) return null;

        // Logika bisnis: cek kecukupan stok per item
        $allSufficient = true;
        foreach ($order['items'] as &$item) {
            $item['sufficient'] = $item['current_stock'] >= $item['qty'];
            if (!$item['sufficient']) $allSufficient = false;
        }
        $order['stock_sufficient'] = $allSufficient;

        return $order;
    }

    /**
     * Buat order baru (checkout)
     * 
     * LOGIKA BISNIS:
     * 1. Ambil harga material terkini dari MaterialRepository
     * 2. Hitung subtotal
     * 3. Hitung fee supplier 3% (Aturan Keuangan #7)
     * 4. Simpan ke database via OrderRepository
     */
    public static function create($umkmId, $supplierId, $items) {
        // 1. Ambil harga & hitung subtotal (logika bisnis)
        $subtotal = 0;
        foreach ($items as &$item) {
            $mat = MaterialRepository::getPrice($item['material_id']);
            if (!$mat) {
                throw new Exception("Material ID {$item['material_id']} tidak ditemukan.");
            }
            $item['price_at_order'] = $mat['price'];
            $subtotal += $mat['price'] * $item['qty'];
        }

        // 2. Hitung fee (logika bisnis — Aturan Keuangan #7: Fee Supplier 3%)
        $fee = (int) round($subtotal * FEE_SUPPLIER);
        $total = $subtotal + $fee;
        $orderCode = OrderRepository::generateCode();

        // 3. Simpan ke database (delegasi ke Repository)
        $orderId = OrderRepository::create(
            [
                'order_code'   => $orderCode,
                'umkm_id'      => $umkmId,
                'supplier_id'  => $supplierId,
                'subtotal'     => $subtotal,
                'fee_supplier' => $fee,
                'total'        => $total
            ],
            $items
        );

        return [
            'id'           => $orderId,
            'order_code'   => $orderCode,
            'subtotal'     => $subtotal,
            'fee_supplier' => $fee,
            'total'        => $total,
            'status'       => 'pending'
        ];
    }

    /**
     * Proses approval order (LOGIKA BISNIS UTAMA)
     * 
     * Alur Microservice:
     *   OrderService → SmartBankService (Payment) → MaterialService (Inventory)
     * 
     * Aturan #3: Output = payment request ke SmartBank
     * Aturan #4: SmartBank sebagai pusat kontrol keuangan
     * 
     * @throws Exception jika validasi gagal atau pembayaran ditolak
     */
    public static function processApproval($orderId, $supplierId) {
        // 1. Ambil data order dari Repository
        $order = self::findWithItems($orderId);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan.', 404);
        }

        // 2. Validasi kepemilikan (logika bisnis)
        if ($order['supplier_id'] != $supplierId) {
            throw new Exception('Akses ditolak. Pesanan bukan untuk supplier Anda.', 403);
        }

        // 3. Validasi status (logika bisnis)
        if ($order['status'] !== 'pending') {
            throw new Exception('Pesanan sudah diproses sebelumnya.', 409);
        }

        // 4. Cek stok setiap item (logika bisnis)
        foreach ($order['items'] as $item) {
            if ($item['current_stock'] < $item['qty']) {
                throw new Exception(
                    "Stok {$item['material_name']} tidak mencukupi. " .
                    "Tersedia: {$item['current_stock']}, Dibutuhkan: {$item['qty']}",
                    400
                );
            }
        }

        // 5. Kirim payment request ke SmartBank (Aturan #3 & #4)
        //    → Komunikasi antar Microservice: Order → Payment
        $paymentResponse = SmartBankService::pay(
            $order['umkm_id'],
            $order['subtotal'],
            $order['fee_supplier'],
            "Payment for order {$order['order_code']} from {$order['umkm_name']}"
        );

        if ($paymentResponse['status'] !== 'success') {
            throw new Exception(
                'Pembayaran ditolak oleh SmartBank: ' . ($paymentResponse['message'] ?? 'Unknown error'),
                402
            );
        }

        // 6. Reduce stock via MaterialService (Inventory Microservice)
        foreach ($order['items'] as $item) {
            MaterialService::reduceStock($item['material_id'], $item['qty']);
        }

        // 7. Update status order via Repository
        $smartbankRef = $paymentResponse['data']['payment_id'] ?? null;
        OrderRepository::approve($orderId, $smartbankRef);

        return [
            'order_code'     => $order['order_code'],
            'subtotal'       => $order['subtotal'],
            'fee_supplier'   => $order['fee_supplier'],
            'total'          => $order['total'],
            'smartbank_ref'  => $smartbankRef,
            'payment_status' => $paymentResponse['status']
        ];
    }

    /**
     * Reject order
     */
    public static function reject($id) {
        return OrderRepository::reject($id);
    }
}
