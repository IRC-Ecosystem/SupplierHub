<?php
/**
 * Auth Controller
 * Fitur 1: Registrasi & Login User
 * 
 * IPO Pattern (Aplikasi.docx):
 *   Input: email, password (login) / name, email, password, role (register)
 *   Proses: Validasi input → cari user → verify password → generate JWT
 *   Output: status + data (token, user info)
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../services/UserService.php';

class AuthController {

    /**
     * POST /api/v1/auth/login
     * Input: email, password
     * Output: JWT token + user data
     */
    public static function login() {
        $input = Validator::getJsonBody();

        // Validasi input (Aturan #6)
        $errors = Validator::validate($input, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        // Cari user
        $user = UserService::findByEmail($input['email']);
        if (!$user) {
            Response::error('Akun tidak ditemukan.', 401);
        }

        // Verify password
        if (!UserService::verifyPassword($input['password'], $user['password'])) {
            Response::error('Password salah.', 401);
        }

        // Generate JWT token
        $token = JWT::encode([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
            'name'    => $user['name'],
        ]);

        Response::success([
            'token'   => $token,
            'user_id' => $user['id'],
            'name'    => $user['name'],
            'email'   => $user['email'],
            'role'    => $user['role'],
        ], 'Login berhasil.');
    }

    /**
     * POST /api/v1/auth/register
     * Input: name, email, password, role
     * Output: user_id
     */
    public static function register() {
        $input = Validator::getJsonBody();

        // Validasi input (Aturan #6)
        $errors = Validator::validate($input, [
            'name'     => 'required|min:3',
            'email'    => 'required|email',
            'password' => 'required|min:8',
            'role'     => 'required|in:umkm,supplier',
        ]);
        if (!empty($errors)) {
            Response::error('Validasi gagal.', 400, $errors);
        }

        // Cek email sudah terdaftar
        $existing = UserService::findByEmail($input['email']);
        if ($existing) {
            Response::error('Email sudah terdaftar.', 409);
        }

        // Create user
        try {
            $userId = UserService::create($input);
            Response::success(['user_id' => $userId], 'Registrasi berhasil. Silakan login.', 201);
        } catch (Exception $e) {
            Response::error('Gagal registrasi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/auth/me
     * Output: profil user dari token
     */
    public static function me($user) {
        $userData = UserService::findById($user['user_id']);
        if (!$userData) {
            Response::error('User tidak ditemukan.', 404);
        }
        Response::success($userData, 'Profil berhasil diambil.');
    }
}
