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
    echo "<script>alert('Akses Ditolak! Halaman Peringatan Stok khusus Pemilik Toko (Owner).'); window.location='kasir.php';</script>";
    exit;
}

// Ambil data pengguna (Pemilik Toko) sebagai identitas pengirim pesan
$user_id = $_SESSION['user_id'];
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($query_user);

// =========================================================================
// MENGAMBIL DATA BARANG DAN MENGHITUNG ROP & EOQ MENGGUNAKAN WMA
// =========================================================================
$kritis_items = [];
$query_barang = mysqli_query($conn, "SELECT * FROM barang ORDER BY stok_sekarang ASC");

// Query 4 bulan terakhir sekaligus untuk mempercepat loading
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

    // Perhitungan EOQ (Saran Jumlah Pesan)
    $S = $row['biaya_pesan'] ?? 10000;
    $H = $row['biaya_simpan'] ?? 500;
    $eoq = ($H > 0 && $D > 0) ? sqrt((2 * ($D * 12) * $S) / $H) : 0;

    // Perhitungan ROP
    $L = $row['lead_time'] ?? 3;
    $rop = ($d_per_hari * $L);
    $stok = $row['stok_sekarang'];
    
    // FILTER: Hanya masukkan barang ke daftar kritis JIKA Stok <= ROP ATAU Stok = 0
    if (($stok <= $rop && $D > 0) || $stok <= 0) {
        $row['rop_value'] = ceil($rop);
        $row['eoq_value'] = ceil($eoq);
        $row['satuan'] = $satuan;
        $kritis_items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringatan Stok - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Animasi Toast */
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .toast-animate { animation: slideUp 0.3s ease-out forwards; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-12">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="bg-gray-100 text-gray-600 p-2 rounded-xl hover:bg-blue-600 hover:text-white transition mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="text-xl font-bold text-gray-800">Peringatan <span class="text-red-500">Stok Kritis</span></h1>
            </div>
            <div class="text-sm text-gray-500 font-medium hidden md:block">Berdasarkan Analisis Prediksi & Titik Aman (ROP)</div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">
        
        <div class="bg-red-50 border border-red-200 p-6 rounded-3xl mb-8 flex flex-col md:flex-row items-start gap-4 shadow-sm relative overflow-hidden">
            <div class="absolute -right-10 -top-10 text-red-100 opacity-50 pointer-events-none">
                <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm0 3.99L19.53 19H4.47L12 5.99zM11 16h2v2h-2zm0-6h2v4h-2z"/></svg>
            </div>
            <div class="bg-red-500 text-white p-3.5 rounded-2xl relative z-10 shadow-lg shadow-red-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="relative z-10">
                <h2 class="text-xl font-black text-red-800 mb-1 tracking-tight">Perhatian! Beberapa barang menyentuh Titik Aman (ROP).</h2>
                <p class="text-sm text-red-600 leading-relaxed font-medium mb-3">
                    Sistem mendeteksi daftar barang di bawah ini memiliki sisa stok yang telah menyentuh atau berada di bawah <b>Titik Aman Stok (Reorder Point)</b>. 
                    Sistem <b>StokPintar</b> siap mengirimkan pesanan otomatis langsung ke WhatsApp Supplier.
                </p>
                <div class="bg-white/60 inline-flex items-center px-3 py-1.5 rounded-lg border border-red-200/50">
                    <span class="text-xs font-bold text-red-700">Penerima Pesan:</span>
                    <span class="text-xs font-black text-gray-800 ml-2">Langsung ke Kontak Supplier Masing-masing Barang</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50/80 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-5 text-xs font-black text-gray-400 uppercase tracking-widest">Produk & Supplier</th>
                            <th class="px-6 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">Status & Titik Aman (ROP)</th>
                            <th class="px-6 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">Saran Pesan (EOQ)</th>
                            <th class="px-6 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Tindakan Cerdas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        if (count($kritis_items) > 0) {
                            foreach($kritis_items as $index => $row): 
                                $satuan = $row['satuan'];
                                $stok = $row['stok_sekarang'];
                                $rop = $row['rop_value'];
                                $eoq = $row['eoq_value'];
                                $nama_brg = addslashes($row['nama_barang']);

                                // PERSIAPAN FORMAT NOMOR WA SUPPLIER
                                $wa_supplier = preg_replace('/[^0-9]/', '', $row['wa_supplier'] ?? '');
                                if (!empty($wa_supplier)) {
                                    if (substr($wa_supplier, 0, 1) === '0') {
                                        $wa_supplier = '62' . substr($wa_supplier, 1);
                                    }
                                }
                                $nama_supplier = !empty($row['nama_supplier']) ? trim($row['nama_supplier']) : 'Supplier';

                                // FORMAT PESAN ORDER KE SUPPLIER
                                $pesan_wa = "Halo *" . $nama_supplier . "*,\n\n";
                                $pesan_wa .= "Kami dari *" . trim($user_data['nama_toko'] ?? 'Toko Kami') . "* (" . trim($user_data['nama_pemilik'] ?? 'Pemilik') . ") ingin melakukan pesanan stok barang:\n\n";
                                $pesan_wa .= "📦 *Nama Barang:* " . $row['nama_barang'] . "\n";
                                $pesan_wa .= "🛒 *Jumlah Order:* *" . $eoq . " " . $satuan . "*\n\n";
                                $pesan_wa .= "Mohon informasikan apakah barang tersedia dan total tagihannya ya.\nTerima kasih 🙏";
                        ?>
                        <tr class="hover:bg-red-50/30 transition-colors group">
                            <td class="px-6 py-5">
                                <div class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($row['nama_barang']); ?></div>
                                <div class="text-xs text-blue-600 font-bold mt-1 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                                    <?php echo !empty($row['nama_supplier']) ? htmlspecialchars($row['nama_supplier']) : 'Supplier Belum Diatur'; ?>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <?php if($stok <= 0): ?>
                                        <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-sm font-black bg-red-100 text-red-700 animate-pulse shadow-sm border border-red-200">
                                            HABIS (0 <?php echo $satuan; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-sm font-bold bg-orange-100 text-orange-700 shadow-sm border border-orange-200">
                                            Sisa <?php echo $stok; ?> <?php echo $satuan; ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-[10px] text-gray-400 font-bold mt-2 uppercase tracking-wider">Titik Aman (ROP): <?php echo $rop; ?> <?php echo $satuan; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <div class="bg-green-50 border border-green-100 p-2 rounded-xl inline-block shadow-sm">
                                    <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest mb-0.5">Pemesanan Optimal</p>
                                    <p class="text-xl font-black text-green-700"><?php echo $eoq; ?> <span class="text-xs font-bold"><?php echo $satuan; ?></span></p>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex flex-col items-end space-y-2">
                                    <?php if(!empty($wa_supplier)): ?>
                                        <!-- Tombol Pemicu Pengiriman Otomatis ke Supplier -->
                                        <button id="btn-wa-<?php echo $index; ?>" onclick='kirimWaOtomatis(this, "<?php echo $wa_supplier; ?>", "<?php echo $nama_brg; ?>", "<?php echo addslashes($nama_supplier); ?>")' title="Kirim PO otomatis ke WA Supplier" class="flex items-center px-4 py-2.5 bg-[#25D366] hover:bg-[#20bd5a] text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-green-200 active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.886.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                            <span class="btn-text">Order ke Supplier</span>
                                        </button>
                                    <?php else: ?>
                                        <!-- Tombol dinonaktifkan jika belum ada nomor supplier -->
                                        <button onclick="alert('Nomor HP Supplier belum diatur! Silakan edit data barang ini di menu Inventaris dan lengkapi info supplier-nya.')" class="flex items-center px-4 py-2.5 bg-gray-300 text-gray-600 text-sm font-bold rounded-xl cursor-not-allowed">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                            WA Supplier Kosong
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        } else { 
                        ?>
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <div class="inline-block bg-green-100 text-green-500 p-5 rounded-full mb-4 shadow-sm border border-green-200">
                                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <h3 class="text-xl font-black text-gray-800 tracking-tight">Semua Stok Aman!</h3>
                                    <p class="text-gray-500 mt-2 font-medium">Berdasarkan analisis Titik Aman Stok (ROP), tidak ada barang yang butuh direstock saat ini.</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3"></div>

    <script>
        // Logika Javascript untuk mensimulasikan pengiriman API Background
        function kirimWaOtomatis(btnElement, noHpSupplier, namaBarang, namaSupplier) {
            const originalContent = btnElement.innerHTML;
            
            // 1. Ubah tombol ke state Loading
            btnElement.disabled = true;
            btnElement.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses Order...
            `;
            
            // CATATAN UNTUK SKRIPSI: 
            // Di lingkungan nyata, di sinilah Anda menaruh kode Fetch/AJAX (seperti CURL) 
            // untuk memanggil API Gateway WA (contoh: Fonnte, Wablas, atau Twilio API).

            setTimeout(() => {
                // 2. Ubah state tombol menjadi Sukses
                btnElement.classList.replace('bg-[#25D366]', 'bg-emerald-600');
                btnElement.classList.replace('hover:bg-[#20bd5a]', 'hover:bg-emerald-700');
                btnElement.innerHTML = `
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Pesanan Terkirim ✓
                `;
                
                // 3. Tampilkan Notifikasi Toast
                showToast(`Pesan pemesanan (Order) untuk produk <b>${namaBarang}</b> telah dikirimkan ke Supplier <b>${namaSupplier}</b> (+${noHpSupplier}).`);
                
            }, 1500);
        }

        // Fungsi untuk memunculkan Toast Notification modern
        function showToast(message) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            toast.className = 'toast-animate bg-gray-900 text-white px-5 py-4 rounded-xl shadow-2xl flex items-start max-w-sm border border-gray-700';
            toast.innerHTML = `
                <div class="bg-green-500 rounded-full p-1 mr-3 mt-0.5">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm mb-1 text-green-400">Order Terkirim Sukses</h4>
                    <p class="text-xs text-gray-300 leading-relaxed">${message}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="ml-4 text-gray-500 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
            
            container.appendChild(toast);

            // Hilangkan toast otomatis setelah 6 detik
            setTimeout(() => {
                if(container.contains(toast)) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(100%)';
                    toast.style.transition = 'all 0.3s ease-in';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 6000);
        }
    </script>
</body>
</html>