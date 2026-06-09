<?php
$materials = Material::getAll();
?>
<div class="mb-6"><h1 class="text-2xl font-bold text-slate-800">Katalog SupplierHub</h1><p class="text-slate-500 text-sm mt-1">Pilih dan beli bahan baku untuk keperluan produksi UMKM Anda.</p></div>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
<?php foreach ($materials as $m): ?>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 flex flex-col h-full transition-all hover:shadow-md hover:border-primary">
        <div class="h-32 bg-slate-50 rounded-lg mb-4 flex items-center justify-center text-slate-300 border border-slate-100">
            <i class="ph-fill <?= $m['icon'] ?? 'ph-package' ?> text-6xl opacity-50 text-primary"></i>
        </div>
        <div class="mb-2"><span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded uppercase tracking-wider"><?= htmlspecialchars($m['category']) ?></span></div>
        <h3 class="font-bold text-slate-800 text-sm mb-1 leading-snug line-clamp-2"><?= htmlspecialchars($m['name']) ?></h3>
        <p class="text-xs text-slate-400 font-mono mb-4">Supplier: <?= htmlspecialchars($m['supplier_name'] ?? 'Pusat') ?></p>
        <div class="mt-auto pt-3 flex flex-col gap-3 border-t border-slate-50">
            <p class="font-bold text-slate-800 text-lg leading-none">Rp <?= number_format($m['price'],0,',','.') ?><span class="text-xs font-normal text-slate-500 ml-1">/<?= $m['unit'] ?></span></p>
            <button onclick="addToCart(<?= $m['id'] ?>)" class="w-full bg-slate-50 hover:bg-primary hover:text-white text-primary border border-slate-200 hover:border-primary text-sm font-semibold py-2 rounded-lg transition-all flex justify-center items-center">
                <i class="ph ph-shopping-cart-simple mr-2 text-lg"></i> Beli
            </button>
        </div>
    </div>
<?php endforeach; ?>
</div>
<script>
const BASE='<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
async function addToCart(materialId){
    const r=await apiCall(BASE+'/api/orders.php?action=add_cart','POST',{material_id:materialId,qty:1});
    if(r.status==='success') showToast('Bahan berhasil ditambahkan ke keranjang.');
    else showToast(r.message||'Gagal menambahkan','error');
}
</script>
