<?php
require 'cek_session_admin.php';
require 'koneksi.php';
require_once __DIR__ . '/log_helper.php';

// Ambil action dari POST (form) atau GET (link hapus)
$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$adminId   = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
$adminNama = $_SESSION['admin_nama'] ?? 'Administrator';

if ($action === 'tambah') {

    $nama_kategori = trim($_POST['nama_kategori'] ?? '');

    if (empty($nama_kategori)) {
        header('Location: ../admin/kategori.php?error=empty');
        exit;
    }

    $stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori) VALUES (:nama)");
    $stmt->bindParam(':nama', $nama_kategori);
    $stmt->execute();

    catatLog($koneksi, 'admin', $adminId, $adminNama, 'Tambah', 'Kategori', "Menambahkan kategori baru: {$nama_kategori}.");

    header('Location: ../admin/kategori.php?success=1');
    exit;

} elseif ($action === 'edit') {

    $id            = $_POST['id'] ?? '';
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');

    if (empty($nama_kategori) || empty($id)) {
        header('Location: ../admin/kategori.php?error=empty');
        exit;
    }

    $stmt = $koneksi->prepare("UPDATE kategori SET nama_kategori = :nama WHERE id = :id");
    $stmt->bindParam(':nama', $nama_kategori);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    catatLog($koneksi, 'admin', $adminId, $adminNama, 'Edit', 'Kategori', "Mengubah nama kategori (ID #{$id}) menjadi '{$nama_kategori}'.");

    header('Location: ../admin/kategori.php?success=1');
    exit;

} elseif ($action === 'hapus') {

    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        header('Location: ../admin/kategori.php');
        exit;
    }

    // Cek apakah kategori masih dipakai oleh buku
    $cek = $koneksi->prepare("SELECT COUNT(*) AS total FROM buku WHERE kategori_id = :id");
    $cek->bindParam(':id', $id);
    $cek->execute();

    if ($cek->fetch()['total'] > 0) {
        header('Location: ../admin/kategori.php?error=ada_buku');
        exit;
    }

    $stmtInfo = $koneksi->prepare("SELECT nama_kategori FROM kategori WHERE id = :id LIMIT 1");
    $stmtInfo->bindParam(':id', $id);
    $stmtInfo->execute();
    $katInfo = $stmtInfo->fetch();

    $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    if ($katInfo) {
        catatLog($koneksi, 'admin', $adminId, $adminNama, 'Hapus', 'Kategori', "Menghapus kategori '{$katInfo['nama_kategori']}'.");
    }

    header('Location: ../admin/kategori.php?success=1');
    exit;

} else {
    header('Location: ../admin/kategori.php');
    exit;
}