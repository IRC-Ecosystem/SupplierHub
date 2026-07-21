<?php
$stats = DashboardController::umkmStats($userId);
$d = $stats['data'];
$orders = Order::getByUmkm($userId);
$cartQuantity = 0;
foreach (($_SESSION['cart'] ?? []) as $item) {
    $cartQuantity += (int) $item['qty'];
}
$pendingPayment = count(array_filter($orders, fn($o) => in_array($o['payment_status'], ['unpaid','pending'], true)));
?>
<div class="page-header">
    <div><h1 class="page-title">Ringkasan pengadaan</h1><p class="page-description">Pantau pesanan, pembayaran, dan kebutuhan bahan baku usaha Anda.</p></div>
    <a href="index.php?p=umkm&page=katalog" class="bg-primary text-white px-4 py-2 text-xs font-semibold"><i class="ph ph-plus mr-1"></i> Buat pesanan</a>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Saldo SmartBank</span><span class="metric-icon"><i class="ph ph-bank"></i></span></div><div class="metric-value text-xl" id="umkm-balance">Memuat...</div><div class="metric-note">Saldo sumber pembayaran</div></div>
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Total pengadaan</span><span class="metric-icon"><i class="ph ph-chart-line-up"></i></span></div><div class="metric-value text-xl">Rp <?= number_format($d['total_spent'],0,',','.') ?></div><div class="metric-note">Order dengan pembayaran terverifikasi</div></div>
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Menunggu pembayaran</span><span class="metric-icon"><i class="ph ph-clock"></i></span></div><div class="metric-value"><?= $pendingPayment ?></div><div class="metric-note">Order belum selesai dibayar</div></div>
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Keranjang</span><span class="metric-icon"><i class="ph ph-shopping-cart-simple"></i></span></div><div class="metric-value"><?= $cartQuantity ?></div><div class="metric-note">Unit yang akan dipesan</div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="panel lg:col-span-2">
        <div class="panel-header"><h2 class="panel-title">Pesanan terbaru</h2><a href="index.php?p=umkm&page=riwayat" class="text-xs font-semibold text-primary">Lihat semua</a></div>
        <div class="panel-body p-0">
            <?php if (!$orders): ?>
                <div class="py-12 text-center"><i class="ph ph-receipt text-2xl text-slate-400"></i><p class="text-sm font-medium text-slate-700 mt-2">Belum ada pesanan</p><p class="text-xs text-slate-500 mt-1">Mulai dari katalog supplier untuk membuat pengadaan.</p></div>
            <?php else: ?>
                <table class="w-full"><thead><tr><th class="text-left px-5">Nomor order</th><th class="text-left px-5">Supplier</th><th class="text-left px-5">Status</th><th class="text-right px-5">Total</th></tr></thead><tbody>
                <?php foreach (array_slice($orders,0,5) as $order):
                    $paid = $order['payment_status'] === 'paid';
                    $pending = $order['payment_status'] === 'pending';
                    $submitted = $order['status'] === 'submitted';
                    $statusClass = 'status-pending';
                    $statusText = 'Belum dibayar';
                    if ($paid) {
                        $statusClass = 'status-success';
                        $statusText = 'Dibayar';
                    } elseif ($pending) {
                        $statusClass = 'status-info';
                        $statusText = 'Verifikasi bank';
                    } elseif ($submitted) {
                        $statusText = 'Menunggu supplier';
                    }
                ?><tr><td class="px-5 font-mono text-xs font-semibold"><?= htmlspecialchars($order['order_code']) ?></td><td class="px-5"><?= htmlspecialchars($order['supplier_name']) ?></td><td class="px-5"><span class="status-chip <?= $statusClass ?>"><?= $statusText ?></span></td><td class="px-5 text-right font-semibold">Rp <?= number_format($order['total'],0,',','.') ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Alur pengadaan</h2></div>
        <div class="panel-body space-y-4 procurement-steps">
            <div class="flex gap-3"><span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold grid place-items-center shrink-0">1</span><div><div class="text-xs font-semibold">Buat order</div><p class="text-[10px] text-slate-500 mt-1">Pilih bahan dari katalog supplier.</p></div></div>
            <div class="flex gap-3"><span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold grid place-items-center shrink-0">2</span><div><div class="text-xs font-semibold">Persetujuan supplier</div><p class="text-[10px] text-slate-500 mt-1">Supplier meninjau ketersediaan pesanan.</p></div></div>
            <div class="flex gap-3"><span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold grid place-items-center shrink-0">3</span><div><div class="text-xs font-semibold">Verifikasi pembayaran</div><p class="text-[10px] text-slate-500 mt-1">Payment request menunggu SmartBank.</p></div></div>
        </div>
    </section>
</div>

<script>
(async()=>{try{const r=await fetch('<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>/api/reports.php?action=umkm_stats').then(x=>x.json());document.getElementById('umkm-balance').innerText='Rp '+new Intl.NumberFormat('id-ID').format(r.data?.smartbank_balance||0);}catch(e){document.getElementById('umkm-balance').innerText='Tidak tersedia';}})();
</script>
