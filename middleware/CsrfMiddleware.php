<?php

class CsrfMiddleware {
    public static function token() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    public static function verify() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['status'=>'error','code'=>'SEC_CSRF_INVALID','message'=>'Token keamanan tidak valid. Muat ulang halaman.']);
            exit;
        }
    }
}
