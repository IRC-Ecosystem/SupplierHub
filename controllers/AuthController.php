<?php
/**
 * Auth Controller
 * Handles login, register, and session management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/LoggerMiddleware.php';

class AuthController {

    /**
     * Login
     * IPO: email+password+role → validate → JWT token + session
     */
    public static function login($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return ['status' => 'error', 'message' => 'Email dan password wajib diisi.'];
        }

        // Find user
        $user = User::findByEmail($data['email']);
        if (!$user) {
            return ['status' => 'error', 'message' => 'Akun tidak ditemukan.'];
        }

        // Verify password
        if (!User::verifyPassword($data['password'], $user['password'])) {
            return ['status' => 'error', 'message' => 'Password salah.'];
        }

        // Generate JWT
        $token = AuthMiddleware::generateToken($user);

        // Set session
        AuthMiddleware::setSession($user);

        return [
            'status'  => 'success',
            'message' => 'Login berhasil.',
            'data'    => [
                'token'    => $token,
                'user_id'  => $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'redirect' => $user['role'] === 'integrator'
                    ? BASE_URL . '/index.php?p=integrator'
                    : ($user['role'] === 'supplier' ? BASE_URL . '/index.php?p=supplier' : BASE_URL . '/index.php?p=umkm')
            ]
        ];
    }

    /**
     * Register
     * IPO: name+email+password+role → create user → success status
     */
    public static function register($data) {
        // Validate input
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return ['status' => 'error', 'message' => 'Semua field wajib diisi.'];
        }

        // Force role to umkm - only UMKM can register via public form
        $data['role'] = 'umkm';

        if (strlen($data['password']) < 8) {
            return ['status' => 'error', 'message' => 'Password minimal 8 karakter.'];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Format email tidak valid.'];
        }

        // Check existing user
        $existing = User::findByEmail($data['email']);
        if ($existing) {
            return ['status' => 'error', 'message' => 'Email sudah terdaftar.'];
        }

        // Create user
        try {
            $userId = User::create($data);
            return [
                'status'  => 'success',
                'message' => 'Registrasi berhasil. Silakan login.',
                'data'    => ['user_id' => $userId]
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Gagal registrasi: ' . $e->getMessage()];
        }
    }
}
