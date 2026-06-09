<?php
/**
 * REST API Router - SupplierHub
 * Entry point utama — semua request masuk ke sini via .htaccess
 * 
 * Mengikuti pola app.ts + routes/ di referensi WS2026:
 *   app.use("/health", healthRouter);
 *   app.use("/api/v1/books", booksRouter);
 * 
 * Aturan Aplikasi.docx yang diterapkan:
 *   #5 - Wajib melalui API Gateway (GatewayMiddleware)
 *   #6 - Validasi & Logging wajib (LoggerMiddleware)
 *   #10 - Setiap endpoint = kontrak sistem
 */

// ===== CORS Headers =====
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===== Load Dependencies =====
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/JWT.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/GatewayMiddleware.php';
require_once __DIR__ . '/middleware/LoggerMiddleware.php';
require_once __DIR__ . '/controllers/HealthController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/MaterialController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/ReportController.php';

// ===== API Gateway Headers (Aturan #5) =====
GatewayMiddleware::addHeaders();

// ===== Parse Request =====
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Hapus base path (/SupplierHub/rest-api) dan query string
$basePath = '/SupplierHub/rest-api';
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = rtrim($path, '/') ?: '/';

// Parse path segments
$segments = array_values(array_filter(explode('/', $path)));
// segments: ['api', 'v1', 'materials', '5'] etc.

// ===== Request Data untuk Logging =====
$requestData = LoggerMiddleware::getRequestData();
$userId = null;

// ===== Router =====
// Mengikuti pola booksRouter.get("/", listBooks) di referensi

try {

    // --------------------------------------------------
    // GET /health
    // --------------------------------------------------
    if ($path === '/health' && $method === 'GET') {
        HealthController::index();
    }

    // --------------------------------------------------
    // GET / (root)
    // --------------------------------------------------
    elseif ($path === '/' && $method === 'GET') {
        Response::success([
            'service'   => 'SupplierHub REST API',
            'version'   => APP_VERSION,
            'endpoints' => [
                'GET  /health'                       => 'Health check',
                'POST /api/v1/auth/login'            => 'Login',
                'POST /api/v1/auth/register'         => 'Register',
                'GET  /api/v1/auth/me'               => 'Profil user',
                'GET  /api/v1/materials'             => 'List materials',
                'GET  /api/v1/materials/:id'         => 'Detail material',
                'POST /api/v1/materials'             => 'Tambah material',
                'PUT  /api/v1/materials/:id'         => 'Update material',
                'DELETE /api/v1/materials/:id'       => 'Hapus material',
                'GET  /api/v1/orders'                => 'List orders',
                'GET  /api/v1/orders/:id'            => 'Detail order',
                'POST /api/v1/orders'                => 'Buat pesanan',
                'POST /api/v1/orders/smart-bundle'   => 'Rekomendasi bundling (Greedy)',
                'PATCH /api/v1/orders/:id/approve'   => 'Approve order',
                'PATCH /api/v1/orders/:id/reject'    => 'Reject order',
                'GET  /api/v1/reports/stats'         => 'Dashboard stats',
            ]
        ], 'Selamat datang di SupplierHub REST API.');
    }

    // --------------------------------------------------
    // AUTH Routes: /api/v1/auth/*
    // --------------------------------------------------
    elseif (isset($segments[0], $segments[1], $segments[2]) 
            && $segments[0] === 'api' && $segments[1] === 'v1' && $segments[2] === 'auth') {
        
        $authAction = $segments[3] ?? '';

        if ($authAction === 'login' && $method === 'POST') {
            AuthController::login();
        }
        elseif ($authAction === 'register' && $method === 'POST') {
            AuthController::register();
        }
        elseif ($authAction === 'me' && $method === 'GET') {
            $user = AuthMiddleware::authenticate();
            $userId = $user['user_id'];
            AuthController::me($user);
        }
        else {
            Response::error("Route $method /api/v1/auth/$authAction tidak ditemukan.", 404);
        }
    }

    // --------------------------------------------------
    // MATERIALS Routes: /api/v1/materials/*
    // --------------------------------------------------
    elseif (isset($segments[0], $segments[1], $segments[2])
            && $segments[0] === 'api' && $segments[1] === 'v1' && $segments[2] === 'materials') {
        
        $user = AuthMiddleware::authenticate();
        $userId = $user['user_id'];
        $materialId = $segments[3] ?? null;

        // GET /api/v1/materials → list
        if ($method === 'GET' && !$materialId) {
            MaterialController::index($user);
        }
        // GET /api/v1/materials/:id → show
        elseif ($method === 'GET' && $materialId) {
            MaterialController::show($user, (int)$materialId);
        }
        // POST /api/v1/materials → create
        elseif ($method === 'POST' && !$materialId) {
            MaterialController::store($user);
        }
        // PUT /api/v1/materials/:id → update
        elseif ($method === 'PUT' && $materialId) {
            MaterialController::update($user, (int)$materialId);
        }
        // DELETE /api/v1/materials/:id → delete
        elseif ($method === 'DELETE' && $materialId) {
            MaterialController::destroy($user, (int)$materialId);
        }
        else {
            Response::error("Route $method pada /api/v1/materials tidak ditemukan.", 404);
        }
    }

    // --------------------------------------------------
    // ORDERS Routes: /api/v1/orders/*
    // --------------------------------------------------
    elseif (isset($segments[0], $segments[1], $segments[2])
            && $segments[0] === 'api' && $segments[1] === 'v1' && $segments[2] === 'orders') {
        
        $user = AuthMiddleware::authenticate();
        $userId = $user['user_id'];
        $orderId = $segments[3] ?? null;
        $action = $segments[4] ?? null;

        // POST /api/v1/orders/smart-bundle → Greedy Bundling Recommendation
        // (Harus sebelum route :id agar 'smart-bundle' tidak terparse sebagai orderId)
        if ($method === 'POST' && $orderId === 'smart-bundle') {
            OrderController::getRecommendation($user);
        }
        // GET /api/v1/orders → list
        elseif ($method === 'GET' && !$orderId) {
            OrderController::index($user);
        }
        // GET /api/v1/orders/:id → show
        elseif ($method === 'GET' && $orderId && !$action) {
            OrderController::show($user, (int)$orderId);
        }
        // POST /api/v1/orders → create (checkout)
        elseif ($method === 'POST' && !$orderId) {
            OrderController::store($user);
        }
        // PATCH /api/v1/orders/:id/approve
        elseif ($method === 'PATCH' && $orderId && $action === 'approve') {
            OrderController::approve($user, (int)$orderId);
        }
        // PATCH /api/v1/orders/:id/reject
        elseif ($method === 'PATCH' && $orderId && $action === 'reject') {
            OrderController::reject($user, (int)$orderId);
        }
        else {
            Response::error("Route $method pada /api/v1/orders tidak ditemukan.", 404);
        }
    }

    // --------------------------------------------------
    // REPORTS Routes: /api/v1/reports/*
    // --------------------------------------------------
    elseif (isset($segments[0], $segments[1], $segments[2])
            && $segments[0] === 'api' && $segments[1] === 'v1' && $segments[2] === 'reports') {
        
        $user = AuthMiddleware::authenticate();
        $userId = $user['user_id'];
        $reportAction = $segments[3] ?? '';

        // GET /api/v1/reports/stats
        if ($reportAction === 'stats' && $method === 'GET') {
            ReportController::stats($user);
        }
        else {
            Response::error("Route $method pada /api/v1/reports tidak ditemukan.", 404);
        }
    }

    // --------------------------------------------------
    // 404 - Route Not Found
    // --------------------------------------------------
    else {
        Response::error("Route $method $path tidak ditemukan.", 404);
    }

} catch (Exception $e) {
    // Log error
    LoggerMiddleware::log($path, $method, $userId, $requestData, ['error' => $e->getMessage()]);
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

// ===== Log Request (Aturan #6) =====
// Note: Response sudah ter-echo oleh controller, log menggunakan output buffer
LoggerMiddleware::log($path, $method, $userId, $requestData, null);
