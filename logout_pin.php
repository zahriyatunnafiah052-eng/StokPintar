<?php
session_start();

/**
 * File: logout_pin.php
 * Fungsi: Menghapus status verifikasi PIN laporan agar area sensitif terkunci kembali.
 * Catatan: Ini TIDAK mengeluarkan (logout) pengguna dari sistem utama, hanya mengunci laporan.
 */

// Menghapus session spesifik untuk verifikasi PIN
if (isset($_SESSION['pin_verified'])) {
    unset($_SESSION['pin_verified']);
}

// Mengarahkan kembali ke dashboard
header("location:dashboard.php");
exit;
?>