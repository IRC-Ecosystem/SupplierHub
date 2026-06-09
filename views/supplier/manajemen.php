<?php
// Manajemen Stok View
$materials = Material::getBySupplier($userId);
?>
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Manajemen Stok Bahan Baku</h1>
        <p class="text-slate-500 text-sm mt-1">Daftar item yang tersedia untuk diakses oleh aplikasi POS dan Marketplace.</p>
    </div>
    <button onclick="openMaterialModal()" class="bg-primary hover:bg-primaryHover text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center shadow-sm shrink-0">
        <i class="ph ph-plus mr-2 text-lg"></i> Tambah Item Baru
    </button>
</div>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 font-semibold tracking-wider">
                    <th class="py-3 px-4">Nama Item & ID</th><th class="py-3 px-4">Kategori</th><th class="py-3 px-4">Harga B2B</th><th class="py-3 px-4">Stok Fisik Tersedia</th><th class="py-3 px-4 w-24">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada bahan baku. Tambahkan data pertama Anda.</td></tr>
                <?php else: foreach ($materials as $m): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4"><div class="font-medium text-slate-800"><?= htmlspecialchars($m['name']) ?></div><div class="text-xs text-slate-500"><?= $m['material_code'] ?></div></td>
                    <td class="py-3 px-4 text-sm text-slate-600"><?= htmlspecialchars($m['category']) ?></td>
                    <td class="py-3 px-4 text-sm font-medium text-slate-800">Rp <?= number_format($m['price'], 0, ',', '.') ?> <span class="text-xs text-slate-500 font-normal">/<?= $m['unit'] ?></span></td>
                    <td class="py-3 px-4"><span class="text-sm font-bold <?= $m['stock'] > 100 ? 'text-green-600' : ($m['stock'] > 0 ? 'text-amber-600' : 'text-red-500') ?> px-2 py-1 bg-white border border-slate-200 rounded"><?= $m['stock'] ?></span></td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <button onclick="openMaterialModal(<?= $m['id'] ?>)" class="w-8 h-8 rounded bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="Edit"><i class="ph ph-pencil-simple"></i></button>
                            <button onclick="deleteMaterial(<?= $m['id'] ?>)" class="w-8 h-8 rounded bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition-colors" title="Hapus"><i class="ph ph-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form CRUD -->
<div id="material-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform flex flex-col" id="material-modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="material-modal-title">Tambah Bahan Baku Baru</h3>
            <button onclick="closeMaterialModal()" class="text-slate-400 hover:text-red-500 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        <form onsubmit="saveMaterial(event)" class="p-6 overflow-y-auto max-h-[70vh]">
            <input type="hidden" id="material-id">
            <div class="space-y-4">
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Nama Item</label><input type="text" id="material-name" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-sm" placeholder="Contoh: Gula Merah Aren"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">Kategori</label><select id="material-category" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-sm bg-white"><option value="Bahan Pokok">Bahan Pokok</option><option value="Cair">Bahan Cair</option><option value="Bumbu & Rempah">Bumbu & Rempah</option><option value="Kemasan">Kemasan</option><option value="Lainnya">Lainnya</option></select></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">Satuan</label><select id="material-unit" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-sm bg-white"><option value="Kg">Kg</option><option value="Gram">Gram</option><option value="Liter">Liter</option><option value="Pcs">Pcs</option><option value="Tray">Tray</option><option value="Dus">Dus</option></select></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">Harga B2B (Rp)</label><input type="number" id="material-price" required min="0" class="w-full px-3 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-sm" placeholder="15000"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">Stok Fisik Tersedia</label><input type="number" id="material-stock" required min="0" class="w-full px-3 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-sm" placeholder="100"></div>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeMaterialModal()" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">Batal</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primaryHover rounded-lg transition-colors shadow-sm">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\") ?>';
const materialsData = <?= json_encode($materials) ?>;

function openMaterialModal(id = null) {
    document.getElementById('material-id').value = '';
    document.getElementById('material-name').value = '';
    document.getElementById('material-category').value = 'Bahan Pokok';
    document.getElementById('material-price').value = '';
    document.getElementById('material-stock').value = '';
    document.getElementById('material-unit').value = 'Kg';

    if (id) {
        const mat = materialsData.find(m => m.id == id);
        if (mat) {
            document.getElementById('material-modal-title').innerText = 'Edit Bahan Baku';
            document.getElementById('material-id').value = mat.id;
            document.getElementById('material-name').value = mat.name;
            document.getElementById('material-category').value = mat.category;
            document.getElementById('material-price').value = mat.price;
            document.getElementById('material-stock').value = mat.stock;
            document.getElementById('material-unit').value = mat.unit;
        }
    } else {
        document.getElementById('material-modal-title').innerText = 'Tambah Bahan Baku Baru';
    }
    const modal = document.getElementById('material-modal');
    const content = document.getElementById('material-modal-content');
    modal.classList.remove('hidden'); modal.classList.add('flex');
    setTimeout(() => { modal.classList.remove('opacity-0'); modal.classList.add('opacity-100'); content.classList.remove('scale-95'); content.classList.add('scale-100'); }, 10);
}

function closeMaterialModal() {
    const modal = document.getElementById('material-modal');
    const content = document.getElementById('material-modal-content');
    modal.classList.remove('opacity-100'); modal.classList.add('opacity-0');
    content.classList.remove('scale-100'); content.classList.add('scale-95');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
}

async function saveMaterial(e) {
    e.preventDefault();
    const id = document.getElementById('material-id').value;
    const data = {
        id: id || undefined,
        name: document.getElementById('material-name').value,
        category: document.getElementById('material-category').value,
        price: parseInt(document.getElementById('material-price').value),
        stock: parseInt(document.getElementById('material-stock').value),
        unit: document.getElementById('material-unit').value
    };
    const action = id ? 'update' : 'create';
    const res = await apiCall(BASE + '/api/materials.php?action=' + action, 'POST', data);
    if (res.status === 'success') { showToast(res.message); setTimeout(() => location.reload(), 500); }
    else { showToast(res.message, 'error'); }
}

async function deleteMaterial(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus bahan baku ini?')) return;
    const res = await apiCall(BASE + '/api/materials.php?action=delete', 'POST', { id });
    if (res.status === 'success') { showToast(res.message, 'info'); setTimeout(() => location.reload(), 500); }
    else { showToast(res.message, 'error'); }
}
</script>
