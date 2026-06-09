<?php
/**
 * Class GreedyBundlingService
 * 
 * Menyediakan layanan rekomendasi paket bahan baku optimal
 * berdasarkan anggaran dan prioritas kategori yang ditentukan.
 */

require_once __DIR__ . '/../models/MaterialRepository.php';
require_once __DIR__ . '/../config/database.php';

class GreedyBundlingService
{
    /**
     * Menghitung rekomendasi paket belanja bahan baku optimal.
     * 
     * @param int $budget Total anggaran dalam Rupiah.
     * @param array $priorityCategories Kategori prioritas.
     * @param int|null $maxItems Maksimal jenis item unik (null = tanpa batas).
     * @return array Hasil rekomendasi beserta rincian biaya.
     */
    public static function recommend(
        int $budget,
        array $priorityCategories = [],
        ?int $maxItems = null
    ): array {
        // Validasi input budget
        if ($budget <= 0) {
            throw new Exception('Budget harus lebih besar dari 0.', 400);
        }

        // Ambil data katalog material aktif
        $result = MaterialRepository::findAll(1, 1000);
        $allMaterials = $result['data'];

        // Filter material dengan stok dan harga valid
        $availableMaterials = array_filter($allMaterials, function ($mat) {
            return (int) $mat['stock'] > 0 && (int) $mat['price'] > 0;
        });

        if (empty($availableMaterials)) {
            throw new Exception('Tidak ada material tersedia di katalog.', 404);
        }

        // Hitung utilitas dan rasio nilai banding harga untuk setiap material
        $materialsWithRatio = [];
        foreach ($availableMaterials as $mat) {
            $utilityScore = self::calculateUtilityScore($mat, $priorityCategories);
            $price = (int) $mat['price'];
            $ratio = $utilityScore / $price;

            $materialsWithRatio[] = [
                'id'            => (int) $mat['id'],
                'name'          => $mat['name'],
                'category'      => $mat['category'],
                'price'         => $price,
                'stock'         => (int) $mat['stock'],
                'unit'          => $mat['unit'],
                'icon'          => $mat['icon'] ?? 'ph-package',
                'supplier_name' => $mat['supplier_name'] ?? 'SupplierHub',
                'utility_score' => $utilityScore,
                'ratio'         => $ratio
            ];
        }

        // Urutkan material berdasarkan rasio tertinggi (descending)
        usort($materialsWithRatio, function ($a, $b) {
            if ($b['ratio'] === $a['ratio']) {
                return 0;
            }
            return ($b['ratio'] > $a['ratio']) ? 1 : -1;
        });

        // Alokasikan sisa anggaran setelah dikurangi biaya layanan
        $feeRate = defined('FEE_SUPPLIER') ? FEE_SUPPLIER : 0.03;
        $effectiveBudget = (int) floor($budget / (1 + $feeRate));
        $remainingBudget = $effectiveBudget;
        $selectedItems   = [];
        $subtotal        = 0;
        $itemCount       = 0;

        foreach ($materialsWithRatio as $material) {
            // Hentikan jika anggaran habis
            if ($remainingBudget <= 0) {
                break;
            }

            // Hentikan jika batas jenis item tercapai
            if ($maxItems !== null && $itemCount >= $maxItems) {
                break;
            }

            // Hitung kuantitas maksimal yang dapat dibeli
            $maxAffordableQty = (int) floor($remainingBudget / $material['price']);
            $maxAvailableQty  = $material['stock'];
            $qtyToBuy         = min($maxAffordableQty, $maxAvailableQty);

            if ($qtyToBuy > 0) {
                $lineCost = $qtyToBuy * $material['price'];

                $selectedItems[] = [
                    'material_id'   => $material['id'],
                    'name'          => $material['name'],
                    'category'      => $material['category'],
                    'unit_price'    => $material['price'],
                    'qty'           => $qtyToBuy,
                    'unit'          => $material['unit'],
                    'line_total'    => $lineCost,
                    'icon'          => $material['icon'],
                    'utility_score' => $material['utility_score'],
                    'ratio'         => round($material['ratio'], 6)
                ];

                $remainingBudget -= $lineCost;
                $subtotal        += $lineCost;
                $itemCount++;
            }
        }

        // Hitung ringkasan transaksi akhir
        $fee             = (int) round($subtotal * $feeRate);
        $grandTotal      = $subtotal + $fee;
        $budgetRemaining = $budget - $grandTotal;
        $utilization     = $budget > 0
            ? round(($grandTotal / $budget) * 100, 2)
            : 0;
        $efficiencyScore = self::calculateEfficiencyScore(
            $selectedItems,
            $budget,
            $subtotal,
            $feeRate
        );

        // Kembalikan data rekomendasi
        return [
            'algorithm'          => 'Greedy by Benefit-to-Cost Ratio',
            'complexity'         => 'O(n log n)',
            'items'              => $selectedItems,
            'summary'            => [
                'total_items'        => count($selectedItems),
                'subtotal'           => $subtotal,
                'fee_supplier'       => $fee,
                'fee_percentage'     => ($feeRate * 100) . '%',
                'grand_total'        => $grandTotal,
                'budget_input'       => $budget,
                'budget_remaining'   => $budgetRemaining,
                'budget_utilization' => $utilization . '%',
                'efficiency_score'   => $efficiencyScore
            ],
            'metadata'           => [
                'total_catalog_items'  => count($availableMaterials),
                'priority_categories'  => $priorityCategories,
                'max_items_limit'      => $maxItems,
                'timestamp'            => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Menghitung skor utilitas material.
     * 
     * @param array $material Data material.
     * @param array $priorityCategories Kategori prioritas.
     * @return int Skor utilitas (0-100).
     */
    private static function calculateUtilityScore(
        array $material,
        array $priorityCategories
    ): int {
        $score = 50; // Skor dasar untuk semua material

        // Tambahkan skor jika termasuk kategori prioritas
        if (!empty($priorityCategories)) {
            if (in_array($material['category'], $priorityCategories, true)) {
                $score += 30;
            }
        }

        // Tambahkan skor berdasarkan stok
        $stock = (int) $material['stock'];
        if ($stock >= 100) {
            $score += 10;
        } elseif ($stock >= 50) {
            $score += 5;
        }

        // Tambahkan skor jika harga terjangkau
        $price = (int) $material['price'];
        if ($price <= 15000) {
            $score += 10;
        }

        // Batasi rentang skor 0-100
        return min(max($score, 0), 100);
    }

    /**
     * Menghitung skor efisiensi alokasi anggaran.
     * 
     * @param array $selectedItems Item yang direkomendasikan.
     * @param int $budget Anggaran awal.
     * @param int $subtotal Total belanja sebelum biaya tambahan.
     * @param float $feeRate Persentase biaya tambahan.
     * @return int Skor efisiensi (0-100).
     */
    private static function calculateEfficiencyScore(
        array $selectedItems,
        int $budget,
        int $subtotal,
        float $feeRate
    ): int {
        if (empty($selectedItems) || $budget <= 0) {
            return 0;
        }

        // Bobot 40% untuk pemanfaatan anggaran
        $effectiveBudget = $budget / (1 + $feeRate);
        $utilization = ($subtotal / $effectiveBudget) * 100;
        $utilizationScore = min($utilization, 100) * 0.40;

        // Bobot 30% untuk variasi jenis item
        $itemCount = count($selectedItems);
        $diversityScore = min($itemCount * 20, 100) * 0.30;

        // Bobot 30% untuk rata-rata kegunaan item
        $totalUtility = array_sum(array_column($selectedItems, 'utility_score'));
        $avgUtility = $totalUtility / $itemCount;
        $utilityScorePart = $avgUtility * 0.30;

        $finalScore = (int) round(
            $utilizationScore + $diversityScore + $utilityScorePart
        );

        return min(max($finalScore, 0), 100);
    }
}
