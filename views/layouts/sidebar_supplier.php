<aside class="app-sidebar flex flex-col hidden md:flex">
    <div class="app-brand">
        <div class="app-brand-mark"><i class="ph ph-buildings"></i></div>
        <div>
            <div class="app-brand-name">SupplierHub</div>
            <div class="app-brand-subtitle">Supplier workspace</div>
        </div>
    </div>
    <nav class="app-nav flex-1 overflow-y-auto">
        <div class="app-nav-label">Workspace</div>
        <a href="index.php?p=supplier&page=dashboard" class="app-nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="ph ph-house"></i><span>Ringkasan</span>
        </a>
        <a href="index.php?p=supplier&page=pesanan" class="app-nav-link <?= ($currentPage ?? '') === 'pesanan' ? 'active' : '' ?>">
            <i class="ph ph-file-text"></i><span>Pesanan</span>
            <?php if (($pendingCount ?? 0) > 0): ?><span class="app-nav-badge"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a href="index.php?p=supplier&page=manajemen" class="app-nav-link <?= ($currentPage ?? '') === 'manajemen' ? 'active' : '' ?>">
            <i class="ph ph-package"></i><span>Produk &amp; stok</span>
        </a>
        <a href="index.php?p=supplier&page=profil" class="app-nav-link <?= ($currentPage ?? '') === 'profil' ? 'active' : '' ?>"><i class="ph ph-identification-card"></i><span>Profil & performa</span></a>
        <div class="app-nav-label mt-5">Pelaporan</div>
        <a href="index.php?p=supplier&page=laporan" class="app-nav-link <?= ($currentPage ?? '') === 'laporan' ? 'active' : '' ?>">
            <i class="ph ph-chart-line"></i><span>Laporan penjualan</span>
        </a>
        <a href="index.php?p=supplier&page=keuangan" class="app-nav-link <?= ($currentPage ?? '') === 'keuangan' ? 'active' : '' ?>">
            <i class="ph ph-wallet"></i><span>Keuangan</span>
        </a>
    </nav>
    <div class="app-user">
        <div class="app-user-avatar"><?= strtoupper(substr($userName ?? 'SP', 0, 2)) ?></div>
        <div><div class="app-user-name"><?= htmlspecialchars($userName ?? 'Supplier') ?></div><div class="app-user-role">Akun supplier</div></div>
    </div>
</aside>
<nav class="mobile-nav md:hidden">
    <a href="index.php?p=supplier&page=dashboard" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="ph ph-house"></i><span>Ringkasan</span></a>
    <a href="index.php?p=supplier&page=pesanan" class="<?= ($currentPage ?? '') === 'pesanan' ? 'active' : '' ?>"><i class="ph ph-file-text"></i><span>Pesanan</span></a>
    <a href="index.php?p=supplier&page=manajemen" class="<?= ($currentPage ?? '') === 'manajemen' ? 'active' : '' ?>"><i class="ph ph-package"></i><span>Produk</span></a>
    <a href="index.php?p=supplier&page=laporan" class="<?= ($currentPage ?? '') === 'laporan' ? 'active' : '' ?>"><i class="ph ph-chart-line"></i><span>Laporan</span></a>
    <a href="index.php?p=supplier&page=keuangan" class="<?= ($currentPage ?? '') === 'keuangan' ? 'active' : '' ?>"><i class="ph ph-wallet"></i><span>Keuangan</span></a>
</nav>
