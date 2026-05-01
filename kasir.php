<?php
session_start();
include 'koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php");
    exit;
}

$message = "";
$status = "";
$show_receipt = false;
$last_id = "";

// --- FUNGSI FORMAT SATUAN OTOMATIS (KG KE GRAM) ---
function formatSatuan($qty, $satuan) {
    $qty = (float)$qty;
    $satuan_lower = strtolower($satuan);
    
    // Jika satuan Kg dan kuantitas di bawah 1 (misal 0.5), ubah ke Gram
    if ($satuan_lower == 'kg' && $qty > 0 && $qty < 1) {
        $gram = $qty * 1000;
        return $gram . " Gram";
    }
    
    // Jika bilangan bulat, hilangkan angka .00 di belakang koma
    $qty_tampil = (floor($qty) == $qty) ? number_format($qty, 0, ',', '.') : $qty;
    return $qty_tampil . " " . $satuan;
}

// 1. Inisialisasi Keranjang jika belum ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// 2. Logika Tambah ke Keranjang
if (isset($_POST['tambah_keranjang'])) {
    $id_barang = $_POST['id_barang'];
    $qty = (float)$_POST['qty'];

    $query = mysqli_query($conn, "SELECT * FROM barang WHERE id = '$id_barang'");
    $barang = mysqli_fetch_assoc($query);

    if ($barang) {
        $satuan_barang = $barang['satuan'] ?? 'Unit'; // Mengambil satuan dari DB
        
        if ($qty > $barang['stok_sekarang']) {
            $message = "Stok tidak mencukupi! Sisa stok: " . $barang['stok_sekarang'] . " " . $satuan_barang;
            $status = "error";
        } else {
            $exists = false;
            foreach ($_SESSION['keranjang'] as $key => $item) {
                if ($item['id'] == $id_barang) {
                    $_SESSION['keranjang'][$key]['qty'] += $qty;
                    $_SESSION['keranjang'][$key]['subtotal'] = $_SESSION['keranjang'][$key]['qty'] * $item['harga'];
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $_SESSION['keranjang'][] = [
                    'id' => $barang['id'],
                    'nama' => $barang['nama_barang'],
                    'harga' => $barang['harga_jual'],
                    'qty' => $qty,
                    'satuan' => $satuan_barang, // Menyimpan satuan ke dalam sesi keranjang
                    'subtotal' => $barang['harga_jual'] * $qty
                ];
            }
        }
    }
}

// 3. Logika Hapus Item atau Reset
if (isset($_GET['hapus_item'])) {
    $key = $_GET['hapus_item'];
    unset($_SESSION['keranjang'][$key]);
    $_SESSION['keranjang'] = array_values($_SESSION['keranjang']); 
}

if (isset($_GET['reset'])) {
    $_SESSION['keranjang'] = [];
}

// 4. Logika Checkout (Simpan ke Database)
if (isset($_POST['checkout'])) {
    if (empty($_SESSION['keranjang'])) {
        $message = "Keranjang masih kosong!";
        $status = "error";
    } else {
        $user_id = $_SESSION['user_id'];
        $invoice = "INV-" . time();
        $total_bayar = $_POST['total_bayar'];
        $uang_bayar = $_POST['uang_bayar'];
        $kembalian = $_POST['kembalian'];
        $metode = $_POST['metode_pembayaran'];

        mysqli_begin_transaction($conn);

        try {
            $q_transaksi = "INSERT INTO transaksi (user_id, no_invoice, total_bayar, metode_pembayaran, bayar, kembali, tanggal_transaksi) 
                            VALUES ('$user_id', '$invoice', '$total_bayar', '$metode', '$uang_bayar', '$kembalian', NOW())";
            
            mysqli_query($conn, $q_transaksi);
            $transaksi_id = mysqli_insert_id($conn);

            foreach ($_SESSION['keranjang'] as $item) {
                $id_b = $item['id'];
                $qty_b = $item['qty'];
                $sub_b = $item['subtotal'];
                $harga_b = $item['harga'];

                mysqli_query($conn, "INSERT INTO detail_transaksi (transaksi_id, barang_id, jumlah_terjual, harga_satuan, subtotal) VALUES ('$transaksi_id', '$id_b', '$qty_b', '$harga_b', '$sub_b')");
                mysqli_query($conn, "UPDATE barang SET stok_sekarang = stok_sekarang - $qty_b WHERE id = '$id_b'");
            }

            mysqli_commit($conn);
            $last_id = $transaksi_id;
            $show_receipt = true; 
            $_SESSION['keranjang'] = [];
            $message = "Transaksi Berhasil!";
            $status = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Transaksi Gagal: " . $e->getMessage();
            $status = "error";
        }
    }
}

$barang_list = mysqli_query($conn, "SELECT * FROM barang WHERE stok_sekarang > 0 ORDER BY nama_barang ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - <?php echo $_SESSION['nama_toko'] ?? 'StokPintar'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Inconsolata:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            html, body { height: auto; background-color: white !important; margin: 0 !important; padding: 0 !important; }
            #receipt-modal { position: static !important; display: block !important; background: none !important; padding: 0 !important; margin: 0 !important; box-shadow: none !important; }
            #receipt-modal > div { box-shadow: none !important; max-width: 100% !important; width: 80mm !important; margin: 0 !important; padding: 0 !important; }
            #receipt-print { display: block !important; border: none !important; width: 80mm !important; padding: 0 !important; margin: 0 !important; }
            .font-mono-receipt { font-family: 'Inconsolata', monospace !important; }
            @page { margin: 0; size: auto; }
        }
        .font-mono-receipt { font-family: 'Inconsolata', monospace; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40 no-print">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center">
                <!-- Tombol Kembali: Jika Owner ke Dashboard, jika Kasir tetap di halaman Kasir (tidak ada back) -->
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'owner'): ?>
                    <a href="dashboard.php" class="text-gray-500 hover:text-blue-600 mr-4" title="Kembali ke Dashboard">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                <?php else: ?>
                    <!-- Ikon keranjang sebagai hiasan jika login sebagai kasir murni -->
                    <div class="text-blue-600 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                <?php endif; ?>
                
                <span class="text-xl font-bold text-blue-700">Kasir <span class="text-green-600">Pintar</span></span>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="text-sm font-medium text-gray-400 italic hidden sm:block"><?php echo $_SESSION['nama_toko'] ?? 'RTA Food'; ?></div>
                <div class="h-8 border-l border-gray-300 mx-2 hidden sm:block"></div>
                
                <!-- Identitas Kasir & Tombol Logout -->
                <div class="flex items-center space-x-3">
                    <span class="text-sm font-bold text-gray-700"><?php echo explode(' ', trim($_SESSION['nama_pemilik']))[0]; ?></span>
                    <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari Kasir?')" class="bg-red-50 text-red-600 p-2 rounded-lg hover:bg-red-500 hover:text-white transition-colors group flex items-center" title="Keluar">
                        <svg class="w-4 h-4 sm:mr-1 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span class="text-xs font-bold hidden sm:inline">Keluar</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8 no-print">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Pilih Produk
                    </h3>
                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Cari Barang</label>
                            <select id="select-barang" name="id_barang" required class="w-full mt-1 px-4 py-2.5 border border-gray-300 rounded-xl outline-none bg-white focus:ring-2 focus:ring-blue-500 transition text-sm">
                                <option value="" data-satuan="">-- Pilih Barang --</option>
                                <?php mysqli_data_seek($barang_list, 0); while($b = mysqli_fetch_assoc($barang_list)): ?>
                                    <option value="<?php echo $b['id']; ?>" data-satuan="<?php echo htmlspecialchars($b['satuan'] ?? 'Unit'); ?>">
                                        <?php echo htmlspecialchars($b['nama_barang']); ?> 
                                        (Stok: <?php echo $b['stok_sekarang']; ?> <?php echo $b['satuan'] ?? 'Unit'; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-widest flex justify-between">
                                Jumlah (Qty)
                                <span id="satuan-hint" class="text-blue-500 lowercase normal-case italic"></span>
                            </label>
                            <input type="number" name="qty" value="1" min="0.001" step="any" required class="w-full mt-1 px-4 py-2.5 border border-gray-300 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition font-bold" placeholder="Misal: 1 atau 0.5">
                        </div>
                        <button type="submit" name="tambah_keranjang" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all active:scale-95 flex justify-center items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Tambah ke Keranjang
                        </button>
                    </form>
                </div>

                <?php if ($message): ?>
                    <div class="p-4 rounded-xl text-sm font-medium <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Daftar Belanja
                        </h3>
                        <a href="?reset=1" class="text-xs font-bold text-red-500 hover:text-red-700 transition uppercase tracking-widest" onclick="return confirm('Kosongkan keranjang?')">Reset</a>
                    </div>
                    
                    <div class="overflow-x-auto min-h-[250px]">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase">Item</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase text-right">Harga</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase text-center">Qty</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase text-right">Subtotal</th>
                                    <th class="px-6 py-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php 
                                $grand_total = 0;
                                if (!empty($_SESSION['keranjang'])): 
                                    foreach ($_SESSION['keranjang'] as $key => $item): 
                                        $grand_total += $item['subtotal'];
                                ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 font-bold text-gray-700"><?php echo htmlspecialchars($item['nama']); ?></td>
                                        <td class="px-6 py-4 text-right text-gray-600">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-4 text-center font-semibold text-blue-700 bg-blue-50/30">
                                            <!-- Menggunakan Fungsi Format Satuan -->
                                            <?php echo formatSatuan($item['qty'], $item['satuan']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-gray-800">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="?hapus_item=<?php echo $key; ?>" class="text-red-300 hover:text-red-600 transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">Keranjang belanja kosong.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-8 bg-gray-50 border-t border-gray-200">
                        <form action="" method="POST">
                            <input type="hidden" name="total_bayar" id="total_bayar" value="<?php echo $grand_total; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-2 uppercase tracking-widest">Metode Bayar</label>
                                    <select name="metode_pembayaran" required class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white font-bold text-gray-700 outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Cash">Cash (Tunai)</option>
                                        <option value="TF Bank">Transfer Bank</option>
                                        <option value="QRIS">QRIS</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-2 uppercase tracking-widest">Uang Dibayar (Rp)</label>
                                    <input type="number" name="uang_bayar" id="input_bayar" required min="<?php echo $grand_total; ?>" placeholder="0" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-xl font-black text-blue-700 outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="space-y-3 mb-8 p-6 bg-white rounded-2xl border border-gray-200 shadow-inner text-sm">
                                <div class="flex justify-between items-center text-gray-400 font-medium"><span>Total Belanja</span><span>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></span></div>
                                <div class="flex justify-between items-center font-bold"><span class="text-gray-500">Kembalian</span><span id="display_kembalian" class="text-xl text-red-500">Rp 0</span><input type="hidden" name="kembalian" id="input_kembalian" value="0"></div>
                                <div class="border-t border-dashed border-gray-200 my-4"></div>
                                <div class="flex justify-between items-center"><span class="text-lg font-bold text-gray-700">GRAND TOTAL</span><span class="text-4xl font-black text-blue-800">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></span></div>
                            </div>
                            <button type="submit" name="checkout" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-5 rounded-2xl shadow-xl transition-all transform active:scale-95 flex justify-center items-center text-xl tracking-wider">PROSES PEMBAYARAN</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL STRUK -->
    <?php if ($show_receipt): 
        $q_res = mysqli_query($conn, "SELECT t.*, u.nama_pemilik FROM transaksi t JOIN users u ON t.user_id = u.id WHERE t.id = '$last_id'");
        $tr = mysqli_fetch_assoc($q_res);
        // Join dengan tabel barang untuk mendapatkan nilai 'satuan'
        $q_items = mysqli_query($conn, "SELECT dt.*, b.nama_barang, b.satuan FROM detail_transaksi dt JOIN barang b ON dt.barang_id = b.id WHERE dt.transaksi_id = '$last_id'");
    ?>
    <div id="receipt-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-sm w-full shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center mb-4 pb-2 border-b no-print">
                <h3 class="font-bold text-gray-800">Transaksi Selesai</h3>
                <button onclick="window.location.reload()" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            
            <!-- Area Cetak Struk -->
            <div id="receipt-print" class="bg-white p-4 font-mono-receipt text-sm text-gray-800 leading-tight border rounded-xl mx-auto">
                <div class="text-center mb-4">
                    <h4 class="font-bold text-lg"><?php echo strtoupper($_SESSION['nama_toko'] ?? 'STOKPINTAR UMKM'); ?></h4>
                    <p class="text-[10px]">Jl. Sukses Berkah UMKM No. 1</p>
                    <p class="text-[10px]">Tanggal: <?php echo date('d/m/Y H:i', strtotime($tr['tanggal_transaksi'])); ?></p>
                    <p class="text-[10px]">Invoice: <?php echo $tr['no_invoice']; ?></p>
                    <div class="border-b border-dashed border-gray-400 my-2"></div>
                </div>
                <div class="space-y-2 mb-4">
                    <?php while($it = mysqli_fetch_assoc($q_items)): ?>
                    <div class="flex justify-between items-start">
                        <div class="pr-2">
                            <div class="font-bold"><?php echo htmlspecialchars($it['nama_barang']); ?></div>
                            <div class="text-[10px]">
                                <!-- Terapkan Format Satuan di Struk -->
                                <?php echo formatSatuan($it['jumlah_terjual'], $it['satuan'] ?? 'Unit'); ?> x Rp <?php echo number_format($it['harga_satuan'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="font-bold">Rp <?php echo number_format($it['subtotal'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div class="border-b border-dashed border-gray-400 mb-2"></div>
                <div class="space-y-1">
                    <div class="flex justify-between font-bold text-base"><span>TOTAL</span><span>Rp <?php echo number_format($tr['total_bayar'], 0, ',', '.'); ?></span></div>
                    <div class="flex justify-between text-[10px]"><span>METODE</span><span><?php echo $tr['metode_pembayaran']; ?></span></div>
                    <div class="flex justify-between text-[10px]"><span>BAYAR</span><span>Rp <?php echo number_format($tr['bayar'], 0, ',', '.'); ?></span></div>
                    <div class="flex justify-between font-bold text-lg"><span>KEMBALI</span><span>Rp <?php echo number_format($tr['kembali'], 0, ',', '.'); ?></span></div>
                </div>
                <div class="text-center mt-6 uppercase border-t border-dashed pt-3">
                    <p class="text-[11px] font-bold">*** Terima Kasih ***</p>
                    <p class="text-[9px] mt-1 italic">Barang tidak dapat ditukar</p>
                    <p class="text-[9px] mt-1">Kasir: <?php echo htmlspecialchars($tr['nama_pemilik'] ?? 'Admin'); ?></p>
                </div>
            </div>

            <div class="mt-6 flex space-x-3 no-print">
                <button onclick="window.print()" class="flex-1 bg-blue-600 text-white py-4 rounded-2xl font-bold shadow-lg hover:bg-blue-700 transition active:scale-95 flex justify-center items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    CETAK STRUK
                </button>
                <button onclick="window.location.reload()" class="flex-1 bg-gray-100 text-gray-600 py-4 rounded-2xl font-bold hover:bg-gray-200 transition">SELESAI</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const total = <?php echo $grand_total; ?>;
        const inputBayar = document.getElementById('input_bayar');
        const displayKembalian = document.getElementById('display_kembalian');
        const inputKembalian = document.getElementById('input_kembalian');
        const selectBarang = document.getElementById('select-barang');
        const satuanHint = document.getElementById('satuan-hint');

        // Menampilkan petunjuk Gram jika user memilih barang dengan satuan Kg
        selectBarang.addEventListener('change', function() {
            let selectedOption = this.options[this.selectedIndex];
            let satuan = selectedOption.getAttribute('data-satuan');
            
            if (satuan.toLowerCase() === 'kg') {
                satuanHint.innerText = "(Ketik 0.5 untuk 500 Gram)";
            } else if (satuan !== "") {
                satuanHint.innerText = "Satuan: " + satuan;
            } else {
                satuanHint.innerText = "";
            }
        });

        function formatRupiah(number) {
            return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number);
        }

        inputBayar.addEventListener('input', function() {
            const bayar = parseFloat(this.value) || 0;
            const kembali = bayar - total;
            if (kembali >= 0) {
                displayKembalian.innerText = formatRupiah(kembali);
                displayKembalian.classList.remove('text-red-500');
                displayKembalian.classList.add('text-green-600');
                inputKembalian.value = kembali;
            } else {
                displayKembalian.innerText = 'Uang Kurang';
                displayKembalian.classList.add('text-red-500');
                displayKembalian.classList.remove('text-green-600');
                inputKembalian.value = 0;
            }
        });
    </script>
</body>
</html>