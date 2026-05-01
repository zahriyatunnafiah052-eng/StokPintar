<?php
session_start();
include 'koneksi.php';

// Proteksi Halaman: Cek apakah user sudah login akun utama
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:login.php");
    exit;
}

$message = "";
$status = "";

// Jika sudah pernah verifikasi PIN dalam sesi ini, langsung ke laporan
if (isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] == true) {
    header("location:laporan_analisis.php");
    exit;
}

if (isset($_POST['verifikasi'])) {
    $pin_input = $_POST['pin'];
    $user_id = $_SESSION['user_id'];

    // Ambil PIN dari database
    $query = mysqli_query($conn, "SELECT security_pin FROM users WHERE id = '$user_id'");
    $data = mysqli_fetch_assoc($query);

    if ($data) {
        // Verifikasi PIN yang di-hash
        if (password_verify($pin_input, $data['security_pin'])) {
            $_SESSION['pin_verified'] = true;
            $message = "Verifikasi Berhasil! Membuka laporan...";
            $status = "success";
            header("refresh:1;url=laporan_analisis.php");
        } else {
            $message = "PIN yang Anda masukkan salah!";
            $status = "error";
        }
    } else {
        $message = "Terjadi kesalahan sistem.";
        $status = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Keamanan - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .pin-input:focus {
            letter-spacing: 0.5em;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">

    <div class="max-w-md w-full">
        <!-- Logo Kembali ke Dashboard -->
        <div class="text-center mb-8">
            <a href="dashboard.php" class="inline-flex items-center text-gray-500 hover:text-blue-700 transition font-medium text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Kembali ke Dashboard
            </a>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
            <!-- Header Keamanan -->
            <div class="bg-yellow-500 p-6 text-white text-center">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h2 class="text-xl font-extrabold uppercase tracking-widest">Area Terproteksi</h2>
                <p class="text-yellow-100 text-xs mt-1">Masukkan PIN 6-digit untuk mengakses laporan</p>
            </div>

            <div class="p-8">
                <!-- Pesan Notifikasi -->
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-xl text-center text-sm <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6 text-center">
                    <div>
                        <input type="password" 
                               name="pin" 
                               maxlength="6" 
                               pattern="\d{6}" 
                               inputmode="numeric"
                               required 
                               autofocus
                               class="pin-input w-full text-center text-3xl font-bold tracking-[0.3em] py-4 border-2 border-gray-200 rounded-2xl focus:border-yellow-500 focus:ring-0 outline-none transition-all placeholder-gray-300" 
                               placeholder="••••••">
                        <p class="text-[10px] text-gray-400 mt-4 uppercase font-bold tracking-tighter italic">Hanya Pemilik yang Memiliki Akses ke Menu Ini</p>
                    </div>

                    <button type="submit" name="verifikasi" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-extrabold py-4 rounded-2xl shadow-lg transition-all transform hover:scale-[1.02] flex justify-center items-center">
                        VERIFIKASI & BUKA LAPORAN
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-8 text-center text-gray-400 text-xs">
            &copy; 2024 StokPintar Keamanan Data UMKM
        </div>
    </div>

</body>
</html>