<?php
/**
 * Payment Model
 * Handles database operations for the local payments ledger
 */

require_once __DIR__ . '/../config/database.php';

class Payment {
    
    /**
     * Create a new payment record (log)
     */
    public static function create($user_id, $type, $amount, $description, $reference_id = null) {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO payments (user_id, type, amount, description, reference_id)
            VALUES (:user_id, :type, :amount, :description, :reference_id)
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_id' => $reference_id
        ]);
        return $db->lastInsertId();
    }

    /**
     * Get all payment history for a user
     */
    public static function getHistory($user_id) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get total income and outcome for a user
     */
    public static function getSummary($user_id) {
        $db = getDB();
        
        $stmtDebit = $db->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = :user_id AND type = 'debit'");
        $stmtDebit->execute(['user_id' => $user_id]);
        $outcome = $stmtDebit->fetch()['total'] ?? 0;

        $stmtCredit = $db->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = :user_id AND type = 'credit'");
        $stmtCredit->execute(['user_id' => $user_id]);
        $income = $stmtCredit->fetch()['total'] ?? 0;

        return [
            'income' => (int) $income,
            'outcome' => (int) $outcome
        ];
    }
}
