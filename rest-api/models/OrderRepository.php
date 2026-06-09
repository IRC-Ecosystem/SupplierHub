<?php
/**
 * Order Repository
 * LAYER: Data Access (Repository Pattern)
 * 
 * Arsitektur: Repository HANYA berisi query database murni.
 * Tidak ada logika bisnis (kalkulasi fee, cek stok, integrasi SmartBank) di sini.
 * 
 * Microservice Domain: Order Service
 * Digunakan oleh: OrderService, ReportService (Service Layer)
 */

require_once __DIR__ . '/../config/database.php';

class OrderRepository {

    /**
     * List orders dengan filter berdasarkan role dan status
     */
    public static function findAll($userId, $role, $status = null, $page = 1, $limit = 10) {
        $db = getDB();
        $offset = ($page - 1) * $limit;

        if ($role === 'supplier') {
            $where = "o.supplier_id = :uid";
            $join = "JOIN users u ON o.umkm_id = u.id";
            $select = "u.name AS umkm_name";
        } else {
            $where = "o.umkm_id = :uid";
            $join = "JOIN users u ON o.supplier_id = u.id";
            $select = "u.name AS supplier_name";
        }

        if ($status) {
            $where .= " AND o.status = :status";
        }

        $sql = "SELECT o.*, $select FROM orders o $join WHERE $where ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        $stmt->bindValue('uid', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', (int)$offset, PDO::PARAM_INT);
        if ($status) {
            $stmt->bindValue('status', $status);
        }
        $stmt->execute();
        $data = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) AS total FROM orders o WHERE $where";
        $countStmt = $db->prepare($countSql);
        $countStmt->bindValue('uid', (int)$userId, PDO::PARAM_INT);
        if ($status) {
            $countStmt->bindValue('status', $status);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Cari order berdasarkan ID
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Cari order lengkap dengan items dan data user
     */
    public static function findWithItems($id) {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT o.*, u.name AS umkm_name, s.name AS supplier_name
            FROM orders o 
            JOIN users u ON o.umkm_id = u.id 
            JOIN users s ON o.supplier_id = s.id
            WHERE o.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) return null;

        $stmt = $db->prepare("
            SELECT oi.*, m.name AS material_name, m.unit, m.stock AS current_stock, m.icon, m.material_code
            FROM order_items oi 
            JOIN materials m ON oi.material_id = m.id 
            WHERE oi.order_id = :oid
        ");
        $stmt->execute(['oid' => $id]);
        $order['items'] = $stmt->fetchAll();

        return $order;
    }

    /**
     * Insert order baru beserta items (dalam transaction)
     * @param array $orderData ['order_code','umkm_id','supplier_id','subtotal','fee_supplier','total']
     * @param array $items [['material_id','qty','price_at_order'], ...]
     * @return int Order ID
     */
    public static function create($orderData, $items) {
        $db = getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO orders (order_code, umkm_id, supplier_id, status, subtotal, fee_supplier, total)
                VALUES (:code, :umkm, :supplier, 'pending', :subtotal, :fee, :total)
            ");
            $stmt->execute([
                'code'     => $orderData['order_code'],
                'umkm'     => $orderData['umkm_id'],
                'supplier' => $orderData['supplier_id'],
                'subtotal' => $orderData['subtotal'],
                'fee'      => $orderData['fee_supplier'],
                'total'    => $orderData['total']
            ]);
            $orderId = (int) $db->lastInsertId();

            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, material_id, qty, price_at_order)
                VALUES (:order_id, :material_id, :qty, :price)
            ");
            foreach ($items as $item) {
                $stmt->execute([
                    'order_id'    => $orderId,
                    'material_id' => $item['material_id'],
                    'qty'         => $item['qty'],
                    'price'       => $item['price_at_order']
                ]);
            }

            $db->commit();
            return $orderId;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update status order menjadi completed
     */
    public static function approve($id, $smartbankRef = null) {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'completed', smartbank_ref = :ref, completed_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute(['ref' => $smartbankRef, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update status order menjadi rejected
     */
    public static function reject($id) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE orders SET status = 'rejected' WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate kode order unik
     */
    public static function generateCode() {
        $db = getDB();
        $stmt = $db->query("SELECT MAX(id) AS max_id FROM orders");
        $row = $stmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;
        return 'ORD-B2B-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Statistik order supplier (untuk ReportService)
     */
    public static function getSupplierOrderStats($supplierId) {
        $db = getDB();

        $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE supplier_id = :sid AND status = 'pending'");
        $stmt->execute(['sid' => $supplierId]);
        $pending = (int) $stmt->fetch()['cnt'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE supplier_id = :sid AND status = 'completed'");
        $stmt->execute(['sid' => $supplierId]);
        $revenue = (int) $stmt->fetch()['total'];

        return ['pending_orders' => $pending, 'total_revenue' => $revenue];
    }

    /**
     * Statistik order UMKM (untuk ReportService)
     */
    public static function getUmkmOrderStats($umkmId) {
        $db = getDB();

        $stmt = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS spent FROM orders WHERE umkm_id = :uid AND status = 'completed'");
        $stmt->execute(['uid' => $umkmId]);
        $row = $stmt->fetch();

        return ['total_orders' => (int) $row['cnt'], 'total_spent' => (int) $row['spent']];
    }
}
