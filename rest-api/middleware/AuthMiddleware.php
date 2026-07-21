<?php
/**
 * Auth Middleware
 * JWT Authentication untuk REST API
 * (Aturan #6: Validasi wajib)
 */

require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../config/database.php';

class AuthMiddleware {

    /**
     * Authenticate user dari Bearer token
     * Return user data atau kirim 401
     */
    public static function authenticate() {
        $token = self::getBearerToken();

        if (!$token) {
            Response::error('Token tidak ditemukan. Sertakan header: Authorization: Bearer <token>', 401);
        }

        $payload = JWT::decode($token);

        if (!$payload) {
            Response::error('Token tidak valid atau sudah kadaluarsa.', 401);
        }

        return [
            'user_id' => $payload['user_id'],
            'email'   => $payload['email'],
            'role'    => $payload['role'],
            'name'    => $payload['name'],
        ];
    }

    /**
     * Require specific role
     * Panggil setelah authenticate()
     */
    public static function requireRole($user, $requiredRole) {
        if ($user['role'] !== $requiredRole) {
            Response::error("Akses ditolak. Hanya role '$requiredRole' yang diizinkan.", 403);
        }
    }

    /**
     * Extract Bearer token dari Authorization header
     */
    private static function getBearerToken() {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if ($headers && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
