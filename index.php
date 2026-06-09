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
    $action = $_GET['cart_action'];
    $idx = (int)($_GET['idx'] ?? 0);
    
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    
    if ($action === 'increase' && isset($_SESSION['cart'][$idx])) {
        $_SESSION['cart'][$idx]['qty']++;
        unset($_SESSION['bundle_discount']);
        unset($_SESSION['bundle_name']);
    } elseif ($action === 'decrease' && isset($_SESSION['cart'][$idx])) {
        $_SESSION['cart'][$idx]['qty']--;
        if ($_SESSION['cart'][$idx]['qty'] <= 0) {
            array_splice($_SESSION['cart'], $idx, 1);
        }
        unset($_SESSION['bundle_discount']);
        unset($_SESSION['bundle_name']);
    } elseif ($action === 'update' && isset($_SESSION['cart'][$idx])) {
        $qty = (int)($_GET['qty'] ?? 1);
        if ($qty <= 0) {
            array_splice($_SESSION['cart'], $idx, 1);
        } else {
            $_SESSION['cart'][$idx]['qty'] = $qty;
        }
        unset($_SESSION['bundle_discount']);
        unset($_SESSION['bundle_name']);
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        unset($_SESSION['bundle_discount']);
        unset($_SESSION['bundle_name']);
    }
    
    header('Location: index.php?p=umkm&page=keranjang');
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
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-10">
            <div class="hidden md:flex items-center text-sm">
                <span class="bg-slate-100 text-slate-600 py-1 px-3 rounded-full flex items-center border border-slate-200">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                    API Gateway Connected
                </span>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Auto Refresh Status -->
                <div class="flex items-center text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold transition-all hover:bg-slate-100">
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2 animate-ping" id="refresh-dot"></span>
                    <i class="ph-bold ph-arrows-clockwise mr-1.5 text-slate-400 animate-spin" id="refresh-icon"></i>
                    <span class="mr-1 hidden sm:inline">Auto Refresh:</span>
                    <span id="refresh-timer" class="text-[10px] text-slate-600 font-mono">1:00</span>
                </div>
                <button class="relative text-slate-400 hover:text-slate-600"><i class="ph ph-bell text-xl"></i>
                    <?php if ($pendingCount > 0): ?><span class="absolute top-0 right-0 -mt-1 -mr-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span></span><?php endif; ?>
                </button>
                <a href="logout.php" class="text-red-500 hover:text-red-700 flex items-center text-sm font-medium transition-colors bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg"><i class="ph ph-sign-out text-lg mr-1.5"></i> Keluar</a>
            </div>
        </header>
        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50">
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
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 z-10">
            <div class="hidden md:flex items-center text-sm text-slate-500">
                <i class="ph ph-shield-check text-primary text-lg mr-2"></i>
                Sistem Terkoneksi: <span class="text-slate-700 font-semibold ml-1">API Gateway & SmartBank</span>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Auto Refresh Status -->
                <div class="flex items-center text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold transition-all hover:bg-slate-100">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2 animate-ping" id="refresh-dot"></span>
                    <i class="ph-bold ph-arrows-clockwise mr-1.5 text-slate-400 animate-spin" id="refresh-icon"></i>
                    <span class="mr-1 hidden sm:inline">Auto Refresh:</span>
                    <span id="refresh-timer" class="text-[10px] text-slate-600 font-mono">1:00</span>
                </div>
                <button class="relative text-slate-400 hover:text-slate-600"><i class="ph ph-bell text-xl"></i></button>
                <a href="logout.php" class="text-red-500 hover:text-red-700 flex items-center text-sm font-medium transition-colors bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg"><i class="ph ph-sign-out text-lg mr-1.5"></i> Keluar</a>
            </div>
        </header>
        <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50">
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
