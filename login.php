<?php
session_start();
include 'koneksi.php';

$message = "";
$status = "";

// Jika pengguna sudah login, langsung arahkan ke jalurnya masing-masing
if (isset($_SESSION['status']) && $_SESSION['status'] == "login") {
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'kasir') {
        header("location:kasir.php");
    } else {
        header("location:dashboard.php");
    }
    exit;
}

// Cek jika ada kiriman pesan dari reset password sukses
if (isset($_GET['pesan']) && $_GET['pesan'] == "reset_sukses") {
    $message = "Kata sandi berhasil diubah! Silakan masuk.";
    $status = "success";
}

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Pastikan nama tabel (users) konsisten dengan database Anda
    $query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($query) === 1) {
        $data = mysqli_fetch_assoc($query);

        // Verifikasi password yang di-hash
        if (password_verify($password, $data['password'])) {
            // Set Session Inti
            $_SESSION['user_id']      = $data['id'];
            $_SESSION['username']     = $data['username'];
            $_SESSION['nama_toko']    = $data['nama_toko'];
            $_SESSION['nama_pemilik'] = $data['nama_pemilik'];
            $_SESSION['status']       = "login";
            
            // Set Session Tambahan (RBAC: Role & Hubungan Karyawan-Bos)
            $_SESSION['role']         = $data['role']; 
            $_SESSION['owner_id']     = $data['owner_id']; 

            $message = "Login Berhasil! Mengalihkan...";
            $status = "success";
            
            // LOGIKA PERSIMPANGAN BERDASARKAN ROLE
            if ($data['role'] == 'kasir') {
                // Jika Kasir, lempar ke menu kasir penjualan
                header("refresh:1;url=kasir.php");
            } else {
                // Jika Owner (Default), lempar ke Dashboard utama
                header("refresh:1;url=dashboard.php");
            }
        } else {
            $message = "Password salah!";
            $status = "error";
        }
    } else {
        $message = "Username tidak ditemukan!";
        $status = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - StokPintar UMKM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-pattern {
            background-color: #f3f4f6;
            background-image: url("https://www.transparenttextures.com/patterns/cubes.png");
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center py-12 px-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden">
        <div class="p-8">
            <!-- Logo & Title -->
            <div class="text-center mb-8">
                <a href="index.php" class="text-3xl font-bold text-blue-700">Stok<span class="text-green-600">Pintar</span></a>
                <p class="text-gray-500 text-sm mt-2">Masuk ke akun manajemen stok Anda</p>
            </div>

            <!-- Pesan Notifikasi -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl text-sm flex items-center space-x-3 <?php echo $status == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <!-- Username -->
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wider">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </span>
                        <input type="text" name="username" required class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Masukkan username">
                    </div>
                </div>

                <!-- Password dengan Fitur Mata -->
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wider">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </span>
                        <!-- ID ditambahkan dan padding right diperlebar (pr-12) agar teks tidak tertutup icon mata -->
                        <input type="password" id="input_password" name="password" required class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Masukkan password">
                        
                        <!-- Tombol Toggle Mata -->
                        <button type="button" id="toggle_password" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-blue-600 focus:outline-none transition-colors">
                            <svg id="eye_icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-600">Ingat Saya</label>
                    </div>
                    <a href="lupa_password.php" class="text-sm text-blue-600 font-bold hover:underline">Lupa Password?</a>
                </div>

                <button type="submit" name="login" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 rounded-xl shadow-lg transition-all transform hover:scale-[1.02] flex justify-center items-center">
                    Masuk Sekarang
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">Belum memiliki akun Toko? 
                    <a href="register.php" class="text-blue-700 font-bold hover:underline">Daftar Gratis</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Script JavaScript untuk Fitur Mata -->
    <script>
        const inputPassword = document.getElementById('input_password');
        const togglePasswordBtn = document.getElementById('toggle_password');
        const eyeIcon = document.getElementById('eye_icon');

        togglePasswordBtn.addEventListener('click', function() {
            // Ubah tipe input dari password ke text, atau sebaliknya
            if (inputPassword.type === 'password') {
                inputPassword.type = 'text';
                // Ubah SVG menjadi ikon mata terbuka/dicoret
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
            } else {
                inputPassword.type = 'password';
                // Kembalikan SVG ke ikon mata standar
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        });
    </script>
</body>
</html>