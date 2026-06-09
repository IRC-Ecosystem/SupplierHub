<?php
$statsResponse = DashboardController::financeStats($userId);
$data = $statsResponse['data'];
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Keuangan & Neraca (Supplier)</h1>
    <p class="text-slate-500 text-sm mt-1">Pantau sisa saldo rekening, total pendapatan, dan riwayat mutasi.</p>
</div>

<!-- Summary Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Left Column: Cards -->
    <div class="lg:col-span-1 space-y-6 flex flex-col justify-between">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl p-6 text-white shadow-lg relative overflow-hidden flex-1 flex flex-col justify-center min-h-[120px]">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative z-10 flex justify-between items-center">
                <div>
                    <p class="text-sm text-blue-100 font-medium mb-1">Saldo Rekening (SmartBank)</p>
                    <h3 class="text-3xl font-extrabold">Rp <?= number_format($data['balance'], 0, ',', '.') ?></h3>
                </div>
                <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center text-3xl">
                    <i class="ph-fill ph-bank text-white"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 border border-slate-100 shadow-sm flex items-center justify-between flex-1 min-h-[120px]">
            <div>
                <p class="text-sm text-slate-500 font-medium mb-1">Total Pendapatan Bersih (Setelah Fee)</p>
                <h3 class="text-2xl font-bold text-slate-800">Rp <?= number_format($data['income'], 0, ',', '.') ?></h3>
            </div>
            <div class="w-14 h-14 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center text-3xl">
                <i class="ph-fill ph-trend-up"></i>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Visual Chart -->
    <div class="lg:col-span-2 bg-white rounded-xl p-5 border border-slate-100 shadow-sm flex flex-col justify-between">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-1.5"><i class="ph ph-chart-bar text-primary"></i> Tren Penerimaan Dana (Buku Kas)</h3>
        <div class="flex-1 min-h-[160px] relative">
            <canvas id="finance-chart"></canvas>
        </div>
    </div>
</div>

<!-- Payment History Table with Tabs -->
<div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h3 class="text-base font-bold text-slate-800 flex items-center"><i class="ph-fill ph-list-numbers mr-2 text-primary"></i>Mutasi Rekening & Buku Kas</h3>
        <!-- Tab Headers -->
        <div class="flex bg-slate-200/60 p-1 rounded-lg">
            <button onclick="switchTab('local')" id="tab-btn-local" class="px-3 py-1.5 rounded-md text-xs font-bold transition-all bg-white text-slate-800 shadow-sm">
                Buku Kas Lokal
            </button>
            <button onclick="switchTab('smartbank')" id="tab-btn-smartbank" class="px-3 py-1.5 rounded-md text-xs font-bold transition-all text-slate-500 hover:text-slate-800">
                Audit Live SmartBank
            </button>
        </div>
    </div>
    
    <!-- Tab 1: Local Buku Kas -->
    <div id="tab-content-local" class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white border-b border-slate-100 text-xs text-slate-400 uppercase tracking-wider">
                    <th class="py-4 px-6 font-semibold">Tanggal</th>
                    <th class="py-4 px-6 font-semibold">Deskripsi</th>
                    <th class="py-4 px-6 font-semibold">Referensi Gateway</th>
                    <th class="py-4 px-6 font-semibold text-right">Nominal</th>
                    <th class="py-4 px-6 text-center font-semibold">Bukti</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if (empty($data['history'])): ?>
                <tr>
                    <td colspan="5" class="py-8 px-6 text-center text-slate-500">Belum ada riwayat penerimaan dana.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($data['history'] as $tx): ?>
                    <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                        <td class="py-4 px-6 text-slate-600"><?= date('d M Y, H:i', strtotime($tx['created_at'])) ?></td>
                        <td class="py-4 px-6 font-medium text-slate-800"><?= htmlspecialchars($tx['description']) ?></td>
                        <td class="py-4 px-6 text-slate-500 font-mono text-xs"><?= htmlspecialchars($tx['reference_id'] ?: '-') ?></td>
                        <td class="py-4 px-6 text-right font-bold text-emerald-600">+ Rp <?= number_format($tx['amount'], 0, ',', '.') ?></td>
                        <td class="py-4 px-6 text-center">
                            <?php if (!empty($tx['reference_id'])): ?>
                            <button onclick="viewReceiptByRef('<?= $tx['reference_id'] ?>')" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition-all hover:shadow-sm">
                                <i class="ph ph-receipt text-sm"></i> Lihat Bukti
                            </button>
                            <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Tab 2: SmartBank Live Ledger -->
    <div id="tab-content-smartbank" class="overflow-x-auto hidden">
        <div id="smartbank-loading" class="flex flex-col items-center justify-center py-12">
            <div class="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin mb-2"></div>
            <p class="text-xs text-slate-500">Menghubungkan ke core ledger SmartBank...</p>
        </div>
        <div id="smartbank-error" class="hidden text-center py-12 text-slate-500 text-xs">
            <i class="ph ph-warning-circle text-2xl text-red-500 mb-2 block"></i>
            Gagal memuat ledger dari core SmartBank API.
        </div>
        <table id="smartbank-table" class="w-full text-left border-collapse hidden">
            <thead>
                <tr class="bg-white border-b border-slate-100 text-xs text-slate-400 uppercase tracking-wider">
                    <th class="py-4 px-6 font-semibold">Waktu</th>
                    <th class="py-4 px-6 font-semibold">Tipe</th>
                    <th class="py-4 px-6 font-semibold">Pengirim</th>
                    <th class="py-4 px-6 font-semibold">Penerima</th>
                    <th class="py-4 px-6 font-semibold">Deskripsi</th>
                    <th class="py-4 px-6 font-semibold text-right">Nominal</th>
                </tr>
            </thead>
            <tbody id="smartbank-table-body" class="text-sm">
                <!-- Injected via JS -->
            </tbody>
        </table>
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
let currentReceiptCode = '';

async function viewReceiptByRef(ref) {
    currentReceiptCode = ref;
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

    const r = await apiCall(BASE + '/api/orders.php?action=detail_by_ref&ref=' + encodeURIComponent(ref));
    if (r.status !== 'success') { 
        showToast('Gagal memuat detail transaksi / data pesanan tidak ditemukan.', 'error'); 
        closeReceiptModal();
        return; 
    }

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

// Tab Switching
async function switchTab(tab) {
    if (tab === 'local') {
        document.getElementById('tab-btn-local').classList.add('bg-white', 'text-slate-800', 'shadow-sm');
        document.getElementById('tab-btn-local').classList.remove('text-slate-500');
        document.getElementById('tab-btn-smartbank').classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
        document.getElementById('tab-btn-smartbank').classList.add('text-slate-500');
        
        document.getElementById('tab-content-local').classList.remove('hidden');
        document.getElementById('tab-content-smartbank').classList.add('hidden');
    } else {
        document.getElementById('tab-btn-smartbank').classList.add('bg-white', 'text-slate-800', 'shadow-sm');
        document.getElementById('tab-btn-smartbank').classList.remove('text-slate-500');
        document.getElementById('tab-btn-local').classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
        document.getElementById('tab-btn-local').classList.add('text-slate-500');
        
        document.getElementById('tab-content-local').classList.add('hidden');
        document.getElementById('tab-content-smartbank').classList.remove('hidden');
        
        await loadSmartBankLedger();
    }
}

async function loadSmartBankLedger() {
    const loading = document.getElementById('smartbank-loading');
    const error = document.getElementById('smartbank-error');
    const table = document.getElementById('smartbank-table');
    const tbody = document.getElementById('smartbank-table-body');
    
    loading.classList.remove('hidden');
    error.classList.add('hidden');
    table.classList.add('hidden');
    tbody.innerHTML = '';
    
    try {
        const res = await fetch('http://localhost/SmartBank/api/smartbank/ledger_transaksi?limit=50').then(r => r.json());
        if (res.status === 'success') {
            const ledger = res.data.ledger || [];
            const myUserId = 3; // SupplierHub B2B is ID 3 in smartbank_db
            const filtered = ledger.filter(item => item.from_user_id == myUserId || item.to_user_id == myUserId);
            
            if (filtered.length === 0) {
                 tbody.innerHTML = `<tr><td colspan="6" class="py-8 px-6 text-center text-slate-500">Tidak ada riwayat transaksi bank untuk akun Anda.</td></tr>`;
            } else {
                filtered.forEach(item => {
                    const date = new Date(item.created_at).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
                    const isDebit = item.from_user_id == myUserId;
                    const typeBadge = isDebit 
                        ? `<span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-bold rounded">DEBIT</span>`
                        : `<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-bold rounded">CREDIT</span>`;
                    const amtColor = isDebit ? 'text-red-600' : 'text-green-600';
                    const sign = isDebit ? '-' : '+';
                    
                    tbody.innerHTML += `
                        <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-6 text-slate-500">${date}</td>
                            <td class="py-4 px-6">${typeBadge}</td>
                            <td class="py-4 px-6 text-slate-700">${item.from_name || '-'}</td>
                            <td class="py-4 px-6 text-slate-700">${item.to_name || '-'}</td>
                            <td class="py-4 px-6 font-medium text-slate-800">${item.description || '-'}</td>
                            <td class="py-4 px-6 text-right font-bold ${amtColor}">${sign} Rp ${new Intl.NumberFormat('id-ID').format(item.amount)}</td>
                        </tr>
                    `;
                });
            }
            loading.classList.add('hidden');
            table.classList.remove('hidden');
        } else {
            throw new Error(res.message);
        }
    } catch(e) {
        loading.classList.add('hidden');
        error.classList.remove('hidden');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Rendering
(function() {
    const chartHistory = <?= json_encode(array_reverse($data['history'])) ?>;
    const chartLabels = chartHistory.map(tx => new Date(tx.created_at).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'}));
    const chartAmounts = chartHistory.map(tx => tx.amount);

    const ctx = document.getElementById('finance-chart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 150);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels.length ? chartLabels : ['Belum ada data'],
            datasets: [{
                label: 'Nominal (Rp)',
                data: chartAmounts.length ? chartAmounts : [0],
                borderColor: '#3b82f6',
                borderWidth: 2.5,
                fill: true,
                backgroundColor: gradient,
                tension: 0.35,
                pointBackgroundColor: '#3b82f6',
                pointRadius: chartHistory.length === 1 ? 4 : 0,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    ticks: {
                        callback: value => 'Rp ' + value.toLocaleString('id-ID')
                    }
                }
            }
        }
    });
})();
</script>
