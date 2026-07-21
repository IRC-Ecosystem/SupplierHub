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
require_once __DIR__ . '/../models/Procurement.php';

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
        return self::createSubmittedOrder($data, $umkm_id);
    }

    private static function createSubmittedOrder($data, $umkm_id) {
        if (empty($data['items']) || !is_array($data['items']) || empty($data['supplier_id'])) {
            return ['status'=>'error','message'=>'Items dan supplier wajib diisi.'];
        }
        $key = trim((string)($data['idempotency_key'] ?? ''));
        if ($key === '' || strlen($key) > 100) {
            return ['status'=>'error','message'=>'Idempotency key wajib diisi dan maksimal 100 karakter.'];
        }
        $existing = Order::findByIdempotencyKey($umkm_id, $key);
        if ($existing) {
            return ['status'=>'success','message'=>'Order yang sama dikembalikan tanpa membuat transaksi baru.','data'=>[
                'order_id'=>$existing['id'],'order_code'=>$existing['order_code'],'total'=>$existing['total'],
                'order_status'=>$existing['status'],'payment_status'=>$existing['payment_status'],'idempotent_replay'=>true
            ]];
        }

        $db = getDB();
        $db->beginTransaction();
        try {
            $subtotal = 0;
            $validated = [];
            foreach ($data['items'] as $item) {
                $matId = (int)($item['material_id'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);
                if ($matId < 1 || $qty < 1) throw new Exception('Data item tidak valid.');
                $stmt = $db->prepare("SELECT id,price,stock,supplier_id,name FROM materials WHERE id=:id FOR UPDATE");
                $stmt->execute(['id'=>$matId]);
                $mat = $stmt->fetch();
                if (!$mat) throw new Exception('Bahan baku tidak ditemukan.');
                if ((int)$mat['supplier_id'] !== (int)$data['supplier_id']) throw new Exception('Semua bahan harus berasal dari supplier yang dipilih.');
                if ((int)$mat['stock'] < $qty) throw new Exception("Stok {$mat['name']} tidak mencukupi.");
                $subtotal += (int)$mat['price'] * $qty;
                $validated[] = ['material_id'=>$matId,'qty'=>$qty,'price'=>(int)$mat['price']];
            }
            // Discount is computed exclusively from server-side session rules.
            $subscription = $_SESSION['subscription'] ?? '';
            $subscriptionRate = $subscription === 'gold' ? 0.10 : ($subscription === 'vip' ? 0.05 : 0.0);
            $subscriptionDiscount = (int)round($subtotal * $subscriptionRate);
            $requestedBundleDiscount = max(0, (int)($_SESSION['bundle_discount'] ?? 0));
            $bundleDiscountCap = (int)round($subtotal * 0.05);
            $bundleDiscount = min($requestedBundleDiscount, $bundleDiscountCap);
            $discount = $subscriptionDiscount + $bundleDiscount;
            $fee = (int)round($subtotal * FEE_SUPPLIER);
            $total = max(0, $subtotal + $fee - $discount);
            $code = 'ORD-B2B-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 9));
            $stmt = $db->prepare("INSERT INTO orders (order_code,umkm_id,supplier_id,status,payment_status,subtotal,fee_supplier,total,idempotency_key) VALUES (:code,:umkm,:supplier,'submitted','unpaid',:subtotal,:fee,:total,:ikey)");
            $stmt->execute(['code'=>$code,'umkm'=>$umkm_id,'supplier'=>$data['supplier_id'],'subtotal'=>$subtotal,'fee'=>$fee,'total'=>$total,'ikey'=>$key]);
            $orderId = (int)$db->lastInsertId();
            $itemStmt = $db->prepare("INSERT INTO order_items (order_id,material_id,qty,price_at_order) VALUES (:oid,:mid,:qty,:price)");
            foreach ($validated as $item) $itemStmt->execute(['oid'=>$orderId,'mid'=>$item['material_id'],'qty'=>$item['qty'],'price'=>$item['price']]);
            Order::addStatusHistory($orderId, null, 'submitted', $umkm_id, 'Checkout dibuat', $db);
            $db->commit();
            return ['status'=>'success','message'=>'Pesanan dibuat dan menunggu persetujuan supplier. Belum ada pembayaran.','data'=>[
                'order_id'=>$orderId,'order_code'=>$code,'total'=>$total,'order_status'=>'submitted','payment_status'=>'unpaid'
            ]];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $existing = Order::findByIdempotencyKey($umkm_id, $key);
                if ($existing) return ['status'=>'success','message'=>'Order yang sama sudah dibuat.','data'=>$existing];
            }
            return ['status'=>'error','message'=>'Gagal membuat pesanan: '.$e->getMessage()];
        }
    }

    private static function legacyDirectCheckout($data, $umkm_id) {
        if (empty($data['items']) || !is_array($data['items'])) {
            return ['status' => 'error', 'message' => 'Items pesanan wajib diisi.'];
        }

        if (empty($data['supplier_id'])) {
            return ['status' => 'error', 'message' => 'Supplier ID wajib diisi.'];
        }

        $idempotencyKey = trim((string)($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return ['status' => 'error', 'message' => 'Idempotency key wajib diisi dan maksimal 100 karakter.'];
        }

        $existing = Order::findByIdempotencyKey($umkm_id, $idempotencyKey);
        if ($existing) {
            return [
                'status' => 'success',
                'message' => 'Transaksi sebelumnya dikembalikan tanpa membuat pembayaran baru.',
                'data' => [
                    'order_id' => $existing['id'],
                    'order_code' => $existing['order_code'],
                    'total' => $existing['total'],
                    'ref' => $existing['smartbank_ref'],
                    'idempotent_replay' => true
                ]
            ];
        }

        $db = getDB();
        $db->beginTransaction();

        try {
            $subtotal = 0;
            // Validate stock and price for each item
            foreach ($data['items'] as $item) {
                $matId = $item['material_id'];
                $qty = $item['qty'];

                $stmt = $db->prepare("SELECT stock, price, name, supplier_id FROM materials WHERE id = :id FOR UPDATE");
                $stmt->execute(['id' => $matId]);
                $mat = $stmt->fetch();

                if (!$mat) {
                    throw new Exception("Bahan baku tidak ditemukan.");
                }

                if ($mat['stock'] < $qty) {
                    throw new Exception("Stok untuk " . $mat['name'] . " tidak mencukupi. Tersedia: " . $mat['stock'] . ", Dibutuhkan: " . $qty);
                }

                if ((int)$mat['supplier_id'] !== (int)$data['supplier_id']) {
                    throw new Exception('Semua bahan harus berasal dari supplier yang dipilih.');
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
                "Direct payment for order {$orderCode}",
                $idempotencyKey
            );

            // EVALUASI STOK BERDASARKAN RESPONS SMARTBANK
            if ($paymentResponse['status'] !== 'success') {
                throw new Exception('Pembayaran ditolak oleh SmartBank: ' . ($paymentResponse['message'] ?? 'Unknown error'));
            }

            // Deduct stock for each item ONLY after success
            foreach ($data['items'] as $item) {
                if (!Material::reduceStock($item['material_id'], $item['qty'])) {
                    throw new Exception('Stok berubah saat checkout. Silakan ulangi transaksi.');
                }
            }

            $ref = $paymentResponse['data']['payment_id'] ?? ('SB-REF-' . date('Ymd') . '-' . rand(1000, 9999));

            // Log ke tabel payments lokal (Buku Kas)
            Payment::create($umkm_id, 'debit', $total, "Pembayaran pesanan {$orderCode}", $ref, $db);
            Payment::create($data['supplier_id'], 'credit', $subtotal - $discount, "Penerimaan dana pesanan {$orderCode}", $ref, $db);

            // Insert completed order directly
            $stmt = $db->prepare("
                INSERT INTO orders (order_code, umkm_id, supplier_id, status, payment_status, subtotal, fee_supplier, total, smartbank_ref, idempotency_key, completed_at)
                VALUES (:code, :umkm, :supplier, 'completed', 'paid', :subtotal, :fee, :total, :ref, :ikey, NOW())
            ");
            $stmt->execute([
                'code'     => $orderCode,
                'umkm'     => $umkm_id,
                'supplier' => $data['supplier_id'],
                'subtotal' => $subtotal,
                'fee'      => $fee,
                'total'    => $total,
                'ref'      => $ref,
                'ikey'     => $idempotencyKey
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
    public static function detail($order_id, $user_id = null, $role = null) {
        $order = Order::getWithItems($order_id);
        if (!$order) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        if ($user_id !== null && !Order::canBeAccessedBy($order, $user_id, $role)) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        // Check stock for each item
        $allSufficient = true;
        foreach ($order['items'] as &$item) {
            $item['sufficient'] = $item['current_stock'] >= $item['qty'];
            if (!$item['sufficient']) $allSufficient = false;
        }
        $order['stock_sufficient'] = $allSufficient;
        $order['status_history'] = Order::getStatusHistory($order_id);

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
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$order_id]);
            $order = $stmt->fetch();
            if (!$order || (int)$order['supplier_id'] !== (int)$supplier_id) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['status'] !== 'submitted') throw new Exception('Pesanan sudah diproses sebelumnya.');
            if (!Order::approve($order_id, null, $resi)) throw new Exception('Status pesanan gagal diperbarui.');
            Order::addStatusHistory($order_id, 'submitted', 'pending_payment', $supplier_id, 'Supplier menerima pesanan', $db);
            Procurement::enqueue($db,(int)$order_id,'SUPPLIER_ORDER_CONFIRMED',['order_id'=>(int)$order_id,'order_code'=>$order['order_code'],'payment_status'=>'unpaid']);
            $db->commit();
            return ['status'=>'success','message'=>'Pesanan diterima. UMKM sekarang dapat mengirim payment request ke SmartBank.','data'=>[
                'order_code'=>$order['order_code'],'order_status'=>'pending_payment','payment_status'=>'unpaid'
            ]];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }

    private static function legacyApprove($order_id, $supplier_id, $resi = null) {
        $db = getDB();
        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT id FROM orders WHERE id = :id FOR UPDATE");
            $lock->execute(['id' => $order_id]);

        // Get order
        $order = Order::getWithItems($order_id);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan.');
        }

        if ($order['supplier_id'] != $supplier_id) {
            throw new Exception('Akses ditolak.');
        }

        if ($order['status'] !== 'pending') {
            throw new Exception('Pesanan sudah diproses sebelumnya.');
        }

        // Check stock
        foreach ($order['items'] as $item) {
            if ($item['current_stock'] < $item['qty']) {
                throw new Exception("Stok {$item['material_name']} tidak mencukupi. Tersedia: {$item['current_stock']}, Dibutuhkan: {$item['qty']}");
            }
        }

        // DEPENDENCY INVERSION (SOLID): Gunakan PaymentGatewayInterface
        /** @var PaymentGatewayInterface $gateway */
        $gateway = 'SmartBankService';
        $paymentResponse = $gateway::pay(
            $order['umkm_id'],
            $order['subtotal'],
            $order['fee_supplier'], // which is exactly 3% calculated when order was created
            "Payment for order {$order['order_code']} from {$order['umkm_name']}",
            'approve-order-' . $order['id']
        );

        // EVALUASI STOK BERDASARKAN RESPONS SMARTBANK
        if ($paymentResponse['status'] !== 'success') {
            throw new Exception('Pembayaran ditolak: ' . ($paymentResponse['message'] ?? 'Unknown error'));
        }

        // Reduce stock ONLY after payment success
        foreach ($order['items'] as $item) {
            if (!Material::reduceStock($item['material_id'], $item['qty'])) {
                throw new Exception('Stok berubah saat approval. Pesanan belum diselesaikan.');
            }
        }

        // Update order status
        $smartbankRef = $paymentResponse['data']['payment_id'] ?? null;
        if (!Order::approve($order_id, $smartbankRef, $resi)) {
            throw new Exception('Status pesanan berubah saat approval. Tidak ada perubahan yang disimpan.');
        }

        // Log ke tabel payments lokal (Buku Kas)
        Payment::create($order['umkm_id'], 'debit', $order['total'], "Pembayaran pesanan {$order['order_code']}", $smartbankRef, $db);
        Payment::create($order['supplier_id'], 'credit', $order['subtotal'], "Penerimaan dana pesanan {$order['order_code']}", $smartbankRef, $db);

        $db->commit();

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
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function simulatePayment($order_id, $umkm_id, $outcome, $idempotencyKey) {
        if (!in_array($outcome, ['success','failed'], true)) return ['status'=>'error','message'=>'Hasil simulasi tidak valid.'];
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) return ['status'=>'error','message'=>'Idempotency key pembayaran wajib diisi.'];
        $db = getDB();
        $db->beginTransaction();
        try {
            $attempt = $db->prepare("SELECT * FROM payment_attempts WHERE idempotency_key=:ikey LIMIT 1");
            $attempt->execute(['ikey'=>$idempotencyKey]);
            $prior = $attempt->fetch();
            if ($prior) {
                $db->rollBack();
                $order = Order::findById($order_id);
                return ['status'=>'success','message'=>'Hasil pembayaran sebelumnya dikembalikan.','data'=>['order_status'=>$order['status'],'payment_status'=>$order['payment_status'],'idempotent_replay'=>true]];
            }
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$order_id]);
            $order = $stmt->fetch();
            if (!$order || (int)$order['umkm_id'] !== (int)$umkm_id) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['payment_status'] === 'paid') {
                $db->rollBack();
                return ['status'=>'success','message'=>'Pesanan sudah dibayar. Tidak ada pembayaran baru.','data'=>['order_status'=>$order['status'],'payment_status'=>'paid','idempotent_replay'=>true]];
            }
            if (!in_array($order['status'], ['pending_payment','payment_failed'], true)) throw new Exception('Pesanan belum dapat dibayar. Tunggu persetujuan supplier.');
            $insertAttempt = $db->prepare("INSERT INTO payment_attempts (order_id,idempotency_key,status,error_message) VALUES (:oid,:ikey,:status,:error)");
            if ($outcome === 'failed') {
                $insertAttempt->execute(['oid'=>$order_id,'ikey'=>$idempotencyKey,'status'=>'failed','error'=>'Simulasi pembayaran gagal']);
                $db->prepare("UPDATE orders SET status='payment_failed',payment_status='failed' WHERE id=:id")->execute(['id'=>$order_id]);
                Order::addStatusHistory($order_id, $order['status'], 'payment_failed', $umkm_id, 'Simulasi pembayaran gagal', $db);
                $db->commit();
                return ['status'=>'success','message'=>'Pembayaran disimulasikan gagal. Stok tidak berubah.','data'=>['order_status'=>'payment_failed','payment_status'=>'failed']];
            }
            $payment = SmartBankService::pay($umkm_id, $order['subtotal'], $order['fee_supplier'], "Local payment {$order['order_code']}", $idempotencyKey);
            if ($payment['status'] !== 'success') throw new Exception($payment['message'] ?? 'Pembayaran gagal.');
            $items = $db->prepare("SELECT oi.*,m.name FROM order_items oi JOIN materials m ON m.id=oi.material_id WHERE oi.order_id=:oid FOR UPDATE");
            $items->execute(['oid'=>$order_id]);
            foreach ($items->fetchAll() as $item) {
                if (!Material::reduceStock($item['material_id'], $item['qty'])) throw new Exception("Stok {$item['name']} tidak mencukupi.");
            }
            $ref = $payment['data']['payment_id'];
            $insertAttempt->execute(['oid'=>$order_id,'ikey'=>$idempotencyKey,'status'=>'succeeded','error'=>null]);
            $db->prepare("UPDATE payment_attempts SET payment_reference=:ref WHERE idempotency_key=:ikey")->execute(['ref'=>$ref,'ikey'=>$idempotencyKey]);
            $db->prepare("UPDATE orders SET status='paid',payment_status='paid',smartbank_ref=:ref,paid_at=NOW() WHERE id=:id")->execute(['ref'=>$ref,'id'=>$order_id]);
            Payment::create($umkm_id,'debit',$order['total'],"Pembayaran pesanan {$order['order_code']}",$ref,$db);
            Payment::create($order['supplier_id'],'credit',$order['subtotal'],"Penerimaan dana pesanan {$order['order_code']}",$ref,$db);
            Order::addStatusHistory($order_id, $order['status'], 'paid', $umkm_id, 'Pembayaran lokal berhasil', $db);
            $db->commit();
            return ['status'=>'success','message'=>'Pembayaran berhasil. Pesanan sekarang berstatus PAID.','data'=>['order_status'=>'paid','payment_status'=>'paid','reference'=>$ref]];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }

    /**
     * Create an idempotent payment request. SupplierHub remains PENDING until
     * SmartBank verifies it through the future callback/event integration.
     */
    public static function requestPayment($order_id, $umkm_id, $idempotencyKey) {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return ['status'=>'error','message'=>'Idempotency key pembayaran wajib diisi.'];
        }
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$order_id]);
            $order = $stmt->fetch();
            if (!$order || (int)$order['umkm_id'] !== (int)$umkm_id) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['payment_status'] === 'paid') {
                $db->rollBack();
                return ['status'=>'success','message'=>'Pesanan sudah dibayar.','data'=>['order_status'=>$order['status'],'payment_status'=>'paid','idempotent_replay'=>true]];
            }
            if (!in_array($order['status'], ['pending_payment','payment_failed'], true)) {
                throw new Exception('Payment request belum dapat dibuat. Tunggu supplier menerima pesanan.');
            }

            $pending = $db->prepare("SELECT * FROM payment_attempts WHERE order_id=:oid AND status='pending' ORDER BY id DESC LIMIT 1");
            $pending->execute(['oid'=>$order_id]);
            $existing = $pending->fetch();
            if ($existing) {
                $db->rollBack();
                return ['status'=>'success','message'=>'Payment request sudah dikirim dan masih menunggu verifikasi SmartBank.','data'=>[
                    'request_id'=>$existing['id'],'order_status'=>'pending_payment','payment_status'=>'pending','idempotent_replay'=>true
                ]];
            }

            $insert = $db->prepare("INSERT INTO payment_attempts (order_id,idempotency_key,status) VALUES (:oid,:ikey,'pending')");
            $insert->execute(['oid'=>$order_id,'ikey'=>$idempotencyKey]);
            $requestId = (int)$db->lastInsertId();
            $db->prepare("UPDATE orders SET status='pending_payment',payment_status='pending' WHERE id=:id")->execute(['id'=>$order_id]);
            if ($order['status'] === 'payment_failed') {
                Order::addStatusHistory($order_id, 'payment_failed', 'pending_payment', $umkm_id, 'Payment request dikirim ulang ke SmartBank', $db);
            }
            $db->commit();
            return ['status'=>'success','message'=>'Payment request dikirim. Menunggu verifikasi SmartBank.','data'=>[
                'request_id'=>$requestId,'order_status'=>'pending_payment','payment_status'=>'pending'
            ]];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                return ['status'=>'success','message'=>'Payment request yang sama sudah tercatat. Menunggu verifikasi SmartBank.','data'=>['order_status'=>'pending_payment','payment_status'=>'pending','idempotent_replay'=>true]];
            }
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }

    /**
     * Internal integration seam for a future authenticated SmartBank callback.
     * This method is intentionally not exposed by the public API yet.
     */
    public static function verifySmartBankPayment($order_id, $outcome, $reference = null) {
        if (!in_array($outcome, ['succeeded','failed'], true)) return ['status'=>'error','message'=>'Status verifikasi SmartBank tidak valid.'];
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM orders WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$order_id]);
            $order = $stmt->fetch();
            if (!$order) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['payment_status'] === 'paid') {
                $db->rollBack();
                return ['status'=>'success','message'=>'Verifikasi sebelumnya sudah diproses.','data'=>['payment_status'=>'paid','idempotent_replay'=>true]];
            }
            $attemptStmt = $db->prepare("SELECT * FROM payment_attempts WHERE order_id=:oid AND status='pending' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $attemptStmt->execute(['oid'=>$order_id]);
            $attempt = $attemptStmt->fetch();
            if (!$attempt) throw new Exception('Tidak ada payment request yang menunggu verifikasi.');

            if ($outcome === 'failed') {
                $db->prepare("UPDATE payment_attempts SET status='failed',error_message='Ditolak SmartBank' WHERE id=:id")->execute(['id'=>$attempt['id']]);
                $db->prepare("UPDATE orders SET status='payment_failed',payment_status='failed' WHERE id=:id")->execute(['id'=>$order_id]);
                Order::addStatusHistory($order_id, $order['status'], 'payment_failed', null, 'Verifikasi SmartBank gagal', $db);
                $db->commit();
                return ['status'=>'success','message'=>'SmartBank menolak pembayaran.','data'=>['order_status'=>'payment_failed','payment_status'=>'failed']];
            }

            if (!$reference) throw new Exception('Referensi SmartBank wajib tersedia untuk pembayaran sukses.');
            $items = $db->prepare("SELECT oi.*,m.name FROM order_items oi JOIN materials m ON m.id=oi.material_id WHERE oi.order_id=:oid FOR UPDATE");
            $items->execute(['oid'=>$order_id]);
            foreach ($items->fetchAll() as $item) {
                if (!Material::reduceStock($item['material_id'], $item['qty'])) throw new Exception("Stok {$item['name']} tidak mencukupi.");
            }
            $db->prepare("UPDATE payment_attempts SET status='succeeded',payment_reference=:ref,error_message=NULL WHERE id=:id")->execute(['ref'=>$reference,'id'=>$attempt['id']]);
            $db->prepare("UPDATE orders SET status='paid',payment_status='paid',smartbank_ref=:ref,paid_at=NOW() WHERE id=:id")->execute(['ref'=>$reference,'id'=>$order_id]);
            Payment::create($order['umkm_id'],'debit',$order['total'],"Pembayaran pesanan {$order['order_code']}",$reference,$db);
            Payment::create($order['supplier_id'],'credit',$order['subtotal'],"Penerimaan dana pesanan {$order['order_code']}",$reference,$db);
            Order::addStatusHistory($order_id, $order['status'], 'paid', null, 'Pembayaran diverifikasi SmartBank', $db);
            Procurement::enqueue($db,(int)$order_id,'SUPPLIER_ORDER_PAID',['order_id'=>(int)$order_id,'order_code'=>$order['order_code'],'payment_reference'=>$reference]);
            $db->commit();
            return ['status'=>'success','message'=>'Pembayaran diverifikasi SmartBank.','data'=>['order_status'=>'paid','payment_status'=>'paid','reference'=>$reference]];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        }
    }

    /**
     * Supplier: Reject order
     */
    public static function reject($order_id, $supplier_id) {
        $order = Order::findById($order_id);
        if (!$order || $order['supplier_id'] != $supplier_id) {
            return ['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'];
        }

        if (!Order::reject($order_id)) {
            return ['status'=>'error','message'=>'Status pesanan sudah diproses sebelumnya.'];
        }
        Order::addStatusHistory($order_id, $order['status'], 'rejected', $supplier_id, 'Supplier menolak pesanan');
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
