<?php
require 'cek_session_siswa.php';
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/pengaturan_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../siswa/pinjam.php');
    exit;
}

// Ambil aturan dinamis batas kuota dan durasi pinjam dari pengaturan sistem
$pengaturan     = ambilPengaturan($koneksi);
$batasPinjam    = (int) ($pengaturan['maks_pinjam_buku'] ?? 3);
$lamaPinjamHari = (int) ($pengaturan['lama_pinjam_hari'] ?? 7);

$anggotaId      = $_SESSION['anggota_id'];
$kode_item      = trim($_POST['kode_item'] ?? '');

if (empty($kode_item)) {
    header('Location: ../siswa/pinjam.php');
    exit;
}

// Cek batas peminjaman aktif sesuai kuota dinamis pengaturan sistem
$cekAktif = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi WHERE anggota_id = :id AND status = 'dipinjam'");
$cekAktif->bindParam(':id', $anggotaId);
$cekAktif->execute();
if ($cekAktif->fetch()['total'] >= $batasPinjam) {
    header('Location: ../siswa/pinjam.php?error=batas');
    exit;
}

// Cari eksemplar buku berdasarkan kode QR unik hasil scan
$stmtItem = $koneksi->prepare("
    SELECT buku_item.*, buku.judul 
    FROM buku_item 
    JOIN buku ON buku_item.buku_id = buku.id
    WHERE buku_item.kode_item = :kode_item LIMIT 1
");
$stmtItem->bindParam(':kode_item', $kode_item);
$stmtItem->execute();
$item = $stmtItem->fetch();

if (!$item) {
    header('Location: ../siswa/pinjam.php?error=tidak_ditemukan');
    exit;
}

if ($item['status'] !== 'tersedia') {
    header('Location: ../siswa/pinjam.php?error=tidak_tersedia');
    exit;
}

// Cek apakah siswa ini sudah meminjam eksemplar lain dari judul buku yang sama
$cekSudah = $koneksi->prepare("
    SELECT transaksi.id FROM transaksi
    JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    WHERE transaksi.anggota_id = :id AND transaksi.status = 'dipinjam' AND buku_item.buku_id = :buku_id
    LIMIT 1
");
$cekSudah->bindParam(':id',      $anggotaId);
$cekSudah->bindParam(':buku_id', $item['buku_id']);
$cekSudah->execute();
if ($cekSudah->fetch()) {
    header('Location: ../siswa/pinjam.php?error=sudah_pinjam');
    exit;
}

// Mulai transaksi database (atomic)
try {
    $koneksi->beginTransaction();

    $tglPinjam     = date('Y-m-d');
    $tglRencana    = date('Y-m-d', strtotime("+{$lamaPinjamHari} days"));
    // Format kode unik: TRX-TahunBulanTanggalJamMenitDetik-IDAnggota-Random
    $kodeTransaksi = 'TRX-' . date('YmdHis') . '-' . $anggotaId . '-' . rand(100, 999);

    $stmtInsert = $koneksi->prepare("
        INSERT INTO transaksi (kode_transaksi, anggota_id, buku_item_id, tgl_pinjam, tgl_kembali_rencana, status)
        VALUES (:kode_transaksi, :anggota_id, :buku_item_id, :tgl_pinjam, :tgl_kembali_rencana, 'dipinjam')
    ");
    $stmtInsert->bindParam(':kode_transaksi', $kodeTransaksi);
    $stmtInsert->bindParam(':anggota_id',     $anggotaId);
    $stmtInsert->bindParam(':buku_item_id',   $item['id']);
    $stmtInsert->bindParam(':tgl_pinjam',     $tglPinjam);
    $stmtInsert->bindParam(':tgl_kembali_rencana', $tglRencana);
    $stmtInsert->execute();

    $stmtUpdate = $koneksi->prepare("UPDATE buku_item SET status = 'dipinjam' WHERE id = :id");
    $stmtUpdate->bindParam(':id', $item['id']);
    $stmtUpdate->execute();

    $koneksi->commit();

    catatLog(
        $koneksi,
        'siswa',
        (int)$anggotaId,
        $_SESSION['anggota_nama'] ?? 'Siswa',
        'Pinjam',
        'Transaksi',
        "Meminjam buku '{$item['judul']}' ({$kode_item}) dengan kode transaksi {$kodeTransaksi}."
    );

    header('Location: ../siswa/pinjam.php?success=' . urlencode($item['judul']));
    exit;

} catch (Exception $e) {
    $koneksi->rollBack();
    header('Location: ../siswa/pinjam.php?error=tidak_ditemukan');
    exit;
}