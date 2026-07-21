<?php
$stats = DashboardController::supplierStats($userId);
$data = $stats['data'];
$pendingOrders = Order::getPending($userId);
?>
<div class="page-header">
    <div><h1 class="page-title">Ringkasan operasional</h1><p class="page-description">Pantau aktivitas pesanan dan ketersediaan produk hari ini.</p></div>
    <a href="index.php?p=supplier&page=manajemen" class="bg-primary text-white px-4 py-2 text-xs font-semibold"><i class="ph ph-plus mr-1"></i> Tambah produk</a>
</div>

<div class="metric-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Produk aktif</span><span class="metric-icon"><i class="ph ph-package"></i></span></div><div class="metric-value"><?= $data['total_items'] ?></div><div class="metric-note">Varian yang dikelola</div></div>
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Perlu ditinjau</span><span class="metric-icon"><i class="ph ph-file-text"></i></span></div><div class="metric-value"><?= $data['pending_orders'] ?></div><div class="metric-note">Pesanan baru dari UMKM</div></div>
    <div class="metric-card"><div class="metric-card-top"><span class="metric-label">Pembayaran terverifikasi</span><span class="metric-icon"><i class="ph ph-check-circle"></i></span></div><div class="metric-value text-xl">Rp <?= number_format($data['total_revenue'], 0, ',', '.') ?></div><div class="metric-note">Akumulasi order berstatus paid</div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="panel lg:col-span-2">
        <div class="panel-header"><h2 class="panel-title">Pesanan yang perlu ditinjau</h2><a href="index.php?p=supplier&page=pesanan" class="text-xs font-semibold text-primary">Lihat semua</a></div>
        <div class="panel-body p-0">
            <?php if (!$pendingOrders): ?>
                <div class="py-12 text-center"><i class="ph ph-check-circle text-2xl text-slate-400"></i><p class="text-sm font-medium text-slate-700 mt-2">Tidak ada pesanan baru</p><p class="text-xs text-slate-500 mt-1">Semua pesanan telah ditinjau.</p></div>
            <?php else: ?>
                <table class="w-full"><thead><tr><th class="text-left px-5">Nomor order</th><th class="text-left px-5">UMKM</th><th class="text-right px-5">Nilai</th><th class="text-right px-5">Aksi</th></tr></thead><tbody>
                <?php foreach (array_slice($pendingOrders,0,5) as $order): ?><tr><td class="px-5 font-mono text-xs font-semibold"><?= htmlspecialchars($order['order_code']) ?></td><td class="px-5"><?= htmlspecialchars($order['umkm_name']) ?></td><td class="px-5 text-right font-semibold">Rp <?= number_format($order['total'],0,',','.') ?></td><td class="px-5 text-right"><a class="text-primary font-semibold" href="index.php?p=supplier&page=pesanan">Tinjau</a></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Akses cepat</h2></div>
        <div class="panel-body space-y-2 quick-actions">
            <a href="index.php?p=supplier&page=pesanan" class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50"><i class="ph ph-file-text text-lg text-slate-500"></i><div><div class="text-xs font-semibold text-slate-800">Kelola pesanan</div><div class="text-[10px] text-slate-500 mt-0.5">Terima atau tolak order baru</div></div></a>
            <a href="index.php?p=supplier&page=manajemen" class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50"><i class="ph ph-package text-lg text-slate-500"></i><div><div class="text-xs font-semibold text-slate-800">Perbarui stok</div><div class="text-[10px] text-slate-500 mt-0.5">Kelola harga dan ketersediaan</div></div></a>
            <a href="index.php?p=supplier&page=laporan" class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50"><i class="ph ph-chart-line text-lg text-slate-500"></i><div><div class="text-xs font-semibold text-slate-800">Buka laporan</div><div class="text-[10px] text-slate-500 mt-0.5">Lihat transaksi yang terverifikasi</div></div></a>
        </div>
    </section>
</div>
