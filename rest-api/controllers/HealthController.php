<?php
/**
 * Health Controller
 * GET /health
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../config/database.php';

class HealthController {

    /**
     * GET /health
     * Cek status API dan koneksi database
     */
    public static function index() {
        $dbStatus = 'ok';
        try {
            $db = getDB();
            $db->query("SELECT 1");
        } catch (Exception $e) {
            $dbStatus = 'error';
        }

        Response::success([
            'service'  => 'supplierhub-api',
            'version'  => APP_VERSION,
            'database' => $dbStatus,
            'timestamp' => date('Y-m-d H:i:s')
        ], 'API berjalan normal.');
    }
}
