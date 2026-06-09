<?php
/**
 * Material Controller
 * Fitur 2: Kelola Stok Bahan Baku (CRUD)
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: item_bahan, qty, harga, kategori, satuan, supplier_id
 *   Proses: Validasi → CRUD → simpan → log
 *   Output: { status, data }
 * 
 * Mengikuti pola controllers/books.ts di referensi
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../services/MaterialService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class MaterialController {

    /**
     * GET /api/v1/materials
     * List semua materials dengan pagination
     * Query params: ?page=1&limit=10&supplier_id=X
     */
    public static function index($user) {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);

        // Jika supplier, tampilkan material miliknya saja
        if ($user['role'] === 'supplier' && !isset($_GET['catalog'])) {
            $result = MaterialService::getBySupplier($user['user_id'], $page, $limit);
        } else {
            $result = MaterialService::list($page, $limit);
        }

        Response::paginated($result['data'], $page, $limit, $result['total'], 'Data bahan baku berhasil diambil.');
    }

    /**
     * GET /api/v1/materials/:id
     * Detail satu material
     */
    public static function show($user, $id) {
        $material = MaterialService::findById($id);

        if (!$material) {
            Response::error('Bahan baku tidak ditemukan.', 404);
        }

        Response::success($material, 'Detail bahan baku berhasil diambil.');
    }

    /**
     * POST /api/v1/materials
     * Tambah material baru (Supplier only)
     */
    public static function store($user) {
        AuthMiddleware::requireRole($user, 'supplier');

        $input = Validator::getJsonBody();

        // Validasi input (Aturan #6)
        $errors = Validator::validate($input, [
            'name'     => 'required|min:3',
            'category' => 'required',
            'price'    => 'required|numeric|min:0',
            'stock'    => 'required|integer|min:0',
            'unit'     => 'required',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        $input['supplier_id'] = $user['user_id'];

        try {
            $material = MaterialService::create($input);
            Response::success($material, 'Bahan baku baru berhasil ditambahkan.', 201);
        } catch (Exception $e) {
            Response::error('Gagal menambahkan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/materials/:id
     * Update material (Supplier only)
     */
    public static function update($user, $id) {
        AuthMiddleware::requireRole($user, 'supplier');

        // Cek exists
        $existing = MaterialService::findById($id);
        if (!$existing) {
            Response::error('Bahan baku tidak ditemukan.', 404);
        }

        // Cek ownership
        if ($existing['supplier_id'] != $user['user_id']) {
            Response::error('Akses ditolak. Bahan baku bukan milik Anda.', 403);
        }

        $input = Validator::getJsonBody();

        // Validasi input
        $errors = Validator::validate($input, [
            'name'     => 'required|min:3',
            'category' => 'required',
            'price'    => 'required|numeric|min:0',
            'stock'    => 'required|integer|min:0',
            'unit'     => 'required',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        try {
            $material = MaterialService::update($id, $input);
            Response::success($material, 'Bahan baku berhasil diperbarui.');
        } catch (Exception $e) {
            Response::error('Gagal memperbarui: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/materials/:id
     * Hapus material (Supplier only)
     */
    public static function destroy($user, $id) {
        AuthMiddleware::requireRole($user, 'supplier');

        $existing = MaterialService::findById($id);
        if (!$existing) {
            Response::error('Bahan baku tidak ditemukan.', 404);
        }

        if ($existing['supplier_id'] != $user['user_id']) {
            Response::error('Akses ditolak. Bahan baku bukan milik Anda.', 403);
        }

        try {
            MaterialService::delete($id);
            Response::noContent();
        } catch (Exception $e) {
            Response::error('Gagal menghapus: ' . $e->getMessage(), 500);
        }
    }
}
