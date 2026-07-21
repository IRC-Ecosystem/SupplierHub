<?php require_once __DIR__ . '/../../middleware/CsrfMiddleware.php'; ?>
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <script>
        // Format Rupiah
        const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

        // Toast notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : (type === 'info' ? 'bg-blue-600' : 'bg-red-600');
            const icon = type === 'success' ? 'ph-check-circle' : (type === 'info' ? 'ph-info' : 'ph-warning-circle');
            toast.className = `flex items-center text-white px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-y-10 opacity-0 ${bgColor} pointer-events-auto`;
            toast.innerHTML = `<i class="ph ${icon} text-xl mr-2"></i><span class="text-sm font-medium">${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
            setTimeout(() => { toast.classList.add('opacity-0', 'translate-y-2'); setTimeout(() => toast.remove(), 300); }, 4000);
        }

        // AJAX helper
        async function apiCall(url, method = 'GET', data = null) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': <?= json_encode(CsrfMiddleware::token()) ?>
                }
            };
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            try {
                const res = await fetch(url, options);
                return await res.json();
            } catch (err) {
                console.error('API Error:', err);
                return { status: 'error', message: 'Koneksi ke server gagal.' };
            }
        }

        // Auto Refresh Logic (Every 1 Minute)
        let secondsRemaining = 60;

        function initAutoRefresh() {
            const timerSpan = document.getElementById('refresh-timer');
            if (!timerSpan) return;

            setInterval(() => {
                secondsRemaining--;
                if (secondsRemaining <= 0) {
                    secondsRemaining = 0;
                    const icon = document.getElementById('refresh-icon');
                    if (icon) {
                        icon.style.transition = 'transform 0.5s ease-in-out';
                        icon.style.transform = 'rotate(720deg)';
                    }
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    const m = Math.floor(secondsRemaining / 60);
                    const s = secondsRemaining % 60;
                    timerSpan.innerText = `${m}:${s.toString().padStart(2, '0')}`;
                }
            }, 1000);
        }

        // Call on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            initAutoRefresh();
        });
    </script>
</body>
</html>
