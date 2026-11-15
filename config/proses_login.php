<?php
session_start();
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: ../login.php?error=empty');
    exit;
}

// ================= CEK KE TABEL ADMIN DULU =================
$stmtAdmin = $koneksi->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
$stmtAdmin->bindParam(':username', $username);
$stmtAdmin->execute();
$admin = $stmtAdmin->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_nama'] = $admin['nama_lengkap'];
    $_SESSION['admin_foto'] = $admin['foto'] ?? null;
    catatLog(
        $koneksi,
        'admin',
        (int)$admin['id'],
        $admin['nama_lengkap'],
        'Login',
        'Autentikasi',
        "Admin {$admin['nama_lengkap']} (@{$admin['username']}) login ke sistem."
    );
    header('Location: ../admin/dashboard.php');
    exit;
}

// ================= KALAU BUKAN ADMIN, CEK KE TABEL ANGGOTA (SISWA) =================
$stmtAnggota = $koneksi->prepare("SELECT * FROM anggota WHERE username = :username LIMIT 1");
$stmtAnggota->bindParam(':username', $username);
$stmtAnggota->execute();
$anggota = $stmtAnggota->fetch();

if ($anggota && password_verify($password, $anggota['password'])) {
    $_SESSION['anggota_id']    = $anggota['id'];
    $_SESSION['anggota_nama']  = $anggota['nama_lengkap'];
    $_SESSION['anggota_kelas'] = $anggota['kelas'];
    $_SESSION['anggota_foto']  = $anggota['foto'] ?? null;
    catatLog(
        $koneksi,
        'siswa',
        (int)$anggota['id'],
        $anggota['nama_lengkap'],
        'Login',
        'Autentikasi',
        "Siswa {$anggota['nama_lengkap']} (NIS: {$anggota['nis']}) login ke sistem."
    );
    header('Location: ../siswa/dashboard.php');
    exit;
}

// ================= KALAU DUA-DUANYA TIDAK COCOK =================
catatLog(
    $koneksi,
    'sistem',
    null,
    $username,
    'Login Gagal',
    'Autentikasi',
    "Percobaan login gagal menggunakan username '{$username}'."
);
header('Location: ../login.php?error=invalid');
exit;