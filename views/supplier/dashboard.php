<?php
// Supplier Dashboard View
$stats = DashboardController::supplierStats($userId);
$data = $stats['data'];
$pendingOrders = Order::getPending($userId);
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Dashboard Admin Supplier</h1>
    <p class="text-slate-500 text-sm mt-1">Pemantauan logistik bahan baku dan status request pembayaran (B2B).</p>
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-circles-four"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Total Varian Produk</p><h3 class="text-2xl font-bold text-slate-800"><?= $data['total_items'] ?> <span class="text-sm font-normal text-slate-500">Item</span></h3></div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-tray-arrow-down"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Pesanan Menunggu Review</p><h3 class="text-2xl font-bold text-slate-800"><?= $data['pending_orders'] ?> <span class="text-sm font-normal text-slate-500">Order</span></h3></div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-money"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Tagihan Terbayar</p><h3 class="text-xl font-bold text-slate-800">Rp <?= number_format($data['total_revenue'], 0, ',', '.') ?></h3></div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm flex flex-col">
        <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center"><i class="ph-fill ph-lightning text-amber-500 mr-2"></i> Aksi Cepat</h3>
        <div class="grid grid-cols-2 gap-4 flex-1">
            <a href="index.php?p=supplier&page=pesanan" class="p-4 bg-blue-50 hover:bg-blue-100 rounded-xl border border-blue-100 text-left transition-colors group flex flex-col justify-center">
                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-primary shadow-sm mb-3 group-hover:scale-110 transition-transform"><i class="ph ph-tray-arrow-down text-xl"></i></div>
                <h4 class="font-bold text-slate-800 text-sm">Review Pesanan</h4><p class="text-xs text-slate-500 mt-1">Cek & proses order masuk dari Klien UMKM</p>
            </a>
            <a href="index.php?p=supplier&page=manajemen" class="p-4 bg-emerald-50 hover:bg-emerald-100 rounded-xl border border-emerald-100 text-left transition-colors group flex flex-col justify-center">
                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-emerald-600 shadow-sm mb-3 group-hover:scale-110 transition-transform"><i class="ph ph-package text-xl"></i></div>
                <h4 class="font-bold text-slate-800 text-sm">Kelola Stok</h4><p class="text-xs text-slate-500 mt-1">Tambah/edit harga & ketersediaan fisik</p>
            </a>
        </div>
    </div>
    <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-lg font-bold text-slate-800 flex items-center"><i class="ph-fill ph-activity text-primary mr-2"></i> Koneksi Ekosistem</h3>
            <span class="px-2.5 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded-full flex items-center uppercase tracking-wider"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span> Terhubung</span>
        </div>
        <div class="space-y-4">
            <div class="flex items-start p-3 rounded-lg hover:bg-slate-50 transition-colors"><div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 mr-4"><i class="ph-fill ph-arrows-left-right text-xl"></i></div><div><h4 class="text-sm font-bold text-slate-800">API Gateway</h4><p class="text-xs text-slate-500 mt-0.5">Endpoint aktif. Routing berjalan normal.</p></div></div>
            <div class="flex items-start p-3 rounded-lg hover:bg-slate-50 transition-colors"><div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 mr-4"><i class="ph-fill ph-bank text-xl"></i></div><div><h4 class="text-sm font-bold text-slate-800">SmartBank (Core)</h4><p class="text-xs text-slate-500 mt-0.5">Otorisasi pembayaran siap. Sinkronisasi ledger OK.</p></div></div>
        </div>
    </div>
</div>
