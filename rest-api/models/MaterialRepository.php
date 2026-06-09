<?php
/**
 * Material Repository
 * LAYER: Data Access (Repository Pattern)
 * 
 * Arsitektur: Repository HANYA berisi query database murni.
 * Tidak ada logika bisnis (kalkulasi fee, generate icon) di sini.
 * 
 * Microservice Domain: Inventory Service
 * Digunakan oleh: MaterialService (Service Layer)
 */

require_once __DIR__ . '/../config/database.php';

class MaterialRepository {

    /**
     * List semua materials dengan pagination
     */
    public static function findAll($page = 1, $limit = 10) {
        $db = getDB();
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT m.*, u.name AS supplier_name 
            FROM materials m 
            JOIN users u ON m.supplier_id = u.id 
            ORDER BY m.name ASC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        $countStmt = $db->query("SELECT COUNT(*) AS total FROM materials");
        $total = (int) $countStmt->fetch()['total'];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * List materials milik supplier tertentu
     */
    public static function findBySupplier($supplierId, $page = 1, $limit = 10) {
        $db = getDB();
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT * FROM materials 
            WHERE supplier_id = :sid 
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('sid', (int)$supplierId, PDO::PARAM_INT);
        $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM materials WHERE supplier_id = :sid");
        $countStmt->execute(['sid' => $supplierId]);
        $total = (int) $countStmt->fetch()['total'];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Cari material berdasarkan ID (dengan nama supplier)
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT m.*, u.name AS supplier_name 
            FROM materials m 
            JOIN users u ON m.supplier_id = u.id 
            WHERE m.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Ambil harga material (untuk kalkulasi order)
     */
    public static function getPrice($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT price FROM materials WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Insert material baru
     */
    public static function create($data) {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO materials (material_code, name, category, price, stock, unit, icon, supplier_id)
            VALUES (:code, :name, :category, :price, :stock, :unit, :icon, :supplier_id)
        ");
        $stmt->execute([
            'code'        => $data['material_code'],
            'name'        => $data['name'],
            'category'    => $data['category'],
            'price'       => $data['price'],
            'stock'       => $data['stock'],
            'unit'        => $data['unit'],
            'icon'        => $data['icon'],
            'supplier_id' => $data['supplier_id']
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update material
     */
    public static function update($id, $data) {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE materials 
            SET name = :name, category = :category, price = :price, 
                stock = :stock, unit = :unit, icon = :icon
            WHERE id = :id
        ");
        $stmt->execute([
            'name'     => $data['name'],
            'category' => $data['category'],
            'price'    => $data['price'],
            'stock'    => $data['stock'],
            'unit'     => $data['unit'],
            'icon'     => $data['icon'],
            'id'       => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Hapus material
     */
    public static function delete($id) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM materials WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Kurangi stok material (setelah order approved)
     */
    public static function reduceStock($id, $qty) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE materials SET stock = stock - :qty WHERE id = :id AND stock >= :qty2");
        $stmt->execute(['qty' => $qty, 'id' => $id, 'qty2' => $qty]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate kode material unik
     */
    public static function generateCode() {
        $db = getDB();
        $stmt = $db->query("SELECT MAX(id) AS max_id FROM materials");
        $row = $stmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;
        return 'MAT-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Statistik stok untuk dashboard supplier
     * (Digunakan oleh ReportService — Aturan #7: Analytics hanya READ)
     */
    public static function getStockStats($supplierId) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total_items, COALESCE(SUM(price * stock), 0) AS stock_valuation 
            FROM materials WHERE supplier_id = :sid
        ");
        $stmt->execute(['sid' => $supplierId]);
        return $stmt->fetch();
    }
}
