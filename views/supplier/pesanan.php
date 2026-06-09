<?php
$orders = Order::getPending($userId);
$completedOrders = Order::getCompleted($userId);
?>
<div class="mb-6"><h1 class="text-2xl font-bold text-slate-800">Pesanan Masuk (API)</h1><p class="text-slate-500 text-sm mt-1">Verifikasi stok sebelum menerbitkan Payment Request.</p></div>
<?php if (empty($orders)): ?>
<div class="text-center py-20 bg-white rounded-xl border border-slate-200 shadow-sm mb-8">
    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4"><i class="ph ph-tray text-3xl text-slate-400"></i></div>
    <h3 class="text-lg font-bold text-slate-700">Belum ada pesanan masuk baru.</h3>
</div>
<?php else: ?>
<div class="space-y-4 mb-8">
<?php foreach ($orders as $o): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:border-primary transition-colors">
        <div>
            <div class="flex items-center gap-3 mb-1"><span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-bold rounded animate-pulse">New Order</span><span class="text-xs text-slate-400 font-mono"><?= $o['order_code'] ?></span></div>
            <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($o['umkm_name']) ?></h3>
            <p class="text-sm text-slate-500"><i class="ph ph-clock mr-1"></i> <?= $o['created_at'] ?> | <?= $o['item_count'] ?> macam bahan</p>
        </div>
        <button onclick="openReviewModal(<?= $o['id'] ?>)" class="w-full md:w-auto bg-primary hover:bg-primaryHover text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center shadow-sm"><i class="ph ph-magnifying-glass mr-2 text-lg"></i> Review &amp; Proses</button>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Riwayat Pesanan Selesai -->
<div class="mb-6">
    <h2 class="text-xl font-bold text-slate-800">Riwayat Pesanan Selesai</h2>
    <p class="text-slate-500 text-sm mt-1">Daftar transaksi B2B yang telah dikonfirmasi dan dibayar lunas.</p>
</div>
<?php if (empty($completedOrders)): ?>
<div class="text-center py-10 bg-white rounded-xl border border-slate-200 shadow-sm">
    <p class="text-slate-500 text-sm">Belum ada riwayat pesanan selesai.</p>
</div>
<?php else: ?>
<div class="space-y-4">
<?php foreach ($completedOrders as $o): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:border-emerald-500 transition-colors">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs font-bold rounded">Lunas</span>
                <span class="text-xs text-slate-400 font-mono"><?= $o['order_code'] ?></span>
            </div>
            <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($o['umkm_name']) ?></h3>
            <p class="text-sm text-slate-500"><i class="ph ph-clock mr-1"></i> <?= $o['completed_at'] ?> | Total: Rp <?= number_format($o['total'], 0, ',', '.') ?></p>
        </div>
        <button onclick="viewReceipt(<?= $o['id'] ?>, '<?= $o['order_code'] ?>')" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-bold transition-all hover:shadow-sm">
            <i class="ph ph-receipt text-lg"></i> Lihat Bukti
        </button>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div id="order-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 transition-opacity opacity-0">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-transform flex flex-col max-h-[90vh]" id="order-modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div><h3 class="text-lg font-bold text-slate-800">Review Pesanan</h3><p class="text-xs text-slate-500" id="modal-order-id"></p></div>
            <button onclick="closeOrderModal()" class="text-slate-400 hover:text-red-500"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1" id="modal-order-body">Loading...</div>
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
            <button onclick="closeOrderModal()" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg">Batal</button>
            <button id="btn-approve" onclick="approveOrder()" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg shadow-sm flex items-center"><i class="ph ph-paper-plane-tilt mr-2"></i> Approve &amp; Kirim Payment Request</button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receipt-modal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-all duration-300 opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[95vh] transform scale-95 transition-all duration-300" id="receipt-modal-card">
        <!-- Action Bar -->
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50 rounded-t-2xl">
            <span class="text-sm font-bold text-slate-700 flex items-center gap-2"><i class="ph-fill ph-receipt text-indigo-600"></i> Bukti Pembayaran</span>
            <div class="flex items-center gap-2">
                <button onclick="downloadReceiptPNG()" id="btn-download-png" class="flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primaryHover text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                    <i class="ph ph-download-simple"></i> Download PNG
                </button>
                <button onclick="closeReceiptModal()" class="w-7 h-7 rounded-full bg-slate-200 hover:bg-red-100 hover:text-red-500 flex items-center justify-center text-slate-500 transition-all">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
        </div>
        <!-- Receipt Content -->
        <div class="overflow-y-auto flex-1 p-5 bg-slate-50">
            <div id="receipt-printable" class="bg-white rounded-xl overflow-hidden shadow-md" style="min-width:320px;">
                <div id="receipt-loading" class="flex flex-col items-center justify-center py-16">
                    <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                    <p class="text-sm text-slate-500">Memuat data...</p>
                </div>
                <div id="receipt-content" class="hidden"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const BASE='<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
let currentOrderId=null;
let currentReceiptCode='';

async function openReviewModal(id){currentOrderId=id;const r=await apiCall(BASE+'/api/orders.php?action=detail&id='+id);if(r.status!=='success'){showToast(r.message,'error');return;}const o=r.data;document.getElementById('modal-order-id').innerText=o.order_code+' - '+o.umkm_name;let ih=o.items.map(i=>`<div class="flex justify-between items-center py-3 border-b border-slate-100 last:border-0"><div class="flex-1"><h4 class="text-sm font-bold text-slate-800">${i.material_name}</h4><p class="text-xs text-slate-500">${i.qty} ${i.unit} x ${formatRupiah(i.price_at_order)}</p></div><div class="w-28 text-right text-xs">${i.sufficient?'<span class="text-green-600 font-bold">OK</span>':'<span class="text-red-600 font-bold">Kurang</span>'}</div><div class="w-28 text-right font-bold text-sm">${formatRupiah(i.price_at_order*i.qty)}</div></div>`).join('');document.getElementById('modal-order-body').innerHTML=`<div class="bg-slate-50 p-4 rounded-xl border mb-4">${ih}</div><div class="bg-blue-50 p-4 rounded-xl border border-blue-100"><div class="flex justify-between text-sm mb-2"><span>Subtotal:</span><span>${formatRupiah(o.subtotal)}</span></div><div class="flex justify-between text-sm mb-2 text-green-600"><span>Fee 3%:</span><span>+${formatRupiah(o.fee_supplier)}</span></div><div class="flex justify-between font-bold text-lg border-t pt-2"><span>Total:</span><span class="text-primary">${formatRupiah(o.total)}</span></div></div>`;const b=document.getElementById('btn-approve');b.disabled=!o.stock_sufficient;b.classList.toggle('opacity-50',!o.stock_sufficient);const m=document.getElementById('order-modal'),c=document.getElementById('order-modal-content');m.classList.remove('hidden');m.classList.add('flex');setTimeout(()=>{m.classList.remove('opacity-0');m.classList.add('opacity-100');c.classList.remove('scale-95');c.classList.add('scale-100');},10);}
function closeOrderModal(){const m=document.getElementById('order-modal'),c=document.getElementById('order-modal-content');m.classList.remove('opacity-100');m.classList.add('opacity-0');c.classList.remove('scale-100');c.classList.add('scale-95');setTimeout(()=>{m.classList.add('hidden');m.classList.remove('flex');},200);}
async function approveOrder(){if(!currentOrderId)return;showToast('Memproses...','info');closeOrderModal();const r=await apiCall(BASE+'/api/orders.php?action=approve','POST',{order_id:currentOrderId});if(r.status==='success'){showToast(r.message);setTimeout(()=>location.reload(),1000);}else showToast(r.message,'error');}

async function viewReceipt(orderId, orderCode) {
    currentReceiptCode = orderCode;
    document.getElementById('receipt-loading').classList.remove('hidden');
    document.getElementById('receipt-content').classList.add('hidden');
    document.getElementById('receipt-content').innerHTML = '';

    const modal = document.getElementById('receipt-modal');
    const card = document.getElementById('receipt-modal-card');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
    }, 10);

    const r = await apiCall(BASE + '/api/orders.php?action=detail&id=' + orderId);
    if (r.status !== 'success') { showToast('Gagal memuat data.', 'error'); return; }

    document.getElementById('receipt-content').innerHTML = buildReceiptHTML(r.data);
    document.getElementById('receipt-loading').classList.add('hidden');
    document.getElementById('receipt-content').classList.remove('hidden');
}

function buildReceiptHTML(o) {
    const fmt = n => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
    const now = new Date();
    const printDate = now.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) + ' ' + now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    const ref = o.smartbank_ref || '-';
    const completedAt = o.completed_at || o.created_at || '-';

    const itemsHTML = (o.items||[]).map(i=>`
        <tr>
            <td style="padding:6px 0;font-size:12px;color:#334155;border-bottom:1px solid #f1f5f9;">${i.material_name}</td>
            <td style="padding:6px 0;font-size:12px;color:#64748b;text-align:center;border-bottom:1px solid #f1f5f9;">${i.qty} ${i.unit}</td>
            <td style="padding:6px 0;font-size:12px;color:#334155;text-align:right;font-weight:600;border-bottom:1px solid #f1f5f9;">${fmt(i.price_at_order*i.qty)}</td>
        </tr>
    `).join('');

    return `
    <div style="background:linear-gradient(135deg,#1e40af,#1e3a8a);padding:24px 20px 20px;text-align:center;">
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;">
            <div style="background:rgba(255,255,255,0.2);border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </div>
            <span style="color:white;font-size:18px;font-weight:800;letter-spacing:0.5px;">SupplierHub</span>
            <span style="background:rgba(255,255,255,0.25);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:99px;letter-spacing:1px;">B2B</span>
        </div>
        <p style="color:rgba(255,255,255,0.8);font-size:11px;letter-spacing:1.5px;font-weight:600;margin:0;">BUKTI PEMBAYARAN LUNAS</p>
    </div>

    <div style="background:#eff6ff;padding:12px 20px;text-align:center;border-bottom:2px dashed #bfdbfe;">
        <p style="font-size:11px;color:#6b7280;margin:0 0 2px 0;">No. Transaksi</p>
        <p style="font-size:15px;font-weight:800;color:#1e293b;font-family:monospace;margin:0;">${o.order_code}</p>
    </div>

    <div style="padding:16px 20px;">
        <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
            <tr><td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">KLIEN (UMKM)</td><td style="font-size:12px;color:#1e293b;padding:3px 0;text-align:right;font-weight:700;">${o.umkm_name||'-'}</td></tr>
            <tr><td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">TGL. PEMBAYARAN</td><td style="font-size:12px;color:#1e293b;padding:3px 0;text-align:right;">${completedAt}</td></tr>
            <tr><td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">REF. SMARTBANK</td><td style="font-size:11px;color:#1e40af;padding:3px 0;text-align:right;font-family:monospace;font-weight:700;">${ref}</td></tr>
        </table>

        <div style="border-top:1px dashed #e2e8f0;padding-top:12px;margin-bottom:12px;">
            <p style="font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:1px;margin:0 0 8px 0;">RINCIAN PESANAN</p>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="font-size:10px;color:#94a3b8;text-align:left;padding-bottom:6px;font-weight:600;">ITEM</th>
                    <th style="font-size:10px;color:#94a3b8;text-align:center;padding-bottom:6px;font-weight:600;">QTY</th>
                    <th style="font-size:10px;color:#94a3b8;text-align:right;padding-bottom:6px;font-weight:600;">SUBTOTAL</th>
                </tr></thead>
                <tbody>${itemsHTML}</tbody>
            </table>
        </div>

        <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;border:1px solid #e2e8f0;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:12px;color:#64748b;">Subtotal Barang</span>
                <span style="font-size:12px;color:#334155;font-weight:600;">${fmt(o.subtotal)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid #e2e8f0;">
                <span style="font-size:12px;color:#64748b;">Fee Supplier (3%)</span>
                <span style="font-size:12px;color:#059669;font-weight:600;">+ ${fmt(o.fee_supplier)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:13px;font-weight:700;color:#1e293b;">TOTAL BAYAR</span>
                <span style="font-size:17px;font-weight:800;color:#1e40af;">${fmt(o.total)}</span>
            </div>
        </div>

        <div style="margin-top:16px;text-align:center;">
            <div style="display:inline-flex;flex-direction:column;align-items:center;background:#dbeafe;border:2px solid #93c5fd;border-radius:24px;padding:8px 20px;min-width:200px;">
                <p style="font-size:15px;font-weight:800;color:#1e40af;margin:0 0 2px 0;letter-spacing:0.5px;text-align:center;width:100%;">LUNAS</p>
                <p style="font-size:10px;color:#3b82f6;margin:0;text-align:center;width:100%;">Pembayaran Terverifikasi SmartBank</p>
            </div>
        </div>

        <p style="text-align:center;font-size:10px;color:#94a3b8;margin:14px 0 0;border-top:1px dashed #e2e8f0;padding-top:10px;">
            Dicetak: ${printDate} &nbsp;|&nbsp; SupplierHub B2B Platform
        </p>
    </div>`;
}

function closeReceiptModal() {
    const modal = document.getElementById('receipt-modal');
    const card = document.getElementById('receipt-modal-card');
    modal.classList.add('opacity-0');
    card.classList.remove('scale-100');
    card.classList.add('scale-95');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300);
}

async function downloadReceiptPNG() {
    const btn = document.getElementById('btn-download-png');
    btn.innerHTML = '<i class="ph ph-circle-notch animate-spin"></i> Menyiapkan...';
    btn.disabled = true;
    try {
        const canvas = await html2canvas(document.getElementById('receipt-printable'), {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        });
        const link = document.createElement('a');
        link.download = `bukti-bayar-${currentReceiptCode}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        showToast('Bukti pembayaran berhasil diunduh!', 'success');
    } catch(e) {
        showToast('Gagal mengunduh. Coba lagi.', 'error');
    }
    btn.innerHTML = '<i class="ph ph-download-simple"></i> Download PNG';
    btn.disabled = false;
}

document.getElementById('receipt-modal').addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});
</script>
