<?php
/**
 * SmartBank Service
 * LAYER: Business Logic (Service Layer)
 * IMPLEMENTS: PaymentServiceInterface (Kontrak Microservice)
 * 
 * Integrasi dengan SmartBank API untuk pembayaran
 * 
 * Arsitektur Microservice:
 *   SmartBankService adalah implementasi dari PaymentServiceInterface.
 *   Jika di masa depan SmartBank diganti bank lain, cukup buat class baru
 *   yang implements PaymentServiceInterface — Order Service tidak perlu berubah.
 * 
 * Aturan #3: Semua output transaksi = payment request
 * Aturan #4: SmartBank sebagai pusat kontrol
 * Aturan #8: Tidak ada uang dibuat bebas
 * 
 * SupplierHub TIDAK mengelola saldo langsung.
 * Semua transaksi → payment request ke SmartBank.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/GatewayMiddleware.php';
require_once __DIR__ . '/../contracts/PaymentServiceInterface.php';

class SmartBankService implements PaymentServiceInterface {

    /**
     * Kirim payment request ke SmartBank
     * 
     * IPO:
     *   Input: user_id, amount, fee_supplier, description
     *   Proses: Validasi → kirim ke SmartBank via Gateway
     *   Output: payment status + reference
     */
    public static function pay($userId, $amount, $feeSupplier, $description = '') {
        $payload = [
            'user_id'      => $userId,
            'amount'       => $amount,
            'fee_supplier' => $feeSupplier,
            'fee_gateway'  => GatewayMiddleware::calculateFee($amount),
            'description'  => $description,
            'source_app'   => 'SupplierHub-REST-API',
            'timestamp'    => date('Y-m-d H:i:s')
        ];

        // Route melalui API Gateway (Aturan #5)
        $payload = GatewayMiddleware::process($payload);

        if (PAYMENT_MODE === 'mock') {
            return self::simulateResponse('/smartbank/pembayaran_transaksi', $payload);
        }

        if (PAYMENT_MODE !== 'smartbank') {
            return ['status' => 'error', 'message' => 'PAYMENT_MODE tidak valid.'];
        }

        // Coba panggil SmartBank API
        $response = self::callSmartBankAPI('/smartbank/pembayaran_transaksi', $payload);

        return $response;
    }

    /**
     * Panggil SmartBank API endpoint
     * Fallback ke simulasi jika API tidak reachable
     */
    private static function callSmartBankAPI($endpoint, $data, $method = 'POST') {
        $url = SMARTBANK_API_URL . $endpoint;

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
                'X-Source-App: SupplierHub-REST-API',
                'X-Gateway-ID: ' . ($data['_gateway']['gateway_id'] ?? 'local')
            ]
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Jika SmartBank API reachable
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
     * Simulasi response SmartBank untuk development
     */
    private static function simulateResponse($endpoint, $data) {
        $refId = 'SB-REF-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return [
            'status'  => 'success',
            'message' => 'Pembayaran berhasil diproses (simulasi)',
            'data'    => [
                'payment_id'     => $refId,
                'amount_debited' => $data['amount'] + $data['fee_supplier'] + ($data['fee_gateway'] ?? 0),
                'user_id'        => $data['user_id'],
                'timestamp'      => date('Y-m-d H:i:s'),
                'simulated'      => true
            ]
        ];
    }
}
