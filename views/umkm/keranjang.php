<?php
$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
$cartItems = [];
foreach ($cart as $item) {
    $mat = Material::findById($item['material_id']);
    if ($mat) {
        $itemTotal = $mat['price'] * $item['qty'];
        $subtotal += $itemTotal;
        $cartItems[] = array_merge($mat, ['cart_qty' => $item['qty'], 'item_total' => $itemTotal]);
    }
}
$fee = (int) round($subtotal * FEE_SUPPLIER);
$bundleDiscount = $_SESSION['bundle_discount'] ?? 0;
$cartFingerprint = hash('sha256', json_encode($_SESSION['cart'] ?? []));
if (empty($_SESSION['checkout_idempotency_key']) || ($_SESSION['checkout_cart_fingerprint'] ?? '') !== $cartFingerprint) {
    $_SESSION['checkout_idempotency_key'] = 'checkout-' . bin2hex(random_bytes(24));
    $_SESSION['checkout_cart_fingerprint'] = $cartFingerprint;
}
$bundleName = $_SESSION['bundle_name'] ?? '';

// Dynamic Membership Subscription Discount
$subscription = $_SESSION['subscription'] ?? '';
$subDiscount = 0;
$subDiscountPercent = 0;
if ($subscription === 'vip') {
    $subDiscountPercent = 5;
    $subDiscount = (int) round($subtotal * 0.05);
} elseif ($subscription === 'gold') {
    $subDiscountPercent = 10;
    $subDiscount = (int) round($subtotal * 0.10);
}

$grandTotal = max(0, $subtotal + $fee - $bundleDiscount - $subDiscount);
?>
<style>
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
  -webkit-appearance: none; 
  margin: 0; 
}
</style>
<div class="mb-6"><h1 class="text-2xl font-bold text-slate-800">Keranjang Belanja</h1><p class="text-slate-500 text-sm mt-1">Review pesanan dan proses pembayaran melalui SmartBank.</p></div>

<?php if (empty($cartItems)): ?>
<div class="flex flex-col items-center justify-center py-20 bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4"><i class="ph ph-shopping-cart text-4xl text-slate-300"></i></div>
    <h3 class="text-lg font-bold text-slate-700 mb-2">Keranjang Anda Kosong</h3>
    <p class="text-sm text-slate-500 mb-6">Silakan pilih bahan baku di Katalog Supplier terlebih dahulu.</p>
    <a href="index.php?p=umkm&page=katalog" class="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primaryHover transition-colors">Lihat Katalog</a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-100 p-5 self-start">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-2">
            <h3 class="font-bold text-slate-800">Daftar Bahan Baku</h3>
            <button onclick="clearCart()" class="text-xs text-red-500 hover:text-red-700 font-bold flex items-center gap-1 transition-colors bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg border border-red-100 shadow-sm">
                <i class="ph ph-trash text-sm"></i> Kosongkan Keranjang
            </button>
        </div>
        <?php foreach ($cartItems as $idx => $item): ?>
        <div class="flex flex-col sm:flex-row justify-between items-center py-4 border-b border-slate-100 last:border-0 gap-4">
            <div class="flex items-center flex-1">
                <div class="w-12 h-12 bg-slate-50 rounded-lg flex items-center justify-center text-primary mr-4 border border-slate-100"><i class="ph-fill <?= $item['icon'] ?> text-2xl"></i></div>
                <div><h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($item['name']) ?></h4><p class="text-xs text-slate-500">Rp <?= number_format($item['price'],0,',','.') ?> / <?= $item['unit'] ?></p></div>
            </div>
            <div class="flex items-center">
                <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-slate-50 mr-4">
                    <a href="index.php?p=umkm&page=keranjang&cart_action=decrease&idx=<?= $idx ?>" class="px-3 py-1 hover:bg-slate-200 text-slate-600 font-bold transition-colors">-</a>
                    <input type="number" min="1" value="<?= $item['cart_qty'] ?>" onchange="updateQty(<?= $idx ?>, this.value)" class="w-12 py-1 bg-white text-sm font-bold text-center border-y-0 border-x border-slate-200 focus:outline-none" style="-moz-appearance: textfield; margin: 0;">
                    <a href="index.php?p=umkm&page=keranjang&cart_action=increase&idx=<?= $idx ?>" class="px-3 py-1 hover:bg-slate-200 text-slate-600 font-bold transition-colors">+</a>
                </div>
                <div class="w-24 text-right"><div class="font-bold text-slate-800 text-sm">Rp <?= number_format($item['item_total'],0,',','.') ?></div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-4">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Ringkasan Pembayaran</h3>
            <div class="space-y-2 text-sm text-slate-600 mb-6 bg-slate-50 p-3 rounded-lg border border-slate-100">
                <div class="flex justify-between"><span>Subtotal</span><span class="font-medium text-slate-800">Rp <?= number_format($subtotal,0,',','.') ?></span></div>
                <div class="flex justify-between items-center text-slate-500"><span>Fee Supplier (3%)</span><span class="font-bold text-slate-800">+ Rp <?= number_format($fee,0,',','.') ?></span></div>
                <?php if ($bundleDiscount > 0): ?>
                <div class="flex justify-between items-center text-emerald-600 font-semibold" id="cart-bundle-row">
                    <span>Diskon <?= htmlspecialchars($bundleName) ?></span>
                    <span class="font-bold">- Rp <?= number_format($bundleDiscount, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($subDiscount > 0): ?>
                <div class="flex justify-between items-center text-emerald-600 font-semibold" id="cart-subscription-row">
                    <span>Diskon <?= $subscription === 'vip' ? 'VIP Member (5%)' : 'Gold Partner (10%)' ?></span>
                    <span class="font-bold">- Rp <?= number_format($subDiscount, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="border-t border-slate-100 pt-3 flex justify-between items-center mb-6">
                <span class="font-bold text-slate-800 text-base">Total Tagihan</span>
                <span class="font-bold text-primary text-xl" id="cart-grand-total">Rp <?= number_format($grandTotal,0,',','.') ?></span>
            </div>
            <div class="bg-blue-50 border border-blue-100 p-3 rounded-lg text-xs text-blue-800 mb-4 flex items-start">
                <i class="ph-fill ph-bank mt-0.5 mr-2 text-lg"></i>
                <p>Pembayaran akan otomatis memotong saldo <strong>SmartBank</strong> Anda melalui API Gateway.</p>
            </div>
            <button onclick="openSmartBankModal()" class="w-full bg-primary hover:bg-primaryHover text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center shadow-md">
                <i class="ph ph-paper-plane-tilt mr-2 text-lg"></i> Buat Pesanan
            </button>
        </div>
    </div>
</div>

<!-- SmartBank Checkout Modal -->
<div id="smartbank-checkout-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden transition-all duration-300 opacity-0">
    <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl border border-slate-100 overflow-hidden transform scale-95 transition-all duration-300 flex flex-col relative" id="smartbank-checkout-card">
        
        <!-- Multi-stage Payment Loader -->
        <div id="sb-payment-loader" class="absolute inset-0 bg-white/95 backdrop-blur-md z-50 hidden flex-col items-center justify-center p-6 text-center transition-all duration-300">
            <div class="w-20 h-20 relative mb-6">
                <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                <div id="sb-payment-spinner" class="absolute inset-0 rounded-full border-4 border-emerald-500 border-t-transparent animate-spin"></div>
                <div id="sb-payment-success-icon" class="absolute inset-0 rounded-full bg-emerald-500 flex items-center justify-center hidden transform scale-0 transition-transform duration-500">
                    <i class="ph-bold ph-check text-white text-4xl"></i>
                </div>
            </div>
            <h3 id="sb-payment-status-title" class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight">Membuat Pesanan...</h3>
            <p id="sb-payment-status-sub" class="text-xs font-medium text-slate-500">Memvalidasi item dan ketersediaan stok</p>
        </div>

        <!-- Modal Header -->
        <div class="px-5 py-4 bg-emerald-600 text-white border-b border-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-lg"><i class="ph-fill ph-bank"></i></div>
                <div>
                    <h3 class="font-extrabold text-sm">Konfirmasi Pesanan B2B</h3>
                    <p class="text-[10px] text-emerald-200">Tahap sebelum pembayaran</p>
                </div>
            </div>
            <button onclick="closeSmartBankModal()" class="w-8 h-8 rounded-full bg-emerald-700 hover:bg-emerald-800 text-white flex items-center justify-center transition-all outline-none">
                <i class="ph-bold ph-x text-base"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 space-y-4">
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 text-center">
                <p class="text-xs text-slate-500 mb-1">Total Pembayaran</p>
                <h3 class="text-3xl font-extrabold text-slate-800">Rp <?= number_format($grandTotal, 0, ',', '.') ?></h3>
            </div>
            
            <div class="space-y-3 bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Merchant</span>
                    <span class="font-bold text-slate-800">SupplierHub B2B</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Status Awal</span>
                    <span class="font-bold text-amber-600"><i class="ph-fill ph-clock mr-1"></i> Menunggu Supplier</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Pembayaran</span>
                    <span class="font-bold text-slate-700">Belum dilakukan</span>
                </div>
            </div>
            
            <div class="p-3 rounded-xl bg-blue-50 border border-blue-100 text-xs text-blue-700">
                Supplier harus menerima pesanan sebelum simulasi pembayaran tersedia di halaman Riwayat Pesanan.
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex gap-3">
            <button onclick="closeSmartBankModal()" class="flex-1 py-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition-all">Batal</button>
            <button onclick="processCheckout()" class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-500/30 transition-all flex justify-center items-center gap-2">
                <i class="ph-fill ph-paper-plane-tilt"></i> Kirim Pesanan
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
const BASE='<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
let cartState = {
    subtotal: <?= $subtotal ?>,
    fee: <?= $fee ?>,
    bundleDiscount: <?= $bundleDiscount ?>,
    subDiscount: <?= $subDiscount ?>,
    total: <?= $grandTotal ?>
};
const checkoutIdempotencyKey = <?= json_encode($_SESSION['checkout_idempotency_key']) ?>;

function clearCart() {
    if (confirm('Apakah Anda yakin ingin mengosongkan seluruh isi keranjang belanja Anda?')) {
        window.location.href = 'index.php?p=umkm&page=keranjang&cart_action=clear';
    }
}

function openSmartBankModal() {
    const modal = document.getElementById('smartbank-checkout-modal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('smartbank-checkout-card').classList.remove('scale-95');
    }, 10);
}

function closeSmartBankModal() {
    const modal = document.getElementById('smartbank-checkout-modal');
    modal.classList.add('opacity-0');
    document.getElementById('smartbank-checkout-card').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

async function processCheckout(){
    const loader = document.getElementById('sb-payment-loader');
    const title = document.getElementById('sb-payment-status-title');
    const sub = document.getElementById('sb-payment-status-sub');
    const spinner = document.getElementById('sb-payment-spinner');
    const checkIcon = document.getElementById('sb-payment-success-icon');
    
    // Show Loader
    loader.classList.remove('hidden');
    loader.style.opacity = '1';

    title.innerText = 'Memvalidasi Pesanan...';
    sub.innerText = 'Memeriksa item dan supplier';
    await new Promise(r => setTimeout(r, 800));

    title.innerText = 'Membuat Order...';
    sub.innerText = 'Menyimpan order tanpa pembayaran';
    
    const totalDiscount = cartState.bundleDiscount + cartState.subDiscount;
    let discountName = '';
    if (cartState.bundleDiscount > 0) {
        discountName = '<?= addslashes($bundleName) ?>';
    }
    if (cartState.subDiscount > 0) {
        if (discountName) discountName += ' & ';
        discountName += '<?= $subscription === 'vip' ? 'Diskon VIP Member (5%)' : 'Diskon Gold Partner (10%)' ?>';
    }

    const r = await apiCall(BASE+'/api/orders.php?action=checkout','POST',{
        supplier_id: 1, 
        from_cart: true,
        discount: totalDiscount,
        voucher_name: discountName
        ,idempotency_key: checkoutIdempotencyKey
    });
    
    await new Promise(r => setTimeout(r, 800));

    if(r.status==='success'){
        title.innerText = 'Pesanan Berhasil Dibuat!';
        sub.innerText = 'Menunggu supplier menerima pesanan. Belum ada pembayaran.';
        spinner.classList.add('hidden');
        checkIcon.classList.remove('hidden');
        setTimeout(() => {
            checkIcon.classList.add('scale-100');
        }, 50);

        setTimeout(() => window.location.href = BASE+'/index.php?p=umkm&page=riwayat', 1500);
    } else {
        closeSmartBankModal();
        showToast(r.message,'error');
    }
}

function updateQty(idx, val) {
    const qty = parseInt(val);
    if (isNaN(qty) || qty <= 0) {
        if (confirm('Apakah Anda yakin ingin menghapus bahan baku ini dari keranjang belanja?')) {
            window.location.href = 'index.php?p=umkm&page=keranjang&cart_action=update&idx=' + idx + '&qty=0';
        } else {
            window.location.reload();
        }
    } else {
        window.location.href = 'index.php?p=umkm&page=keranjang&cart_action=update&idx=' + idx + '&qty=' + qty;
    }
}
</script>
