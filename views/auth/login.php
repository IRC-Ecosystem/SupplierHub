<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?p=' . $_SESSION['role']);
    exit;
}
$pageTitle = 'Masuk / Daftar - B2BLink';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/professional.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                colors: { brand: { 50:'#eff6ff',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8' }, umkm:'#10b981', supplier:'#3b82f6' },
                animation: { 'float':'float 6s ease-in-out infinite','slide-up':'slideUp 0.8s cubic-bezier(0.16,1,0.3,1) forwards','pulse-slow':'pulse 4s cubic-bezier(0.4,0,0.6,1) infinite' },
                keyframes: { float: {'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-20px)'}}, slideUp: {'0%':{opacity:'0',transform:'translateY(30px)'},'100%':{opacity:'1',transform:'translateY(0)'}} }
            }}
        }
    </script>
    <style>
        .bg-grid-pattern { background-image: radial-gradient(circle,#cbd5e1 1px,transparent 1px); background-size: 24px 24px; }
        .fade-enter { opacity:0; transform:translateY(10px); }
        .fade-enter-active { opacity:1; transform:translateY(0); transition:all 0.4s cubic-bezier(0.4,0,0.2,1); }
        .input-group:focus-within label { color: var(--focus-color, #2563eb); }
        .input-group:focus-within i { color: var(--focus-color, #2563eb); }
        .text-gradient { background-clip:text; -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    </style>
</head>
<body class="auth-page bg-slate-50 font-sans min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-x-hidden">
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute inset-0 bg-grid-pattern opacity-40"></div>
        <div class="absolute -top-[20%] -right-[10%] w-[60%] h-[60%] bg-blue-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-pulse-slow"></div>
        <div class="absolute -bottom-[20%] -left-[10%] w-[60%] h-[60%] bg-emerald-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-pulse-slow" style="animation-delay:2s;"></div>
    </div>

    <div class="w-full max-w-5xl z-10 opacity-0 animate-slide-up flex flex-col gap-4 sm:gap-6">
        <div class="self-start">
            <a href="landingpage.html" class="inline-flex items-center text-slate-600 hover:text-slate-900 font-bold transition-all bg-white/70 backdrop-blur-xl px-5 py-2.5 rounded-2xl border border-white shadow-sm hover:shadow-md hover:-translate-y-0.5 group text-sm sm:text-base">
                <div class="w-7 h-7 bg-white rounded-full flex items-center justify-center mr-3 shadow-sm group-hover:-translate-x-1 transition-transform"><i class="ph-bold ph-arrow-left text-slate-700"></i></div>
                Kembali ke Beranda
            </a>
        </div>

        <div class="bg-white/80 backdrop-blur-2xl rounded-3xl md:rounded-[2.5rem] shadow-2xl shadow-slate-200/50 overflow-hidden flex flex-col md:flex-row min-h-[650px] border border-white relative">
            <!-- Loading Overlay -->
            <div id="loading-overlay" class="absolute inset-0 bg-white/90 backdrop-blur-md z-50 hidden flex-col items-center justify-center transition-opacity duration-300 opacity-0 rounded-3xl md:rounded-[2.5rem]">
                <div class="w-20 h-20 relative mb-6">
                    <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                    <div id="loading-spinner" class="absolute inset-0 rounded-full border-4 border-emerald-500 border-t-transparent animate-spin"></div>
                </div>
                <h3 id="loading-text" class="text-2xl font-extrabold text-slate-800 mb-2 tracking-tight">Mengautentikasi...</h3>
                <p id="loading-subtext" class="text-sm font-medium text-slate-500">Mempersiapkan workspace B2B Anda</p>
            </div>

            <!-- Left Panel -->
            <div class="w-full md:w-5/12 bg-slate-900 text-white p-8 lg:p-12 flex flex-col justify-between relative overflow-hidden hidden md:flex" id="side-panel">
                <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:24px_24px]"></div>
                <div id="side-gradient" class="absolute inset-0 bg-gradient-to-br from-emerald-600/30 via-transparent to-transparent transition-colors duration-700 pointer-events-none"></div>
                <div class="relative z-10">
                    <a href="landingpage.html" class="flex items-center gap-3 mb-12 lg:mb-16 cursor-pointer group">
                        <div class="w-12 h-12 bg-gradient-to-br from-brand-400 to-brand-600 rounded-xl flex items-center justify-center shadow-lg shadow-brand-500/30 transform group-hover:scale-105 transition-transform"><i class="ph-fill ph-intersect text-3xl text-white"></i></div>
                        <span class="font-extrabold text-2xl tracking-tight">SupplierHub</span>
                    </a>
                    <div>
                        <h2 class="text-3xl lg:text-4xl font-extrabold mb-4 lg:mb-6 leading-tight" id="side-title">Pengadaan yang lebih tertata</h2>
                        <p class="text-slate-300 text-sm lg:text-base leading-relaxed font-medium" id="side-desc">Kelola katalog, pesanan supplier, dan status pembayaran dalam satu ruang kerja.</p>
                    </div>
                </div>
                <div class="relative z-10 bg-white/5 backdrop-blur-xl p-5 lg:p-6 rounded-3xl border border-white/10 shadow-2xl mt-8">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/10"><i class="ph-fill ph-shield-check text-xl lg:text-2xl text-emerald-400" id="security-icon"></i></div>
                        <div><p class="text-sm font-bold text-white mb-1">Akses berbasis peran</p><p class="text-xs text-slate-300 leading-relaxed font-medium">Portal menyesuaikan akses untuk akun UMKM, supplier, dan integrator.</p></div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Forms -->
            <div class="w-full md:w-7/12 p-6 sm:p-8 lg:p-14 relative flex flex-col justify-center bg-white/40">
                <a href="landingpage.html" class="flex items-center justify-center gap-2 mb-8 md:hidden cursor-pointer">
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-500 to-brand-700 rounded-xl flex items-center justify-center shadow-lg shadow-brand-500/30 text-white"><i class="ph-fill ph-intersect text-2xl"></i></div>
                    <span class="font-extrabold text-2xl text-slate-900 tracking-tight">SupplierHub</span>
                </a>

                <!-- Tab Toggle -->
                <div class="flex p-1.5 bg-slate-200/50 backdrop-blur rounded-2xl mb-8 lg:mb-10 w-full max-w-sm mx-auto relative">
                    <div id="tab-indicator" class="absolute top-1.5 left-1.5 bottom-1.5 w-[calc(50%-6px)] bg-white rounded-xl shadow-sm border border-slate-100 transition-all duration-300 ease-out z-0"></div>
                    <button onclick="switchTab('login')" id="tab-login" class="flex-1 py-2.5 lg:py-3 text-sm font-extrabold text-brand-600 transition-colors z-10 relative tracking-wide">Masuk</button>
                    <button onclick="switchTab('register')" id="tab-register" class="flex-1 py-2.5 lg:py-3 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors z-10 relative tracking-wide">Daftar Baru</button>
                </div>

                <!-- LOGIN VIEW -->
                <div id="view-login" class="fade-enter-active">
                    <div class="text-center mb-8 lg:mb-10">
                        <h3 class="text-3xl font-extrabold text-slate-900 tracking-tight">Masuk ke Portal</h3>
                        <p class="text-slate-500 text-sm mt-2 font-medium">Akses portal bisnis B2BLink Anda di bawah ini.</p>
                    </div>
                    <form onsubmit="handleLogin(event)" class="space-y-5 lg:space-y-6 max-w-md mx-auto">
                        <div class="space-y-4 sm:space-y-5">
                            <div class="input-group" style="--focus-color:#3b82f6;" id="login-email-group">
                                <label for="login-email" class="block text-xs sm:text-sm font-bold text-slate-700 mb-2 transition-colors tracking-wide">Email Bisnis</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="ph-fill ph-envelope-simple text-lg sm:text-xl text-slate-400"></i></div>
                                    <input type="email" required class="block w-full pl-11 sm:pl-12 pr-4 py-3 sm:py-3.5 border-2 border-slate-200/60 rounded-2xl focus:ring-0 outline-none transition-all bg-white text-slate-800 font-semibold text-sm sm:text-base hover:border-slate-300 shadow-sm" placeholder="email@bisnis.com" id="login-email">
                                </div>
                            </div>
                            <div class="input-group" style="--focus-color:#3b82f6;" id="login-password-group">
                                <div class="flex justify-between items-center mb-2">
                                    <label for="login-password" class="block text-xs sm:text-sm font-bold text-slate-700 transition-colors tracking-wide">Kata Sandi</label>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="ph-fill ph-lock-key text-lg sm:text-xl text-slate-400"></i></div>
                                    <input type="password" required class="block w-full pl-11 sm:pl-12 pr-12 py-3 sm:py-3.5 border-2 border-slate-200/60 rounded-2xl focus:ring-0 outline-none transition-all bg-white text-slate-800 font-semibold text-sm sm:text-base hover:border-slate-300 shadow-sm" placeholder="••••••••" id="login-password">
                                </div>
                            </div>
                        </div>
                        <button type="submit" id="btn-login" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-extrabold py-4 px-4 rounded-2xl transition-all shadow-lg shadow-blue-500/30 flex justify-center items-center text-base sm:text-lg mt-6 sm:mt-8 hover:-translate-y-1">
                            Masuk Sistem <i class="ph-bold ph-arrow-right ml-2 text-lg sm:text-xl"></i>
                        </button>
                    </form>
                </div>

                <!-- REGISTER VIEW -->
                <div id="view-register" class="hidden">
                    <div class="text-center mb-8 lg:mb-10">
                        <h3 class="text-3xl font-extrabold text-slate-900 tracking-tight">Daftar Bisnis Baru</h3>
                        <p class="text-slate-500 text-sm mt-2 font-medium">Bergabung dengan ekosistem B2B terintegrasi hari ini.</p>
                    </div>
                    <form onsubmit="handleRegister(event)" class="space-y-4 sm:space-y-5 max-w-md mx-auto">

                        <div class="space-y-3 sm:space-y-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="ph-fill ph-buildings text-lg sm:text-xl text-slate-400"></i></div>
                                <label for="reg-name" class="sr-only">Nama Usaha / Perusahaan</label>
                                <input type="text" required id="reg-name" class="block w-full pl-11 sm:pl-12 pr-4 py-3 sm:py-3.5 border-2 border-slate-200/60 rounded-2xl focus:ring-0 outline-none bg-white text-slate-800 font-semibold text-sm sm:text-base transition-all hover:border-slate-300 shadow-sm" placeholder="Nama Usaha / Perusahaan">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="ph-fill ph-envelope-simple text-lg sm:text-xl text-slate-400"></i></div>
                                <label for="reg-email" class="sr-only">Email Bisnis Resmi</label>
                                <input type="email" required id="reg-email" class="block w-full pl-11 sm:pl-12 pr-4 py-3 sm:py-3.5 border-2 border-slate-200/60 rounded-2xl focus:ring-0 outline-none bg-white text-slate-800 font-semibold text-sm sm:text-base transition-all hover:border-slate-300 shadow-sm" placeholder="Email Bisnis Resmi">
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="ph-fill ph-lock-key text-lg sm:text-xl text-slate-400"></i></div>
                                <label for="reg-password" class="sr-only">Kata Sandi</label>
                                <input type="password" required id="reg-password" class="block w-full pl-11 sm:pl-12 pr-12 py-3 sm:py-3.5 border-2 border-slate-200/60 rounded-2xl focus:ring-0 outline-none bg-white text-slate-800 font-semibold text-sm sm:text-base transition-all hover:border-slate-300 shadow-sm" placeholder="Buat Kata Sandi (Min. 8 karakter)">
                            </div>
                        </div>
                        <button type="submit" id="btn-register" class="w-full bg-slate-900 hover:bg-black text-white font-extrabold py-3.5 sm:py-4 px-4 rounded-2xl transition-all shadow-xl shadow-slate-900/20 mt-4 sm:mt-6 text-base sm:text-lg hover:-translate-y-1">Buat Akun Bisnis</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentRole = 'umkm';
        const BASE = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\") ?>';

        function switchTab(tab) {
            const vl = document.getElementById('view-login'), vr = document.getElementById('view-register');
            const tl = document.getElementById('tab-login'), tr = document.getElementById('tab-register');
            const ti = document.getElementById('tab-indicator');
            if (tab === 'login') {
                vr.classList.add('hidden'); vl.classList.remove('hidden');
                setTimeout(() => vl.classList.add('fade-enter-active'), 10);
                ti.style.transform = 'translateX(0)';
                tl.className = 'flex-1 py-2.5 lg:py-3 text-sm font-extrabold text-brand-600 transition-colors z-10 relative tracking-wide';
                tr.className = 'flex-1 py-2.5 lg:py-3 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors z-10 relative tracking-wide';
            } else {
                vl.classList.add('hidden'); vr.classList.remove('hidden');
                setTimeout(() => vr.classList.add('fade-enter-active'), 10);
                ti.style.transform = 'translateX(100%)';
                tr.className = 'flex-1 py-2.5 lg:py-3 text-sm font-extrabold text-brand-600 transition-colors z-10 relative tracking-wide';
                tl.className = 'flex-1 py-2.5 lg:py-3 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors z-10 relative tracking-wide';
            }
        }

        function updateTheme(role) {
            currentRole = role;
            const btn = document.getElementById('btn-login');
            const sg = document.getElementById('side-gradient');
            const si = document.getElementById('security-icon');
            if (role === 'umkm') {
                btn.className = 'w-full bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-extrabold py-4 px-4 rounded-2xl transition-all shadow-xl shadow-emerald-500/30 flex justify-center items-center text-base sm:text-lg mt-6 sm:mt-8 hover:-translate-y-1';
                if(sg) sg.className = 'absolute inset-0 bg-gradient-to-br from-emerald-600/30 via-transparent to-transparent transition-colors duration-700 pointer-events-none';
                if(si) { si.classList.remove('text-blue-400'); si.classList.add('text-emerald-400'); }
            } else {
                btn.className = 'w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-extrabold py-4 px-4 rounded-2xl transition-all shadow-xl shadow-blue-500/30 flex justify-center items-center text-base sm:text-lg mt-6 sm:mt-8 hover:-translate-y-1';
                if(sg) sg.className = 'absolute inset-0 bg-gradient-to-br from-blue-600/30 via-transparent to-transparent transition-colors duration-700 pointer-events-none';
                if(si) { si.classList.remove('text-emerald-400'); si.classList.add('text-blue-400'); }
            }
        }

        async function handleLogin(e) {
            e.preventDefault();
            const overlay = document.getElementById('loading-overlay');
            const spinner = document.getElementById('loading-spinner');
            const text = document.getElementById('loading-text');
            const sub = document.getElementById('loading-subtext');

            overlay.classList.remove('hidden'); overlay.classList.add('flex');
            setTimeout(() => { overlay.classList.remove('opacity-0'); overlay.classList.add('opacity-100'); }, 10);

            // Neutral professional brand loading state initially
            spinner.className = `absolute inset-0 rounded-full border-4 border-blue-500 border-t-transparent animate-spin`;
            text.innerText = 'Mengautentikasi...';
            sub.innerText = 'Memverifikasi kredensial akun Anda';

            const res = await fetch(BASE + '/api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('login-email').value,
                    password: document.getElementById('login-password').value
                })
            }).then(r => r.json());

            if (res.status === 'success') {
                localStorage.setItem('jwt_token', res.data.token);

                const role = res.data.role;
                text.innerText = role === 'integrator' ? 'Integration Console Siap!' : (role === 'umkm' ? 'Portal UMKM Siap!' : 'SupplierHub Siap!');
                sub.innerText = role === 'integrator' ? 'Memuat kontrak endpoint lintas aplikasi' : (role === 'umkm' ? 'Menghubungkan ke Katalog Supplier' : 'Memuat Data Gudang & Pesanan');

                spinner.classList.remove('border-t-transparent','animate-spin');
                spinner.innerHTML = '<i class="ph-bold ph-check text-white text-4xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></i>';

                const c = role === 'umkm' ? 'emerald' : (role === 'integrator' ? 'violet' : 'blue');
                spinner.className = `absolute inset-0 rounded-full border-4 border-${c}-500 bg-${c}-500`;

                setTimeout(() => { window.location.href = res.data.redirect; }, 800);
            } else {
                overlay.classList.add('opacity-0'); overlay.classList.remove('opacity-100');
                setTimeout(() => { overlay.classList.add('hidden'); overlay.classList.remove('flex'); }, 300);
                alert(res.message);
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-register');
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="ph-bold ph-spinner animate-spin mr-2 text-xl inline-block"></i> Mendaftarkan...';
            btn.disabled = true;

            const res = await fetch(BASE + '/api/auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('reg-name').value,
                    email: document.getElementById('reg-email').value,
                    password: document.getElementById('reg-password').value,
                    role: 'umkm'
                })
            }).then(r => r.json());

            if (res.status === 'success') {
                btn.innerHTML = '<i class="ph-bold ph-check-circle mr-2 text-xl inline-block"></i> Pendaftaran Berhasil!';
                setTimeout(() => { switchTab('login'); btn.innerHTML = orig; btn.disabled = false; }, 1500);
            } else {
                alert(res.message);
                btn.innerHTML = orig; btn.disabled = false;
            }
        }

        window.onload = () => { updateTheme('supplier'); };
    </script>
</body>
</html>
