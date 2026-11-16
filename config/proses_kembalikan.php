<?php
require 'cek_session_siswa.php';
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/pengaturan_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../siswa/riwayat.php');
    exit;
}

$anggotaId = $_SESSION['anggota_id'];
$kode_item = trim($_POST['kode_item'] ?? '');

if (empty($kode_item)) {
    header('Location: ../siswa/riwayat.php');
    exit;
}

// Cari buku_item berdasarkan kode_item hasil scan
$stmtItem = $koneksi->prepare("
    SELECT buku_item.*, buku.judul 
    FROM buku_item 
    JOIN buku ON buku_item.buku_id = buku.id
    WHERE kode_item = :kode_item LIMIT 1
");
$stmtItem->bindParam(':kode_item', $kode_item);
$stmtItem->execute();
$item = $stmtItem->fetch();

if (!$item) {
    header('Location: ../siswa/riwayat.php?error=tidak_ditemukan');
    exit;
}

// Cari transaksi aktif (status dipinjam) untuk buku_item ini, dan pastikan milik anggota yang login
$stmtTrx = $koneksi->prepare("
    SELECT * FROM transaksi 
    WHERE buku_item_id = :buku_item_id AND status = 'dipinjam'
    ORDER BY id DESC LIMIT 1
");
$stmtTrx->bindParam(':buku_item_id', $item['id']);
$stmtTrx->execute();
$transaksi = $stmtTrx->fetch();

if (!$transaksi) {
    header('Location: ../siswa/riwayat.php?error=tidak_ditemukan');
    exit;
}

if ($transaksi['anggota_id'] != $anggotaId) {
    header('Location: ../siswa/riwayat.php?error=bukan_milik');
    exit;
}

// Ambil besaran tarif denda keterlambatan dari pengaturan sistem
$pengaturan    = ambilPengaturan($koneksi);
$tarifDenda    = (int) ($pengaturan['denda_per_hari'] ?? 1000);

// Cek keterlambatan dan kalkulasi denda
$tglSekarang   = date('Y-m-d');
$isTerlambat   = $tglSekarang > $transaksi['tgl_kembali_rencana'];
$statusFinal   = $isTerlambat ? 'terlambat' : 'dikembalikan';
$hariTerlambat = 0;
$nominalDenda  = 0;

if ($isTerlambat) {
    $hariTerlambat = (int) round((strtotime($tglSekarang) - strtotime($transaksi['tgl_kembali_rencana'])) / 86400);
    $nominalDenda  = $hariTerlambat * $tarifDenda;
}

try {
    $koneksi->beginTransaction();

    $stmtUpdateTrx = $koneksi->prepare("
        UPDATE transaksi 
        SET tgl_kembali_aktual = :tgl_kembali_aktual, status = :status, denda = :denda
        WHERE id = :id
    ");
    $stmtUpdateTrx->bindParam(':tgl_kembali_aktual', $tglSekarang);
    $stmtUpdateTrx->bindParam(':status', $statusFinal);
    $stmtUpdateTrx->bindValue(':denda',  $nominalDenda, PDO::PARAM_INT);
    $stmtUpdateTrx->bindParam(':id', $transaksi['id']);
    $stmtUpdateTrx->execute();

    $stmtUpdateItem = $koneksi->prepare("UPDATE buku_item SET status = 'tersedia' WHERE id = :id");
    $stmtUpdateItem->bindParam(':id', $item['id']);
    $stmtUpdateItem->execute();

    $koneksi->commit();

    $ketTelat = $isTerlambat ? "terlambat {$hariTerlambat} hari (denda " . formatRupiah($nominalDenda) . ")" : 'tepat waktu';
    catatLog(
        $koneksi,
        'siswa',
        (int)$anggotaId,
        $_SESSION['anggota_nama'] ?? 'Siswa',
        'Kembali',
        'Transaksi',
        "Mengembalikan buku '{$item['judul']}' ({$kode_item}) secara {$ketTelat} (Kode: {$transaksi['kode_transaksi']})."
    );

    $redirect = '../siswa/riwayat.php?success=' . urlencode($item['judul']);
    if ($isTerlambat) {
        $redirect .= '&terlambat=1&hari=' . $hariTerlambat . '&denda=' . $nominalDenda;
    }
    header('Location: ' . $redirect);
    exit;

} catch (Exception $e) {
    $koneksi->rollBack();
    header('Location: ../siswa/riwayat.php?error=tidak_ditemukan');
    exit;
}