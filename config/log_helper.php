<?php
// =========================================
// config/log_helper.php
// Helper pencatatan log aktivitas sistem
// =========================================

/**
 * Mencatat aktivitas pengguna (admin/siswa/sistem) ke tabel log_aktivitas.
 * Dibungkus try-catch agar kegagalan pencatatan log tidak membatalkan proses utama.
 */
function catatLog(
    PDO $koneksi,
    string $role,
    ?int $userId,
    string $namaPelaku,
    string $aksi,
    string $modul,
    string $keterangan
): void {
    $roleValid = in_array($role, ['admin', 'siswa', 'sistem'], true) ? $role : 'sistem';
    $namaPelaku = trim($namaPelaku) !== '' ? substr(trim($namaPelaku), 0, 100) : 'Sistem';
    $aksi       = substr(trim($aksi), 0, 50);
    $modul      = substr(trim($modul), 0, 50);
    $ipAddress  = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $stmt = $koneksi->prepare("
            INSERT INTO log_aktivitas (role, user_id, nama_pelaku, aksi, modul, keterangan, ip_address)
            VALUES (:role, :user_id, :nama_pelaku, :aksi, :modul, :keterangan, :ip_address)
        ");
        $stmt->bindValue(':role',        $roleValid);
        $stmt->bindValue(':user_id',     $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':nama_pelaku', $namaPelaku);
        $stmt->bindValue(':aksi',        $aksi);
        $stmt->bindValue(':modul',       $modul);
        $stmt->bindValue(':keterangan',  $keterangan);
        $stmt->bindValue(':ip_address',  $ipAddress, $ipAddress === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        // Abaikan jika tabel belum dibuat atau gagal menulis log
    }
}

