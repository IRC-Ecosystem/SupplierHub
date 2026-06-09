<?php
/**
 * Logger Middleware
 * Mencatat setiap API request/response ke database
 * (Aturan #6: Validasi & Logging wajib)
 */

require_once __DIR__ . '/../config/database.php';

class LoggerMiddleware {

    /**
     * Log API request dan response ke tabel transaction_logs
     */
    public static function log($endpoint, $method, $userId = null, $requestData = null, $responseData = null) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO transaction_logs (endpoint, method, user_id, request_data, response_data, ip_address)
                VALUES (:endpoint, :method, :user_id, :req, :res, :ip)
            ");
            $stmt->execute([
                'endpoint' => $endpoint,
                'method'   => $method,
                'user_id'  => $userId,
                'req'      => is_string($requestData) ? $requestData : json_encode($requestData),
                'res'      => is_string($responseData) ? $responseData : json_encode($responseData),
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Logging tidak boleh menghentikan aplikasi
            error_log("REST API LoggerMiddleware Error: " . $e->getMessage());
        }
    }

    /**
     * Ambil data request saat ini untuk logging
     * Menghapus field sensitif (password, token)
     */
    public static function getRequestData() {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $_GET;
        } else {
            $input = file_get_contents('php://input');
            $jsonData = json_decode($input, true);
            $data = $jsonData ?: $_POST;
        }

        // Hapus field sensitif
        unset($data['password']);
        unset($data['token']);

        return $data;
    }
}
