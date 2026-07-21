<aside class="app-sidebar flex flex-col hidden md:flex">
    <div class="app-brand">
        <div class="app-brand-mark"><i class="ph ph-buildings"></i></div>
        <div>
            <div class="app-brand-name">SupplierHub</div>
            <div class="app-brand-subtitle">Procurement workspace</div>
        </div>
    </div>
    <nav class="app-nav flex-1 overflow-y-auto">
        <div class="app-nav-label">Pengadaan</div>
        <a href="index.php?p=umkm&page=dashboard" class="app-nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="ph ph-house"></i><span>Ringkasan</span>
        </a>
        <a href="index.php?p=umkm&page=katalog" class="app-nav-link <?= ($currentPage ?? '') === 'katalog' ? 'active' : '' ?>">
            <i class="ph ph-magnifying-glass"></i><span>Katalog supplier</span>
        </a>
        <a href="index.php?p=umkm&page=keranjang" class="app-nav-link <?= ($currentPage ?? '') === 'keranjang' ? 'active' : '' ?>">
            <i class="ph ph-shopping-cart-simple"></i><span>Keranjang</span>
            <?php
            $sidebarCartCount = 0;
            foreach (($_SESSION['cart'] ?? []) as $sidebarCartItem) $sidebarCartCount += (int)$sidebarCartItem['qty'];
            if ($sidebarCartCount > 0): ?><span class="app-nav-badge"><?= $sidebarCartCount ?></span><?php endif; ?>
        </a>
        <a href="index.php?p=umkm&page=riwayat" class="app-nav-link <?= ($currentPage ?? '') === 'riwayat' ? 'active' : '' ?>">
            <i class="ph ph-receipt"></i><span>Pesanan saya</span>
        </a>
        <div class="app-nav-label mt-5">Akun</div>
        <a href="index.php?p=umkm&page=keuangan" class="app-nav-link <?= ($currentPage ?? '') === 'keuangan' ? 'active' : '' ?>">
            <i class="ph ph-wallet"></i><span>Keuangan</span>
        </a>
    </nav>
    <div class="app-user">
        <div class="app-user-avatar"><?= strtoupper(substr($userName ?? 'UM', 0, 2)) ?></div>
        <div><div class="app-user-name"><?= htmlspecialchars($userName ?? 'UMKM') ?></div><div class="app-user-role">Akun UMKM</div></div>
    </div>
</aside>
<nav class="mobile-nav md:hidden">
    <a href="index.php?p=umkm&page=dashboard" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="ph ph-house"></i><span>Ringkasan</span></a>
    <a href="index.php?p=umkm&page=katalog" class="<?= ($currentPage ?? '') === 'katalog' ? 'active' : '' ?>"><i class="ph ph-magnifying-glass"></i><span>Katalog</span></a>
    <a href="index.php?p=umkm&page=keranjang" class="<?= ($currentPage ?? '') === 'keranjang' ? 'active' : '' ?>"><i class="ph ph-shopping-cart-simple"></i><span>Keranjang</span></a>
    <a href="index.php?p=umkm&page=riwayat" class="<?= ($currentPage ?? '') === 'riwayat' ? 'active' : '' ?>"><i class="ph ph-receipt"></i><span>Pesanan</span></a>
    <a href="index.php?p=umkm&page=keuangan" class="<?= ($currentPage ?? '') === 'keuangan' ? 'active' : '' ?>"><i class="ph ph-wallet"></i><span>Keuangan</span></a>
</nav>
