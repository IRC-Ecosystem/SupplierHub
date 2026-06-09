<?php
/**
 * Order Model
 * Handles orders and order_items CRUD operations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Order {

    /**
     * Create new order with items
     */
    public static function create($umkm_id, $supplier_id, $items, $discount = 0) {
        $db = getDB();
        $db->beginTransaction();

        try {
            // Calculate totals
            $subtotal = 0;
            foreach ($items as &$item) {
                $stmt = $db->prepare("SELECT price FROM materials WHERE id = :id");
                $stmt->execute(['id' => $item['material_id']]);
                $mat = $stmt->fetch();
                $item['price_at_order'] = $mat['price'];
                $subtotal += $mat['price'] * $item['qty'];
            }

            $fee = (int) round($subtotal * FEE_SUPPLIER);
            $total = ($subtotal + $fee) - $discount;
            if ($total < 0) $total = 0;
            $orderCode = self::generateCode();

            // Insert order
            $stmt = $db->prepare("
                INSERT INTO orders (order_code, umkm_id, supplier_id, status, subtotal, fee_supplier, total)
                VALUES (:code, :umkm, :supplier, 'pending', :subtotal, :fee, :total)
            ");
            $stmt->execute([
                'code'     => $orderCode,
                'umkm'     => $umkm_id,
                'supplier' => $supplier_id,
                'subtotal' => $subtotal,
                'fee'      => $fee,
                'total'    => $total
            ]);
            $orderId = $db->lastInsertId();

            // Insert order items
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
            return [
                'id'         => $orderId,
                'order_code' => $orderCode,
                'subtotal'   => $subtotal,
                'fee'        => $fee,
                'total'      => $total
            ];
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get pending orders for supplier
     */
    public static function getPending($supplier_id) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT o.*, u.name as umkm_name 
            FROM orders o 
            JOIN users u ON o.umkm_id = u.id 
            WHERE o.supplier_id = :sid AND o.status = 'pending' 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute(['sid' => $supplier_id]);
        $orders = $stmt->fetchAll();

        // Attach items count
        foreach ($orders as &$order) {
            $stmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM order_items WHERE order_id = :oid");
            $stmt2->execute(['oid' => $order['id']]);
            $order['item_count'] = $stmt2->fetch()['cnt'];
        }

        return $orders;
    }

    /**
     * Get completed orders for supplier (laporan)
     */
    public static function getCompleted($supplier_id) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT o.*, u.name as umkm_name 
            FROM orders o 
            JOIN users u ON o.umkm_id = u.id 
            WHERE o.supplier_id = :sid AND o.status = 'completed' 
            ORDER BY o.completed_at DESC
        ");
        $stmt->execute(['sid' => $supplier_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get orders by UMKM user (riwayat)
     */
    public static function getByUmkm($umkm_id) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT o.*, u.name as supplier_name 
            FROM orders o 
            JOIN users u ON o.supplier_id = u.id 
            WHERE o.umkm_id = :uid 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute(['uid' => $umkm_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get order with items detail
     */
    public static function getWithItems($order_id) {
        $db = getDB();
        
        // Get order
        $stmt = $db->prepare("
            SELECT o.*, u.name as umkm_name, s.name as supplier_name
            FROM orders o 
            JOIN users u ON o.umkm_id = u.id 
            JOIN users s ON o.supplier_id = s.id
            WHERE o.id = :id
        ");
        $stmt->execute(['id' => $order_id]);
        $order = $stmt->fetch();

        if (!$order) return null;

        // Get items
        $stmt = $db->prepare("
            SELECT oi.*, m.name as material_name, m.unit, m.stock as current_stock, m.icon
            FROM order_items oi 
            JOIN materials m ON oi.material_id = m.id 
            WHERE oi.order_id = :oid
        ");
        $stmt->execute(['oid' => $order_id]);
        $order['items'] = $stmt->fetchAll();

        return $order;
    }

    /**
     * Find order by ID
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Approve order and update status
     */
    public static function approve($order_id, $smartbank_ref = null, $resi = null) {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'completed', smartbank_ref = :ref, resi_pengiriman = :resi, completed_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute([
            'ref'  => $smartbank_ref,
            'resi' => $resi,
            'id'   => $order_id
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reject order
     */
    public static function reject($order_id) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE orders SET status = 'rejected' WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $order_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get dashboard stats for supplier
     */
    public static function getSupplierStats($supplier_id) {
        $db = getDB();

        // Pending count
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE supplier_id = :sid AND status = 'pending'");
        $stmt->execute(['sid' => $supplier_id]);
        $pending = $stmt->fetch()['cnt'];

        // Total revenue (completed)
        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE supplier_id = :sid AND status = 'completed'");
        $stmt->execute(['sid' => $supplier_id]);
        $revenue = $stmt->fetch()['total'];

        return [
            'pending_orders' => (int) $pending,
            'total_revenue'  => (int) $revenue
        ];
    }

    /**
     * Get dashboard stats for UMKM
     */
    public static function getUmkmStats($umkm_id) {
        $db = getDB();

        $stmt = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as spent FROM orders WHERE umkm_id = :uid AND status = 'completed'");
        $stmt->execute(['uid' => $umkm_id]);
        $row = $stmt->fetch();

        return [
            'total_orders' => (int) $row['cnt'],
            'total_spent'  => (int) $row['spent']
        ];
    }

    /**
     * Generate unique order code
     */
    private static function generateCode() {
        $db = getDB();
        $stmt = $db->query("SELECT MAX(id) as max_id FROM orders");
        $row = $stmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;
        return 'ORD-B2B-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }
}
