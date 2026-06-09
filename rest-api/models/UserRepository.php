<?php
/**
 * User Repository
 * LAYER: Data Access (Repository Pattern)
 * 
 * Arsitektur: Repository HANYA berisi query database murni.
 * Tidak ada logika bisnis (validasi, hashing) di sini.
 * 
 * Digunakan oleh: UserService (Service Layer)
 */

require_once __DIR__ . '/../config/database.php';

class UserRepository {

    /**
     * Cari user berdasarkan email
     * @return array|false
     */
    public static function findByEmail($email) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Cari user berdasarkan ID (tanpa password untuk keamanan)
     * @return array|false
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Insert user baru ke database
     * @param array $data ['name', 'email', 'password' (sudah di-hash), 'role']
     * @return int ID user yang baru dibuat
     */
    public static function create($data) {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role) 
            VALUES (:name, :email, :password, :role)
        ");
        $stmt->execute([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => $data['role']
        ]);
        return (int) $db->lastInsertId();
    }
}
