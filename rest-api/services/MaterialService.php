<?php
/**
 * Material Service
 * LAYER: Business Logic (Service Layer)
 * 
 * Arsitektur: Service berisi LOGIKA BISNIS murni.
 * Akses database didelegasikan ke MaterialRepository.
 * 
 * Microservice Domain: Inventory Service
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: item_bahan, qty, harga, kategori, satuan, supplier_id
 *   Proses: Validasi → CRUD → simpan → log
 *   Output: data bahan baku
 */

require_once __DIR__ . '/../models/MaterialRepository.php';

class MaterialService {

    /**
     * List semua materials dengan pagination (untuk katalog UMKM)
     */
    public static function list($page = 1, $limit = 10) {
        return MaterialRepository::findAll($page, $limit);
    }

    /**
     * List materials milik supplier tertentu
     */
    public static function getBySupplier($supplierId, $page = 1, $limit = 10) {
        return MaterialRepository::findBySupplier($supplierId, $page, $limit);
    }

    /**
     * Cari material berdasarkan ID
     */
    public static function findById($id) {
        return MaterialRepository::findById($id);
    }

    /**
     * Buat material baru
     * Logika bisnis: generate kode unik + assign icon berdasarkan kategori
     */
    public static function create($data) {
        // Generate kode material (logika bisnis)
        $data['material_code'] = MaterialRepository::generateCode();
        
        // Assign icon berdasarkan kategori (logika bisnis)
        $data['icon'] = $data['icon'] ?? self::getIcon($data['category']);

        $id = MaterialRepository::create($data);
        return MaterialRepository::findById($id);
    }

    /**
     * Update material
     * Logika bisnis: assign icon jika tidak diberikan
     */
    public static function update($id, $data) {
        $data['icon'] = $data['icon'] ?? self::getIcon($data['category']);
        MaterialRepository::update($id, $data);
        return MaterialRepository::findById($id);
    }

    /**
     * Hapus material
     */
    public static function delete($id) {
        return MaterialRepository::delete($id);
    }

    /**
     * Kurangi stok setelah order di-approve
     * Dipanggil oleh OrderService saat processApproval()
     */
    public static function reduceStock($id, $qty) {
        return MaterialRepository::reduceStock($id, $qty);
    }

    /**
     * LOGIKA BISNIS: Mapping icon berdasarkan kategori material
     * (Ini bukan urusan database, murni aturan tampilan)
     */
    private static function getIcon($category) {
        $icons = [
            'Bahan Pokok'    => 'ph-package',
            'Cair'           => 'ph-drop',
            'Bumbu & Rempah' => 'ph-plant',
            'Kemasan'        => 'ph-box-arrow-up',
            'Lainnya'        => 'ph-cube'
        ];
        return $icons[$category] ?? 'ph-package';
    }
}
