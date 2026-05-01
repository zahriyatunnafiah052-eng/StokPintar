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

// Jika yang mencoba masuk BUKAN owner, tendang ke kasir
if ($_SESSION['role'] != 'owner') {
    echo "<script>alert('Akses Ditolak! Halaman ini khusus Pemilik Toko.'); window.location='kasir.php';</script>";
    exit;
}

$owner_id = $_SESSION['user_id'];
$message = "";
$status = "";

// =========================================================
// 2. LOGIKA TAMBAH KARYAWAN (KASIR)
// =========================================================
if (isset($_POST['tambah_karyawan'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);

    // Cek apakah username sudah dipakai orang lain
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cek) > 0) {
        $message = "Username '$username' sudah terdaftar. Silakan pilih username lain.";
        $status = "error";
    } else {
        // Enkripsi password kasir
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert dengan role 'kasir' dan owner_id milik bos yang sedang login
        // Catatan: Kita menggunakan kolom 'nama_pemilik' untuk menyimpan nama karyawan
        $query = "INSERT INTO users (nama_pemilik, telepon, username, password, role, owner_id) 
                  VALUES ('$nama', '$telepon', '$username', '$hashed_password', 'kasir', '$owner_id')";
        
        if (mysqli_query($conn, $query)) {
            $message = "Akun Kasir berhasil ditambahkan!";
            $status = "success";
        } else {
            $message = "Gagal menambah kasir: " . mysqli_error($conn);
            $status = "error";
        }
    }
}

// =========================================================
// 3. LOGIKA EDIT KARYAWAN
// =========================================================
if (isset($_POST['edit_karyawan'])) {
    $id_karyawan = $_POST['id_karyawan'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $password_baru = $_POST['password_baru'];

    // Cek apakah username dipakai kasir lain (kecuali dirinya sendiri)
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' AND id != '$id_karyawan'");
    if (mysqli_num_rows($cek) > 0) {
        $message = "Username '$username' sudah dipakai orang lain.";
        $status = "error";
    } else {
        // Jika password diisi, maka update passwordnya juga
        if (!empty($password_baru)) {
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $query = "UPDATE users SET nama_pemilik='$nama', username='$username', telepon='$telepon', password='$hashed_password' 
                      WHERE id='$id_karyawan' AND owner_id='$owner_id'";
        } else {
            // Jika kosong, update data selain password
            $query = "UPDATE users SET nama_pemilik='$nama', username='$username', telepon='$telepon' 
                      WHERE id='$id_karyawan' AND owner_id='$owner_id'";
        }

        if (mysqli_query($conn, $query)) {
            $message = "Data Kasir berhasil diperbarui!";
            $status = "success";
        } else {
            $message = "Gagal memperbarui kasir.";
            $status = "error";
        }
    }
}

// =========================================================
// 4. LOGIKA HAPUS KARYAWAN
// =========================================================
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    // Pastikan hanya bisa menghapus karyawan miliknya sendiri
    $q_hapus = mysqli_query($conn, "DELETE FROM users WHERE id='$id_hapus' AND owner_id='$owner_id' AND role='kasir'");
    if ($q_hapus) {
        $message = "Akun kasir berhasil dihapus.";
        $status = "success";
    }
}

// Ambil daftar karyawan khusus milik owner yang sedang login
$query_karyawan = mysqli_query($conn, "SELECT * FROM users WHERE owner_id = '$owner_id' AND role = 'kasir' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Karyawan - StokPintar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .modal-enter { animation: modalFadeIn 0.3s ease-out forwards; }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-12">

    <!-- Top Navigation -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center">
                    <a href="dashboard.php" class="bg-gray-100 text-gray-600 p-2.5 rounded-xl hover:bg-blue-600 hover:text-white transition mr-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <span class="text-xl font-black text-gray-800 tracking-tight hidden sm:block">Manajemen <span class="text-indigo-600">Karyawan</span></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-xs font-bold bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg border border-indigo-100 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Akses Owner
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
        
        <!-- Header Section -->
        <div class="bg-indigo-600 rounded-3xl p-8 mb-8 text-white shadow-xl shadow-indigo-200 relative overflow-hidden flex flex-col md:flex-row items-center justify-between">
            <div class="absolute -right-10 -top-10 opacity-20">
                <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div class="relative z-10 md:w-2/3">
                <h1 class="text-3xl font-black mb-2 tracking-tight">Daftar Akun Kasir</h1>
                <p class="text-indigo-200 font-medium text-sm leading-relaxed">Buat dan kelola akun untuk karyawan Anda. Akun yang dibuat di sini hanya akan memiliki akses ke fitur Kasir Penjualan untuk mencetak struk, tanpa bisa melihat Laporan Analisis atau Inventori.</p>
            </div>
            <div class="relative z-10 mt-6 md:mt-0">
                <button onclick="openModal('modal-tambah')" class="bg-white text-indigo-600 hover:bg-gray-50 px-6 py-3 rounded-xl font-bold shadow-lg transition flex items-center active:scale-95">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Tambah Kasir
                </button>
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-2xl text-sm font-bold flex items-center <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabel Karyawan -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Nama Karyawan</th>
                            <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Informasi Akun Login</th>
                            <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Status Role</th>
                            <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (mysqli_num_rows($query_karyawan) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($query_karyawan)): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center mr-3 border border-indigo-200">
                                                <?php echo strtoupper(substr($row['nama_pemilik'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($row['nama_pemilik']); ?></div>
                                                <div class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($row['telepon']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200 inline-flex items-center text-sm">
                                            <span class="text-gray-500 font-bold mr-2">Username:</span>
                                            <span class="font-black text-indigo-600"><?php echo htmlspecialchars($row['username']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="bg-blue-50 text-blue-600 border border-blue-200 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-widest">
                                            <?php echo htmlspecialchars($row['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <!-- Tombol Edit (Kirim data via JSON ke JS) -->
                                            <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition border border-transparent hover:border-blue-200" title="Edit/Reset Password">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                            <a href="?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus akses kasir ini? Data transaksinya akan tetap aman.')" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition border border-transparent hover:border-red-200" title="Hapus Akses">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-16 text-center">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-800 mb-1">Belum Ada Karyawan</h3>
                                    <p class="text-sm text-gray-500">Silakan tambahkan akun untuk staf kasir Anda.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ============================================== -->
    <!-- MODAL TAMBAH KARYAWAN -->
    <!-- ============================================== -->
    <div id="modal-tambah" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden modal-enter">
            <div class="bg-indigo-600 p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Buat Akun Kasir
                </h3>
                <button onclick="closeModal('modal-tambah')" class="text-white hover:text-indigo-200 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <form action="" method="POST" class="p-6 space-y-5">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Lengkap Karyawan</label>
                    <input type="text" name="nama" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition font-medium" placeholder="Cth: Budi Santoso">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">No. WhatsApp</label>
                    <input type="text" name="telepon" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition font-medium" placeholder="0812...">
                </div>
                
                <div class="border-t border-gray-100 pt-4 mt-2">
                    <p class="text-xs font-black text-indigo-800 uppercase tracking-widest mb-3">Informasi Login</p>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Username</label>
                            <input type="text" name="username" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition font-medium bg-indigo-50 text-indigo-900" placeholder="Cth: kasir_budi">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Password Akun</label>
                            <input type="password" name="password" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition font-medium bg-indigo-50" placeholder="Buat kata sandi">
                            <p class="text-[10px] text-gray-400 mt-1">*Berikan username & password ini kepada karyawan Anda.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" name="tambah_karyawan" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all active:scale-95 mt-4">
                    Simpan & Daftarkan
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- MODAL EDIT KARYAWAN -->
    <!-- ============================================== -->
    <div id="modal-edit" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden modal-enter">
            <div class="bg-blue-600 p-5 flex justify-between items-center text-white">
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data Kasir
                </h3>
                <button onclick="closeModal('modal-edit')" class="text-white hover:text-blue-200 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <form action="" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="id_karyawan" id="edit_id">
                
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Lengkap Karyawan</label>
                    <input type="text" name="nama" id="edit_nama" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">No. WhatsApp</label>
                    <input type="text" name="telepon" id="edit_telepon" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium">
                </div>
                
                <div class="border-t border-gray-100 pt-4 mt-2">
                    <p class="text-xs font-black text-blue-800 uppercase tracking-widest mb-3">Informasi Login</p>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Username</label>
                            <input type="text" name="username" id="edit_username" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium bg-blue-50">
                        </div>
                        <div class="bg-orange-50 p-3 border border-orange-100 rounded-xl">
                            <label class="text-xs font-bold text-orange-800 uppercase tracking-wider flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Reset Password Baru
                            </label>
                            <input type="password" name="password_baru" class="w-full mt-2 px-4 py-2 border border-orange-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition font-medium" placeholder="Isi hanya jika ingin diganti...">
                        </div>
                    </div>
                </div>

                <button type="submit" name="edit_karyawan" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all active:scale-95 mt-4">
                    Perbarui Data
                </button>
            </form>
        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama').value = data.nama_pemilik;
            document.getElementById('edit_telepon').value = data.telepon;
            document.getElementById('edit_username').value = data.username;
            openModal('modal-edit');
        }

        // Tutup modal jika klik background gelap
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-black/60')) {
                event.target.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>