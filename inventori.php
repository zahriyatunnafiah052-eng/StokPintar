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
    echo "<script>alert('Akses Ditolak! Halaman Inventori khusus Pemilik Toko (Owner).'); window.location='kasir.php';</script>";
    exit;
}

$message = "";
$status = "";

// --- 2. Logika HAPUS BARANG ---
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $query = mysqli_query($conn, "DELETE FROM barang WHERE id = '$id'");
    if ($query) {
        $message = "Barang berhasil dihapus!";
        $status = "success";
    } else {
        $message = "Gagal menghapus barang.";
        $status = "error";
    }
}

// --- 3. Logika TAMBAH BARANG ---
if (isset($_POST['tambah_barang'])) {
    $nama = $_POST['nama_barang'];
    $satuan = $_POST['satuan'];
    $stok = $_POST['stok'];
    $harga = $_POST['harga_jual'];
    $biaya_pesan = $_POST['biaya_pesan'];
    $biaya_simpan = $_POST['biaya_simpan'];
    $lead_time = $_POST['lead_time'];
    $nama_supplier = $_POST['nama_supplier'];
    $wa_supplier = preg_replace('/[^0-9]/', '', $_POST['wa_supplier']); // Hanya simpan angka

    // Membuat kode barang unik otomatis (Format: BRG + Timestamp)
    $kode_barang = "BRG" . time();

    // Pengecekan cerdas: Cek apakah kolom kode_barang ada di tabel database
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM barang LIKE 'kode_barang'");
    
    if (mysqli_num_rows($check_col) > 0) {
        // Jika kolom kode_barang ADA, masukkan kode otomatisnya
        $query = mysqli_query($conn, "INSERT INTO barang (kode_barang, nama_barang, satuan, stok_sekarang, harga_jual, biaya_pesan, biaya_simpan, lead_time, nama_supplier, wa_supplier) VALUES ('$kode_barang', '$nama', '$satuan', '$stok', '$harga', '$biaya_pesan', '$biaya_simpan', '$lead_time', '$nama_supplier', '$wa_supplier')");
    } else {
        // Jika kolom kode_barang TIDAK ADA, gunakan query standar
        $query = mysqli_query($conn, "INSERT INTO barang (nama_barang, satuan, stok_sekarang, harga_jual, biaya_pesan, biaya_simpan, lead_time, nama_supplier, wa_supplier) VALUES ('$nama', '$satuan', '$stok', '$harga', '$biaya_pesan', '$biaya_simpan', '$lead_time', '$nama_supplier', '$wa_supplier')");
    }
    
    if ($query) {
        $message = "Barang berhasil ditambahkan!";
        $status = "success";
    } else {
        $message = "Gagal menambah barang: " . mysqli_error($conn);
        $status = "error";
    }
}

// --- 4. Logika EDIT BARANG (UPDATE) ---
if (isset($_POST['edit_barang'])) {
    $id = $_POST['id'];
    $nama = $_POST['nama_barang'];
    $satuan = $_POST['satuan'];
    $stok = $_POST['stok'];
    $harga = $_POST['harga_jual'];
    $biaya_pesan = $_POST['biaya_pesan'];
    $biaya_simpan = $_POST['biaya_simpan'];
    $lead_time = $_POST['lead_time'];
    $nama_supplier = $_POST['nama_supplier'];
    $wa_supplier = preg_replace('/[^0-9]/', '', $_POST['wa_supplier']); // Hanya simpan angka

    $query = mysqli_query($conn, "UPDATE barang SET 
        nama_barang = '$nama', 
        satuan = '$satuan',
        stok_sekarang = '$stok', 
        harga_jual = '$harga', 
        biaya_pesan = '$biaya_pesan', 
        biaya_simpan = '$biaya_simpan', 
        lead_time = '$lead_time',
        nama_supplier = '$nama_supplier',
        wa_supplier = '$wa_supplier'
        WHERE id = '$id'");

    if ($query) {
        $message = "Barang berhasil diperbarui!";
        $status = "success";
    } else {
        $message = "Gagal memperbarui barang: " . mysqli_error($conn);
        $status = "error";
    }
}

// Ambil semua data barang
$barang_list = mysqli_query($conn, "SELECT * FROM barang ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-gray-400 hover:text-blue-600 mr-4 transition" title="Kembali ke Dashboard">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="text-xl font-bold text-blue-800">Manajemen <span class="text-green-600">Stok</span></h1>
            </div>
            <!-- Menggunakan session nama_toko agar dinamis -->
            <div class="text-sm font-medium text-gray-400 italic"><?php echo htmlspecialchars($_SESSION['nama_toko'] ?? 'Toko Saya'); ?></div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Daftar Inventori</h2>
                <p class="text-gray-500 text-sm">Kelola item barang dan parameter biaya untuk analisis.</p>
            </div>
            <button onclick="openModal('modal-tambah')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-bold flex items-center shadow-md transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Barang
            </button>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl text-sm font-medium <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Barang</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Stok</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Harga Jual</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Biaya Pemesanan & Biaya Penyimpanan</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Lama Pengiriman</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Info Supplier</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($row = mysqli_fetch_assoc($barang_list)): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?php echo $row['nama_barang']; ?></div>
                                <!-- Jika kolom kode_barang ada, akan ditampilkan, jika tidak fallback ke ID -->
                                <div class="text-[10px] text-gray-400 uppercase font-mono tracking-tighter">KODE: <?php echo isset($row['kode_barang']) && !empty($row['kode_barang']) ? $row['kode_barang'] : str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $row['stok_sekarang'] <= 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'; ?>">
                                    <?php echo $row['stok_sekarang']; ?> <?php echo $row['satuan'] ?? 'Unit'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-gray-700">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs text-gray-500 font-medium block">Biaya Pengiriman: Rp <?php echo number_format($row['biaya_pesan'] ?? 0, 0, ',', '.'); ?></span>
                                <span class="text-xs text-gray-500 font-medium block">Biaya Penyimpanan: Rp <?php echo number_format($row['biaya_simpan'] ?? 0, 0, ',', '.'); ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-xs font-bold"><?php echo $row['lead_time'] ?? 0; ?> Hari</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="font-bold text-gray-700 text-sm"><?php echo !empty($row['nama_supplier']) ? htmlspecialchars($row['nama_supplier']) : '<span class="text-gray-300 italic">-</span>'; ?></div>
                                <div class="text-xs text-green-600 font-medium"><?php echo !empty($row['wa_supplier']) ? '+' . htmlspecialchars($row['wa_supplier']) : ''; ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-2">
                                    <button 
                                        onclick='openEditModal(<?php echo json_encode($row); ?>)'
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition" 
                                        title="Edit Barang">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <a href="?hapus=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('Hapus barang ini?')" 
                                       class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                       title="Hapus Barang">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Tambah -->
    <div id="modal-tambah" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="font-bold text-xl text-gray-800">Tambah Barang Baru</h3>
                <button onclick="closeModal('modal-tambah')" class="text-gray-400 hover:text-gray-600 font-bold text-2xl">&times;</button>
            </div>
            <form action="" method="POST" class="p-6 space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Barang</label>
                    <input type="text" name="nama_barang" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stok Awal</label>
                        <input type="number" name="stok" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Satuan</label>
                        <select name="satuan" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-white font-medium">
                            <option value="Pcs">Pcs</option>
                            <option value="Kg">Kg</option>
                            <option value="Gram">Gram</option>
                            <option value="Dus">Karton/Dus</option>
                            <option value="Botol">Botol</option>
                            <option value="Liter">Liter</option>
                            <option value="Pack">Pack</option>
                            <option value="Unit">Unit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                    </div>
                </div>

                <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 space-y-4 mt-2">
                    <p class="text-[10px] font-black text-blue-800 uppercase tracking-widest">Parameter Prediksi EOQ & ROP</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Biaya Ongkir / Pesan (Rp)</label>
                            <input type="number" name="biaya_pesan" required placeholder="Cth: 10000" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Biaya Simpan Gudang (Rp)</label>
                            <input type="number" name="biaya_simpan" required placeholder="Cth: 500" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Lama Pengiriman / Lead Time (Hari)</label>
                        <input type="number" name="lead_time" required placeholder="Cth: 3" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                </div>

                <!-- FORM SUPPLIER BARU -->
                <div class="bg-green-50 p-4 rounded-xl border border-green-100 space-y-4">
                    <p class="text-[10px] font-black text-green-800 uppercase tracking-widest flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                        Kontak Supplier
                    </p>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nama Toko/Supplier</label>
                        <input type="text" name="nama_supplier" placeholder="Cth: Agen Sembako Makmur" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor WhatsApp Supplier</label>
                        <input type="text" name="wa_supplier" placeholder="Cth: 08123456789" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm">
                        <p class="text-[9px] text-green-600 mt-1 italic">*Digunakan untuk fitur kirim WA otomatis saat stok menipis.</p>
                    </div>
                </div>

                <button type="submit" name="tambah_barang" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl shadow-lg hover:bg-blue-700 transition active:scale-95">Simpan Barang</button>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="modal-edit" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="font-bold text-xl text-gray-800">Edit Data Barang</h3>
                <button onclick="closeModal('modal-edit')" class="text-gray-400 hover:text-gray-600 font-bold text-2xl">&times;</button>
            </div>
            <form action="" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="id" id="edit-id">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Barang</label>
                    <input type="text" name="nama_barang" id="edit-nama" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stok</label>
                        <input type="number" name="stok" id="edit-stok" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Satuan</label>
                        <select name="satuan" id="edit-satuan" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-white font-medium">
                            <option value="Pcs">Pcs</option>
                            <option value="Kg">Kg</option>
                            <option value="Gram">Gram</option>
                            <option value="Dus">Karton/Dus</option>
                            <option value="Botol">Botol</option>
                            <option value="Liter">Liter</option>
                            <option value="Pack">Pack</option>
                            <option value="Unit">Unit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="edit-harga" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                    </div>
                </div>

                <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 space-y-4 mt-2">
                    <p class="text-[10px] font-black text-blue-800 uppercase tracking-widest">Parameter Prediksi EOQ & ROP</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Biaya Ongkir / Pesan (Rp)</label>
                            <input type="number" name="biaya_pesan" id="edit-s" required class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Biaya Simpan Gudang (Rp)</label>
                            <input type="number" name="biaya_simpan" id="edit-h" required class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Lama Pengiriman / Lead Time (Hari)</label>
                        <input type="number" name="lead_time" id="edit-lt" required class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                </div>

                <!-- FORM SUPPLIER LAMA (EDIT) -->
                <div class="bg-green-50 p-4 rounded-xl border border-green-100 space-y-4">
                    <p class="text-[10px] font-black text-green-800 uppercase tracking-widest flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                        Kontak Supplier
                    </p>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nama Toko/Supplier</label>
                        <input type="text" name="nama_supplier" id="edit-nama-supplier" placeholder="Cth: Agen Sembako Makmur" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor WhatsApp Supplier</label>
                        <input type="text" name="wa_supplier" id="edit-wa-supplier" placeholder="Cth: 08123456789" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm">
                    </div>
                </div>

                <button type="submit" name="edit_barang" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl shadow-lg hover:bg-blue-700 transition active:scale-95">Perbarui Data</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openEditModal(data) {
            document.getElementById('edit-id').value = data.id;
            document.getElementById('edit-nama').value = data.nama_barang;
            document.getElementById('edit-satuan').value = data.satuan || 'Unit';
            document.getElementById('edit-stok').value = data.stok_sekarang;
            document.getElementById('edit-harga').value = data.harga_jual;
            document.getElementById('edit-s').value = data.biaya_pesan;
            document.getElementById('edit-h').value = data.biaya_simpan;
            document.getElementById('edit-lt').value = data.lead_time;
            
            // Tambahan Field Supplier
            document.getElementById('edit-nama-supplier').value = data.nama_supplier || '';
            document.getElementById('edit-wa-supplier').value = data.wa_supplier || '';
            
            openModal('modal-edit');
        }

        // Tutup modal jika klik di luar area putih
        window.onclick = function(event) {
            if (event.target.id === 'modal-tambah') closeModal('modal-tambah');
            if (event.target.id === 'modal-edit') closeModal('modal-edit');
        }
    </script>
</body>
</html>