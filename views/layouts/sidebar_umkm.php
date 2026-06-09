<!-- Sidebar UMKM -->
<aside class="w-64 bg-slate-900 text-white flex flex-col hidden md:flex transition-all duration-300">
    <div class="h-16 flex items-center px-6 border-b border-slate-700">
        <i class="ph-fill ph-storefront text-primary text-2xl mr-2"></i>
        <span class="text-xl font-bold tracking-wide">Portal<span class="text-primary">UMKM</span></span>
    </div>

    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <a href="index.php?p=umkm&page=dashboard" id="nav-dashboard"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'dashboard' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-squares-four text-xl mr-3"></i> Dashboard
        </a>
        <a href="index.php?p=umkm&page=katalog" id="nav-katalog"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'katalog' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-shopping-bag text-xl mr-3"></i> Katalog Supplier
        </a>
        <a href="index.php?p=umkm&page=keranjang" id="nav-keranjang"
            class="nav-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'keranjang' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <div class="flex items-center">
                <i class="ph ph-shopping-cart text-xl mr-3"></i> Keranjang
            </div>
            <?php 
            $cartCount = 0;
            if (isset($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $item) {
                    $cartCount += $item['qty'];
                }
            }
            if ($cartCount > 0): ?>
            <span class="bg-secondary text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $cartCount ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php?p=umkm&page=riwayat" id="nav-riwayat"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'riwayat' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-clock-counter-clockwise text-xl mr-3"></i> Riwayat Pesanan
        </a>
        <a href="index.php?p=umkm&page=keuangan" id="nav-keuangan"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'keuangan' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-wallet text-xl mr-3"></i> Keuangan & Neraca
        </a>
    </nav>

    <div class="p-4 border-t border-slate-700">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-sm font-bold">
                <?= strtoupper(substr($userName ?? 'WB', 0, 2)) ?>
            </div>
            <div class="ml-3">
                <?php
                $sub = $_SESSION['subscription'] ?? '';
                $badge = '';
                if ($sub === 'vip') {
                    $badge = ' <i class="ph-fill ph-crown text-yellow-400" title="VIP Member"></i>';
                } elseif ($sub === 'gold') {
                    $badge = ' <i class="ph-fill ph-star text-amber-400" title="Gold Partner"></i>';
                }
                ?>
                <p class="text-sm font-medium flex items-center gap-1.5"><?= htmlspecialchars($userName ?? 'Warung Bu Ani') ?><?= $badge ?></p>
                <p class="text-xs text-slate-400"><?= $sub === 'vip' ? 'VIP Member' : ($sub === 'gold' ? 'Gold Partner' : 'Node POS') ?></p>
            </div>
        </div>
    </div>
</aside>
