<?php
/**
 * Material Model
 * Handles CRUD operations for bahan baku / materials
 */

require_once __DIR__ . '/../config/database.php';

class Material {

    /**
     * Get all materials by supplier
     */
    public static function getBySupplier($supplier_id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM materials WHERE supplier_id = :sid ORDER BY created_at DESC");
        $stmt->execute(['sid' => $supplier_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get all materials (for catalog - UMKM view)
     */
    public static function getAll() {
        $db = getDB();
        $stmt = $db->query("SELECT m.*, u.name as supplier_name FROM materials m JOIN users u ON m.supplier_id = u.id ORDER BY m.name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Find material by ID
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM materials WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Find material by code
     */
    public static function findByCode($code) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM materials WHERE material_code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }

    /**
     * Create new material
     */
    public static function create($data) {
        $db = getDB();
        $code = self::generateCode();
        $stmt = $db->prepare("
            INSERT INTO materials (material_code, name, category, price, stock, unit, icon, supplier_id)
            VALUES (:code, :name, :category, :price, :stock, :unit, :icon, :supplier_id)
        ");
        $stmt->execute([
            'code'        => $code,
            'name'        => $data['name'],
            'category'    => $data['category'],
            'price'       => $data['price'],
            'stock'       => $data['stock'],
            'unit'        => $data['unit'],
            'icon'        => $data['icon'] ?? 'ph-package',
            'supplier_id' => $data['supplier_id']
        ]);
        return ['id' => $db->lastInsertId(), 'material_code' => $code];
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
        return $stmt->execute([
            'name'     => $data['name'],
            'category' => $data['category'],
            'price'    => $data['price'],
            'stock'    => $data['stock'],
            'unit'     => $data['unit'],
            'icon'     => $data['icon'] ?? 'ph-package',
            'id'       => $id
        ]);
    }

    /**
     * Delete material
     */
    public static function delete($id) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM materials WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Reduce stock after order approved
     */
    public static function reduceStock($id, $qty) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE materials SET stock = stock - :qty WHERE id = :id AND stock >= :qty2");
        $stmt->execute(['qty' => $qty, 'id' => $id, 'qty2' => $qty]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check stock availability for multiple items
     */
    public static function checkStock($items) {
        $results = [];
        foreach ($items as $item) {
            $material = self::findById($item['material_id']);
            if ($material) {
                $results[] = [
                    'material_id'   => $material['id'],
                    'material_name' => $material['name'],
                    'requested_qty' => $item['qty'],
                    'available'     => $material['stock'],
                    'sufficient'    => $material['stock'] >= $item['qty']
                ];
            }
        }
        return $results;
    }

    /**
     * Generate unique material code
     */
    private static function generateCode() {
        $db = getDB();
        $stmt = $db->query("SELECT MAX(id) as max_id FROM materials");
        $row = $stmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;
        return 'MAT-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }
}
