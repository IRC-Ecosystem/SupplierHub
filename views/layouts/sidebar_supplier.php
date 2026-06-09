<!-- Sidebar Supplier -->
<aside class="w-64 bg-slate-900 text-white flex flex-col hidden md:flex transition-all duration-300">
    <div class="h-16 flex items-center px-6 border-b border-slate-700">
        <i class="ph-fill ph-package text-secondary text-2xl mr-2"></i>
        <span class="text-xl font-bold tracking-wide">Supplier<span class="text-blue-400">Hub</span></span>
    </div>

    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <a href="index.php?p=supplier&page=dashboard" id="nav-dashboard"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'dashboard' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-squares-four text-xl mr-3"></i> Dashboard Admin
        </a>
        <a href="index.php?p=supplier&page=manajemen" id="nav-manajemen"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'manajemen' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-stack text-xl mr-3"></i> Manajemen Stok
        </a>
        <a href="index.php?p=supplier&page=pesanan" id="nav-pesanan"
            class="nav-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'pesanan' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <div class="flex items-center">
                <i class="ph ph-tray-arrow-down text-xl mr-3"></i> Pesanan Masuk
            </div>
            <?php if (($pendingCount ?? 0) > 0): ?>
            <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php?p=supplier&page=laporan" id="nav-laporan"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'laporan' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-chart-line-up text-xl mr-3"></i> Laporan Tagihan
        </a>
        <a href="index.php?p=supplier&page=keuangan" id="nav-keuangan"
            class="nav-btn w-full flex items-center px-3 py-2.5 rounded-lg <?= ($currentPage ?? '') === 'keuangan' ? 'bg-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?> font-medium transition-colors">
            <i class="ph ph-wallet text-xl mr-3"></i> Keuangan & Neraca
        </a>
    </nav>

</aside>
