<?php
require 'cek_session_admin.php';
require 'koneksi.php';
require_once __DIR__ . '/upload_cover.php';

// Jika file yang diunggah melebihi post_max_size, PHP mengosongkan $_POST sepenuhnya
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    header('Location: ../admin/buku.php?error=cover&pesan=' . urlencode('Ukuran file melebihi batas server. Gunakan cover maksimal 2 MB.'));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'tambah') {

    $judul            = trim($_POST['judul'] ?? '');
    $pengarang        = trim($_POST['pengarang'] ?? '');
    $penerbit         = trim($_POST['penerbit'] ?? '');
    $tahun_terbit     = $_POST['tahun_terbit'] ?? null;
    $kategori_id      = $_POST['kategori_id'] ?? null;
    $jumlah_eksemplar = (int)($_POST['jumlah_eksemplar'] ?? 1);

    if ($jumlah_eksemplar < 0) $jumlah_eksemplar = 0;
    if ($jumlah_eksemplar > 100) $jumlah_eksemplar = 100;

    if (empty($judul) || empty($kategori_id)) {
        header('Location: ../admin/buku.php?error=empty');
        exit;
    }

    // Upload cover (opsional). Dilakukan sebelum transaksi supaya file tidak valid langsung ditolak.
    $hasilCover = simpanCover($_FILES['cover'] ?? null);
    if (!$hasilCover['ok']) {
        header('Location: ../admin/buku.php?error=cover&pesan=' . urlencode($hasilCover['error']));
        exit;
    }
    $coverBaru = $hasilCover['file']; // null jika tidak ada file diunggah

    try {
        $koneksi->beginTransaction();

        // Auto-generate kode_buku yang unik
        do {
            $kode_buku = 'BK-' . date('YmdHis') . rand(10, 99);
            $cekKode = $koneksi->prepare("SELECT COUNT(*) AS total FROM buku WHERE kode_buku = :kode");
            $cekKode->bindParam(':kode', $kode_buku);
            $cekKode->execute();
        } while ($cekKode->fetch()['total'] > 0);

        $stmt = $koneksi->prepare("
            INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, kategori_id, cover)
            VALUES (:kode_buku, :judul, :pengarang, :penerbit, :tahun_terbit, :kategori_id, :cover)
        ");
        $stmt->bindParam(':kode_buku',    $kode_buku);
        $stmt->bindParam(':judul',        $judul);
        $stmt->bindParam(':pengarang',    $pengarang);
        $stmt->bindParam(':penerbit',     $penerbit);
        $stmt->bindParam(':tahun_terbit', $tahun_terbit);
        $stmt->bindParam(':kategori_id',  $kategori_id);
        $stmt->bindValue(':cover',        $coverBaru, $coverBaru === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        $bukuId = $koneksi->lastInsertId();

        // Generate N eksemplar fisik dengan kode QR unik otomatis
        if ($jumlah_eksemplar > 0) {
            $stmtItem = $koneksi->prepare("INSERT INTO buku_item (kode_item, buku_id, status) VALUES (:kode_item, :buku_id, 'tersedia')");
            for ($i = 1; $i <= $jumlah_eksemplar; $i++) {
                $kode_item = "BK-{$bukuId}-" . str_pad($i, 3, '0', STR_PAD_LEFT);
                $stmtItem->bindParam(':kode_item', $kode_item);
                $stmtItem->bindParam(':buku_id',   $bukuId);
                $stmtItem->execute();
            }
        }

        $koneksi->commit();
        header('Location: ../admin/buku.php?success=tambah&id=' . $bukuId . '&jumlah=' . $jumlah_eksemplar);
        exit;

    } catch (Exception $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        hapusCover($coverBaru); // jangan tinggalkan file yatim jika data gagal disimpan
        header('Location: ../admin/buku.php?error=system');
        exit;
    }

} elseif ($action === 'edit') {

    $id           = $_POST['id'] ?? '';
    $judul        = trim($_POST['judul'] ?? '');
    $pengarang    = trim($_POST['pengarang'] ?? '');
    $penerbit     = trim($_POST['penerbit'] ?? '');
    $tahun_terbit = $_POST['tahun_terbit'] ?? null;
    $kategori_id  = $_POST['kategori_id'] ?? null;
    $hapusCover   = !empty($_POST['hapus_cover']);

    if (empty($judul) || empty($kategori_id) || empty($id)) {
        header('Location: ../admin/buku.php?error=empty');
        exit;
    }

    // Ambil cover yang sedang dipakai
    $stmtLama = $koneksi->prepare("SELECT cover FROM buku WHERE id = :id");
    $stmtLama->bindParam(':id', $id);
    $stmtLama->execute();
    $barisLama = $stmtLama->fetch();
    if (!$barisLama) {
        header('Location: ../admin/buku.php');
        exit;
    }
    $coverLama = $barisLama['cover'] ?? null;

    // Upload cover baru (opsional)
    $hasilCover = simpanCover($_FILES['cover'] ?? null);
    if (!$hasilCover['ok']) {
        header('Location: ../admin/buku.php?edit=' . urlencode($id) . '&error=cover&pesan=' . urlencode($hasilCover['error']));
        exit;
    }
    $coverBaru = $hasilCover['file'];

    // Tentukan cover akhir: file baru > hapus > tetap pakai yang lama
    if ($coverBaru !== null) {
        $coverFinal = $coverBaru;
    } elseif ($hapusCover) {
        $coverFinal = null;
    } else {
        $coverFinal = $coverLama;
    }

    try {
        $stmt = $koneksi->prepare("
            UPDATE buku SET judul = :judul, pengarang = :pengarang, penerbit = :penerbit,
                tahun_terbit = :tahun_terbit, kategori_id = :kategori_id, cover = :cover
            WHERE id = :id
        ");
        $stmt->bindParam(':judul',        $judul);
        $stmt->bindParam(':pengarang',    $pengarang);
        $stmt->bindParam(':penerbit',     $penerbit);
        $stmt->bindParam(':tahun_terbit', $tahun_terbit);
        $stmt->bindParam(':kategori_id',  $kategori_id);
        $stmt->bindValue(':cover',        $coverFinal, $coverFinal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id',           $id);
        $stmt->execute();
    } catch (Exception $e) {
        hapusCover($coverBaru); // batalkan file baru jika update gagal
        header('Location: ../admin/buku.php?error=system');
        exit;
    }

    // Data tersimpan, baru hapus file cover lama yang sudah tidak dipakai
    if (!empty($coverLama) && $coverLama !== $coverFinal) {
        hapusCover($coverLama);
    }

    header('Location: ../admin/buku.php?success=edit');
    exit;

} elseif ($action === 'hapus') {

    $id = $_GET['id'] ?? '';
    if (empty($id)) { header('Location: ../admin/buku.php'); exit; }

    // Cek apakah judul masih punya eksemplar
    $cek = $koneksi->prepare("SELECT COUNT(*) AS total FROM buku_item WHERE buku_id = :id");
    $cek->bindParam(':id', $id);
    $cek->execute();

    if ($cek->fetch()['total'] > 0) {
        header('Location: ../admin/buku.php?error=ada_item');
        exit;
    }

    // Simpan nama cover dulu supaya file bisa dihapus setelah barisnya terhapus
    $stmtCover = $koneksi->prepare("SELECT cover FROM buku WHERE id = :id");
    $stmtCover->bindParam(':id', $id);
    $stmtCover->execute();
    $coverHapus = $stmtCover->fetch()['cover'] ?? null;

    $stmt = $koneksi->prepare("DELETE FROM buku WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    hapusCover($coverHapus);

    header('Location: ../admin/buku.php?success=hapus');
    exit;

} else {
    header('Location: ../admin/buku.php');
    exit;
}