<?php
/**
 * SmartBank Service
 * Handles integration with SmartBank API for payment processing
 * 
 * Sesuai Aplikasi.docx:
 * - SupplierHub TIDAK mengelola saldo
 * - Semua transaksi → payment request ke SmartBank
 * - POST /supplier/pay → SmartBank
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../contracts/PaymentGatewayInterface.php';

class SmartBankService implements PaymentGatewayInterface {

    /**
     * Send payment request to SmartBank
     * IPO Pattern:
     *   Input: user_id, amount, fee, description
     *   Proses: Validasi → kirim ke SmartBank via Gateway
     *   Output: payment status + reference
     */
    public static function pay($user_id, $amount, $fee_supplier, $description = '', $idempotencyKey = null) {
        // Map local SupplierHub user ID to SmartBank account user ID
        // local ID 1 (Supplier) -> SmartBank ID 3
        // local ID 2 (UMKM)     -> SmartBank ID 2
        $mappedUserId = $user_id;
        if ($user_id == 1) {
            $mappedUserId = 3;
        }

        $payload = [
            'user_id'      => $mappedUserId,
            'amount'       => $amount,
            'fee_supplier' => $fee_supplier,
            'fee_gateway'  => GatewayMiddleware::calculateFee($amount),
            'description'  => $description,
            'source_app'   => 'SupplierHub',
            'timestamp'    => date('Y-m-d H:i:s')
        ];

        if ($idempotencyKey) {
            $payload['idempotency_key'] = $idempotencyKey;
        }

        // Route through API Gateway
        $payload = GatewayMiddleware::process($payload);

        // Local development uses an explicit mock. A SmartBank outage must never
        // silently turn into a successful payment in integration mode.
        if (PAYMENT_MODE === 'mock') {
            return self::simulateResponse('/smartbank/pembayaran_transaksi', $payload);
        }

        if (PAYMENT_MODE !== 'smartbank') {
            return ['status' => 'error', 'message' => 'PAYMENT_MODE tidak valid.'];
        }

        $response = self::callSmartBankAPI('/smartbank/pembayaran_transaksi', $payload);

        return $response;
    }

    /**
     * Get user balance from SmartBank
     */
    public static function getBalance($user_id) {
        // Map local SupplierHub user ID to SmartBank account user ID
        $mappedUserId = $user_id;
        if ($user_id == 1) {
            $mappedUserId = 3;
        }

        $payload = [
            'user_id'    => $mappedUserId,
            'source_app' => 'SupplierHub'
        ];

        if (PAYMENT_MODE === 'mock') {
            return self::simulateResponse('/smartbank/manajemen_saldo', $payload);
        }

        if (PAYMENT_MODE !== 'smartbank') {
            return ['status' => 'error', 'message' => 'PAYMENT_MODE tidak valid.'];
        }

        $response = self::callSmartBankAPI('/smartbank/manajemen_saldo', $payload, 'GET');

        return $response;
    }

    /**
     * Call SmartBank API endpoint
     * Falls back to simulation if API is not reachable
     */
    private static function callSmartBankAPI($endpoint, $data, $method = 'POST') {
        $url = SMARTBANK_API_URL . $endpoint;

        // Try HTTP request to SmartBank
        $ch = curl_init();
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Source-App: SupplierHub',
                'X-Gateway-ID: ' . ($data['_gateway']['gateway_id'] ?? 'local')
            ]
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // If SmartBank API is reachable
        if ($result && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($result, true);
            if ($decoded) {
                return $decoded;
            }
        }

        return [
            'status' => 'error',
            'message' => $error
                ? 'SmartBank tidak dapat dihubungi: ' . $error
                : 'SmartBank mengembalikan HTTP ' . $httpCode,
            'data' => ['http_code' => $httpCode]
        ];
    }

    /**
     * Simulate SmartBank response for offline development
     */
    private static function simulateResponse($endpoint, $data) {
        $seed = $data['idempotency_key'] ?? bin2hex(random_bytes(16));
        $refId = 'LOCAL-' . strtoupper(substr(hash('sha256', $seed), 0, 20));

        if (strpos($endpoint, 'pembayaran_transaksi') !== false) {
            return [
                'status'      => 'success',
                'message'     => 'Pembayaran berhasil diproses (simulasi)',
                'data'        => [
                    'payment_id'     => $refId,
                    'amount_debited' => $data['amount'] + $data['fee_supplier'] + ($data['fee_gateway'] ?? 0),
                    'user_id'        => $data['user_id'],
                    'timestamp'      => date('Y-m-d H:i:s'),
                    'simulated'      => true
                ]
            ];
        }

        if (strpos($endpoint, 'manajemen_saldo') !== false) {
            return [
                'status'  => 'success',
                'message' => 'Saldo berhasil diambil (simulasi)',
                'data'    => [
                    'user_id' => $data['user_id'],
                    'balance' => SALDO_AWAL,
                    'simulated' => true
                ]
            ];
        }

        return [
            'status'  => 'success',
            'message' => 'Request diproses (simulasi)',
            'data'    => ['simulated' => true]
        ];
    }
}
