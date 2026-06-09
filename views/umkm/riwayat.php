<?php
$orders = Order::getByUmkm($userId);
?>
<div class="mb-6"><h1 class="text-2xl font-bold text-slate-800">Riwayat Pesanan</h1><p class="text-slate-500 text-sm mt-1">Daftar transaksi B2B yang telah dibayar lunas.</p></div>
<div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead><tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 font-semibold tracking-wider"><th class="py-3 px-4">ID Transaksi &amp; Waktu</th><th class="py-3 px-4">Total Nilai</th><th class="py-3 px-4">Status</th><th class="py-3 px-4">Keterangan</th><th class="py-3 px-4">Bukti Bayar</th></tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada riwayat belanja.</td></tr>
            <?php else: foreach ($orders as $o): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4"><div class="font-mono text-sm font-bold text-slate-700"><?= $o['order_code'] ?></div><div class="text-xs text-slate-500"><?= $o['created_at'] ?></div></td>
                    <td class="py-3 px-4 text-sm font-bold text-slate-800">Rp <?= number_format($o['total'],0,',','.') ?></td>
                    <td class="py-3 px-4">
                        <?php if ($o['status'] === 'completed'): ?>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold border border-green-200"><i class="ph-fill ph-check-circle mr-1"></i>Lunas</span>
                        <?php elseif ($o['status'] === 'pending'): ?>
                        <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs font-bold border border-amber-200"><i class="ph-fill ph-clock mr-1"></i>Menunggu</span>
                        <?php else: ?>
                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold border border-red-200"><?= ucfirst($o['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-sm text-slate-600">
                        <?php if ($o['status'] === 'completed'): ?><i class="ph ph-truck mr-1 text-slate-400"></i>Menunggu Kurir
                        <?php elseif ($o['status'] === 'pending'): ?>Menunggu konfirmasi supplier
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php if ($o['status'] === 'completed'): ?>
                        <button onclick="viewReceipt(<?= $o['id'] ?>, '<?= $o['order_code'] ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold transition-all hover:shadow-sm">
                            <i class="ph ph-receipt text-sm"></i> Lihat Bukti
                        </button>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receipt-modal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-all duration-300 opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[95vh] transform scale-95 transition-all duration-300" id="receipt-modal-card">
        <!-- Modal Action Bar (NOT captured in PNG) -->
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50 rounded-t-2xl" id="receipt-action-bar">
            <span class="text-sm font-bold text-slate-700 flex items-center gap-2"><i class="ph-fill ph-receipt text-emerald-600"></i> Bukti Pembayaran</span>
            <div class="flex items-center gap-2">
                <button onclick="downloadReceiptPNG()" id="btn-download-png" class="flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primaryHover text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                    <i class="ph ph-download-simple"></i> Download PNG
                </button>
                <button onclick="closeReceiptModal()" class="w-7 h-7 rounded-full bg-slate-200 hover:bg-red-100 hover:text-red-500 flex items-center justify-center text-slate-500 transition-all">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Receipt Content (THIS is captured as PNG) -->
        <div class="overflow-y-auto flex-1 p-5 bg-slate-50">
            <div id="receipt-printable" class="bg-white rounded-xl overflow-hidden shadow-md font-sans" style="min-width:320px;">
                <!-- Loading state -->
                <div id="receipt-loading" class="flex flex-col items-center justify-center py-16">
                    <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mb-3"></div>
                    <p class="text-sm text-slate-500">Memuat data...</p>
                </div>
                <!-- Content injected by JS -->
                <div id="receipt-content" class="hidden"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const BASE='<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/\\") ?>';
let currentReceiptCode = '';

async function viewReceipt(orderId, orderCode) {
    currentReceiptCode = orderCode;
    const modal = document.getElementById('receipt-modal');
    const card = document.getElementById('receipt-modal-card');
    document.getElementById('receipt-loading').classList.remove('hidden');
    document.getElementById('receipt-content').classList.add('hidden');
    document.getElementById('receipt-content').innerHTML = '';

    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
    }, 10);

    // Fetch order detail
    const r = await apiCall(BASE + '/api/orders.php?action=detail&id=' + orderId);
    if (r.status !== 'success') { showToast('Gagal memuat detail pesanan.', 'error'); return; }
    const o = r.data;

    // Render receipt
    document.getElementById('receipt-content').innerHTML = buildReceiptHTML(o);
    document.getElementById('receipt-loading').classList.add('hidden');
    document.getElementById('receipt-content').classList.remove('hidden');
}

function buildReceiptHTML(o) {
    const fmt = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
    const now = new Date();
    const printDate = now.toLocaleDateString('id-ID', {day:'2-digit',month:'long',year:'numeric'}) + ' ' + now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});

    const itemsHTML = (o.items || []).map(i => `
        <tr>
            <td style="padding:6px 0;font-size:12px;color:#334155;border-bottom:1px solid #f1f5f9;">${i.material_name}</td>
            <td style="padding:6px 0;font-size:12px;color:#64748b;text-align:center;border-bottom:1px solid #f1f5f9;">${i.qty} ${i.unit}</td>
            <td style="padding:6px 0;font-size:12px;color:#334155;text-align:right;font-weight:600;border-bottom:1px solid #f1f5f9;">${fmt(i.price_at_order * i.qty)}</td>
        </tr>
    `).join('');

    const ref = o.smartbank_ref || '-';
    const completedAt = o.completed_at || o.created_at || '-';

    return `
    <div style="background:linear-gradient(135deg,#059669,#047857);padding:24px 20px 20px;text-align:center;">
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
        <p style="color:rgba(255,255,255,0.8);font-size:11px;letter-spacing:1.5px;font-weight:600;margin:0;">BUKTI PEMBAYARAN</p>
    </div>

    <div style="background:#f0fdf4;padding:12px 20px;text-align:center;border-bottom:2px dashed #d1fae5;">
        <p style="font-size:11px;color:#6b7280;margin:0 0 2px 0;">No. Transaksi</p>
        <p style="font-size:15px;font-weight:800;color:#1e293b;font-family:monospace;margin:0;">${o.order_code}</p>
    </div>

    <div style="padding:16px 20px;">
        <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
            <tr>
                <td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">PEMBELI</td>
                <td style="font-size:12px;color:#1e293b;padding:3px 0;text-align:right;font-weight:700;">${o.umkm_name || '-'}</td>
            </tr>
            <tr>
                <td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">TGL. BAYAR</td>
                <td style="font-size:12px;color:#1e293b;padding:3px 0;text-align:right;">${completedAt}</td>
            </tr>
            <tr>
                <td style="font-size:11px;color:#94a3b8;padding:3px 0;font-weight:600;letter-spacing:0.5px;">REF. SMARTBANK</td>
                <td style="font-size:11px;color:#059669;padding:3px 0;text-align:right;font-family:monospace;font-weight:700;">${ref}</td>
            </tr>
        </table>

        <div style="border-top:1px dashed #e2e8f0;padding-top:12px;margin-bottom:12px;">
            <p style="font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:1px;margin:0 0 8px 0;">RINCIAN PESANAN</p>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="font-size:10px;color:#94a3b8;text-align:left;padding-bottom:6px;font-weight:600;">ITEM</th>
                        <th style="font-size:10px;color:#94a3b8;text-align:center;padding-bottom:6px;font-weight:600;">QTY</th>
                        <th style="font-size:10px;color:#94a3b8;text-align:right;padding-bottom:6px;font-weight:600;">SUBTOTAL</th>
                    </tr>
                </thead>
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
                <span style="font-size:17px;font-weight:800;color:#059669;">${fmt(o.total)}</span>
            </div>
        </div>

        <div style="margin-top:16px;text-align:center;">
            <div style="display:inline-flex;flex-direction:column;align-items:center;background:#dcfce7;border:2px solid #bbf7d0;border-radius:24px;padding:8px 20px;min-width:200px;">
                <p style="font-size:15px;font-weight:800;color:#15803d;margin:0 0 2px 0;letter-spacing:0.5px;text-align:center;width:100%;">LUNAS</p>
                <p style="font-size:10px;color:#16a34a;margin:0;text-align:center;width:100%;">Pembayaran Terverifikasi SmartBank</p>
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

    const el = document.getElementById('receipt-printable');
    try {
        const canvas = await html2canvas(el, {
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

// Close on backdrop click
document.getElementById('receipt-modal').addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});
</script>
