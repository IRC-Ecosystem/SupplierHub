<?php
/**
 * API Gateway Middleware
 * Simulates API Gateway routing (Aturan #5: Wajib melalui API Gateway)
 */

require_once __DIR__ . '/../config/constants.php';

class GatewayMiddleware {

    /**
     * Process request through gateway
     * Adds gateway headers and calculates gateway fee
     */
    public static function process($requestData) {
        // Add gateway metadata
        $gatewayData = [
            'gateway_id'        => 'GW-' . uniqid(),
            'gateway_timestamp' => date('Y-m-d H:i:s'),
            'gateway_fee'       => FEE_GATEWAY,
            'source_app'        => 'SupplierHub',
            'version'           => APP_VERSION
        ];

        return array_merge($requestData, ['_gateway' => $gatewayData]);
    }

    /**
     * Calculate gateway fee for a transaction
     */
    public static function calculateFee($amount) {
        return (int) round($amount * FEE_GATEWAY);
    }

    /**
     * Validate gateway headers (for incoming requests from other apps)
     */
    public static function validateRequest() {
        // In production, would validate API keys, signatures, etc.
        // For simulation, we accept all requests
        return true;
    }

    /**
     * Add gateway response headers
     */
    public static function addResponseHeaders() {
        header('X-Gateway-ID: GW-' . uniqid());
        header('X-Gateway-App: SupplierHub');
        header('X-Gateway-Version: ' . APP_VERSION);
    }
}
