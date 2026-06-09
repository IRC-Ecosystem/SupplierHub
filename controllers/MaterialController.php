<?php
/**
 * Material Controller
 * CRUD operations for bahan baku (Kelola Stok)
 * 
 * IPO Pattern per Aplikasi.docx:
 * Input: item_bahan, qty, harga, kategori, satuan, supplier_id
 * Proses: Validasi → CRUD → simpan → log
 * Output: { status, data }
 */

require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';

class MaterialController {

    /**
     * List all materials for supplier
     */
    public static function list($supplier_id) {
        $materials = Material::getBySupplier($supplier_id);
        return [
            'status'  => 'success',
            'message' => 'Data bahan baku berhasil diambil.',
            'data'    => $materials
        ];
    }

    /**
     * List all materials for catalog (UMKM view)
     */
    public static function catalog() {
        $materials = Material::getAll();
        return [
            'status'  => 'success',
            'message' => 'Katalog berhasil diambil.',
            'data'    => $materials
        ];
    }

    /**
     * Get single material detail
     */
    public static function detail($id) {
        $material = Material::findById($id);
        if (!$material) {
            return ['status' => 'error', 'message' => 'Bahan baku tidak ditemukan.'];
        }
        return [
            'status' => 'success',
            'data'   => $material
        ];
    }

    /**
     * Create new material
     */
    public static function create($data, $supplier_id) {
        // Validate
        $errors = self::validate($data);
        if (!empty($errors)) {
            return ['status' => 'error', 'message' => implode(', ', $errors)];
        }

        // Assign icon based on category
        $data['icon'] = self::getIcon($data['category']);
        $data['supplier_id'] = $supplier_id;

        try {
            $result = Material::create($data);
            return [
                'status'  => 'success',
                'message' => 'Bahan baku baru berhasil ditambahkan.',
                'data'    => $result
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Gagal menambahkan: ' . $e->getMessage()];
        }
    }

    /**
     * Update existing material
     */
    public static function update($id, $data) {
        // Check exists
        $existing = Material::findById($id);
        if (!$existing) {
            return ['status' => 'error', 'message' => 'Bahan baku tidak ditemukan.'];
        }

        // Validate
        $errors = self::validate($data);
        if (!empty($errors)) {
            return ['status' => 'error', 'message' => implode(', ', $errors)];
        }

        $data['icon'] = self::getIcon($data['category']);

        try {
            Material::update($id, $data);
            return [
                'status'  => 'success',
                'message' => 'Bahan baku berhasil diperbarui.',
                'data'    => ['id' => $id]
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Gagal memperbarui: ' . $e->getMessage()];
        }
    }

    /**
     * Delete material
     */
    public static function delete($id) {
        $existing = Material::findById($id);
        if (!$existing) {
            return ['status' => 'error', 'message' => 'Bahan baku tidak ditemukan.'];
        }

        try {
            Material::delete($id);
            return [
                'status'  => 'success',
                'message' => 'Bahan baku berhasil dihapus.',
                'data'    => ['id' => $id]
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Gagal menghapus: ' . $e->getMessage()];
        }
    }

    /**
     * Validate material data
     */
    private static function validate($data) {
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Nama item wajib diisi';
        if (empty($data['category'])) $errors[] = 'Kategori wajib diisi';
        if (!isset($data['price']) || $data['price'] < 0) $errors[] = 'Harga harus >= 0';
        if (!isset($data['stock']) || $data['stock'] < 0) $errors[] = 'Stok harus >= 0';
        if (empty($data['unit'])) $errors[] = 'Satuan wajib diisi';
        return $errors;
    }

    /**
     * Get icon based on category
     */
    private static function getIcon($category) {
        $icons = [
            'Bahan Pokok'   => 'ph-package',
            'Cair'          => 'ph-drop',
            'Bumbu & Rempah' => 'ph-plant',
            'Kemasan'       => 'ph-box-arrow-up',
            'Lainnya'       => 'ph-cube'
        ];
        return $icons[$category] ?? 'ph-package';
    }
}
