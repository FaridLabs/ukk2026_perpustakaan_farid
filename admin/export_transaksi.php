<?php
/**
 * ============================================================================
 * EKSPOR LAPORAN TRANSAKSI SIRKULASI PERPUSTAKAAN (EXCEL / CSV COMPATIBLE)
 * ============================================================================
 * Mengunduh rekapan transaksi perpustakaan ke format CSV yang kompatibel penuh
 * dengan Microsoft Excel, Google Sheets, dan LibreOffice Calc.
 * Dilengkapi UTF-8 BOM agar aksen dan karakter teks Indonesia terbaca sempurna.
 */

require_once __DIR__ . '/../config/cek_session_admin.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/pengaturan_helper.php';

$appConfig = ambilPengaturan($koneksi);
$appNama   = $appConfig['nama_aplikasi'] ?? 'Pinjam.In';
$appSekolah = $appConfig['nama_sekolah'] ?? 'SMKN 1 Panyingkiran';
$appPerpus  = $appConfig['nama_perpustakaan'] ?? 'Perpustakaan Digital SMKN 1 Panyingkiran';

// Ambil parameter filter sesuai halaman riwayat transaksi
$keyword      = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$tglDari      = trim($_GET['tgl_dari'] ?? '');
$tglSampai    = trim($_GET['tgl_sampai'] ?? '');
$today        = date('Y-m-d');

$sql = "
    SELECT transaksi.*, anggota.nama_lengkap, anggota.nis, anggota.kelas, buku.judul, buku_item.kode_item
    FROM transaksi
    LEFT JOIN anggota ON transaksi.anggota_id = anggota.id
    LEFT JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    LEFT JOIN buku ON buku_item.buku_id = buku.id
    WHERE 1=1
";
$params = [];

if (!empty($keyword)) {
    $sql .= " AND (anggota.nama_lengkap LIKE :kw OR anggota.nis LIKE :kw OR buku.judul LIKE :kw OR buku_item.kode_item LIKE :kw OR transaksi.kode_transaksi LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

if (!empty($filterStatus)) {
    if ($filterStatus === 'terlambat') {
        $sql .= " AND (transaksi.status = 'terlambat' OR (transaksi.status = 'dipinjam' AND transaksi.tgl_kembali_rencana < CURDATE()))";
    } elseif ($filterStatus === 'dipinjam') {
        $sql .= " AND transaksi.status = 'dipinjam'";
    } elseif ($filterStatus === 'dikembalikan') {
        $sql .= " AND (transaksi.status = 'dikembalikan' OR (transaksi.status = 'terlambat' AND transaksi.tgl_kembali_aktual IS NOT NULL))";
    } else {
        $sql .= " AND transaksi.status = :status";
        $params[':status'] = $filterStatus;
    }
}

if (!empty($tglDari)) {
    $sql .= " AND transaksi.tgl_pinjam >= :tgl_dari";
    $params[':tgl_dari'] = $tglDari;
}
if (!empty($tglSampai)) {
    $sql .= " AND transaksi.tgl_pinjam <= :tgl_sampai";
    $params[':tgl_sampai'] = $tglSampai;
}

$sql .= " ORDER BY transaksi.tgl_pinjam DESC, transaksi.id DESC";

$stmt = $koneksi->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Siapkan nama file unduhan
$namaFile = 'Laporan_Sirkulasi_' . date('Ymd_His') . '.csv';

// Header HTTP untuk memaksa download file CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tulis UTF-8 Byte Order Mark (BOM) agar Microsoft Excel membuka encoding UTF-8 dengan rapi
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header Informasi Laporan (Kop ringkas)
fputcsv($output, [$appNama . ' - ' . $appPerpus]);
fputcsv($output, [$appSekolah]);
fputcsv($output, ['Tanggal Ekspor: ' . date('d/m/Y H:i') . ' WIB']);
fputcsv($output, ['Petugas: ' . ($_SESSION['admin_nama'] ?? 'Administrator')]);
fputcsv($output, ['Filter: ' . ($filterStatus ? ucfirst($filterStatus) : 'Semua Data')]);
fputcsv($output, []); // Baris kosong

// Judul Kolom Tabel
fputcsv($output, [
    'No',
    'Kode Transaksi',
    'NIS Siswa',
    'Nama Lengkap',
    'Kelas',
    'Kode Eksemplar',
    'Judul Buku',
    'Tanggal Pinjam',
    'Batas Waktu',
    'Tanggal Kembali',
    'Keterangan Sirkulasi',
    'Denda (Rp)',
    'Status Denda',
    'Status Sirkulasi'
]);

// Isi Baris Data
foreach ($data as $i => $row) {
    $isAktifDipinjam = ($row['status'] === 'dipinjam');
    $isTerlambat     = ($isAktifDipinjam && $row['tgl_kembali_rencana'] < $today) || $row['status'] === 'terlambat';

    $keterangan = '-';
    if ($isAktifDipinjam) {
        if ($today > $row['tgl_kembali_rencana']) {
            $h = (int) round((strtotime($today) - strtotime($row['tgl_kembali_rencana'])) / 86400);
            $keterangan = "Lewat {$h} hari";
        } else {
            $h = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($today)) / 86400);
            $keterangan = "Sisa {$h} hari";
        }
    } elseif ($row['tgl_kembali_aktual']) {
        if ($row['tgl_kembali_aktual'] > $row['tgl_kembali_rencana']) {
            $h = (int) round((strtotime($row['tgl_kembali_aktual']) - strtotime($row['tgl_kembali_rencana'])) / 86400);
            $keterangan = "Telat {$h} hari";
        } else {
            $keterangan = "Tepat waktu";
        }
    }

    $statusDenda = '-';
    if ((int)$row['denda'] > 0) {
        $statusDenda = (($row['status_denda'] ?? 'lunas') === 'belum_lunas') ? 'Belum Lunas' : 'Lunas';
    }

    $statusLabel = 'Dipinjam';
    if ($isTerlambat) {
        $statusLabel = 'Terlambat';
    } elseif ($row['status'] === 'dikembalikan') {
        $statusLabel = 'Dikembalikan';
    }

    fputcsv($output, [
        $i + 1,
        $row['kode_transaksi'],
        $row['nis'] ?? '-',
        $row['nama_lengkap'] ?? '[Anggota Dihapus]',
        $row['kelas'] ?? '-',
        $row['kode_item'] ?? '-',
        $row['judul'] ?? '[Buku Dihapus]',
        $row['tgl_pinjam'],
        $row['tgl_kembali_rencana'],
        $row['tgl_kembali_aktual'] ?? '-',
        $keterangan,
        (int)$row['denda'],
        $statusDenda,
        $statusLabel
    ]);
}

fclose($output);
exit;
