<?php
session_start();
include 'koneksi.php';

// =========================================================
// 1. PROTEKSI RBAC (ROLE-BASED ACCESS CONTROL)
// =========================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php");
    exit;
}

// GEMBOK: Jika yang masuk adalah kasir, tendang kembali ke halaman kasir!
if (isset($_SESSION['role']) && $_SESSION['role'] == 'kasir') {
    echo "<script>alert('Akses Ditolak! Area ini khusus Pemilik Toko (Owner).'); window.location='kasir.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$nama_toko = $_SESSION['nama_toko'] ?? 'Toko Saya';
$nama_pemilik = $_SESSION['nama_pemilik'] ?? 'Admin';

// 2. Hitung total barang (Untuk Menu Inventori)
$query_barang = mysqli_query($conn, "SELECT COUNT(*) as total FROM barang");
$data_barang = mysqli_fetch_assoc($query_barang);
$total_barang = $data_barang['total'] ?? 0;

// =========================================================================
// 3. Cek stok menipis (SINKRONISASI 100% DENGAN PREDIKSI WMA & ROP)
// =========================================================================
$low_stock_count = 0;
$query_semua_barang = mysqli_query($conn, "SELECT * FROM barang");

// Query 4 bulan terakhir sekaligus untuk efisiensi
$sales_history_all = [];
$q_all_monthly = mysqli_query($conn, "
    SELECT dt.barang_id, YEAR(t.tanggal_transaksi) as thn, MONTH(t.tanggal_transaksi) as bln, SUM(dt.jumlah_terjual) as total
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE t.tanggal_transaksi >= DATE_SUB(NOW(), INTERVAL 4 MONTH)
    GROUP BY dt.barang_id, thn, bln
");
while($r = mysqli_fetch_assoc($q_all_monthly)) {
    $sales_history_all[$r['barang_id']][$r['thn']][$r['bln']] = (float)$r['total'];
}

$curr_y = (int)date('Y');
$curr_m = (int)date('n');

while($row = mysqli_fetch_assoc($query_semua_barang)) {
    $id_b = $row['id'];
    
    // Tarik data t-1, t-2, t-3 secara terbalik (Mundur)
    $m1 = $curr_m - 1; $y1 = $curr_y; if($m1 <= 0) { $m1 += 12; $y1--; }
    $m2 = $curr_m - 2; $y2 = $curr_y; if($m2 <= 0) { $m2 += 12; $y2--; }
    $m3 = $curr_m - 3; $y3 = $curr_y; if($m3 <= 0) { $m3 += 12; $y3--; }

    $a1 = $sales_history_all[$id_b][$y1][$m1] ?? 0;
    $a2 = $sales_history_all[$id_b][$y2][$m2] ?? 0;
    $a3 = $sales_history_all[$id_b][$y3][$m3] ?? 0;

    // Perhitungan WMA 3-Periode untuk D (Demand Bulan Depan)
    $w1 = 3; $w2 = 2; $w3 = 1;
    $sum_w = $w1 + $w2 + $w3;
    $D_wma = (($a1 * $w1) + ($a2 * $w2) + ($a3 * $w3)) / $sum_w;
    
    $D = ceil($D_wma); // D = Permintaan (Prediksi WMA dibulatkan)

    // Fallback: Jika belum ada histori 3 bulan, pakai total riwayat bulan ini saja.
    if ($D == 0) {
        $q_fallback = mysqli_query($conn, "SELECT SUM(jumlah_terjual) as total FROM detail_transaksi dt JOIN transaksi t ON dt.transaksi_id = t.id WHERE dt.barang_id = '$id_b' AND t.tanggal_transaksi >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $res_fallback = mysqli_fetch_assoc($q_fallback);
        $D = ceil((float)($res_fallback['total'] ?? 0));
    }

    $d_per_hari = $D / 30;

    // Hitung ROP (Reorder Point)
    $L = $row['lead_time'] ?? 3;
    $rop = ($d_per_hari * $L);
    $stok = $row['stok_sekarang'];
    
    // Jika stok kurang dari/sama dengan ROP, atau stok habis (0), hitung sebagai kritis
    if (($stok <= $rop && $D > 0) || $stok <= 0) {
        $low_stock_count++;
    }
}
// =========================================================================

// 4. Hitung omzet hari ini
$today = date('Y-m-d');
$query_sales = mysqli_query($conn, "SELECT SUM(total_bayar) as total FROM transaksi WHERE DATE(tanggal_transaksi) = '$today'");
$data_sales = mysqli_fetch_assoc($query_sales);
$sales_today = $data_sales['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        
        /* Animasi Pop-up Penjelasan */
        .modal-enter { animation: modalFadeIn 0.3s ease-out forwards; }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-12">

    <!-- Top Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <span class="text-2xl font-black text-blue-800 tracking-tight">Stok<span class="text-green-500">Pintar</span></span>
                </div>
                <div class="flex items-center space-x-5">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($nama_pemilik); ?></p>
                        <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($nama_toko); ?></p>
                    </div>
                    <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-700 font-bold text-sm border-2 border-white shadow-sm">
                        <?php echo strtoupper(substr($nama_pemilik, 0, 2)); ?>
                    </div>
                    <a href="logout.php" class="bg-red-50 text-red-600 p-2.5 rounded-xl hover:bg-red-500 hover:text-white transition-colors group" title="Keluar">
                        <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4-4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8 flex-grow">
        
        <!-- Welcome & Banner Omzet -->
        <div class="mb-10">
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-3xl font-extrabold text-gray-900">Halo, <?php echo explode(' ', trim($nama_pemilik))[0]; ?>! 👋</h1>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg uppercase tracking-wider border border-blue-200">Akses Owner</span>
            </div>
            <p class="text-gray-500 font-medium mb-6">Pilih menu di bawah untuk mengelola bisnis Anda hari ini.</p>
            
            <!-- Banner Omzet Hari Ini -->
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-3xl p-8 text-white shadow-lg shadow-emerald-200 relative overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between">
                <div class="absolute -right-10 -top-10 opacity-20">
                    <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <div class="relative z-10">
                    <p class="text-emerald-100 font-bold uppercase tracking-widest text-sm mb-1">Pendapatan Hari Ini</p>
                    <h2 class="text-4xl md:text-5xl font-black">Rp <?php echo number_format($sales_today, 0, ',', '.'); ?></h2>
                </div>
                <div class="mt-4 md:mt-0 relative z-10 bg-white/20 backdrop-blur-md px-4 py-2 rounded-xl border border-white/30 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-yellow-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    <span class="font-bold text-sm text-white">Transaksi Aktif</span>
                </div>
            </div>
        </div>

        <!-- Menu Utama (Grid Responsif: 2 kolom di tablet, 3 kolom di desktop) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 relative">
            
            <!-- 1. Menu Kasir -->
            <a href="kasir.php" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 card-hover transition-all block relative">
                <!-- Tombol Info (Membuka Pop-up) -->
                <button onclick="openInfo(event, 'info-kasir')" class="absolute top-6 right-6 p-2 text-gray-300 hover:text-blue-600 hover:bg-blue-50 rounded-full transition-all z-20" title="Pelajari Fitur Ini">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                
                <div class="flex items-center justify-between mb-6">
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300 shadow-sm border border-blue-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Kasir Penjualan</h3>
                <p class="text-gray-500 font-medium pr-8">Lakukan transaksi, kelola pesanan, dan cetak struk.</p>
            </a>

            <!-- 2. Menu Inventori -->
            <a href="inventori.php" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 card-hover transition-all block relative">
                <!-- Tombol Info -->
                <button onclick="openInfo(event, 'info-inventori')" class="absolute top-6 right-6 p-2 text-gray-300 hover:text-purple-600 hover:bg-purple-50 rounded-full transition-all z-20" title="Pelajari Fitur Ini">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>

                <div class="flex items-center justify-between mb-6">
                    <div class="w-16 h-16 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition-colors duration-300 shadow-sm border border-purple-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <!-- Angka Jumlah Barang -->
                    <div class="bg-purple-100 text-purple-700 px-4 py-2 rounded-xl font-black text-xl shadow-sm mr-8">
                        <?php echo $total_barang; ?> <span class="text-xs font-bold text-purple-500 uppercase">Item</span>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Stok Barang</h3>
                <p class="text-gray-500 font-medium pr-8">Tambah, edit harga, dan info inventori gudang Anda.</p>
            </a>

            <!-- 3. Menu Peringatan Stok (SINKRON DENGAN ROP) -->
            <a href="stok_menipis.php" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 card-hover transition-all block relative overflow-hidden">
                <!-- Tombol Info -->
                <button onclick="openInfo(event, 'info-rop')" class="absolute top-6 right-6 p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-full transition-all z-20" title="Pelajari Fitur Ini">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>

                <?php if($low_stock_count > 0): ?>
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-red-100 rounded-full blur-3xl opacity-60"></div>
                <?php endif; ?>
                
                <div class="flex items-center justify-between mb-6 relative z-10">
                    <div class="w-16 h-16 <?php echo $low_stock_count > 0 ? 'bg-red-50 text-red-500 border border-red-200 group-hover:bg-red-500' : 'bg-gray-50 text-gray-500 border border-gray-200 group-hover:bg-gray-500'; ?> rounded-2xl flex items-center justify-center group-hover:text-white transition-colors duration-300 shadow-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <?php if($low_stock_count > 0): ?>
                        <div class="bg-red-500 text-white px-4 py-2 rounded-xl font-black text-xl shadow-md animate-pulse mr-8">
                            <?php echo $low_stock_count; ?> <span class="text-xs font-bold text-red-200 uppercase">Kritis</span>
                        </div>
                    <?php else: ?>
                        <div class="bg-green-100 text-green-700 px-4 py-2 rounded-xl font-bold text-sm shadow-sm mr-8">
                            Semua Aman ✓
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2 relative z-10">Peringatan Stok</h3>
                <p class="text-gray-500 font-medium relative z-10 pr-8">Daftar barang mau habis yang perlu segera dibeli.</p>
            </a>

            <!-- 4. Menu Analisis Laporan -->
            <a href="laporan_analisis.php" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 card-hover transition-all block relative overflow-hidden">
                <!-- Tombol Info -->
                <button onclick="openInfo(event, 'info-analisis')" class="absolute top-6 right-6 p-2 text-gray-300 hover:text-yellow-600 hover:bg-yellow-50 rounded-full transition-all z-20" title="Pelajari Fitur Ini">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>

                <div class="flex items-center justify-between mb-6 relative z-10">
                    <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-amber-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2 relative z-10">Laporan & Analisis</h3>
                <p class="text-gray-500 font-medium relative z-10 pr-8">Akses data Prediksi (Forecasting), EOQ, ROP, dan Histori Penjualan.</p>
            </a>

            <!-- 5. MENU BARU: Manajemen Karyawan (RBAC) -->
            <a href="karyawan.php" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 card-hover transition-all block relative">
                <!-- Tombol Info -->
                <button onclick="openInfo(event, 'info-karyawan')" class="absolute top-6 right-6 p-2 text-gray-300 hover:text-indigo-600 hover:bg-indigo-50 rounded-full transition-all z-20" title="Pelajari Fitur Ini">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                
                <div class="flex items-center justify-between mb-6">
                    <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300 shadow-sm border border-indigo-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Manajemen Karyawan</h3>
                <p class="text-gray-500 font-medium pr-8">Kelola akun dan batasi akses login staf kasir Anda.</p>
            </a>

        </div>
    </main>

    <!-- ======================================================= -->
    <!-- AREA MODAL POP-UP EDUKASI (VISUAL & BAHASA AWAM)        -->
    <!-- ======================================================= -->

    <!-- Modal Kasir -->
    <div id="info-kasir" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter relative">
            <button onclick="closeInfo('info-kasir')" class="absolute top-4 right-4 text-white hover:text-gray-200 transition z-10"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-center text-white">
                <div class="text-6xl mb-2 drop-shadow-md">🛒</div>
                <h3 class="text-xl font-bold">Kasir Penjualan</h3>
                <p class="text-xs text-blue-200 mt-1 uppercase tracking-widest">(Point of Sale)</p>
            </div>
            <div class="p-6 space-y-4 text-gray-700 text-sm">
                <p class="leading-relaxed">Lebih dari sekadar kalkulator, ini adalah <b>sistem kasir digital</b> warung Anda.</p>
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
                    <p class="italic">"Setiap kali Anda mengetik barang yang dibeli pelanggan di sini, sistem akan menghitung kembaliannya dan <b>otomatis mengurangi sisa stok</b> di gudang."</p>
                </div>
                <button onclick="closeInfo('info-kasir')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition mt-2">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Modal Inventori -->
    <div id="info-inventori" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter relative">
            <button onclick="closeInfo('info-inventori')" class="absolute top-4 right-4 text-white hover:text-gray-200 transition z-10"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            <div class="bg-gradient-to-r from-purple-600 to-fuchsia-600 p-8 text-center text-white">
                <div class="text-6xl mb-2 drop-shadow-md">📦</div>
                <h3 class="text-xl font-bold">Stok Barang</h3>
                <p class="text-xs text-purple-200 mt-1 uppercase tracking-widest">(Inventori Gudang)</p>
            </div>
            <div class="p-6 space-y-4 text-gray-700 text-sm">
                <p class="leading-relaxed">Ini adalah <b>Gudang Digital</b> Anda. Catat dan daftarkan semua barang jualan Anda di menu ini.</p>
                <div class="bg-purple-50 p-4 rounded-xl border border-purple-100">
                    <p class="italic">"Sistem akan mencatat sisa stok dengan sangat teliti siang dan malam. Anda juga bisa mengatur harga jual hingga mencatat ongkos kirim barang di sini."</p>
                </div>
                <button onclick="closeInfo('info-inventori')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition mt-2">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Modal ROP -->
    <div id="info-rop" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter relative">
            <button onclick="closeInfo('info-rop')" class="absolute top-4 right-4 text-white hover:text-gray-200 transition z-10"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            <div class="bg-gradient-to-r from-red-500 to-rose-500 p-8 text-center text-white">
                <div class="text-6xl mb-2 drop-shadow-md">🚨</div>
                <h3 class="text-xl font-bold">Peringatan Stok</h3>
                <p class="text-xs text-red-200 mt-1 uppercase tracking-widest">(Batas Aman / ROP)</p>
            </div>
            <div class="p-6 space-y-4 text-gray-700 text-sm">
                <p class="leading-relaxed">Menu ini berfungsi sebagai <b>Alarm Pintar</b> sebelum dagangan Anda kosong dan pelanggan kecewa.</p>
                <div class="bg-red-50 p-4 rounded-xl border border-red-100">
                    <p class="italic">"Jika butuh waktu 3 hari dari pesan ke *supplier* sampai barang tiba, sistem akan menyalakan alarm peringatan tepat saat sisa stok di toko Anda hanya cukup untuk 3 hari ke depan!"</p>
                </div>
                <button onclick="closeInfo('info-rop')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition mt-2">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Modal Forecasting & EOQ -->
    <div id="info-analisis" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter relative">
            <button onclick="closeInfo('info-analisis')" class="absolute top-4 right-4 text-white hover:text-gray-200 transition z-10"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            <div class="bg-gradient-to-r from-yellow-500 to-amber-500 p-8 text-center text-white">
                <div class="text-6xl mb-2 drop-shadow-md">📊</div>
                <h3 class="text-xl font-bold">Laporan & Prediksi</h3>
                <p class="text-xs text-yellow-100 mt-1 uppercase tracking-widest">(Forecasting & EOQ)</p>
            </div>
            <div class="p-6 space-y-4 text-gray-700 text-sm">
                <p class="leading-relaxed">Ini adalah <b>Pusat Kecerdasan</b> dari StokPintar yang memiliki 2 otak utama:</p>
                <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-200 space-y-3">
                    <p><b>1. Tebak Laku <span class="text-yellow-700">(Forecasting)</span>:</b> <br>Sistem melihat riwayat bulan lalu untuk memprediksi berapa banyak barang akan laku bulan ini.</p>
                    <hr class="border-yellow-200">
                    <p><b>2. Saran Belanja <span class="text-yellow-700">(EOQ)</span>:</b> <br>Sistem menghitung angka yang paling pas untuk kulakan agar ongkos kirim dan biaya sewa gudang sangat murah.</p>
                </div>
                <button onclick="closeInfo('info-analisis')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition mt-2">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Modal Baru: Karyawan -->
    <div id="info-karyawan" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden modal-enter relative">
            <button onclick="closeInfo('info-karyawan')" class="absolute top-4 right-4 text-white hover:text-gray-200 transition z-10"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-8 text-center text-white">
                <div class="text-6xl mb-2 drop-shadow-md">👥</div>
                <h3 class="text-xl font-bold">Manajemen Karyawan</h3>
                <p class="text-xs text-indigo-200 mt-1 uppercase tracking-widest">(Kontrol Akses Kasir)</p>
            </div>
            <div class="p-6 space-y-4 text-gray-700 text-sm">
                <p class="leading-relaxed">Menu ini memberikan Anda kendali penuh untuk <b>membuat dan mengatur akun karyawan</b> (Kasir).</p>
                <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                    <p class="italic">"Akun yang Anda buat di sini hanya memiliki akses ke layar Kasir Penjualan. Karyawan <b>tidak bisa</b> melihat omzet, laporan, maupun mengubah stok gudang Anda."</p>
                </div>
                <button onclick="closeInfo('info-karyawan')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3.5 rounded-xl transition mt-2">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <!-- JS Untuk Mengatur Pop-up -->
    <script>
        // Membuka modal dan mencegah tag <a> pindah halaman
        function openInfo(event, modalId) {
            event.preventDefault(); // Cegah pindah ke halaman lain
            event.stopPropagation(); // Cegah event klik merembet ke elemen luar
            
            document.getElementById(modalId).classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Hilangkan scroll saat pop up muncul
        }

        // Menutup modal
        function closeInfo(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto'; // Kembalikan scroll
        }

        // Klik area hitam di luar pop-up untuk menutup
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-black/60')) {
                event.target.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    </script>
    
    <footer class="text-center mt-auto">
        <p class="text-xs text-gray-400">&copy; <?php echo date("Y"); ?> StokPintar UMKM - Memberdayakan Usaha Anda.</p>
    </footer>

</body>
</html>