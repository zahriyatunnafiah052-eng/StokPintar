<?php
session_start();
include 'koneksi.php';

$message = "";
$status = "";
$step = 1; // 1: Input Username, 2: Reset Password

// Logika Tahap 1: Cek Username
if (isset($_POST['cek_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $query = mysqli_query($conn, "SELECT * FROM user WHERE username = '$username'");
    
    if (mysqli_num_rows($query) > 0) {
        $_SESSION['reset_user'] = $username;
        $step = 2;
    } else {
        $message = "Username tidak ditemukan!";
        $status = "error";
    }
}

// Logika Tahap 2: Update Password
if (isset($_POST['reset_password'])) {
    $pass_baru = $_POST['password_baru'];
    $konfirmasi = $_POST['konfirmasi_password'];
    $username = $_SESSION['reset_user'];

    if ($pass_baru === $konfirmasi) {
        // Enkripsi password (sesuaikan dengan metode login Anda, misal MD5 atau password_hash)
        $hashed_password = password_hash($pass_baru, PASSWORD_DEFAULT);
        
        // Gunakan MD5 jika login Anda masih menggunakan MD5
        // $hashed_password = md5($pass_baru);

        $query = mysqli_query($conn, "UPDATE user SET password = '$hashed_password' WHERE username = '$username'");

        if ($query) {
            unset($_SESSION['reset_user']);
            header("location:login.php?pesan=reset_sukses");
            exit;
        } else {
            $message = "Gagal memperbarui kata sandi.";
            $status = "error";
        }
    } else {
        $message = "Konfirmasi kata sandi tidak cocok!";
        $status = "error";
        $step = 2;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - StokPintar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-8 rounded-3xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-600">Stok<span class="text-green-500">Pintar</span></h1>
            <p class="text-gray-500 text-sm mt-2">Pemulihan Akun Manajemen Stok</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-xl text-xs font-bold <?php echo $status == 'error' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- Tahap 1: Masukkan Username -->
            <form action="" method="POST" class="space-y-6">
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Username Anda</label>
                    <div class="relative mt-2">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </span>
                        <input type="text" name="username" required placeholder="Masukkan username" class="w-full pl-10 pr-4 py-3 bg-blue-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                    </div>
                </div>
                <button type="submit" name="cek_user" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition-all active:scale-95">
                    Verifikasi Akun
                </button>
            </form>
        <?php else: ?>
            <!-- Tahap 2: Reset Password -->
            <form action="" method="POST" class="space-y-4">
                <p class="text-xs text-gray-500 mb-4 italic text-center">Halo <strong><?php echo $_SESSION['reset_user']; ?></strong>, silakan atur kata sandi baru Anda.</p>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Kata Sandi Baru</label>
                    <input type="password" name="password_baru" required class="w-full mt-2 px-4 py-3 bg-blue-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Konfirmasi Kata Sandi</label>
                    <input type="password" name="konfirmasi_password" required class="w-full mt-2 px-4 py-3 bg-blue-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <button type="submit" name="reset_password" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl shadow-lg transition-all active:scale-95">
                    Simpan Kata Sandi Baru
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="login.php" class="text-sm text-blue-600 font-bold hover:underline">Kembali ke Login</a>
        </div>
    </div>

</body>
</html>