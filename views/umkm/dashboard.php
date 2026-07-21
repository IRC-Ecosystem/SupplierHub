<?php
$stats = DashboardController::umkmStats($userId);
$d = $stats['data'];
$cartCount = count($_SESSION['cart'] ?? []);
?>
<div class="mb-6"><h1 class="text-2xl font-bold text-slate-800">Dashboard UMKM</h1><p class="text-slate-500 text-sm mt-1">Ringkasan aktivitas pengadaan bahan baku Anda.</p></div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-emerald-100 text-primary flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-wallet"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Saldo SmartBank</p><h3 class="text-xl font-bold text-slate-800" id="umkm-balance">Memuat...</h3></div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-receipt"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Total Pengeluaran</p><h3 class="text-xl font-bold text-slate-800">Rp <?= number_format($d['total_spent'],0,',','.') ?></h3></div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-amber-100 text-secondary flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-shopping-bag"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Total Pesanan</p><h3 class="text-2xl font-bold text-slate-800"><?= $d['total_orders'] ?> <span class="text-sm font-normal text-slate-500">Kali</span></h3></div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex items-center">
        <div class="w-12 h-12 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-2xl mr-4"><i class="ph-fill ph-shopping-cart-simple"></i></div>
        <div><p class="text-sm text-slate-500 font-medium mb-1">Isi Keranjang Belanja</p><h3 class="text-2xl font-bold text-slate-800"><?= $cartCount ?> <span class="text-sm font-normal text-slate-500">Item</span></h3></div>
    </div>
</div>
<div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm mb-6">
    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center"><i class="ph ph-info text-primary mr-2"></i> Informasi Sistem Ekosistem B2B</h3>
    <ul class="space-y-3 text-sm text-slate-600">
        <li class="flex items-start"><i class="ph-fill ph-check-circle text-primary mt-0.5 mr-2"></i> <strong>Pembayaran Otomatis:</strong>&nbsp;Setiap transaksi akan langsung memotong saldo SmartBank Anda secara otomatis melalui API Gateway.</li>
        <li class="flex items-start"><i class="ph-fill ph-check-circle text-primary mt-0.5 mr-2"></i> <strong>Biaya Layanan (Fee):</strong>&nbsp;Terdapat fee layanan supplier sebesar <strong>3%</strong> dari total belanja bahan baku.</li>
        <li class="flex items-start"><i class="ph-fill ph-check-circle text-primary mt-0.5 mr-2"></i> <strong>Logistik:</strong>&nbsp;Setelah pembayaran sukses, LogistiKita akan secara otomatis dijadwalkan untuk penjemputan barang.</li>
    </ul>
</div>

<!-- Flash Sale Banner -->
<div class="bg-gradient-to-r from-red-500 via-orange-500 to-yellow-500 rounded-xl p-5 mb-6 shadow-lg relative overflow-hidden">
    <div class="absolute top-0 right-0 w-40 h-40 bg-white/10 rounded-full -mr-10 -mt-10"></div>
    <div class="absolute bottom-0 left-20 w-24 h-24 bg-white/10 rounded-full -mb-8"></div>
    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center">
                <i class="ph-fill ph-lightning text-3xl text-white"></i>
            </div>
            <div>
                <h3 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="ph-fill ph-fire text-yellow-200"></i> FLASH SALE Bahan Baku!
                </h3>
                <p class="text-white/80 text-sm">Diskon hingga <span class="font-bold text-yellow-200 text-base">25%</span> untuk pembelian hari ini</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex gap-2 text-center" id="countdown-timer">
                <div class="bg-white/20 backdrop-blur rounded-lg px-3 py-2 min-w-[52px]">
                    <div class="text-white font-bold text-xl leading-none" id="cd-hours">08</div>
                    <div class="text-white/70 text-[10px] mt-1">Jam</div>
                </div>
                <div class="text-white font-bold text-xl flex items-center pb-3">:</div>
                <div class="bg-white/20 backdrop-blur rounded-lg px-3 py-2 min-w-[52px]">
                    <div class="text-white font-bold text-xl leading-none" id="cd-mins">45</div>
                    <div class="text-white/70 text-[10px] mt-1">Menit</div>
                </div>
                <div class="text-white font-bold text-xl flex items-center pb-3">:</div>
                <div class="bg-white/20 backdrop-blur rounded-lg px-3 py-2 min-w-[52px]">
                    <div class="text-white font-bold text-xl leading-none" id="cd-secs">30</div>
                    <div class="text-white/70 text-[10px] mt-1">Detik</div>
                </div>
            </div>
            <a href="index.php?p=umkm&page=katalog" class="bg-white text-orange-600 font-bold text-sm px-5 py-2.5 rounded-lg hover:bg-orange-50 transition-all shadow-md whitespace-nowrap">
                Belanja Sekarang <i class="ph ph-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- Promo Bundling Section -->
<div class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
            <i class="ph-fill ph-gift text-orange-500 text-xl"></i> Paket Bundling Hemat
        </h2>
        <a href="index.php?p=umkm&page=katalog" class="text-sm text-primary font-semibold hover:underline flex items-center gap-1">
            Lihat Semua <i class="ph ph-caret-right"></i>
        </a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Bundle 1: Paket UMKM Starter -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden hover:shadow-lg hover:border-primary transition-all group">
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-4 relative border-b border-slate-100">
                <span class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow">Hemat 15%</span>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center">
                        <i class="ph-fill ph-package text-2xl text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-emerald-800 font-bold">Paket UMKM Starter</h3>
                        <p class="text-emerald-600/70 text-xs">Bahan pokok usaha kecil</p>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <ul class="space-y-2 text-sm text-slate-600 mb-4">
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-emerald-500"></i> Tepung Terigu 10 Kg</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-emerald-500"></i> Gula Pasir 5 Kg</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-emerald-500"></i> Minyak Goreng 5 Liter</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-emerald-500"></i> Telur Ayam 2 Tray</li>
                </ul>
                <div class="flex items-end justify-between pt-3 border-t border-slate-100">
                    <div>
                        <p class="text-xs text-slate-400 line-through">Rp 343.500</p>
                        <p class="text-xl font-bold text-emerald-600">Rp 291.975</p>
                    </div>
                    <button onclick="buyStarterBundle()" class="bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-600 border border-emerald-200 hover:border-emerald-600 text-xs font-bold px-4 py-2 rounded-lg transition-all">
                        <i class="ph ph-wallet mr-1"></i> Ambil Paket
                    </button>
                </div>
            </div>
        </div>

        <!-- Bundle 2: Paket Warung Makan -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden hover:shadow-lg hover:border-primary transition-all group">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 relative border-b border-slate-100">
                <span class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow">Hemat 20%</span>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                        <i class="ph-fill ph-cooking-pot text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-blue-800 font-bold">Paket Warung Makan</h3>
                        <p class="text-blue-600/70 text-xs">Lengkap untuk dapur warung</p>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <ul class="space-y-2 text-sm text-slate-600 mb-4">
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-blue-500"></i> Tepung Terigu 25 Kg</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-blue-500"></i> Minyak Goreng 10 Liter</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-blue-500"></i> Telur Ayam 5 Tray</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-blue-500"></i> Bawang Merah 3 Kg</li>
                </ul>
                <div class="flex items-end justify-between pt-3 border-t border-slate-100">
                    <div>
                        <p class="text-xs text-slate-400 line-through">Rp 675.000</p>
                        <p class="text-xl font-bold text-blue-600">Rp 540.000</p>
                    </div>
                    <button onclick="buyWarungBundle()" class="bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-600 border border-blue-200 hover:border-blue-600 text-xs font-bold px-4 py-2 rounded-lg transition-all">
                        <i class="ph ph-wallet mr-1"></i> Ambil Paket
                    </button>
                </div>
            </div>
        </div>

        <!-- Bundle 3: Paket Bakery Pro -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden hover:shadow-lg hover:border-primary transition-all group">
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 p-4 relative border-b border-slate-100">
                <span class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow">Best Seller</span>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center">
                        <i class="ph-fill ph-bread text-2xl text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-purple-800 font-bold">Paket Bakery Pro</h3>
                        <p class="text-purple-600/70 text-xs">Khusus usaha roti & kue</p>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <ul class="space-y-2 text-sm text-slate-600 mb-4">
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-purple-500"></i> Tepung Terigu 50 Kg</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-purple-500"></i> Gula Pasir 20 Kg</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-purple-500"></i> Telur Ayam 10 Tray</li>
                    <li class="flex items-center gap-2"><i class="ph-fill ph-check-circle text-purple-500"></i> Minyak Goreng 5 Liter</li>
                </ul>
                <div class="flex items-end justify-between pt-3 border-t border-slate-100">
                    <div>
                        <p class="text-xs text-slate-400 line-through">Rp 1.295.000</p>
                        <p class="text-xl font-bold text-purple-600">Rp 1.010.100</p>
                    </div>
                    <button onclick="buyBakeryBundle()" class="bg-purple-50 hover:bg-purple-600 hover:text-white text-purple-600 border border-purple-200 hover:border-purple-600 text-xs font-bold px-4 py-2 rounded-lg transition-all">
                        <i class="ph ph-wallet mr-1"></i> Ambil Paket
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Diskon Kategori Section -->
<div class="mb-6">
    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2 mb-4">
        <i class="ph-fill ph-tag text-red-500 text-xl"></i> Diskon Per Kategori
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4 text-center hover:shadow-md transition-all cursor-pointer group" onclick="buyBahanPokokPromo()">
            <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <i class="ph-fill ph-package text-2xl text-amber-600"></i>
            </div>
            <h4 class="font-bold text-slate-800 text-sm">Bahan Pokok</h4>
            <span class="inline-block mt-2 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-full">Diskon 10%</span>
            <p class="text-xs text-slate-400 mt-2">Min. pembelian 50 Kg</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4 text-center hover:shadow-md transition-all cursor-pointer group" onclick="buyBumbuPromo()">
            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <i class="ph-fill ph-plant text-2xl text-green-600"></i>
            </div>
            <h4 class="font-bold text-slate-800 text-sm">Bumbu & Rempah</h4>
            <span class="inline-block mt-2 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-full">Diskon 8%</span>
            <p class="text-xs text-slate-400 mt-2">Min. pembelian 10 Kg</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 border border-blue-200 rounded-xl p-4 text-center hover:shadow-md transition-all cursor-pointer group" onclick="buyBahanCairPromo()">
            <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <i class="ph-fill ph-drop text-2xl text-blue-600"></i>
            </div>
            <h4 class="font-bold text-slate-800 text-sm">Bahan Cair</h4>
            <span class="inline-block mt-2 bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-full">Diskon 12%</span>
            <p class="text-xs text-slate-400 mt-2">Min. pembelian 20 L</p>
        </div>
        <div class="bg-gradient-to-br from-pink-50 to-rose-50 border border-pink-200 rounded-xl p-4 text-center hover:shadow-md transition-all cursor-pointer group" onclick="buyMemberPromo()">
            <div class="w-14 h-14 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                <i class="ph-fill ph-star text-2xl text-pink-600"></i>
            </div>
            <h4 class="font-bold text-slate-800 text-sm">Member Eksklusif</h4>
            <span class="inline-block mt-2 bg-purple-600 text-white text-[10px] font-bold px-3 py-1 rounded-full">Extra 5%</span>
            <p class="text-xs text-slate-400 mt-2">Khusus pelanggan setia</p>
        </div>
    </div>
</div>

<!-- Produk Terlaris & Tips -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Produk Terlaris -->
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="ph-fill ph-trophy text-yellow-500"></i> Produk Terlaris Minggu Ini
        </h3>
        <div class="space-y-3">
            <div class="flex items-center gap-3 p-3 bg-yellow-50 border border-yellow-100 rounded-lg">
                <span class="w-7 h-7 bg-yellow-400 text-white rounded-full flex items-center justify-center text-xs font-bold shadow">1</span>
                <div class="flex-1">
                    <p class="font-semibold text-sm text-slate-800">Tepung Terigu Segitiga Biru</p>
                    <p class="text-xs text-slate-400">Terjual 1.250 Kg minggu ini</p>
                </div>
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Rp 12.000/Kg</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-lg">
                <span class="w-7 h-7 bg-slate-400 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                <div class="flex-1">
                    <p class="font-semibold text-sm text-slate-800">Minyak Goreng Sawit</p>
                    <p class="text-xs text-slate-400">Terjual 870 Liter minggu ini</p>
                </div>
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Rp 17.000/L</span>
            </div>
            <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-lg">
                <span class="w-7 h-7 bg-amber-600 text-white rounded-full flex items-center justify-center text-xs font-bold">3</span>
                <div class="flex-1">
                    <p class="font-semibold text-sm text-slate-800">Gula Pasir Kristal Putih</p>
                    <p class="text-xs text-slate-400">Terjual 650 Kg minggu ini</p>
                </div>
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Rp 16.500/Kg</span>
            </div>
        </div>
    </div>

    <!-- Tips & Keuntungan -->
    <div class="bg-white rounded-xl p-5 border border-slate-100 shadow-sm">
        <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="ph-fill ph-lightbulb text-amber-500"></i> Tips Belanja Hemat B2B
        </h3>
        <div class="space-y-3">
            <div class="flex items-start gap-3 p-3 bg-emerald-50 border border-emerald-100 rounded-lg">
                <div class="w-8 h-8 bg-emerald-500 text-white rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="ph-fill ph-stack text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm text-slate-800">Beli Bundling Lebih Untung</p>
                    <p class="text-xs text-slate-500 mt-0.5">Hemat hingga 22% dengan paket bundling dibanding beli satuan.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                <div class="w-8 h-8 bg-blue-500 text-white rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="ph-fill ph-calendar-check text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm text-slate-800">Belanja di Awal Bulan</p>
                    <p class="text-xs text-slate-500 mt-0.5">Stok lebih lengkap & harga cenderung lebih stabil di awal bulan.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 bg-purple-50 border border-purple-100 rounded-lg">
                <div class="w-8 h-8 bg-purple-500 text-white rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="ph-fill ph-chart-line-up text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm text-slate-800">Pantau Harga Rutin</p>
                    <p class="text-xs text-slate-500 mt-0.5">Cek katalog tiap minggu untuk info diskon & promo terbaru.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subscription Plans Section -->
<div class="mb-8">
    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2 mb-4">
        <i class="ph-fill ph-crown text-amber-500 text-xl animate-bounce"></i> Langganan Keanggotaan B2B
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- VIP Member Card -->
        <?php $isActiveVip = ($_SESSION['subscription'] ?? '') === 'vip'; ?>
        <div class="bg-white rounded-2xl border-2 <?= $isActiveVip ? 'border-yellow-400 ring-4 ring-yellow-100' : 'border-slate-100 hover:border-yellow-300' ?> p-6 shadow-sm hover:shadow-md transition-all relative flex flex-col justify-between">
            <?php if ($isActiveVip): ?>
                <span class="absolute top-4 right-4 bg-yellow-400 text-slate-900 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider shadow">Keanggotaan Aktif</span>
            <?php endif; ?>
            <div>
                <div class="flex items-center gap-3.5 mb-4">
                    <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center text-yellow-500 text-2xl border border-yellow-100">
                        <i class="ph-fill ph-crown"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-slate-800 text-base">VIP Member</h4>
                        <p class="text-xs text-slate-400">Upgrade prioritas bisnis UMKM</p>
                    </div>
                </div>
                <div class="mb-5">
                    <p class="text-slate-400 text-xs line-through font-medium">Rp 149.000</p>
                    <p class="text-2xl font-extrabold text-slate-800">Rp 99.000<span class="text-xs font-normal text-slate-400 ml-1">/ Bulan</span></p>
                </div>
                <ul class="space-y-2.5 text-xs text-slate-600 mb-6">
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-yellow-500"></i> Diskon Bahan Baku 5% (Seluruh Katalog)</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-yellow-500"></i> Prioritas Pengiriman oleh LogistiKita</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-yellow-500"></i> Akses Awal untuk Produk Flash Sale</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-yellow-500"></i> Lencana Crown VIP Emas di Profil</li>
                </ul>
            </div>
            <?php if ($isActiveVip): ?>
                <div class="flex flex-col gap-2 w-full">
                    <button disabled class="w-full py-3 rounded-xl bg-slate-100 text-slate-400 border border-slate-200 font-extrabold text-xs cursor-default">
                        Langganan VIP Aktif
                    </button>
                    <button onclick="cancelSubscription('vip')" class="w-full py-2.5 rounded-xl bg-red-50 hover:bg-red-600 hover:text-white text-red-600 border border-red-200 hover:border-red-600 font-bold text-xs transition-all flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="ph ph-x-circle text-base"></i> Batalkan Langganan
                    </button>
                </div>
            <?php else: ?>
                <button onclick="buySubscription('vip', 99000)" class="w-full py-3 rounded-xl border border-yellow-300 font-extrabold text-xs transition-all bg-yellow-50 hover:bg-yellow-500 hover:text-white text-yellow-600 shadow-sm hover:shadow">
                    Aktifkan VIP Sekarang
                </button>
            <?php endif; ?>
        </div>

        <!-- Gold Partner Card -->
        <?php $isActiveGold = ($_SESSION['subscription'] ?? '') === 'gold'; ?>
        <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-2xl border-2 <?= $isActiveGold ? 'border-amber-400 ring-4 ring-amber-500/20' : 'border-slate-800 hover:border-amber-400/70' ?> p-6 shadow-lg hover:shadow-xl transition-all relative flex flex-col justify-between text-white group">
            <?php if ($isActiveGold): ?>
                <span class="absolute top-4 right-4 bg-gradient-to-r from-amber-400 to-orange-500 text-slate-900 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider shadow">Keanggotaan Aktif</span>
            <?php endif; ?>
            <div>
                <div class="flex items-center gap-3.5 mb-4">
                    <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-400 text-2xl border border-amber-500/20">
                        <i class="ph-fill ph-star animate-pulse"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-white text-base flex items-center gap-1.5">Gold Partner <span class="bg-amber-500/20 text-amber-300 text-[8px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wider">Best Value</span></h4>
                        <p class="text-xs text-slate-400">Keuntungan kargo & diskon maksimal</p>
                    </div>
                </div>
                <div class="mb-5">
                    <p class="text-slate-400 text-xs line-through font-medium">Rp 299.000</p>
                    <p class="text-2xl font-extrabold text-amber-400">Rp 199.000<span class="text-xs font-normal text-slate-400 ml-1">/ Bulan</span></p>
                </div>
                <ul class="space-y-2.5 text-xs text-slate-300 mb-6">
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-amber-400"></i> Diskon Bahan Baku 10% (Seluruh Katalog)</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-amber-400"></i> B2B Loyalty: Pesan 5x, Kirim ke-6 GRATIS!</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-amber-400"></i> Lencana Bintang Emas Premium di Profil</li>
                    <li class="flex items-center gap-2 font-medium"><i class="ph-fill ph-check-circle text-amber-400"></i> Manajer Akuntan Finansial B2B Khusus</li>
                </ul>
            </div>
            <?php if ($isActiveGold): ?>
                <div class="flex flex-col gap-2 w-full">
                    <button disabled class="w-full py-3 rounded-xl bg-white/10 text-slate-400 border border-white/5 font-extrabold text-xs cursor-default">
                        Langganan Gold Aktif
                    </button>
                    <button onclick="cancelSubscription('gold')" class="w-full py-2.5 rounded-xl bg-red-500/10 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/20 hover:border-red-600 font-bold text-xs transition-all flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="ph ph-x-circle text-base"></i> Batalkan Langganan
                    </button>
                </div>
            <?php else: ?>
                <button onclick="buySubscription('gold', 199000)" class="w-full py-3 rounded-xl border border-amber-400/40 font-extrabold text-xs transition-all bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white shadow shadow-amber-500/20 hover:-translate-y-0.5">
                    Aktifkan Gold Sekarang
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Subscription Checkout Modal -->
<div id="subscription-checkout-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden transition-all duration-300 opacity-0">
    <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl border border-slate-100 overflow-hidden transform scale-95 transition-all duration-300 flex flex-col relative" id="subscription-checkout-card">
        
        <!-- Multi-stage Payment Loader -->
        <div id="sub-payment-loader" class="absolute inset-0 bg-white/95 backdrop-blur-md z-50 hidden flex-col items-center justify-center p-6 text-center transition-all duration-300">
            <div class="w-20 h-20 relative mb-6">
                <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                <div id="sub-payment-spinner" class="absolute inset-0 rounded-full border-4 border-primary border-t-transparent animate-spin"></div>
                <div id="sub-payment-success-icon" class="absolute inset-0 rounded-full bg-emerald-500 flex items-center justify-center hidden transform scale-0 transition-transform duration-500">
                    <i class="ph-bold ph-check text-white text-4xl"></i>
                </div>
            </div>
            <h3 id="sub-payment-status-title" class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight">Menghubungkan ke API Gateway...</h3>
            <p id="sub-payment-status-sub" class="text-xs font-medium text-slate-500">Mempersiapkan transaksi langganan aman B2B Anda</p>
        </div>

        <!-- Modal Header -->
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-lg"><i class="ph-fill ph-crown"></i></div>
                <h3 class="font-extrabold text-slate-800 text-sm">Pembayaran Langganan B2B</h3>
            </div>
            <button onclick="closeSubCheckout()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-700 flex items-center justify-center transition-all outline-none">
                <i class="ph-bold ph-x text-base"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 space-y-4">
            <div class="p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-100 flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600 text-xl shrink-0"><i class="ph-fill ph-shield-check"></i></div>
                <div>
                    <h4 class="text-xs font-bold text-slate-800" id="sub-package-display-name">VIP Member Subscription</h4>
                    <p class="text-[10px] text-slate-500">Aktivasi instan via SmartBank API</p>
                </div>
            </div>

            <!-- Benefits List inside Modal -->
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60">
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5"><i class="ph-fill ph-crown text-amber-500"></i> Keuntungan yang Didapatkan:</h4>
                <ul class="space-y-2 text-[11px] text-slate-600 font-medium" id="sub-modal-benefits">
                    <!-- Dynamic benefits checklist -->
                </ul>
            </div>

            <!-- Price Breakdown -->
            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 space-y-2 text-xs">
                <div class="flex justify-between items-center text-slate-500">
                    <span>Biaya Keanggotaan</span>
                    <span class="font-bold text-slate-800" id="sub-breakdown-price">Rp 0</span>
                </div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>Pajak Transaksi (0%)</span>
                    <span class="font-bold text-slate-800">Rp 0</span>
                </div>
                <div class="border-t border-slate-200 pt-2 flex justify-between items-center font-extrabold text-sm text-slate-900">
                    <span>Total Pembayaran</span>
                    <span class="text-primary text-base" id="sub-breakdown-total">Rp 0</span>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex gap-3">
            <button onclick="closeSubCheckout()" class="flex-1 py-2.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all">Batal</button>
            <button onclick="processSubPayment()" class="flex-1 py-2.5 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs shadow-md transition-all">Bayar Sekarang</button>
        </div>
    </div>
</div>

<script>
// Fetch balance from SmartBank
(async()=>{
    try {
        const url = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>/api/reports.php?action=umkm_stats';
        const res = await fetch(url).then(r => r.json());
        if (res.status === 'success') {
            const bal = res.data.smartbank_balance;
            document.getElementById('umkm-balance').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(bal);
        } else {
            document.getElementById('umkm-balance').innerText = 'Rp 0';
        }
    } catch(e) { document.getElementById('umkm-balance').innerText = 'Rp 0'; }
})();

// Flash Sale Countdown Timer
(function(){
    const now = new Date();
    const endOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
    function updateCountdown(){
        const diff = endOfDay - new Date();
        if(diff <= 0) return;
        const h = Math.floor(diff/3600000);
        const m = Math.floor((diff%3600000)/60000);
        const s = Math.floor((diff%60000)/1000);
        document.getElementById('cd-hours').textContent = String(h).padStart(2,'0');
        document.getElementById('cd-mins').textContent = String(m).padStart(2,'0');
        document.getElementById('cd-secs').textContent = String(s).padStart(2,'0');
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
})();

// Bundle redirect logic
async function addBundleToCart(bundleName, items, discount) {
    showToast('Menambahkan ' + bundleName + ' ke keranjang...', 'info');
    try {
        const BASE_API = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
        const r = await apiCall(BASE_API + '/api/orders.php?action=add_bundle', 'POST', {
            bundle_name: bundleName,
            items: items,
            discount: discount
        });
        if (r.status === 'success') {
            showToast(r.message);
            setTimeout(() => {
                window.location.href = BASE_API + '/index.php?p=umkm&page=keranjang';
            }, 1000);
        } else {
            showToast(r.message || 'Gagal menambahkan paket.', 'error');
        }
    } catch(e) {
        showToast('Koneksi gagal.', 'error');
    }
}

function buyStarterBundle() {
    const items = [
        { material_id: 1, qty: 10 },
        { material_id: 2, qty: 5 },
        { material_id: 3, qty: 5 },
        { material_id: 4, qty: 2 }
    ];
    addBundleToCart('Paket UMKM Starter', items, 51525);
}

function buyWarungBundle() {
    const items = [
        { material_id: 1, qty: 25 },
        { material_id: 3, qty: 10 },
        { material_id: 4, qty: 5 },
        { material_id: 5, qty: 3 }
    ];
    addBundleToCart('Paket Warung Makan', items, 175000);
}

function buyBakeryBundle() {
    const items = [
        { material_id: 1, qty: 50 },
        { material_id: 2, qty: 20 },
        { material_id: 4, qty: 10 },
        { material_id: 3, qty: 5 }
    ];
    addBundleToCart('Paket Bakery Pro', items, 284900);
}

function buyBahanPokokPromo() {
    const items = [{ material_id: 1, qty: 50 }];
    addBundleToCart('Promo Bahan Pokok (Diskon 10%)', items, 60000);
}

function buyBumbuPromo() {
    const items = [{ material_id: 5, qty: 10 }];
    addBundleToCart('Promo Bumbu & Rempah (Diskon 8%)', items, 28000);
}

function buyBahanCairPromo() {
    const items = [{ material_id: 3, qty: 20 }];
    addBundleToCart('Promo Bahan Cair (Diskon 12%)', items, 40800);
}

function buyMemberPromo() {
    const items = [
        { material_id: 1, qty: 10 },
        { material_id: 2, qty: 10 },
        { material_id: 3, qty: 10 }
    ];
    addBundleToCart('Promo Member Eksklusif (Diskon 15%)', items, 68250);
}

// Subscription Pay Logic
let subCheckoutState = { type: '', price: 0 };

function buySubscription(type, price) {
    subCheckoutState.type = type;
    subCheckoutState.price = price;

    const displayName = type === 'vip' ? 'VIP Member Subscription' : 'Gold Partner Subscription';
    document.getElementById('sub-package-display-name').innerText = displayName;
    document.getElementById('sub-breakdown-price').innerText = 'Rp ' + price.toLocaleString('id-ID');
    document.getElementById('sub-breakdown-total').innerText = 'Rp ' + price.toLocaleString('id-ID');

    // Populate benefits dynamically
    const benefitsList = document.getElementById('sub-modal-benefits');
    benefitsList.innerHTML = '';
    
    let benefits = [];
    if (type === 'vip') {
        benefits = [
            'Diskon Bahan Baku 5% (Seluruh Katalog)',
            'Prioritas Pengiriman oleh LogistiKita',
            'Akses Awal untuk Produk Flash Sale',
            'Lencana Crown VIP Emas di Profil'
        ];
    } else {
        benefits = [
            'Diskon Bahan Baku 10% (Seluruh Katalog)',
            'B2B Loyalty: Pesan 5x, Kirim ke-6 GRATIS!',
            'Lencana Bintang Emas Premium di Profil',
            'Manajer Akuntan Finansial B2B Khusus'
        ];
    }

    benefits.forEach(benefit => {
        const li = document.createElement('li');
        li.className = 'flex items-center gap-2';
        li.innerHTML = `<i class="ph-fill ph-check-circle text-emerald-500 text-sm"></i> <span>${benefit}</span>`;
        benefitsList.appendChild(li);
    });

    // Open Modal
    const modal = document.getElementById('subscription-checkout-modal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('subscription-checkout-card').classList.remove('scale-95');
    }, 10);
}

function closeSubCheckout() {
    const modal = document.getElementById('subscription-checkout-modal');
    modal.classList.add('opacity-0');
    document.getElementById('subscription-checkout-card').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('sub-payment-loader').classList.add('hidden');
        document.getElementById('sub-payment-success-icon').classList.add('hidden');
        document.getElementById('sub-payment-success-icon').classList.remove('scale-100');
        document.getElementById('sub-payment-spinner').classList.remove('hidden');
    }, 300);
}

async function processSubPayment() {
    const loader = document.getElementById('sub-payment-loader');
    const title = document.getElementById('sub-payment-status-title');
    const sub = document.getElementById('sub-payment-status-sub');
    const spinner = document.getElementById('sub-payment-spinner');
    const checkIcon = document.getElementById('sub-payment-success-icon');

    // Show Loader
    loader.classList.remove('hidden');
    loader.style.opacity = '1';

    // Step 1: Connecting
    title.innerText = 'Menghubungkan ke API Gateway...';
    sub.innerText = 'Membuka jalur transaksi terenkripsi B2BLink';

    await new Promise(r => setTimeout(r, 800));

    // Step 2: Auth SmartBank
    title.innerText = 'Memproses Otorisasi SmartBank...';
    sub.innerText = 'Memverifikasi ledger & mendebit saldo';

    await new Promise(r => setTimeout(r, 800));

    // Step 3: Activating
    title.innerText = 'Mengaktifkan Keanggotaan B2B...';
    sub.innerText = 'Mencatatkan lencana kepesertaan digital Anda';

    const BASE_API = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';

    try {
        const response = await apiCall(BASE_API + '/api/orders.php?action=subscribe', 'POST', {
            subscription_type: subCheckoutState.type,
            price: subCheckoutState.price
        });

        await new Promise(r => setTimeout(r, 800));

        if (response.status === 'success') {
            title.innerText = 'Aktivasi Keanggotaan Sukses!';
            sub.innerText = 'Selamat! Akun B2BLink Anda sekarang berstatus ' + subCheckoutState.type.toUpperCase();
 
            // Success animation
            spinner.classList.add('hidden');
            checkIcon.classList.remove('hidden');
            setTimeout(() => {
                checkIcon.classList.add('scale-100');
            }, 50);
 
            showToast(response.message);
 
            setTimeout(() => {
                location.reload();
            }, 1800);
        } else {
            loader.classList.add('hidden');
            showToast(response.message || 'Pembayaran ditolak', 'error');
        }
    } catch (e) {
        loader.classList.add('hidden');
        showToast('Koneksi ke gateway gagal.', 'error');
    }
}

async function cancelSubscription(type) {
    if (!confirm('Apakah Anda yakin ingin membatalkan langganan B2B ' + type.toUpperCase() + ' Anda? Keuntungan diskon dan prioritas pengiriman Anda akan langsung dinonaktifkan.')) {
        return;
    }
    
    showToast('Membatalkan langganan...', 'info');
    const BASE_API = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
    
    try {
        const response = await apiCall(BASE_API + '/api/orders.php?action=cancel_subscription', 'POST', {});
        
        if (response.status === 'success') {
            showToast(response.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(response.message || 'Gagal membatalkan langganan.', 'error');
        }
    } catch (e) {
        showToast('Koneksi gagal.', 'error');
    }
}
</script>
