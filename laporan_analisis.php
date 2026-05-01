<?php
session_start();
include 'koneksi.php';

// =========================================================
// 1. PROTEKSI HALAMAN & RBAC (ROLE-BASED ACCESS CONTROL)
// =========================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php");
    exit;
}

// GEMBOK: Jika yang masuk adalah kasir, tendang kembali ke halaman kasir!
if (isset($_SESSION['role']) && $_SESSION['role'] == 'kasir') {
    echo "<script>alert('Akses Ditolak! Halaman Laporan & Analisis khusus Pemilik Toko (Owner).'); window.location='kasir.php';</script>";
    exit;
}

// 2. LOGIKA IMPORT DATA CSV (HISTORIS TRANSAKSI) DENGAN DEBUGGING
$import_message = "";
$import_status = "";
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ringkasan';

if (isset($_POST['import_csv']) && isset($_FILES['file_csv'])) {
    $active_tab = 'transaksi'; // Pindah tab otomatis
    $file = $_FILES['file_csv']['tmp_name'];
    
    if ($file) {
        $handle = fopen($file, "r");
        
        $first_line = fgets($handle);
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';
        rewind($handle);

        $row_count = 0;
        $success_count = 0;
        
        // Pastikan ada session user_id untuk menghindari error kolom kosong
        $id_user_login = $_SESSION['user_id'] ?? 1; 
        
        mysqli_begin_transaction($conn);
        try {
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $row_count++;
                if ($row_count == 1) continue; 
                
                if(count($data) >= 5) {
                    $tanggal = mysqli_real_escape_string($conn, trim($data[0]));
                    $nama_barang = mysqli_real_escape_string($conn, trim($data[1]));
                    $qty = (float)trim($data[2]);
                    $harga = (int)trim($data[3]);
                    $metode = mysqli_real_escape_string($conn, trim($data[4]));
                    
                    // CEK NAMA BARANG
                    $q_brg = mysqli_query($conn, "SELECT id FROM barang WHERE nama_barang = '$nama_barang' LIMIT 1");
                    
                    if (mysqli_num_rows($q_brg) > 0) {
                        $b_data = mysqli_fetch_assoc($q_brg);
                        $id_barang = $b_data['id'];
                        $subtotal = $qty * $harga;
                        
                        // Perbaikan Nomor Invoice agar 100% Unik (menyertakan $row_count)
                        $invoice = "INV-CSV-" . date('ymd', strtotime($tanggal)) . "-" . $row_count . "-" . rand(1000, 9999);
                        $datetime = (strlen($tanggal) <= 10) ? $tanggal . " 12:00:00" : $tanggal;
                        
                        $q_trx = "INSERT INTO transaksi (user_id, no_invoice, total_bayar, metode_pembayaran, bayar, kembali, tanggal_transaksi) 
                                  VALUES ('$id_user_login', '$invoice', '$subtotal', '$metode', '$subtotal', '0', '$datetime')";
                        
                        if (!mysqli_query($conn, $q_trx)) {
                            throw new Exception("Error Tabel Transaksi: " . mysqli_error($conn));
                        }
                        
                        $trx_id = mysqli_insert_id($conn);
                        
                        $q_dtl = "INSERT INTO detail_transaksi (transaksi_id, barang_id, jumlah_terjual, harga_satuan, subtotal) 
                                  VALUES ('$trx_id', '$id_barang', '$qty', '$harga', '$subtotal')";
                        
                        if (!mysqli_query($conn, $q_dtl)) {
                            throw new Exception("Error Tabel Detail: " . mysqli_error($conn));
                        }
                        
                        $success_count++;
                    }
                }
            }
            mysqli_commit($conn);
            
            // Evaluasi Kesuksesan
            if ($success_count > 0) {
                $import_message = "Berhasil mengimpor $success_count baris transaksi masa lalu!";
                $import_status = "success";
            } else {
                $import_message = "File CSV terbaca, tapi 0 data masuk. Pastikan NAMA BARANG di CSV sama persis dengan di Website!";
                $import_status = "error";
            }
            
        } catch(Exception $e) {
            mysqli_rollback($conn);
            $import_message = "Gagal Impor - " . $e->getMessage();
            $import_status = "error";
        }
        fclose($handle);
    }
}

// 3. Logika Filter Bulan & Tahun
$bulan_filter = $_GET['bulan'] ?? date('m');
$tahun_filter = $_GET['tahun'] ?? date('Y');

// 4. Ambil Data Riwayat Penjualan untuk Tabel
$query_riwayat = mysqli_query($conn, "SELECT t.no_invoice, t.tanggal_transaksi, b.nama_barang, dt.jumlah_terjual, dt.subtotal, t.metode_pembayaran 
    FROM detail_transaksi dt 
    JOIN transaksi t ON dt.transaksi_id = t.id 
    JOIN barang b ON dt.barang_id = b.id 
    WHERE MONTH(t.tanggal_transaksi) = '$bulan_filter' AND YEAR(t.tanggal_transaksi) = '$tahun_filter' 
    ORDER BY t.tanggal_transaksi DESC");

// 5. Logika Grafik Penjualan Harian
$jumlah_hari = cal_days_in_month(CAL_GREGORIAN, (int)$bulan_filter, (int)$tahun_filter);
$data_harian = array_fill(1, $jumlah_hari, 0); 

$query_grafik = mysqli_query($conn, "SELECT DAY(tanggal_transaksi) as tgl, SUM(total_bayar) as total 
    FROM transaksi 
    WHERE MONTH(tanggal_transaksi) = '$bulan_filter' AND YEAR(tanggal_transaksi) = '$tahun_filter' 
    GROUP BY DAY(tanggal_transaksi)");

while($g = mysqli_fetch_assoc($query_grafik)) {
    $data_harian[(int)$g['tgl']] = (int)$g['total'];
}

$grafik_labels = array_keys($data_harian);
$grafik_values = array_values($data_harian);

// 6. Ringkasan Statistik
$q_stats = mysqli_query($conn, "SELECT SUM(total_bayar) as omzet, COUNT(id) as total_trx 
    FROM transaksi WHERE MONTH(tanggal_transaksi) = '$bulan_filter' AND YEAR(tanggal_transaksi) = '$tahun_filter'");
$stats = mysqli_fetch_assoc($q_stats);

// 7. PRA-KALKULASI ANALISIS EOQ & ROP
$analysis_data = [];
$query_barang = mysqli_query($conn, "SELECT * FROM barang");

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

while($row = mysqli_fetch_assoc($query_barang)) {
    $id_b = $row['id'];
    $satuan = $row['satuan'] ?? 'Unit';
    
    $m1 = $curr_m - 1; $y1 = $curr_y; if($m1 <= 0) { $m1 += 12; $y1--; }
    $m2 = $curr_m - 2; $y2 = $curr_y; if($m2 <= 0) { $m2 += 12; $y2--; }
    $m3 = $curr_m - 3; $y3 = $curr_y; if($m3 <= 0) { $m3 += 12; $y3--; }

    $a1 = $sales_history_all[$id_b][$y1][$m1] ?? 0;
    $a2 = $sales_history_all[$id_b][$y2][$m2] ?? 0;
    $a3 = $sales_history_all[$id_b][$y3][$m3] ?? 0;

    $w1 = 3; $w2 = 2; $w3 = 1;
    $sum_w = $w1 + $w2 + $w3;
    $D_wma = (($a1 * $w1) + ($a2 * $w2) + ($a3 * $w3)) / $sum_w;
    
    $D = ceil($D_wma);

    if ($D == 0) {
        $q_fallback = mysqli_query($conn, "SELECT SUM(jumlah_terjual) as total FROM detail_transaksi dt JOIN transaksi t ON dt.transaksi_id = t.id WHERE dt.barang_id = '$id_b' AND t.tanggal_transaksi >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $res_fallback = mysqli_fetch_assoc($q_fallback);
        $D = ceil((float)($res_fallback['total'] ?? 0));
    }

    $d_per_hari = $D / 30;

    $S = $row['biaya_pesan'] ?? 10000;
    $H = $row['biaya_simpan'] ?? 500;
    $eoq = ($H > 0 && $D > 0) ? sqrt((2 * ($D * 12) * $S) / $H) : 0;

    $L = $row['lead_time'] ?? 3;
    $rop = ($d_per_hari * $L);
    $stok = $row['stok_sekarang'];
    $perlu_pesan = ($stok <= $rop && $D > 0) || $stok <= 0;

    $row['satuan'] = $satuan;
    $row['D'] = $D;
    $row['eoq'] = ceil($eoq);
    $row['rop'] = ceil($rop);
    $row['d_per_hari'] = $d_per_hari;
    $row['perlu_pesan'] = $perlu_pesan;
    
    $analysis_data[] = $row;
}

// 8. LOGIKA EVALUASI WMA & PREDIKSI BEBERAPA BULAN KE DEPAN
$selected_barang_id = $_GET['prediksi_barang_id'] ?? null;
$selected_tahun = $_GET['prediksi_tahun'] ?? date('Y');

$wma_results = [];
$future_predictions = [];
$avg_mape = 0;
$avg_akurasi = null;
$nama_barang_terpilih = "";

if ($selected_barang_id) {
    $active_tab = 'forecasting';
    
    $q_nama = mysqli_query($conn, "SELECT nama_barang FROM barang WHERE id = '$selected_barang_id'");
    $b_data = mysqli_fetch_assoc($q_nama);
    $nama_barang_terpilih = $b_data['nama_barang'] ?? 'Produk';

    $tahun_lalu = $selected_tahun - 1;
    $q_monthly = mysqli_query($conn, "
        SELECT YEAR(t.tanggal_transaksi) as thn, MONTH(t.tanggal_transaksi) as bln, SUM(dt.jumlah_terjual) as total
        FROM detail_transaksi dt
        JOIN transaksi t ON dt.transaksi_id = t.id
        WHERE dt.barang_id = '$selected_barang_id' 
          AND (YEAR(t.tanggal_transaksi) = '$selected_tahun' OR YEAR(t.tanggal_transaksi) = '$tahun_lalu')
        GROUP BY thn, bln
    ");
    
    $sales_data = [];
    while($r = mysqli_fetch_assoc($q_monthly)) {
        $sales_data[$r['thn']][$r['bln']] = (float)$r['total'];
    }
    
    $get_sales = function($y, $m) use ($sales_data) {
        if ($m <= 0) {
            $m += 12;
            $y -= 1;
        }
        return $sales_data[$y][$m] ?? 0;
    };

    $weights = [3, 2, 1];
    $sum_weights = array_sum($weights);
    $months_name = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    
    $total_mape = 0;
    $count_mape = 0;

    $current_y = (int)date('Y');
    $current_m = (int)date('n');

    $hist_t3 = $get_sales($selected_tahun, -2);
    $hist_t2 = $get_sales($selected_tahun, -1);
    $hist_t1 = $get_sales($selected_tahun, 0);
    $sliding_history = [$hist_t3, $hist_t2, $hist_t1];

    for ($m = 1; $m <= 12; $m++) {
        $aktual = $get_sales($selected_tahun, $m);
        $is_future = ($selected_tahun > $current_y) || ($selected_tahun == $current_y && $m > $current_m);

        $has_past_data = ($sliding_history[0] > 0 || $sliding_history[1] > 0 || $sliding_history[2] > 0);
        
        $wma = null;
        $mape = null;
        $akurasi = null;

        if ($has_past_data || $aktual > 0) {
            $wma = (($sliding_history[2] * $weights[0]) + ($sliding_history[1] * $weights[1]) + ($sliding_history[0] * $weights[2])) / $sum_weights;
            
            if (!$is_future && $aktual > 0) {
                $error = abs($aktual - $wma);
                $mape = ($error / $aktual) * 100;
                
                $akurasi = 100 - $mape;
                // LOGIKA CERDAS: Mencegah akurasi menjadi minus. Paling mentok adalah 0% (Salah Total)
                if($akurasi < 0) $akurasi = 0; 
                
                $total_mape += $mape;
                $count_mape++;
            }
        }

        $wma_results[] = [
            'tahun' => $selected_tahun,
            'bulan_nama' => $months_name[$m-1],
            'aktual' => $is_future ? '-' : $aktual,
            'wma' => $wma !== null ? number_format($wma, 2) : '-',
            // Simpan nilai asli (raw) agar bisa diuji di HTML
            'mape_raw' => $mape, 
            'akurasi_raw' => $akurasi,
            'mape' => $mape !== null ? number_format($mape, 2) : '-',
            'akurasi' => $akurasi !== null ? number_format($akurasi, 2) : '-'
        ];

        $next_history_val = $is_future ? ($wma !== null ? $wma : 0) : $aktual;
        
        $sliding_history[0] = $sliding_history[1];
        $sliding_history[1] = $sliding_history[2];
        $sliding_history[2] = $next_history_val;
    }

    $avg_akurasi = null; // Tambahan inisialisasi
    if ($count_mape > 0) {
        $avg_mape = $total_mape / $count_mape;
        // LOGIKA ANTI-MINUS: Kunci akurasi paling rendah di 0%
        $avg_akurasi = 100 - $avg_mape;
        if ($avg_akurasi < 0) {
            $avg_akurasi = 0; 
        }
    }

    $start_pred_m = $current_m + 1;
    $start_pred_y = $current_y;
    if ($start_pred_m > 12) {
        $start_pred_m -= 12;
        $start_pred_y += 1;
    }

    $hist_1 = $get_sales($current_y, $current_m);     
    $hist_2 = $get_sales($current_y, $current_m - 1); 
    $hist_3 = $get_sales($current_y, $current_m - 2); 

    $temp_hist = [$hist_3, $hist_2, $hist_1];

    for ($i = 0; $i < 3; $i++) {
        $pred = (($temp_hist[2] * $weights[0]) + ($temp_hist[1] * $weights[1]) + ($temp_hist[0] * $weights[2])) / $sum_weights;
        
        $pred_m = $current_m + 1 + $i;
        $pred_y = $current_y;
        while ($pred_m > 12) {
            $pred_m -= 12;
            $pred_y += 1;
        }

        $future_predictions[] = [
            'bulan_nama' => $months_name[$pred_m - 1],
            'tahun' => $pred_y,
            'prediksi' => ceil($pred)
        ];

        $temp_hist[0] = $temp_hist[1];
        $temp_hist[1] = $temp_hist[2];
        $temp_hist[2] = $pred; 
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Analisis - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .tab-content { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .menu-card { transition: all 0.3s ease; }
        .menu-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Navigation -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-gray-500 hover:text-blue-600 mr-4 transition bg-gray-100 p-2 rounded-lg" title="Kembali ke Dashboard">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <span class="text-xl font-bold text-blue-800 tracking-tight">Laporan <span class="text-green-500">Analisis</span></span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-xs font-bold bg-green-50 text-green-600 px-3 py-1.5 rounded-lg uppercase tracking-wider flex items-center shadow-sm border border-green-100">
                    <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                    Akses Terbuka
                </span>
                <!-- Tombol kembali ke dashboard sebagai pengganti tombol logout PIN sebelumnya -->
                <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-white border border-gray-300 hover:border-gray-500 hover:bg-gray-500 px-3 py-1.5 rounded-lg transition">Tutup Laporan</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8 flex-grow w-full">
        
        <!-- Filter Header -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-200 mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-gray-800 tracking-tight">Data Laporan & Analisis Cerdas</h1>
                <p class="text-gray-500 font-medium text-sm mt-1">Periode Transaksi: <?php echo date("F Y", mktime(0, 0, 0, (int)$bulan_filter, 10, (int)$tahun_filter)); ?></p>
            </div>
            <form action="" method="GET" class="flex items-center space-x-3 w-full md:w-auto">
                <input type="hidden" name="tab" value="transaksi">
                <select name="bulan" class="px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-bold text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none w-full md:w-auto">
                    <?php 
                    $months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                    foreach($months as $i => $m): $val = sprintf("%02d", $i+1);
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $bulan_filter == $val ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tahun" class="px-4 py-2.5 border border-gray-200 rounded-xl bg-gray-50 text-sm font-bold text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none w-full md:w-auto">
                    <?php for($y = date('Y') + 1; $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $tahun_filter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 transition">Filter</button>
            </form>
        </div>

        <!-- Notifikasi Import -->
        <?php if ($import_message): ?>
            <div class="mb-8 p-4 rounded-2xl text-sm font-bold flex items-center justify-center <?php echo $import_status == 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                <?php echo $import_message; ?>
            </div>
        <?php endif; ?>

        <!-- ============================================== -->
        <!-- MENU NAVIGASI PINTAR (Tab Buttons)             -->
        <!-- ============================================== -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <button onclick="switchTab('ringkasan')" id="btn-ringkasan" class="tab-btn menu-card ring-2 ring-blue-500 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center focus:outline-none">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <span class="font-bold text-gray-800 text-sm">Ringkasan & Grafik</span>
            </button>

            <button onclick="switchTab('transaksi')" id="btn-transaksi" class="tab-btn menu-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center focus:outline-none relative">
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <span class="font-bold text-gray-800 text-sm">Riwayat Transaksi</span>
            </button>

            <button onclick="switchTab('forecasting')" id="btn-forecasting" class="tab-btn menu-card bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center focus:outline-none">
                <div class="w-12 h-12 bg-cyan-50 text-cyan-600 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                </div>
                <span class="font-bold text-gray-800 text-sm block leading-tight">Forecasting<br><span class="text-[10px] text-gray-500 font-normal">(Prediksi Penjualan)</span></span>
            </button>

            <button onclick="switchTab('eoq')" id="btn-eoq" class="tab-btn menu-card bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center focus:outline-none">
                <div class="w-12 h-12 bg-green-50 text-green-600 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="font-bold text-gray-800 text-sm block leading-tight">EOQ & ROP<br><span class="text-[10px] text-gray-500 font-normal">(Saran Pemesanan)</span></span>
            </button>
        </div>

        <!-- ============================================== -->
        <!-- AREA KONTEN 1 & 2                              -->
        <!-- ============================================== -->
        <div id="tab-ringkasan" class="tab-content block space-y-6">
            <div class="bg-gradient-to-r from-blue-700 to-blue-900 rounded-3xl p-8 text-white shadow-xl flex flex-col md:flex-row items-center justify-between relative overflow-hidden">
                <div class="relative z-10 text-center md:text-left">
                    <p class="text-blue-200 font-bold uppercase tracking-widest text-sm mb-2">Total Omzet Bulan Ini</p>
                    <h3 class="text-4xl md:text-5xl font-black">Rp <?php echo number_format($stats['omzet'] ?? 0, 0, ',', '.'); ?></h3>
                </div>
                <div class="mt-6 md:mt-0 relative z-10 bg-white/10 backdrop-blur-sm p-4 rounded-2xl border border-white/20 text-center">
                    <p class="text-xs text-blue-200 uppercase font-bold tracking-wider">Total Transaksi</p>
                    <p class="text-3xl font-black mt-1"><?php echo $stats['total_trx'] ?? 0; ?></p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <div class="h-[350px] w-full"><canvas id="salesChart"></canvas></div>
            </div>
        </div>

        <div id="tab-transaksi" class="tab-content hidden">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div><h2 class="text-lg font-black text-gray-800">Daftar Riwayat Transaksi</h2></div>
                    <button onclick="openModal('modal-import')" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm border border-emerald-200">Import Data Historis (.CSV)</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase">
                            <tr><th class="px-6 py-4">No. Invoice & Waktu</th><th class="px-6 py-4">Barang</th><th class="px-6 py-4 text-center">Qty</th><th class="px-6 py-4 text-center">Metode</th><th class="px-6 py-4 text-right">Subtotal</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(mysqli_num_rows($query_riwayat) > 0): while($trx = mysqli_fetch_assoc($query_riwayat)): ?>
                                <tr class="hover:bg-gray-50 transition text-sm">
                                    <td class="px-6 py-4"><div class="font-bold text-blue-700"><?php echo $trx['no_invoice']; ?></div><div class="text-[11px] text-gray-500 font-medium"><?php echo date("d M Y - H:i", strtotime($trx['tanggal_transaksi'])); ?></div></td>
                                    <td class="px-6 py-4 font-bold"><?php echo htmlspecialchars($trx['nama_barang']); ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-gray-600"><?php echo $trx['jumlah_terjual']; ?></td>
                                    <td class="px-6 py-4 text-center"><span class="px-3 py-1 rounded-lg text-[10px] bg-gray-100 border text-gray-700 font-bold"><?php echo $trx['metode_pembayaran']; ?></span></td>
                                    <td class="px-6 py-4 text-right font-black">Rp <?php echo number_format($trx['subtotal'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">Belum ada transaksi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- ============================================== -->
        <!-- AREA KONTEN 3: FORECASTING                     -->
        <!-- ============================================== -->
        <div id="tab-forecasting" class="tab-content hidden">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                <!-- Header Tab Prediksi -->
                <div class="p-6 border-b border-gray-100 flex items-center bg-gradient-to-r from-blue-50 to-white">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mr-4 shadow-sm border border-blue-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-gray-800">Forecasting (Prediksi Penjualan di Masa Mendatang)</h2>
                        <p class="text-xs text-gray-500 font-medium mt-1">Pantau tingkat akurasi riwayat data masa lalu, serta dapatkan proyeksi cerdas kebutuhan stok untuk beberapa bulan ke depan.</p>
						<p class="text-xs text-gray-500 font-medium mt-1">Prediksi dilakukan dengan memberikan bobot terbesar untuk data terbaru.</p>
						<p class="text-xs text-gray-500 font-medium mt-1">Note: Prediksi ini tidak cocok untuk barang yang bersifat musiman.</p>
                    </div>
                </div>
                
                <!-- Form Filter -->
                <div class="p-6 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row gap-4 items-end">
                    <form action="" method="GET" class="flex flex-col md:flex-row items-end space-y-4 md:space-y-0 md:space-x-4 w-full">
                        <input type="hidden" name="tab" value="forecasting">
                        <input type="hidden" name="bulan" value="<?php echo $bulan_filter; ?>">
                        <input type="hidden" name="tahun" value="<?php echo $tahun_filter; ?>">

                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Pilih Barang / Produk</label>
                            <select name="prediksi_barang_id" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none font-medium bg-white">
                                <option value="">-- Pilih Produk untuk Diprediksi --</option>
                                <?php mysqli_data_seek($query_barang, 0); while($b = mysqli_fetch_assoc($query_barang)): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo $selected_barang_id == $b['id'] ? 'selected' : ''; ?>><?php echo $b['nama_barang']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="w-full md:w-32">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Tahun</label>
                            <input type="number" name="prediksi_tahun" value="<?php echo $selected_tahun; ?>" class="w-full px-4 py-3 rounded-xl border border-gray-300 text-center focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                        </div>
                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-md transition-all active:scale-95">
                            Tampilkan Prediksi
                        </button>
                    </form>
                </div>

                <?php if($selected_barang_id): ?>
                
                <!-- Tabel Evaluasi WMA -->
                <div class="overflow-x-auto">
                    <table class="w-full text-center">
                        <thead class="bg-white border-b border-gray-200 text-xs font-black text-gray-500 uppercase tracking-widest">
                            <tr>
                                <th class="px-6 py-5">Tahun</th>
                                <th class="px-6 py-5">Bulan</th>
                                <th class="px-6 py-5 border-l border-gray-100 bg-gray-50/50 text-gray-700">Penjualan Aktual</th>
                                <th class="px-6 py-5 border-l border-gray-100 text-blue-700">Forecasting <br><span class="text-[10px] font-medium text-gray-400 tracking-normal capitalize">(Perkiraan Terjual)</span></th>
                                <th class="px-6 py-5 border-l border-gray-100">Tingkat Error (%)</th>
                                <th class="px-6 py-5 border-l border-gray-100 bg-blue-50/30 text-blue-700">Ketepatan Akurasi (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($wma_results as $res): ?>
                            <tr class="hover:bg-gray-50 transition text-sm text-gray-700 font-medium">
                                <td class="px-6 py-4 text-gray-400"><?php echo $res['tahun']; ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800"><?php echo $res['bulan_nama']; ?></td>
                                <td class="px-6 py-4 border-l border-gray-100 bg-gray-50/30 font-bold"><?php echo $res['aktual']; ?></td>
                                <td class="px-6 py-4 border-l border-gray-100 font-bold text-gray-600"><?php echo $res['wma']; ?></td>
                                
                                <!-- Kolom Error: Beri Label Jika Over-forecasting -->
                                <td class="px-6 py-4 border-l border-gray-100">
                                    <span class="text-red-500 font-bold text-base"><?php echo $res['mape']; ?> <?php echo $res['mape'] !== '-' ? '%' : ''; ?></span>
                                    <?php if($res['mape_raw'] !== null && $res['mape_raw'] >= 100): ?>
                                        <br><span class="text-[9px] text-red-400 uppercase tracking-wider font-bold mt-1 block leading-tight">(Tebakan Jauh Lebih Tinggi<br>Dari Kenyataan)</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom Akurasi: Beri label Jika 0% -->
                                <td class="px-6 py-4 border-l border-gray-100 bg-blue-50/10 font-bold <?php echo $res['akurasi_raw'] == 0 && $res['akurasi'] !== '-' ? 'text-red-600' : 'text-blue-600'; ?>">
                                    <span class="text-base"><?php echo $res['akurasi'] !== '-' ? $res['akurasi'] . ' %' : '-'; ?></span>
                                    <?php if($res['akurasi_raw'] !== null && $res['akurasi_raw'] == 0): ?>
                                        <br><span class="text-[9px] text-red-500 font-bold mt-1 block uppercase tracking-wider bg-red-100 px-2 py-0.5 rounded-full inline-block">Meleset Jauh</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary / Average Accuracy -->
                <div class="p-6 bg-gray-50 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex-1">
                        <h4 class="font-bold text-gray-800 text-lg">Rata-rata Akurasi Sistem</h4>
                        <p class="text-xs text-gray-500 mt-1">Persentase keberhasilan model peramalan dibandingkan dengan data penjualan aktual <span class="font-bold text-gray-700"><?php echo htmlspecialchars($nama_barang_terpilih); ?></span> pada tahun <?php echo $selected_tahun; ?>.</p>
                        
                        <!-- Tambahan Pesan Edukasi Jika Akurasi 0% -->
                        <?php if($avg_akurasi !== null && $avg_akurasi == 0): ?>
                            <p class="text-[10px] text-red-600 font-bold mt-3 bg-red-100 px-3 py-2 rounded-lg inline-flex items-center">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                Rata-rata meleset karena produk ini memiliki riwayat lonjakan penjualan musiman ekstrem.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="px-6 py-4 rounded-2xl bg-white border border-gray-200 shadow-sm flex items-center justify-center min-w-[150px]">
                        <span class="text-3xl font-black <?php echo $avg_akurasi >= 70 ? 'text-green-600' : ($avg_akurasi > 0 ? 'text-orange-500' : 'text-red-600'); ?>">
                            <?php echo $avg_akurasi !== null ? number_format($avg_akurasi, 2) . ' %' : '-'; ?>
                        </span>
                    </div>
                </div>

                <!-- Kartu Prediksi Bulan Kedepan -->
                <div class="p-6 bg-white border-t border-gray-200">
                    <div class="mb-6 flex items-center">
                        <div class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="font-black text-gray-800 text-lg">Proyeksi Kebutuhan Beberapa Bulan Kedepan</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach($future_predictions as $idx => $fp): ?>
                        <div class="bg-gradient-to-br from-indigo-600 to-blue-800 p-6 rounded-2xl text-white shadow-xl relative overflow-hidden group">
                            <div class="absolute right-0 top-0 opacity-10 group-hover:scale-110 transition-transform duration-500">
                                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                            </div>
                            <p class="text-indigo-200 font-bold uppercase tracking-widest text-[10px] mb-2 bg-white/10 inline-block px-3 py-1 rounded-full">Proyeksi Bulan ke-<?php echo $idx + 1; ?></p>
                            <h4 class="text-2xl font-black mb-4"><?php echo $fp['bulan_nama'] . ' ' . $fp['tahun']; ?></h4>
                            <p class="text-xs text-indigo-200 mb-1 font-medium">Estimasi Kebutuhan Stok:</p>
                            <p class="text-5xl font-black drop-shadow-md text-cyan-300"><?php echo $fp['prediksi']; ?> <span class="text-sm font-bold text-white">Unit</span></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- State Kosong / Belum Pilih Barang -->
                <div class="p-16 text-center bg-white">
                    <svg class="w-20 h-20 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <h3 class="text-xl font-bold text-gray-500">Pilih Produk Terlebih Dahulu</h3>
                    <p class="text-sm text-gray-400 mt-2">Pilih nama barang dan tahun pada form di atas untuk memunculkan tabel analisis forecasting dan proyeksi beberapa bulan ke depan.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- AREA KONTEN 4: EOQ & ROP                       -->
        <!-- ============================================== -->
        <div id="tab-eoq" class="tab-content hidden">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Header Tab EOQ & ROP -->
                <div class="p-6 border-b border-gray-100 flex items-center bg-gradient-to-r from-green-50 to-white">
                    <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center mr-4 shadow-sm border border-green-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-gray-800">EOQ (Economic Order Quantity) & ROP (Reorder Point)</h2>
                        <p class="text-xs text-gray-500 font-medium mt-1">Dapatkan rekomendasi jumlah belanja yang paling efisien <span class="font-bold text-gray-700">(Saran Pemesanan Optimal)</span> dan ketahui kapan harus segera memesan kembali <span class="font-bold text-gray-700">(Titik Aman)</span>.</p>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Produk & Stok Saat Ini</th>
                                <th class="px-6 py-4 text-center bg-yellow-50/50 text-yellow-800 border-x border-gray-100">Titik Aman Stok (ROP)</th>
                                <th class="px-6 py-4 text-center bg-green-50/50 text-green-800">Saran Pemesanan Optimal (EOQ)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($analysis_data as $row): ?>
                            <tr class="hover:bg-gray-50 transition text-sm">
                                <td class="px-6 py-5">
                                    <div class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                    <div class="mt-1 flex items-center">
                                        <span class="text-xs font-bold <?php echo $row['perlu_pesan'] ? 'text-red-500 bg-red-50' : 'text-gray-600 bg-gray-100'; ?> px-2 py-0.5 rounded border <?php echo $row['perlu_pesan'] ? 'border-red-200' : 'border-gray-200'; ?>">
                                            Sisa Stok: <?php echo $row['stok_sekarang']; ?> <?php echo $row['satuan']; ?>
                                        </span>
                                    </div>
                                </td>
                                <!-- Kolom ROP -->
                                <td class="px-6 py-5 text-center bg-yellow-50/20 border-x border-gray-50">
                                    <div class="font-black text-yellow-700 text-xl"><?php echo $row['rop']; ?> <span class="text-xs font-bold"><?php echo $row['satuan']; ?></span></div>
                                    <div class="text-[10px] text-gray-400 font-medium mt-1 uppercase tracking-wider leading-relaxed">Segera Pesan ke Supplier <br>Saat Stok Menyentuh Angka Ini</div>
                                </td>
                                <!-- Kolom EOQ -->
                                <td class="px-6 py-5 text-center bg-green-50/20">
                                    <div class="font-black text-green-700 text-3xl drop-shadow-sm"><?php echo $row['eoq'] > 0 ? $row['eoq'] : "-"; ?> <span class="text-xs font-bold"><?php echo $row['satuan']; ?></span></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal Import CSV dilewati agar hemat ruang, silakan gunakan dari kode sebelumnya -->

    <!-- Script Untuk Pindah Tab & Grafik -->
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('block');
            });
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50/30');
            });
            
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            document.getElementById('tab-' + tabId).classList.add('block');
            document.getElementById('btn-' + tabId).classList.add('ring-2', 'ring-blue-500', 'bg-blue-50/30');
        }
        const activeTab = "<?php echo $active_tab; ?>";
        switchTab(activeTab);

        // Render Grafik Tren Penjualan Harian
        const ctx = document.getElementById('salesChart').getContext('2d');
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($grafik_labels); ?>,
                datasets: [{
                    label: 'Total Omzet Harian (Rp)',
                    data: <?php echo json_encode($grafik_values); ?>,
                    borderColor: '#2563eb', // blue-600
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4, 
                    pointRadius: 5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)', 
                        titleFont: { size: 13, family: 'Inter' },
                        bodyFont: { size: 14, family: 'Inter', weight: 'bold' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) { return 'Tanggal ' + context[0].label; },
                            label: function(context) { return 'Omzet: Rp ' + context.raw.toLocaleString('id-ID'); }
                        }
                    }
                },
                scales: {
                    x: { 
                        display: true,
                        grid: { display: false },
                        title: {
                            display: true,
                            text: 'Tanggal Transaksi (Hari)',
                            color: '#64748b',
                            font: { family: 'Inter', size: 12, weight: 'bold' }
                        },
                        ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 } }
                    },
                    y: { 
                        display: true,
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false }, 
                        title: {
                            display: true,
                            text: 'Total Omzet (Rupiah)',
                            color: '#64748b',
                            font: { family: 'Inter', size: 12, weight: 'bold' }
                        },
                        ticks: { 
                            color: '#94a3b8', 
                            font: { family: 'Inter', size: 11 },
                            callback: function(value) {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000) + ' Jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000) + ' Rb';
                                return 'Rp ' + value;
                            },
                            maxTicksLimit: 6
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>