<?php
/**
 * User Model
 * Handles user CRUD operations
 */

require_once __DIR__ . '/../config/database.php';

class User {
    
    /**
     * Find user by email
     */
    public static function findByEmail($email) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Find user by ID
     */
    public static function findById($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create new user
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
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => $data['role']
        ]);
        return $db->lastInsertId();
    }

    /**
     * Get all users by role
     */
    public static function getByRole($role) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE role = :role");
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll();
    }

    /**
     * Verify password
     */
    public static function verifyPassword($plainPassword, $hashedPassword) {
        return password_verify($plainPassword, $hashedPassword);
    }
}
