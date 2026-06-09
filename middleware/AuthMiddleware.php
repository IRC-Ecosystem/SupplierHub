<?php
/**
 * JWT Authentication Middleware
 * Handles JWT token creation and validation
 */

require_once __DIR__ . '/../config/constants.php';

class AuthMiddleware {

    /**
     * Generate JWT token
     */
    public static function generateToken($user) {
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        $payload = self::base64UrlEncode(json_encode([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
            'name'    => $user['name'],
            'iat'     => time(),
            'exp'     => time() + JWT_EXPIRY
        ]));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
        );

        return "$header.$payload.$signature";
    }

    /**
     * Validate JWT token and return payload
     */
    public static function validateToken($token = null) {
        if ($token === null) {
            $token = self::getBearerToken();
        }

        if (!$token) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($header, $payload, $signature) = $parts;

        // Verify signature
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
        );

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        // Decode payload
        $data = json_decode(self::base64UrlDecode($payload), true);

        // Check expiry
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * Require authentication - returns user data or sends 401
     */
    public static function requireAuth($requiredRole = null) {
        // Check session first (for web views)
        session_start();
        if (isset($_SESSION['user_id'])) {
            $user = [
                'user_id' => $_SESSION['user_id'],
                'email'   => $_SESSION['email'],
                'role'    => $_SESSION['role'],
                'name'    => $_SESSION['name']
            ];

            if ($requiredRole && $user['role'] !== $requiredRole) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Role tidak sesuai.']);
                exit;
            }

            return $user;
        }

        // Check JWT token (for API calls)
        $tokenData = self::validateToken();
        if (!$tokenData) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Token tidak valid atau sudah kadaluarsa.']);
            exit;
        }

        if ($requiredRole && $tokenData['role'] !== $requiredRole) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Role tidak sesuai.']);
            exit;
        }

        return $tokenData;
    }

    /**
     * Check if user is logged in (non-blocking)
     */
    public static function getUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            return [
                'user_id' => $_SESSION['user_id'],
                'email'   => $_SESSION['email'],
                'role'    => $_SESSION['role'],
                'name'    => $_SESSION['name']
            ];
        }

        $tokenData = self::validateToken();
        return $tokenData;
    }

    /**
     * Set session after login
     */
    public static function setSession($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
    }

    /**
     * Extract Bearer token from Authorization header
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

        // Also check query parameter (fallback for testing)
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Base64 URL-safe encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
