<?php
/**
 * Inventory Service Interface (Kontrak Microservice)
 * 
 * ARSITEKTUR MICROSERVICE:
 * Interface ini mendefinisikan KONTRAK komunikasi antara
 * Order Service dan Inventory Service (Material/Stok).
 * 
 * Prinsip: Loose Coupling
 * - Order Service memanggil checkStock() sebelum approve
 * - Order Service memanggil reduceStock() setelah payment berhasil
 * - Implementasi inventory bisa berubah tanpa mengubah Order Service
 * 
 * Aturan Aplikasi.docx:
 * #1 - Setiap fitur = 1 node sistem
 * #10 - Setiap endpoint = kontrak sistem
 */

interface InventoryServiceInterface {

    /**
     * Cek ketersediaan stok material
     * 
     * @param int $materialId ID material yang dicek
     * @param int $qty Jumlah yang dibutuhkan
     * @return bool true jika stok mencukupi
     */
    public static function checkStock($materialId, $qty);

    /**
     * Kurangi stok material setelah order di-approve
     * 
     * @param int $materialId ID material
     * @param int $qty Jumlah yang dikurangi
     * @return bool true jika berhasil
     */
    public static function reduceStock($materialId, $qty);
}
