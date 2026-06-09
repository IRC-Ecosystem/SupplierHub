<?php
/**
 * API Gateway Middleware
 * (Aturan #5: Wajib melalui API Gateway)
 * 
 * Menambahkan header gateway di setiap response dan menghitung fee gateway.
 */

require_once __DIR__ . '/../config/database.php';

class GatewayMiddleware {

    /**
     * Tambahkan gateway headers ke response
     * Dipanggil di awal setiap request
     */
    public static function addHeaders() {
        header('X-Gateway-ID: GW-' . uniqid());
        header('X-Gateway-App: SupplierHub-REST-API');
        header('X-Gateway-Version: ' . APP_VERSION);
        header('X-Gateway-Fee: ' . (FEE_GATEWAY * 100) . '%');
    }

    /**
     * Hitung fee gateway untuk suatu transaksi
     * Fee Gateway = 0.5% (sesuai Aplikasi.docx Aturan Keuangan #10)
     */
    public static function calculateFee($amount) {
        return (int) round($amount * FEE_GATEWAY);
    }

    /**
     * Proses request melalui gateway (menambahkan metadata)
     */
    public static function process($requestData) {
        return array_merge($requestData, [
            '_gateway' => [
                'gateway_id'        => 'GW-' . uniqid(),
                'gateway_timestamp' => date('Y-m-d H:i:s'),
                'gateway_fee'       => FEE_GATEWAY,
                'source_app'        => 'SupplierHub-REST-API',
                'version'           => APP_VERSION
            ]
        ]);
    }
}
