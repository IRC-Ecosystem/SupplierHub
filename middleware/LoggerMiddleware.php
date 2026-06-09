<?php
/**
 * Logger Middleware
 * Logs every API request/response (Aturan #6: Validasi & Logging wajib)
 */

require_once __DIR__ . '/../models/TransactionLog.php';

class LoggerMiddleware {

    /**
     * Log API request and response
     */
    public static function log($endpoint, $userId = null, $requestData = null, $responseData = null) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        
        TransactionLog::log(
            $endpoint,
            $method,
            $userId,
            $requestData,
            $responseData
        );
    }

    /**
     * Get current request data for logging
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

        // Remove sensitive fields
        unset($data['password']);
        unset($data['token']);

        return $data;
    }
}
