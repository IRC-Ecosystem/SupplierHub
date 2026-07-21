<?php
/**
 * SupplierHub - Main Router
 * Routes requests to appropriate views based on ?p= and ?page= parameters
 */

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/models/Order.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

$p = $_GET['p'] ?? '';

// ============================================
// PUBLIC ROUTES
// ============================================

// Landing page / root
if (empty($p)) {
    // If logged in, redirect to portal
    if (isset($_SESSION['user_id'])) {
        header('Location: index.php?p=' . $_SESSION['role']);
        exit;
    }
    // Show landing page
    header('Location: landingpage.html');
    exit;
}

// Login page
if ($p === 'login') {
    if (isset($_SESSION['user_id'])) {
        header('Location: index.php?p=' . $_SESSION['role']);
        exit;
    }
    require __DIR__ . '/views/auth/login.php';
    exit;
}

// ============================================
// PROTECTED ROUTES - Require Authentication
// ============================================

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?p=login');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];
$currentPage = $_GET['page'] ?? 'dashboard';

// ============================================
// CART ACTIONS (UMKM)
// ============================================
if ($p === 'umkm' && isset($_GET['cart_action'])) {
    http_response_code(405);
    header('Allow: POST');
    echo 'Perubahan keranjang melalui GET dinonaktifkan.';
    exit;
}

// ============================================
// SUPPLIER PORTAL
// ============================================
if ($p === 'supplier') {
    if ($userRole !== 'supplier') {
        header('Location: index.php?p=' . $userRole);
        exit;
    }

    // Get pending count for badge
    $pendingCount = count(Order::getPending($userId));
    $pageTitle = 'Admin Gudang - SupplierHub B2B';

    require __DIR__ . '/views/layouts/header.php';
    require __DIR__ . '/views/layouts/sidebar_supplier.php';
    ?>
    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-hidden relative">
        <!-- Topbar -->
        <header class="app-topbar border-b flex items-center justify-between z-10">
            <div><div class="app-topbar-title">Portal Supplier</div><div class="app-topbar-meta">Kelola produk, pesanan, dan pembayaran</div></div>
            <div class="app-topbar-actions">
                <button class="app-icon-button" aria-label="Notifikasi"><i class="ph ph-bell"></i></button>
                <a href="logout.php" class="app-logout"><i class="ph ph-sign-out"></i> Keluar</a>
            </div>
        </header>
        <!-- Content Area -->
        <div class="app-content flex-1 overflow-y-auto">
            <?php
            switch ($currentPage) {
                case 'manajemen': require __DIR__ . '/views/supplier/manajemen.php'; break;
                case 'pesanan':   require __DIR__ . '/views/supplier/pesanan.php'; break;
                case 'laporan':   require __DIR__ . '/views/supplier/laporan.php'; break;
                case 'keuangan':  require __DIR__ . '/views/supplier/keuangan.php'; break;
                default:          require __DIR__ . '/views/supplier/dashboard.php'; break;
            }
            ?>
        </div>
    </main>
    <?php
    require __DIR__ . '/views/layouts/footer.php';
    exit;
}

// ============================================
// UMKM PORTAL
// ============================================
if ($p === 'umkm') {
    if ($userRole !== 'umkm') {
        header('Location: index.php?p=' . $userRole);
        exit;
    }

    $pageTitle = 'Portal Pengadaan B2B - UMKM';

    require __DIR__ . '/views/layouts/header.php';
    require __DIR__ . '/views/layouts/sidebar_umkm.php';
    ?>
    <main class="flex-1 flex flex-col overflow-hidden relative">
        <header class="app-topbar border-b flex items-center justify-between z-10">
            <div><div class="app-topbar-title">Portal Pengadaan</div><div class="app-topbar-meta">Pengadaan bahan baku untuk <?= htmlspecialchars($userName) ?></div></div>
            <div class="app-topbar-actions">
                <button class="app-icon-button" aria-label="Notifikasi"><i class="ph ph-bell"></i></button>
                <a href="logout.php" class="app-logout"><i class="ph ph-sign-out"></i> Keluar</a>
            </div>
        </header>
        <div class="app-content flex-1 overflow-y-auto">
            <?php
            switch ($currentPage) {
                case 'katalog':   require __DIR__ . '/views/umkm/katalog.php'; break;
                case 'keranjang': require __DIR__ . '/views/umkm/keranjang.php'; break;
                case 'riwayat':   require __DIR__ . '/views/umkm/riwayat.php'; break;
                case 'keuangan':  require __DIR__ . '/views/umkm/keuangan.php'; break;
                default:          require __DIR__ . '/views/umkm/dashboard.php'; break;
            }
            ?>
        </div>
    </main>
    <?php
    require __DIR__ . '/views/layouts/footer.php';
    exit;
}

// Fallback
header('Location: index.php?p=login');
