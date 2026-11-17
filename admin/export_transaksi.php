<?php
/**
 * ============================================================================
 * EKSPOR LAPORAN TRANSAKSI SIRKULASI PERPUSTAKAAN (FORMAT MICROSOFT EXCEL .XLS)
 * ============================================================================
 * Mengunduh rekapan sirkulasi peminjaman & pengembalian perpustakaan ke format
 * Microsoft Excel resmi dengan tata letak profesional, kop sekolah, lebar kolom
 * otomatis, garis tabel rapi, pewarnaan status, dan format mata uang Rupiah.
 */

require_once __DIR__ . '/../config/cek_session_admin.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/pengaturan_helper.php';

$appConfig  = ambilPengaturan($koneksi);
$appNama    = $appConfig['nama_aplikasi'] ?? 'Pinjam.In';
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

// Hitung total denda
$totalDendaKumulatif = 0;
foreach ($data as $r) {
    $totalDendaKumulatif += (int)($r['denda'] ?? 0);
}

// Nama file unduhan
$namaFile = 'Laporan_Sirkulasi_' . date('Ymd_His') . '.xls';

// Header HTTP untuk unduhan file Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Format tanggal Indonesia ringkas
function formatTglExcel(?string $t): string {
    if (!$t || $t === '0000-00-00') return '-';
    $time = strtotime($t);
    return date('d/m/Y', $time);
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml>
  <x:ExcelWorkbook>
    <x:ExcelWorksheets>
      <x:ExcelWorksheet>
        <x:Name>Laporan Sirkulasi</x:Name>
        <x:WorksheetOptions>
          <x:DisplayGridlines/>
          <x:FitToPage/>
          <x:Print>
            <x:ValidPrinterInfo/>
            <x:PaperSizeIndex>9</x:PaperSizeIndex>
            <x:HorizontalResolution>600</x:HorizontalResolution>
            <x:VerticalResolution>600</x:VerticalResolution>
          </x:Print>
        </x:WorksheetOptions>
      </x:ExcelWorksheet>
    </x:ExcelWorksheets>
  </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 10px; }
  table { border-collapse: collapse; width: 100%; table-layout: auto; }
  
  /* Kop Laporan */
  .kop-main { font-size: 15pt; font-weight: bold; color: #1F7A4D; text-align: left; }
  .kop-sub  { font-size: 12pt; font-weight: bold; color: #14232B; text-align: left; }
  .kop-meta { font-size: 9.5pt; color: #5F6D73; text-align: left; }
  
  /* Header Tabel */
  th { background-color: #217350; color: #ffffff; font-weight: bold; border: 1.5px solid #17543A; padding: 10px 8px; text-align: center; vertical-align: middle; }
  
  /* Sel Data */
  td { border: 1px solid #CBD5E1; padding: 8px 9px; vertical-align: middle; font-size: 9.5pt; }
  
  /* Utility Kelas */
  .tc { text-align: center; }
  .tl { text-align: left; }
  .tr { text-align: right; }
  
  /* MSO Format untuk Excel */
  .mso-text { mso-number-format: "\@"; }
  .mso-curr { mso-number-format: "\"Rp\"\#\,\#\#0"; }
  
  /* Zebra Striping */
  .row-even { background-color: #FFFFFF; }
  .row-odd  { background-color: #F8FAF9; }
  
  /* Label Status */
  .badge-kembali { background-color: #DDF3E8; color: #1B7A55; font-weight: bold; text-align: center; border-radius: 4px; }
  .badge-pinjam  { background-color: #E3ECFD; color: #2F63D8; font-weight: bold; text-align: center; border-radius: 4px; }
  .badge-telat   { background-color: #FBE1E1; color: #C23B3B; font-weight: bold; text-align: center; border-radius: 4px; }
  .badge-lunas   { background-color: #DDF3E8; color: #1B7A55; font-weight: bold; text-align: center; }
  .badge-belum   { background-color: #FDEBD3; color: #B4600A; font-weight: bold; text-align: center; }
  
  /* Total Ringkasan */
  .row-total { background-color: #EAF3EE; font-weight: bold; border-top: 2px solid #217350; border-bottom: 2px solid #217350; }
</style>
</head>
<body>

<table>
  <!-- KOP LAPORAN RESMI -->
  <tr>
    <td colspan="14" class="kop-main"><?= htmlspecialchars($appNama) ?> &ndash; <?= htmlspecialchars($appPerpus) ?></td>
  </tr>
  <tr>
    <td colspan="14" class="kop-sub"><?= htmlspecialchars($appSekolah) ?></td>
  </tr>
  <tr>
    <td colspan="14" class="kop-meta">
      Tanggal Cetak: <b><?= date('d/m/Y H:i') ?> WIB</b> &nbsp;|&nbsp; 
      Petugas: <b><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?></b> &nbsp;|&nbsp;
      Filter Status: <b><?= $filterStatus ? ucfirst(htmlspecialchars($filterStatus)) : 'Semua Data' ?></b>
      <?php if (!empty($keyword)): ?>
        &nbsp;|&nbsp; Kata Kunci: "<b><?= htmlspecialchars($keyword) ?></b>"
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td colspan="14" style="height: 12px; border: none;"></td>
  </tr>

  <!-- HEADER TABEL -->
  <thead>
    <tr>
      <th style="width: 45px;">No</th>
      <th style="width: 170px;">Kode Transaksi</th>
      <th style="width: 105px;">NIS Siswa</th>
      <th style="width: 190px;">Nama Lengkap</th>
      <th style="width: 95px;">Kelas</th>
      <th style="width: 125px;">Kode Eksemplar</th>
      <th style="width: 260px;">Judul Buku</th>
      <th style="width: 110px;">Tanggal Pinjam</th>
      <th style="width: 110px;">Batas Waktu</th>
      <th style="width: 110px;">Tanggal Kembali</th>
      <th style="width: 130px;">Keterangan Sirkulasi</th>
      <th style="width: 120px;">Denda</th>
      <th style="width: 110px;">Status Denda</th>
      <th style="width: 125px;">Status Sirkulasi</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($data)): ?>
      <tr>
        <td colspan="14" class="tc" style="padding: 24px; color: #64748b; font-style: italic;">
          Tidak ada data transaksi yang sesuai dengan filter pencarian saat ini.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($data as $i => $row): 
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

        $dendaNominal = (int)($row['denda'] ?? 0);
        $statusDendaText = '-';
        $statusDendaClass = '';
        if ($dendaNominal > 0) {
            $isLunas = (($row['status_denda'] ?? 'lunas') === 'lunas');
            $statusDendaText = $isLunas ? 'Lunas' : 'Belum Lunas';
            $statusDendaClass = $isLunas ? 'badge-lunas' : 'badge-belum';
        }

        $statusLabel = 'Dipinjam';
        $statusClass = 'badge-pinjam';
        if ($isTerlambat) {
            $statusLabel = 'Terlambat';
            $statusClass = 'badge-telat';
        } elseif ($row['status'] === 'dikembalikan') {
            $statusLabel = 'Dikembalikan';
            $statusClass = 'badge-kembali';
        }

        $rowClass = ($i % 2 === 0) ? 'row-even' : 'row-odd';
      ?>
        <tr class="<?= $rowClass ?>">
          <td class="tc"><?= $i + 1 ?></td>
          <td class="tc mso-text" style="font-weight: 600;"><?= htmlspecialchars($row['kode_transaksi']) ?></td>
          <td class="tc mso-text"><?= htmlspecialchars($row['nis'] ?? '-') ?></td>
          <td class="tl" style="font-weight: 600;"><?= htmlspecialchars($row['nama_lengkap'] ?? '[Anggota Dihapus]') ?></td>
          <td class="tc"><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
          <td class="tc mso-text"><?= htmlspecialchars($row['kode_item'] ?? '-') ?></td>
          <td class="tl"><?= htmlspecialchars($row['judul'] ?? '[Buku Dihapus]') ?></td>
          <td class="tc"><?= formatTglExcel($row['tgl_pinjam']) ?></td>
          <td class="tc"><?= formatTglExcel($row['tgl_kembali_rencana']) ?></td>
          <td class="tc"><?= formatTglExcel($row['tgl_kembali_aktual']) ?></td>
          <td class="tc"><?= htmlspecialchars($keterangan) ?></td>
          <td class="tr mso-curr" style="<?= $dendaNominal > 0 ? 'color: #C23B3B; font-weight: bold;' : 'color: #64748b;' ?>">
            <?= $dendaNominal > 0 ? 'Rp ' . number_format($dendaNominal, 0, ',', '.') : 'Rp 0' ?>
          </td>
          <td class="tc <?= $statusDendaClass ?>"><?= $statusDendaText ?></td>
          <td class="tc <?= $statusClass ?>"><?= $statusLabel ?></td>
        </tr>
      <?php endforeach; ?>

      <!-- BARIS TOTAL RINGKASAN -->
      <tr class="row-total">
        <td colspan="7" class="tl" style="padding: 10px 12px;">
          TOTAL KESELURUHAN: <b><?= count($data) ?> Transaksi</b>
        </td>
        <td colspan="4" class="tr">TOTAL KAS DENDA:</td>
        <td class="tr mso-curr" style="color: #C23B3B; font-size: 10.5pt;">
          Rp <?= number_format($totalDendaKumulatif, 0, ',', '.') ?>
        </td>
        <td colspan="2" class="tc" style="font-size: 9pt; color: #475569;">
          <?= $totalDendaKumulatif > 0 ? 'Tercatat di Sistem' : 'Nihil' ?>
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>
<?php exit; ?>
