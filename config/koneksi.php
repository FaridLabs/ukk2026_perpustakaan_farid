<?php
// =========================================
// config/koneksi.php
// Koneksi Database Pinjam.In menggunakan PDO
// =========================================

// Zona waktu PHP (WIB) - mempengaruhi date(), strtotime(), DateTime
date_default_timezone_set('Asia/Jakarta');

$host   = 'localhost';
$dbname = 'ukk2026_perpustakaan_farid';
$user   = 'root';
$pass   = ''; // sesuaikan jika password MySQL XAMPP kamu diisi

try {
    $koneksi = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    // Supaya error ditampilkan sebagai Exception (memudahkan debugging)
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Default fetch mode: associative array
    $koneksi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Zona waktu MySQL (WIB = UTC+7) - mempengaruhi NOW(), CURDATE(), CURRENT_TIMESTAMP
    $koneksi->exec("SET time_zone = '+07:00'");

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}