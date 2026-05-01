<?php
// Sertakan koneksi database
include 'koneksi.php';

$message = "";
$status = "";

if (isset($_POST['register'])) {
    // Ambil data dari form
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $nama_pemilik = mysqli_real_escape_string($conn, $_POST['nama_pemilik']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $nama_toko = mysqli_real_escape_string($conn, $_POST['nama_toko']);
    $jenis_usaha = mysqli_real_escape_string($conn, $_POST['jenis_usaha']);
    $alamat_toko = mysqli_real_escape_string($conn, $_POST['alamat_toko']);

    // Cek apakah username sudah ada
    $cek_user = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cek_user) > 0) {
        $message = "Username sudah terdaftar, silakan gunakan yang lain.";
        $status = "error";
    } else {
        // Enkripsi Password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert ke database DENGAN ROLE 'owner'
        // (Kolom security_pin sudah tidak digunakan)
        $query = "INSERT INTO users (username, password, nama_pemilik, telepon, nama_toko, jenis_usaha, alamat_toko, role) 
                  VALUES ('$username', '$hashed_password', '$nama_pemilik', '$telepon', '$nama_toko', '$jenis_usaha', '$alamat_toko', 'owner')";
        
        if (mysqli_query($conn, $query)) {
            $message = "Registrasi Pemilik Berhasil! Silakan Login.";
            $status = "success";
            // Redirect ke login setelah 2 detik
            header("refresh:2;url=login.php");
        } else {
            $message = "Gagal mendaftar: " . mysqli_error($conn);
            $status = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pemilik - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-pattern {
            background-color: #f3f4f6;
            background-image: url("https://www.transparenttextures.com/patterns/cubes.png");
        }
        /* Menghilangkan panah atas/bawah pada input type number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; margin: 0; 
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center py-12 px-4">

    <div class="max-w-2xl w-full bg-white rounded-3xl shadow-xl overflow-hidden flex flex-col md:flex-row">
        <!-- Sidebar Info -->
        <div class="md:w-1/3 bg-blue-700 p-8 text-white flex flex-col justify-center relative overflow-hidden">
            <div class="absolute -top-10 -right-10 opacity-20">
                <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm0 3.99L19.53 19H4.47L12 5.99zM11 16h2v2h-2zm0-6h2v4h-2z"/></svg>
            </div>
            <div class="relative z-10">
                <h2 class="text-3xl font-bold mb-2">Daftarkan Usaha Anda</h2>
                <div class="bg-blue-800/50 text-blue-200 text-[10px] font-bold uppercase tracking-widest py-1 px-3 rounded-md inline-block mb-6 border border-blue-500">
         
                </div>
                
                <p class="text-blue-100 mb-6 text-sm leading-relaxed">Mulai kelola stok UMKM Anda dengan perhitungan yang lebih akurat dan aman.</p>
                <div class="space-y-4">
                    <div class="flex items-center space-x-3 text-sm">
                        <span class="bg-blue-600 p-2 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></span>
                        <span class="font-medium">Akses Laporan & Analisis</span>
                    </div>
                    <div class="flex items-center space-x-3 text-sm">
                        <span class="bg-blue-600 p-2 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></span>
                        <span class="font-medium">Kelola Akun Karyawan</span>
                    </div>
                </div>
                <div class="mt-12 pt-8 border-t border-blue-600/50">
                    <p class="text-xs text-blue-200">Sudah punya akun?</p>
                    <a href="login.php" class="text-white font-bold hover:underline flex items-center mt-1">
                        Masuk di sini
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Form Area -->
        <div class="md:w-2/3 p-8">
            <div class="mb-6 text-center md:text-left flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <span class="text-2xl font-black text-blue-700 tracking-tight">Stok<span class="text-green-500">Pintar</span></span>
                    <p class="text-gray-500 text-sm mt-1 font-medium">Lengkapi data profil usaha</p>
                </div>
            </div>

            <!-- Pesan Edukasi (Info Pemisahan Kasir) 
               
            </div>

            <!-- Pesan Notifikasi -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg text-sm font-bold flex items-center <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Akun -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Username</label>
                        <input type="text" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="johndoe">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Password</label>
                        <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="••••••••">
                    </div>
                    
                    <!-- Data Diri -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="Nama Lengkap">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">No. Telepon/WA</label>
                        <input type="text" name="telepon" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="08123456789">
                    </div>

                    <!-- Profil Usaha -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nama Toko</label>
                        <input type="text" name="nama_toko" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="Toko Berkah">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Jenis Usaha</label>
                        <select name="jenis_usaha" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition bg-white font-medium">
                            <option value="Ritel/Toko Kelontong">Ritel/Toko Kelontong</option>
                            <option value="Kuliner/Makanan">Kuliner/Makanan</option>
                            <option value="Pakaian/Fashion">Pakaian/Fashion</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1 mb-4">
                    <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Alamat Usaha</label>
                    <textarea name="alamat_toko" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition font-medium" placeholder="Alamat lengkap lokasi usaha..."></textarea>
                </div>

                <button type="submit" name="register" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all transform active:scale-95 text-lg mt-6">
                    Daftar
                </button>
            </form>
        </div>
    </div>

</body>
</html>