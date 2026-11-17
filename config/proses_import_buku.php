<?php
/**
 * ============================================================================
 * PROSES IMPORT KOLEKSI BUKU MASSAL DARI FILE CSV
 * ============================================================================
 * Memungkinkan pustakawan mengunggah template CSV untuk menambahkan puluhan atau
 * ratusan buku sekaligus secara otomatis beserta pembuatan eksemplar fisiknya.
 */

require_once __DIR__ . '/cek_session_admin.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/log_helper.php';

$adminId   = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
$adminNama = $_SESSION['admin_nama'] ?? 'Administrator';

// Mode 1: Unduh Contoh Template CSV
if (isset($_GET['template']) && $_GET['template'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_import_buku.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM untuk Excel
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header Kolom Template
    fputcsv($out, ['Judul Buku', 'Pengarang', 'Penerbit', 'Tahun Terbit', 'Kategori', 'Jumlah Eksemplar']);
    
    // Baris Contoh Data
    fputcsv($out, ['Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2005', 'Fiksi', '3']);
    fputcsv($out, ['Dasar-Dasar Rekayasa Perangkat Lunak', 'Kemendikbudristek', 'Pusat Kurikulum', '2022', 'Pendidikan', '5']);
    fputcsv($out, ['Filosofi Teras', 'Henry Manampiring', 'Kompas', '2019', 'Pengembangan Diri', '2']);

    fclose($out);
    exit;
}

// Mode 2: Pemrosesan Unggah File CSV
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/buku.php');
    exit;
}

if (!isset($_FILES['file_csv']) || ($_FILES['file_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: ../admin/buku.php?error=empty&msg=' . urlencode('Pilih file CSV yang valid.'));
    exit;
}

$fileTmp = $_FILES['file_csv']['tmp_name'];
if (empty($fileTmp) || !file_exists($fileTmp)) {
    header('Location: ../admin/buku.php?error=empty&msg=' . urlencode('File CSV tidak ditemukan.'));
    exit;
}

$handle = fopen($fileTmp, 'r');
if (!$handle) {
    header('Location: ../admin/buku.php?error=system&msg=' . urlencode('Gagal membaca file CSV.'));
    exit;
}

// Lewati UTF-8 BOM jika ada
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// Baca header baris pertama
$header = fgetcsv($handle, 1000, ',');
if (!$header) {
    fclose($handle);
    header('Location: ../admin/buku.php?error=empty&msg=' . urlencode('File CSV kosong.'));
    exit;
}

// Cache kategori yang sudah ada di database untuk efisiensi query
$kategoriMap = [];
$stmtKat = $koneksi->query("SELECT id, LOWER(TRIM(nama_kategori)) AS nama FROM kategori");
while ($k = $stmtKat->fetch(PDO::FETCH_ASSOC)) {
    $kategoriMap[$k['nama']] = (int)$k['id'];
}

$totalBerhasil = 0;

try {
    $koneksi->beginTransaction();

    $stmtInsertBuku = $koneksi->prepare("
        INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, kategori_id, cover)
        VALUES (:kode_buku, :judul, :pengarang, :penerbit, :tahun_terbit, :kategori_id, NULL)
    ");

    $stmtInsertItem = $koneksi->prepare("
        INSERT INTO buku_item (kode_item, buku_id, status)
        VALUES (:kode_item, :buku_id, 'tersedia')
    ");

    $stmtInsertKat = $koneksi->prepare("INSERT INTO kategori (nama_kategori) VALUES (:nama)");

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        // Abaikan baris kosong
        if (empty(array_filter($row))) {
            continue;
        }

        $judul        = trim($row[0] ?? '');
        $pengarang    = trim($row[1] ?? '');
        $penerbit     = trim($row[2] ?? '');
        $tahun        = !empty($row[3]) ? (int)$row[3] : null;
        $namaKategori = trim($row[4] ?? 'Umum');
        $jumlahItem   = !empty($row[5]) ? max(1, min(50, (int)$row[5])) : 1;

        if (empty($judul)) {
            continue; // judul wajib ada
        }

        // Resolusi Kategori: Buat baru jika kategori belum ada di database
        $katKey = strtolower($namaKategori);
        if (isset($kategoriMap[$katKey])) {
            $kategoriId = $kategoriMap[$katKey];
        } else {
            $stmtInsertKat->execute([':nama' => $namaKategori]);
            $kategoriId = (int)$koneksi->lastInsertId();
            $kategoriMap[$katKey] = $kategoriId;
        }

        // Generate kode master buku unik
        do {
            $kodeBuku = 'BK-' . date('YmdHis') . rand(10, 99);
            $cek = $koneksi->prepare("SELECT COUNT(*) FROM buku WHERE kode_buku = :kode");
            $cek->execute([':kode' => $kodeBuku]);
        } while ($cek->fetchColumn() > 0);

        // Simpan data master buku
        $stmtInsertBuku->execute([
            ':kode_buku'    => $kodeBuku,
            ':judul'        => $judul,
            ':pengarang'    => $pengarang ?: null,
            ':penerbit'     => $penerbit ?: null,
            ':tahun_terbit' => $tahun,
            ':kategori_id'  => $kategoriId,
        ]);

        $bukuId = (int)$koneksi->lastInsertId();

        // Buat sejumlah eksemplar fisik otomatis
        for ($i = 1; $i <= $jumlahItem; $i++) {
            $kodeItem = "BK-{$bukuId}-" . str_pad($i, 3, '0', STR_PAD_LEFT);
            $stmtInsertItem->execute([
                ':kode_item' => $kodeItem,
                ':buku_id'   => $bukuId
            ]);
        }

        $totalBerhasil++;
    }

    $koneksi->commit();
    fclose($handle);

    catatLog(
        $koneksi,
        'admin',
        $adminId,
        $adminNama,
        'Import CSV',
        'Buku',
        "Mengimpor massal {$totalBerhasil} judul buku baru dari file CSV."
    );

    header('Location: ../admin/buku.php?success=import&jumlah=' . $totalBerhasil);
    exit;

} catch (Exception $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    fclose($handle);
    header('Location: ../admin/buku.php?error=system&msg=' . urlencode('Terjadi kesalahan saat memproses baris CSV: ' . $e->getMessage()));
    exit;
}
