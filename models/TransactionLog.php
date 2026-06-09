<?php
/**
 * TransactionLog Model
 * Logs every API request for audit trail (Aturan #6: Validasi & Logging wajib)
 */

require_once __DIR__ . '/../config/database.php';

class TransactionLog {

    /**
     * Log an API request/response
     */
    public static function log($endpoint, $method, $user_id, $requestData, $responseData) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO transaction_logs (endpoint, method, user_id, request_data, response_data, ip_address)
                VALUES (:endpoint, :method, :user_id, :req, :res, :ip)
            ");
            $stmt->execute([
                'endpoint' => $endpoint,
                'method'   => $method,
                'user_id'  => $user_id,
                'req'      => is_string($requestData) ? $requestData : json_encode($requestData),
                'res'      => is_string($responseData) ? $responseData : json_encode($responseData),
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Logging should not break the application
            error_log("TransactionLog Error: " . $e->getMessage());
        }
    }

    /**
     * Get logs by user
     */
    public static function getByUser($user_id, $limit = 50) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM transaction_logs 
            WHERE user_id = :uid 
            ORDER BY created_at DESC 
            LIMIT :lim
        ");
        $stmt->bindValue('uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get recent logs
     */
    public static function getRecent($limit = 100) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM transaction_logs ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
