<?php
/**
 * Report Service
 * LAYER: Business Logic (Service Layer)
 * 
 * Arsitektur: Analytics service yang HANYA MEMBACA data.
 * (Aturan #7: Analytics tidak boleh mengubah data transaksi)
 * 
 * Menggunakan Repository dari domain lain untuk mengambil statistik.
 * Ini menunjukkan komunikasi antar Microservice secara read-only.
 */

require_once __DIR__ . '/../models/OrderRepository.php';
require_once __DIR__ . '/../models/MaterialRepository.php';

class ReportService {

    /**
     * Statistik dashboard supplier
     * 
     * Cross-domain query:
     *   - MaterialRepository → stok & valuasi (Inventory domain)
     *   - OrderRepository → pending & revenue (Order domain)
     */
    public static function supplierStats($supplierId) {
        $stock = MaterialRepository::getStockStats($supplierId);
        $orders = OrderRepository::getSupplierOrderStats($supplierId);

        return [
            'stock_valuation' => (int) $stock['stock_valuation'],
            'total_items'     => (int) $stock['total_items'],
            'pending_orders'  => $orders['pending_orders'],
            'total_revenue'   => $orders['total_revenue']
        ];
    }

    /**
     * Statistik dashboard UMKM
     */
    public static function umkmStats($umkmId) {
        return OrderRepository::getUmkmOrderStats($umkmId);
    }
}
