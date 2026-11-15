<?php
session_start();
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../daftar.php');
    exit;
}

$nis                 = trim($_POST['nis'] ?? '');
$nama_lengkap        = trim($_POST['nama_lengkap'] ?? '');
$kelas               = trim($_POST['kelas'] ?? '');
$no_hp               = trim($_POST['no_hp'] ?? '');
$alamat              = trim($_POST['alamat'] ?? '');
$username            = trim($_POST['username'] ?? '');
$password            = $_POST['password'] ?? '';
$konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

// Validasi field wajib
if (empty($nis) || empty($nama_lengkap) || empty($kelas) || empty($username) || empty($password)) {
    header('Location: ../daftar.php?error=empty');
    exit;
}

// Validasi konfirmasi password
if ($password !== $konfirmasi_password) {
    header('Location: ../daftar.php?error=password_beda');
    exit;
}

// Cek NIS sudah terdaftar atau belum
$cekNis = $koneksi->prepare("SELECT id FROM anggota WHERE nis = :nis LIMIT 1");
$cekNis->bindParam(':nis', $nis);
$cekNis->execute();
if ($cekNis->fetch()) {
    header('Location: ../daftar.php?error=nis_ada');
    exit;
}

// Cek username sudah dipakai atau belum
$cekUsername = $koneksi->prepare("SELECT id FROM anggota WHERE username = :username LIMIT 1");
$cekUsername->bindParam(':username', $username);
$cekUsername->execute();
if ($cekUsername->fetch()) {
    header('Location: ../daftar.php?error=username_ada');
    exit;
}

// Hash password sebelum disimpan
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Simpan ke database
$stmt = $koneksi->prepare("
    INSERT INTO anggota (nis, nama_lengkap, kelas, no_hp, alamat, username, password)
    VALUES (:nis, :nama_lengkap, :kelas, :no_hp, :alamat, :username, :password)
");
$stmt->bindParam(':nis', $nis);
$stmt->bindParam(':nama_lengkap', $nama_lengkap);
$stmt->bindParam(':kelas', $kelas);
$stmt->bindParam(':no_hp', $no_hp);
$stmt->bindValue(':alamat', $alamat !== '' ? $alamat : null, $alamat !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
$stmt->bindParam(':username', $username);
$stmt->bindParam(':password', $passwordHash);
$stmt->execute();

$anggotaBaruId = (int)$koneksi->lastInsertId();
catatLog(
    $koneksi,
    'siswa',
    $anggotaBaruId ?: null,
    $nama_lengkap,
    'Daftar',
    'Anggota',
    "Siswa {$nama_lengkap} (NIS: {$nis}, Kelas: {$kelas}) mendaftar sebagai anggota baru."
);

// Redirect langsung ke login dengan notifikasi sukses daftar
header('Location: ../login.php?success=daftar');
exit;