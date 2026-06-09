<?php
/**
 * User Service
 * LAYER: Business Logic (Service Layer)
 * 
 * Arsitektur: Service berisi LOGIKA BISNIS murni.
 * Akses database didelegasikan ke UserRepository.
 * 
 * Microservice Domain: Auth Service
 * Mengikuti pola services/books.ts dari referensi pertemuan 06
 */

require_once __DIR__ . '/../models/UserRepository.php';

class UserService {

    /**
     * Cari user berdasarkan email
     * @return array|false
     */
    public static function findByEmail($email) {
        return UserRepository::findByEmail($email);
    }

    /**
     * Cari user berdasarkan ID (tanpa password)
     * @return array|false
     */
    public static function findById($id) {
        return UserRepository::findById($id);
    }

    /**
     * Buat user baru
     * Logika bisnis: password di-hash sebelum disimpan ke database
     * @return int ID user yang baru dibuat
     */
    public static function create($data) {
        // Hash password (logika bisnis — bukan urusan Repository)
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return UserRepository::create($data);
    }

    /**
     * Verifikasi password (logika bisnis keamanan)
     */
    public static function verifyPassword($plainPassword, $hashedPassword) {
        return password_verify($plainPassword, $hashedPassword);
    }
}
