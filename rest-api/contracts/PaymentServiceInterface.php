<?php
/**
 * Payment Service Interface (Kontrak Microservice)
 * 
 * ARSITEKTUR MICROSERVICE:
 * Interface ini mendefinisikan KONTRAK komunikasi antara
 * Order Service dan Payment Service (SmartBank).
 * 
 * Prinsip: Loose Coupling
 * - Order Service tidak perlu tahu BAGAIMANA pembayaran diproses
 * - Order Service hanya perlu tahu APA yang bisa dipanggil (interface ini)
 * - Implementasi bisa diganti tanpa mengubah Order Service
 * 
 * Aturan Aplikasi.docx:
 * #3 - Semua output transaksi = payment request
 * #4 - SmartBank sebagai pusat kontrol
 * #10 - Setiap endpoint = kontrak sistem
 */

interface PaymentServiceInterface {

    /**
     * Kirim payment request
     * 
     * @param int $userId ID user yang membayar
     * @param int $amount Jumlah pembayaran (subtotal)
     * @param int $fee Fee yang dikenakan
     * @param string $description Deskripsi transaksi
     * @return array ['status' => 'success'|'failed', 'data' => [...]]
     */
    public static function pay($userId, $amount, $fee, $description);
}
