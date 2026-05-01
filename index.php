<?php
session_start();
// Cek status login
$is_logged_in = isset($_SESSION['status']) && $_SESSION['status'] == "login";
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StokPintar UMKM - Manajemen Inventori Cerdas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #047857 100%);
            position: relative;
        }
        
        /* Animasi Melayang (Floating) untuk Ornamen */
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-delayed { animation: float 6s ease-in-out 3s infinite; }
        .animate-float-fast { animation: float 4s ease-in-out 1s infinite; }

        /* Animasi Masuk (Fade In Up) */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }

        /* Hover Effect Feature Cards */
        .feature-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Hover Effect Menu Pintar */
        .menu-item .icon-box { transition: all 0.3s ease; }
        .menu-item:hover .icon-box {
            transform: scale(1.15) translateY(-5px);
            background-color: #2563eb; /* text-blue-600 */
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }
        .menu-item:hover span {
            color: #1e40af;
        }

        /* Animasi Pop-up Penjelasan */
        .modal-enter { animation: modalFadeIn 0.3s ease-out forwards; }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="text-gray-800 overflow-x-hidden">

    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm fixed top-0 w-full z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2 fade-in-up">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-200">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <a href="index.php" class="text-2xl font-black text-blue-800 tracking-tight">Stok<span class="text-green-500">Pintar</span></a>
                </div>
                
                <!-- NAVBAR BARU (Sesuai Permintaan) -->
                <div class="hidden md:flex space-x-6 lg:space-x-8 items-center fade-in-up delay-100">
                    <a href="#beranda" class="text-gray-600 hover:text-blue-700 font-bold transition">Beranda</a>
                    <a href="#fitur" class="text-gray-600 hover:text-blue-700 font-bold transition">Keunggulan Sistem</a>
                    
                    <?php if($is_logged_in): ?>
                        <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 transition font-bold shadow-lg shadow-blue-200">Dashboard</a>
                    <?php else: ?>
                        <a href="register.php" class="text-gray-600 hover:text-blue-700 font-bold transition">Daftar</a>
                        <a href="login.php" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 transition font-bold shadow-lg shadow-blue-200">Masuk</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden fade-in-up delay-100">
                    <button class="text-gray-600 focus:outline-none p-2 rounded-lg bg-gray-100">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section (Disesuaikan agar menu tampil di layar pertama / Above the Fold) -->
    <header id="beranda" class="hero-gradient text-white pt-28 pb-32 px-4 relative overflow-hidden text-center border-b-[8px] border-green-400 min-h-[85vh] flex flex-col justify-center">
        <!-- Efek Cahaya / Blobs Animasi di Background -->
        <div class="absolute top-0 left-10 w-72 h-72 bg-blue-500 rounded-full mix-blend-screen filter blur-[80px] opacity-50 animate-float"></div>
        <div class="absolute top-20 right-20 w-80 h-80 bg-green-400 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-float-delayed"></div>
        <div class="absolute -bottom-20 left-1/3 w-96 h-96 bg-purple-500 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-float-fast"></div>

        <!-- Ornamen Ikon 3D / Emoji Melayang -->
        <div class="absolute hidden lg:block top-1/4 left-[15%] text-6xl animate-float drop-shadow-2xl opacity-90 select-none">📦</div>
        <div class="absolute hidden lg:block top-1/3 right-[15%] text-7xl animate-float-delayed drop-shadow-2xl opacity-90 select-none">📈</div>
        <div class="absolute hidden lg:block bottom-1/4 left-[25%] text-5xl animate-float-fast drop-shadow-2xl opacity-80 select-none">🛒</div>
        <div class="absolute hidden lg:block bottom-1/3 right-[25%] text-5xl animate-float drop-shadow-2xl opacity-80 select-none">💡</div>

        <div class="max-w-4xl mx-auto w-full z-10 relative">
            
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold leading-tight mb-4 fade-in-up delay-100 drop-shadow-lg">
                Stok <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-green-300 to-emerald-500">Pintar</span>
            </h1>
            <p class="text-base md:text-xl text-blue-50 mb-8 leading-relaxed max-w-3xl mx-auto fade-in-up delay-200 font-medium">
                Tinggalkan cara lama. Optimalkan stok barang Anda dengan teknologi cerdas kami. Kurangi biaya penyimpanan, hindari kerugian, dan jangan pernah kehabisan stok lagi.
            </p>
        </div>
    </header>

    <!-- MENU PINTAR (Diangkat ke atas agar tidak perlu scroll) -->
    <section class="max-w-4xl mx-auto px-4 relative z-20 -mt-24 mb-16 fade-in-up delay-300">
        <div class="bg-white rounded-[2rem] shadow-2xl p-6 md:p-8 border border-gray-100 relative overflow-hidden">
            <!-- Dekorasi background melengkung di dalam card -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-50 rounded-full blur-2xl opacity-60 pointer-events-none"></div>
            
            <h3 class="text-center text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-6">Menu Pintar</h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 text-center relative z-10">
                <!-- Tombol Menu: Kasir -->
                <button onclick="openModal('modal-kasir')" class="menu-item flex flex-col items-center justify-start focus:outline-none w-full group">
                    <div class="icon-box w-16 h-16 md:w-20 md:h-20 bg-purple-50 text-purple-600 rounded-[1.25rem] flex items-center justify-center mb-3 shadow-sm border border-purple-100">
                        <svg class="w-8 h-8 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <span class="text-sm md:text-base font-bold text-gray-600 transition-colors">Kasir Pintar</span>
                </button>

                <!-- Tombol Menu: Forecasting -->
                <button onclick="openModal('modal-forecasting')" class="menu-item flex flex-col items-center justify-start focus:outline-none w-full group">
                    <div class="icon-box w-16 h-16 md:w-20 md:h-20 bg-blue-50 text-blue-600 rounded-[1.25rem] flex items-center justify-center mb-3 shadow-sm border border-blue-100">
                        <svg class="w-8 h-8 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    </div>
                    <span class="text-sm md:text-base font-bold text-gray-600 transition-colors">Forecasting</span>
                </button>

                <!-- Tombol Menu: EOQ -->
                <button onclick="openModal('modal-eoq')" class="menu-item flex flex-col items-center justify-start focus:outline-none w-full group">
                    <div class="icon-box w-16 h-16 md:w-20 md:h-20 bg-emerald-50 text-emerald-600 rounded-[1.25rem] flex items-center justify-center mb-3 shadow-sm border border-emerald-100">
                        <svg class="w-8 h-8 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span class="text-sm md:text-base font-bold text-gray-600 transition-colors">EOQ (Economic Order Quantity)</span>
                </button>

                <!-- Tombol Menu: ROP -->
                <button onclick="openModal('modal-rop')" class="menu-item flex flex-col items-center justify-start focus:outline-none w-full group">
                    <div class="icon-box w-16 h-16 md:w-20 md:h-20 bg-amber-50 text-amber-500 rounded-[1.25rem] flex items-center justify-center mb-3 shadow-sm border border-amber-100">
                        <svg class="w-8 h-8 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <span class="text-sm md:text-base font-bold text-gray-600 transition-colors">ROP(Reorder Point)</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Fitur Section -->
    <section id="fitur" class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16 fade-in-up">
                <h2 class="text-3xl font-black text-gray-900 mb-4 tracking-tight">Keunggulan Utama Kami</h2>
                <div class="w-24 h-1.5 bg-blue-600 rounded-full mx-auto"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card p-8 bg-white rounded-[2rem] shadow-xl border border-gray-100 fade-in-up delay-100 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-blue-200">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Forecasting (Prediksi)</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">Sistem otomatis mempelajari data bulan kemarin untuk memprediksi secara akurat berapa banyak barang yang akan laku bulan ini.</p>
					<p class="text-gray-500 leading-relaxed font-medium"> </p>
					<p class="text-gray-500 leading-relaxed font-medium">Catatan: Sistem ini tidak akurat untuk barang musiman.</p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card p-8 bg-white rounded-[2rem] shadow-xl border border-gray-100 fade-in-up delay-200 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-green-50 rounded-bl-full -z-10"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-green-400 to-emerald-600 text-white rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-green-200">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Saran Belanja Pintar</h3>
                    <p class="text-gray-500 leading-relaxed font-medium"> Menggunakan Economic Order Quantity & Reorder Point(EOQ & ROP) untuk memberi tahu Anda <b>jumlah paling pas</b> untuk kulakan dan <b>kapan waktu terbaik</b> untuk memesannya.</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card p-8 bg-white rounded-[2rem] shadow-xl border border-gray-100 fade-in-up delay-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50 rounded-bl-full -z-10"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-amber-400 to-orange-500 text-white rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-amber-200">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Notifikasi WhatsApp</h3>
                    <p class="text-gray-500 leading-relaxed font-medium">Saat stok menipis, alarm akan menyala dan menyiapkan pesan WhatsApp otomatis agar Anda bisa langsung order ke supplier dengan 1 klik.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12 px-4 mt-10">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center">
            <div class="mb-6 md:mb-0 text-center md:text-left">
                <span class="text-2xl font-black text-white">Stok<span class="text-green-500">Pintar</span></span>
                <p class="mt-2 text-sm font-medium">Memberdayakan UMKM dengan Manajemen Inventori Modern.</p>
            </div>
            <div class="flex space-x-6 font-medium text-sm">
                <p>&copy; <?php echo date("Y"); ?> StokPintar UMKM. Semua Hak Dilindungi.</p>
            </div>
        </div>
    </footer>

    <!-- ========================================================= -->
    <!-- AREA MODAL (TAMPILAN UI TRIAL/MOCKUP SEPERTI APLIKASI ASLI) -->
    <!-- ========================================================= -->

    <!-- Modal Kasir -->
    <div id="modal-kasir" class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter">
            <div class="bg-indigo-600 p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Fitur Kasir Pintar
                </h3>
                <button onclick="closeModal('modal-kasir')" class="text-white hover:text-indigo-200 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-5 text-center leading-relaxed">Catat penjualan dengan cepat. Sistem akan otomatis memotong stok di gudang dan mencetak struk secara instan.</p>
                
                <!-- Mockup UI Kasir -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 shadow-inner">
                    <div class="flex justify-between items-center border-b border-gray-200 pb-3 mb-3">
                        <span class="font-bold text-gray-700 text-sm">Keranjang Kasir</span>
                        <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-1 rounded">INV-001</span>
                    </div>
                    <div class="flex justify-between items-center mb-2 text-sm text-gray-800">
                        <span>Keripik Mbote (x2)</span>
                        <span class="font-bold">Rp 30.000</span>
                    </div>
                    <div class="flex justify-between items-center mb-3 text-sm text-gray-800">
                        <span>Jus Mangga (x1)</span>
                        <span class="font-bold">Rp 10.000</span>
                    </div>
                    <div class="border-t border-gray-200 pt-3 flex justify-between items-center mb-4">
                        <span class="font-bold text-gray-600 text-sm">TOTAL</span>
                        <span class="font-black text-indigo-600 text-lg">Rp 40.000</span>
                    </div>
                    <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl transition text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Bayar Sekarang
                    </button>
                </div>

                <button onclick="closeModal('modal-kasir')" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold py-3 rounded-xl transition mt-4 text-sm shadow-sm">Tutup Cuplikan</button>
            </div>
        </div>
    </div>

    <!-- Modal Forecasting -->
    <div id="modal-forecasting" class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter">
            <div class="bg-blue-600 p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    Fitur Forecasting
                </h3>
                <button onclick="closeModal('modal-forecasting')" class="text-white hover:text-blue-200 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-5 text-center leading-relaxed">Sistem membaca riwayat data bulan lalu untuk memprediksi dengan akurat seberapa laku barang Anda di masa depan.</p>
                
                <!-- Mockup UI Forecasting -->
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden text-xs">
                    <div class="bg-blue-50 p-3 border-b border-blue-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                        <span class="font-bold text-blue-800">Prediksi Penjualan Masa Depan</span>
                    </div>
                    <table class="w-full text-center">
                        <tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="p-3">Bulan</th>
                            <th class="p-3 border-l border-gray-100">Aktual</th>
                            <th class="p-3 border-l border-gray-100 text-blue-600">Forecast</th>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="p-3 font-bold text-gray-800">April</td>
                            <td class="p-3 font-bold text-gray-400 bg-gray-50/50">120</td>
                            <td class="p-3 font-black text-blue-600 text-sm">115</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="p-3 bg-blue-50/30 text-blue-700 font-bold flex items-center justify-center gap-1 border-t border-gray-100">
                                Ketepatan Akurasi: 95.8% ✓
                            </td>
                        </tr>
                    </table>
                </div>

                <button onclick="closeModal('modal-forecasting')" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold py-3 rounded-xl transition mt-4 text-sm shadow-sm">Tutup Cuplikan</button>
            </div>
        </div>
    </div>

    <!-- Modal EOQ -->
    <div id="modal-eoq" class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter">
            <div class="bg-[#10b981] p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Saran Pemesanan (EOQ)
                </h3>
                <button onclick="closeModal('modal-eoq')" class="text-white hover:text-green-200 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-5 text-center leading-relaxed">Dapatkan saran jumlah belanja paling hemat agar ongkos kirim murah dan gudang tidak penuh/kedaluwarsa.</p>
                
                <!-- Mockup UI EOQ -->
                <div class="bg-green-50 border border-green-100 rounded-2xl p-6 text-center shadow-inner relative overflow-hidden">
                    <div class="absolute right-0 top-0 opacity-10">
                        <svg class="w-24 h-24 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-black text-green-700 uppercase tracking-widest mb-2 bg-white px-2 py-1 rounded-md inline-block shadow-sm">Saran Belanja Optimal (EOQ)</p>
                        <p class="text-5xl font-black text-green-700 mb-1">29 <span class="text-lg font-bold text-green-600">Kg</span></p>
                        <p class="text-xs text-gray-500 font-medium mt-2">Jumlah kulakan ideal agar ongkir hemat</p>
                    </div>
                </div>

                <button onclick="closeModal('modal-eoq')" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold py-3 rounded-xl transition mt-4 text-sm shadow-sm">Tutup Cuplikan</button>
            </div>
        </div>
    </div>

    <!-- Modal ROP -->
    <div id="modal-rop" class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter">
            <div class="bg-amber-500 p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Batas Peringatan (ROP)
                </h3>
                <button onclick="closeModal('modal-rop')" class="text-white hover:text-amber-200 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-5 text-center leading-relaxed">Alarm otomatis kapan Anda harus segera kulakan sebelum stok habis total, dilengkapi integrasi langsung ke WhatsApp.</p>
                
                <!-- Mockup UI ROP -->
                <div class="bg-white border border-red-200 rounded-2xl p-4 shadow-sm flex flex-col gap-3">
                    <div class="flex items-start justify-between border-b border-gray-100 pb-3">
                        <div>
                            <p class="font-bold text-gray-900 text-base">Kurma Sukari</p>
                            <span class="text-[10px] text-gray-500 font-mono mt-1 font-medium bg-gray-100 inline-block px-2 py-0.5 rounded">KODE: 003</span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-lg text-xs font-bold shadow-sm border border-orange-200">Sisa 79 kg</span>
                            <span class="text-[9px] text-gray-400 font-bold mt-1 uppercase tracking-wider">Batas ROP: 160 KG</span>
                        </div>
                    </div>
                    
                    <button class="w-full bg-[#25D366] text-white text-xs font-bold py-2.5 rounded-xl flex items-center justify-center shadow-md shadow-green-200">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.886.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        Kirim Otomatis ke Supplier
                    </button>
                </div>

                <button onclick="closeModal('modal-rop')" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold py-3 rounded-xl transition mt-4 text-sm shadow-sm">Tutup Cuplikan</button>
            </div>
        </div>
    </div>

    <!-- JavaScript untuk mengontrol Modal Pop-up -->
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Mencegah background scroll
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Kembalikan scroll
        }

        // Tutup modal kalau user klik di luar area putih (overlay)
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-black/60')) {
                event.target.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>