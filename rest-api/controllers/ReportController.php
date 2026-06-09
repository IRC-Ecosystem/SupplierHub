<?php
/**
 * Report Controller
 * Fitur 5: Laporan & Dashboard
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: user_id dari JWT token
 *   Proses: Query aggregate stats
 *   Output: status + data stats
 * 
 * Aturan #7: Analytics hanya membaca data (read-only)
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/ReportService.php';

class ReportController {

    /**
     * GET /api/v1/reports/stats
     * Dashboard stats (auto-detect role dari JWT)
     */
    public static function stats($user) {
        if ($user['role'] === 'supplier') {
            $stats = ReportService::supplierStats($user['user_id']);
            Response::success($stats, 'Statistik supplier berhasil diambil.');
        } else {
            $stats = ReportService::umkmStats($user['user_id']);
            Response::success($stats, 'Statistik UMKM berhasil diambil.');
        }
    }
}
