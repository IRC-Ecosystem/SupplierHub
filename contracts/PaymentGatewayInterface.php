<?php
/**
 * PaymentGatewayInterface
 * Defines the contract for payment processing gateways
 * Sesuai prinsip SOLID (Liskov Substitution Principle)
 */

interface PaymentGatewayInterface {
    
    /**
     * Process payment
     * 
     * @param int $user_id
     * @param int|float $amount
     * @param int|float $fee
     * @param string $description
     * @return array ['status' => 'success|error', 'message' => string, 'data' => mixed]
     */
    public static function pay($user_id, $amount, $fee, $description = '');

    /**
     * Get user balance
     * 
     * @param int $user_id
     * @return array ['status' => 'success|error', 'data' => ['balance' => float|int]]
     */
    public static function getBalance($user_id);
}
