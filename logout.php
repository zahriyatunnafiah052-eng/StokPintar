<?php
// Memulai session
session_start();

// Menghapus semua variabel session
$_SESSION = [];

// Menghancurkan session secara total
session_destroy();

// Menghapus cookie session jika ada (opsional tapi disarankan untuk keamanan ekstra)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Mengarahkan pengguna kembali ke halaman login utama
header("location:login.php");
exit;
?>