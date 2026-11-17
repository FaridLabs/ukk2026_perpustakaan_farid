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

// Cari transaksi pinjaman aktif milik siswa yang sesuai dengan kode QR / barcode buku ini
$stmtTrx = $koneksi->prepare("
    SELECT transaksi.*, buku_item.id AS item_id, buku_item.kode_item, buku.judul 
    FROM transaksi 
    JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    JOIN buku ON buku_item.buku_id = buku.id
    WHERE buku_item.kode_item = :kode_item 
      AND transaksi.anggota_id = :anggota_id 
      AND transaksi.status = 'dipinjam'
    ORDER BY transaksi.id DESC LIMIT 1
");
$stmtTrx->bindParam(':kode_item',  $kode_item);
$stmtTrx->bindParam(':anggota_id', $anggotaId);
$stmtTrx->execute();
$transaksi = $stmtTrx->fetch();

if (!$transaksi) {
    // Cek apakah buku terdaftar tetapi dipinjam siswa lain / sudah dikembalikan
    $cekAda = $koneksi->prepare("SELECT id FROM buku_item WHERE kode_item = :kode_item LIMIT 1");
    $cekAda->bindParam(':kode_item', $kode_item);
    $cekAda->execute();
    if ($cekAda->fetch()) {
        header('Location: ../siswa/riwayat.php?error=bukan_milik');
    } else {
        header('Location: ../siswa/riwayat.php?error=tidak_ditemukan');
    }
    exit;
}

$itemId = $transaksi['item_id'];
$judulBuku = $transaksi['judul'];

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

    $statusDenda = ($isTerlambat && $nominalDenda > 0) ? 'belum_lunas' : 'lunas';

    $stmtUpdateTrx = $koneksi->prepare("
        UPDATE transaksi 
        SET tgl_kembali_aktual = :tgl_kembali_aktual, status = :status, denda = :denda, status_denda = :status_denda
        WHERE id = :id
    ");
    $stmtUpdateTrx->bindParam(':tgl_kembali_aktual', $tglSekarang);
    $stmtUpdateTrx->bindParam(':status', $statusFinal);
    $stmtUpdateTrx->bindValue(':denda',  $nominalDenda, PDO::PARAM_INT);
    $stmtUpdateTrx->bindParam(':status_denda', $statusDenda);
    $stmtUpdateTrx->bindParam(':id', $transaksi['id']);
    $stmtUpdateTrx->execute();

    $stmtUpdateItem = $koneksi->prepare("UPDATE buku_item SET status = 'tersedia' WHERE id = :id");
    $stmtUpdateItem->bindParam(':id', $itemId);
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
        "Mengembalikan buku '{$judulBuku}' ({$kode_item}) secara {$ketTelat} (Kode: {$transaksi['kode_transaksi']})."
    );

    $redirect = '../siswa/riwayat.php?success=' . urlencode($judulBuku);
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