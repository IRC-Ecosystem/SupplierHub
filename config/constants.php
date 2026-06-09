<?php
/**
 * Application Constants
 * SupplierHub B2B Application
 */

// Fee & Financial Rules (sesuai Aplikasi.docx)
define('FEE_SUPPLIER', 0.03);       // 3% margin supplier
define('FEE_GATEWAY', 0.005);       // 0.5% fee API Gateway
define('FEE_BANK', 0.01);           // 1% fee SmartBank
define('SALDO_AWAL', 50000);        // Rp 50.000 saldo awal user

// JWT Configuration
define('JWT_SECRET', 'supplierhub_b2b_secret_key_2026');
define('JWT_EXPIRY', 86400); // 24 hours

// SmartBank API (kelompok lain)
define('SMARTBANK_API_URL', 'http://localhost/SmartBank/api');

// API Gateway (bersama)
define('GATEWAY_API_URL', 'http://localhost/APIGateway/api');

// Application
define('APP_NAME', 'SupplierHub B2B');
define('APP_VERSION', '1.0.0');

// Auto-detect BASE_URL:
// - Laragon/Apache: http://localhost/SupplierHub → BASE_URL = '/SupplierHub'
// - PHP built-in server: http://localhost:8000   → BASE_URL = ''
$_port = $_SERVER['SERVER_PORT'] ?? 80;
if ((int)$_port !== 80 && (int)$_port !== 443) {
    // PHP built-in server atau port non-standar → root langsung
    define('BASE_URL', '');
} else {
    // Laragon / Apache → ada subfolder
    define('BASE_URL', '/SupplierHub');
}
unset($_port);
