<?php
/**
 * Database & Application Configuration - REST API SupplierHub
 * 
 * ARSITEKTUR: Single Source of Truth
 * Konstanta bersama (Fee, JWT, SmartBank) dimuat dari config/constants.php
 * agar tidak terjadi duplikasi antar layer frontend dan REST API.
 * 
 * Hanya konstanta khusus REST API (DB credentials) yang didefinisikan di sini.
 */

// ===== Shared Constants (Single Source of Truth) =====
// Fee, JWT, SmartBank URL, APP_VERSION didefinisikan di sini:
require_once __DIR__ . '/../../config/constants.php';

// ===== Database Credentials (REST API specific) =====
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'supplierhub_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// ===== REST API Identifier =====
if (!defined('REST_API_NAME')) define('REST_API_NAME', 'SupplierHub REST API');

/**
 * Get PDO database connection (singleton pattern)
 * 
 * Arsitektur: Semua Repository menggunakan fungsi ini
 * untuk mendapatkan koneksi database yang sama (shared connection).
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}
