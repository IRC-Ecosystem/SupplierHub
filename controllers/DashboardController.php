<?php
/**
 * Dashboard Controller
 * Provides statistics for Supplier and UMKM dashboards
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../services/SmartBankService.php';

class DashboardController {

    /**
     * Get supplier dashboard statistics
     */
    public static function supplierStats($supplier_id) {
        // Stock valuation
        $materials = Material::getBySupplier($supplier_id);
        $stockValuation = 0;
        foreach ($materials as $m) {
            $stockValuation += $m['price'] * $m['stock'];
        }

        // Order stats
        $orderStats = Order::getSupplierStats($supplier_id);

        // Fetch real balance from API Gateway (SmartBank)
        /** @var PaymentGatewayInterface $gateway */
        $gateway = 'SmartBankService';
        $balanceResponse = $gateway::getBalance($supplier_id);
        $balance = $balanceResponse['data']['saldo'] ?? $balanceResponse['data']['balance'] ?? 0;

        return [
            'status' => 'success',
            'data'   => [
                'stock_valuation' => $stockValuation,
                'total_items'     => count($materials),
                'pending_orders'  => $orderStats['pending_orders'],
                'total_revenue'   => $orderStats['total_revenue'],
                'smartbank_balance' => $balance
            ]
        ];
    }

    /**
     * Get UMKM dashboard statistics
     */
    public static function umkmStats($umkm_id) {
        $stats = Order::getUmkmStats($umkm_id);

        // Fetch real balance from API Gateway (SmartBank)
        /** @var PaymentGatewayInterface $gateway */
        $gateway = 'SmartBankService';
        $balanceResponse = $gateway::getBalance($umkm_id);
        $balance = $balanceResponse['data']['saldo'] ?? $balanceResponse['data']['balance'] ?? 0;

        return [
            'status' => 'success',
            'data'   => [
                'total_orders' => $stats['total_orders'],
                'total_spent'  => $stats['total_spent'],
                'smartbank_balance' => $balance
            ]
        ];
    }

    /**
     * Get Finance/Ledger statistics
     */
    public static function financeStats($user_id) {
        // Fetch real balance from API Gateway (SmartBank)
        /** @var PaymentGatewayInterface $gateway */
        $gateway = 'SmartBankService';
        $balanceResponse = $gateway::getBalance($user_id);
        $balance = $balanceResponse['data']['saldo'] ?? $balanceResponse['data']['balance'] ?? 0;

        // Fetch local ledger (Neraca) from payments
        $summary = Payment::getSummary($user_id);
        $history = Payment::getHistory($user_id);

        return [
            'status' => 'success',
            'data' => [
                'balance' => $balance,
                'income' => $summary['income'],
                'outcome' => $summary['outcome'],
                'history' => $history
            ]
        ];
    }
}
