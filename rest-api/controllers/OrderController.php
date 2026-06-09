<?php
/**
 * Order Controller
 * LAYER: Presentation / HTTP Handler
 * 
 * Arsitektur: Controller HANYA bertugas:
 *   1. Menerima HTTP request
 *   2. Validasi input format
 *   3. Memanggil Service (logika bisnis)
 *   4. Mengirim HTTP response
 * 
 * TIDAK ADA logika bisnis (cek stok, hitung fee, panggil SmartBank) di sini.
 * Semua didelegasikan ke OrderService.
 * 
 * Fitur 3: Order Bahan Baku + Fitur 4: Konfirmasi Order
 * Fitur 5: Rekomendasi Bundling Optimal (Algoritma Greedy)
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: supplier_id, items[] (checkout) / order_id (approve/reject)
 *   Proses: Validasi → delegasi ke OrderService
 *   Output: order data + payment status
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/GreedyBundlingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class OrderController {

    /**
     * GET /api/v1/orders
     * List orders (filtered by role)
     */
    public static function index($user) {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        $status = $_GET['status'] ?? null;

        $result = OrderService::list($user['user_id'], $user['role'], $status, $page, $limit);

        Response::paginated($result['data'], $page, $limit, $result['total'], 'Data pesanan berhasil diambil.');
    }

    /**
     * GET /api/v1/orders/:id
     * Detail order + items + stock check
     */
    public static function show($user, $id) {
        $order = OrderService::findWithItems($id);

        if (!$order) {
            Response::error('Pesanan tidak ditemukan.', 404);
        }

        // Cek akses: hanya UMKM pemesan atau supplier tujuan
        if ($order['umkm_id'] != $user['user_id'] && $order['supplier_id'] != $user['user_id']) {
            Response::error('Akses ditolak.', 403);
        }

        Response::success($order, 'Detail pesanan berhasil diambil.');
    }

    /**
     * POST /api/v1/orders
     * Buat pesanan baru / checkout (UMKM only)
     * 
     * Controller hanya: validasi input → panggil Service → kirim response
     */
    public static function store($user) {
        AuthMiddleware::requireRole($user, 'umkm');

        $input = Validator::getJsonBody();

        // Validasi input format (bukan logika bisnis)
        $errors = Validator::validate($input, [
            'supplier_id' => 'required|integer',
            'items'       => 'required|array',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        if (empty($input['items'])) {
            Response::error('Items pesanan tidak boleh kosong.', 400);
        }

        foreach ($input['items'] as $i => $item) {
            if (empty($item['material_id']) || empty($item['qty']) || $item['qty'] <= 0) {
                Response::error("Item ke-" . ($i + 1) . " tidak valid. material_id dan qty (> 0) wajib diisi.", 400);
            }
        }

        // Delegasi ke Service (semua logika bisnis ada di sana)
        try {
            $result = OrderService::create($user['user_id'], $input['supplier_id'], $input['items']);
            Response::success($result, 'Pesanan berhasil dibuat. Menunggu konfirmasi supplier.', 201);
        } catch (Exception $e) {
            Response::error('Gagal membuat pesanan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/v1/orders/:id/approve
     * Approve order (Supplier only)
     * 
     * SEBELUM refactor: 60 baris logika bisnis di Controller
     * SESUDAH refactor: 1 panggilan ke OrderService::processApproval()
     */
    public static function approve($user, $id) {
        AuthMiddleware::requireRole($user, 'supplier');

        try {
            $result = OrderService::processApproval($id, $user['user_id']);
            Response::success($result, "Pesanan berhasil di-approve. Payment request telah dikirim ke SmartBank.");
        } catch (Exception $e) {
            $code = $e->getCode() ?: 400;
            Response::error($e->getMessage(), $code);
        }
    }

    /**
     * PATCH /api/v1/orders/:id/reject
     * Reject order (Supplier only)
     */
    public static function reject($user, $id) {
        AuthMiddleware::requireRole($user, 'supplier');

        $order = OrderService::findById($id);
        if (!$order || $order['supplier_id'] != $user['user_id']) {
            Response::error('Pesanan tidak ditemukan.', 404);
        }

        if ($order['status'] !== 'pending') {
            Response::error('Pesanan sudah diproses sebelumnya.', 409);
        }

        OrderService::reject($id);

        Response::success(null, 'Pesanan berhasil ditolak.');
    }

    /**
     * Memberikan rekomendasi bundling paket bahan baku optimal.
     * 
     * @param array $user Data user yang aktif.
     * @return void
     */
    public static function getRecommendation($user) {
        $input = Validator::getJsonBody();

        // Validasi format data input
        $errors = Validator::validate($input, [
            'budget' => 'required|integer',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        $budget = (int) $input['budget'];
        if ($budget <= 0) {
            Response::error('Budget harus lebih besar dari 0.', 400);
        }

        // Ambil parameter tambahan
        $priorityCategories = $input['priority_categories'] ?? [];
        $maxItems = isset($input['max_items']) ? (int) $input['max_items'] : null;

        // Validasi kategori prioritas
        if (!is_array($priorityCategories)) {
            Response::error('priority_categories harus berupa array.', 400);
        }

        // Validasi batas maksimal jenis item
        if ($maxItems !== null && $maxItems <= 0) {
            Response::error('max_items harus lebih besar dari 0.', 400);
        }

        // Dapatkan rekomendasi dari bundling service
        try {
            $result = GreedyBundlingService::recommend($budget, $priorityCategories, $maxItems);
            Response::success($result, 'Rekomendasi bundling berhasil dihitung dengan algoritma Greedy (Fractional Knapsack).');
        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
