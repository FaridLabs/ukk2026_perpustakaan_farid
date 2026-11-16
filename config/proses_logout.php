<?php
// =========================================
// config/proses_logout.php
// Logout untuk admin maupun siswa
// =========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';

if (isset($_SESSION['admin_id'])) {
    catatLog(
        $koneksi,
        'admin',
        (int)$_SESSION['admin_id'],
        $_SESSION['admin_nama'] ?? 'Administrator',
        'Logout',
        'Autentikasi',
        'Admin keluar (logout) dari sistem.'
    );
} elseif (isset($_SESSION['anggota_id'])) {
    catatLog(
        $koneksi,
        'siswa',
        (int)$_SESSION['anggota_id'],
        $_SESSION['anggota_nama'] ?? 'Siswa',
        'Logout',
        'Autentikasi',
        'Siswa keluar (logout) dari sistem.'
    );
}

session_unset();
session_destroy();

header('Location: ../index.php');
exit;