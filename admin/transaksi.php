<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/pengaturan_helper.php';

$pengaturan   = ambilPengaturan($koneksi);
$tarifDenda   = (int) ($pengaturan['denda_per_hari'] ?? 1000);

$keyword      = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$tglDari      = trim($_GET['tgl_dari'] ?? '');
$tglSampai    = trim($_GET['tgl_sampai'] ?? '');
$today        = date('Y-m-d');

// Statistik ringkas transaksi
$totalSemua     = $koneksi->query("SELECT COUNT(*) FROM transaksi")->fetchColumn() ?? 0;
$totalDipinjam  = $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE status = 'dipinjam'")->fetchColumn() ?? 0;
$totalKembali   = $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE status = 'dikembalikan'")->fetchColumn() ?? 0;
$totalTerlambat = $koneksi->query("
    SELECT COUNT(*) FROM transaksi 
    WHERE status = 'terlambat' OR (status = 'dipinjam' AND tgl_kembali_rencana < CURDATE())
")->fetchColumn() ?? 0;

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
$data = $stmt->fetchAll();

$pageTitle   = 'Riwayat Transaksi';
$currentPage = 'transaksi';
$extraHead   = '<link rel="stylesheet" href="../assets/css/tabel.css?v=3">';

$blnId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function tglId(?string $t, array $b): string {
    if (!$t) return '-';
    $ts = strtotime($t);
    return date('j', $ts) . ' ' . $b[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
require_once 'includes/print_report.php';
$infoCetak = infoCetakWib();

$adaFilter = $keyword !== '' || $filterStatus !== '' || $tglDari !== '' || $tglSampai !== '';
$labelStatus = ['dipinjam' => 'Sedang dipinjam', 'dikembalikan' => 'Sudah dikembalikan', 'terlambat' => 'Terlambat'];
$ringkas = [];
if ($filterStatus !== '') $ringkas[] = 'Status: ' . ($labelStatus[$filterStatus] ?? $filterStatus);
if ($keyword !== '')      $ringkas[] = 'Kata kunci: "' . $keyword . '"';
if ($tglDari !== '')      $ringkas[] = 'Dari ' . tglId($tglDari, $blnId);
if ($tglSampai !== '')    $ringkas[] = 'Sampai ' . tglId($tglSampai, $blnId);

// Tautan pil status: pertahankan kata kunci dan rentang tanggal
$pertahankan = array_filter(['keyword' => $keyword, 'tgl_dari' => $tglDari, 'tgl_sampai' => $tglSampai], fn($v) => $v !== '');
$pilHref = fn(string $st) => 'transaksi.php' . ($pertahankan || $st !== '' ? '?' . http_build_query($st !== '' ? $pertahankan + ['status' => $st] : $pertahankan) : '');

require 'includes/header.php';
?>
<div class="d-head">
    <div>
        <h1>Riwayat &amp; Laporan Transaksi</h1>
        <p>Arsip seluruh aktivitas sirkulasi peminjaman dan pengembalian buku siswa.</p>
    </div>
    <div class="d-btn-row">
        <button type="button" onclick="cetakLaporan('Laporan_Transaksi_PinjamIn_<?= date('Y-m-d') ?>')" class="btn-d main" title="Cetak laporan atau pilih Simpan sebagai PDF"><?= ic('printer') ?>Cetak / Export PDF</button>
    </div>
</div>

<!-- RINGKASAN -->
<section class="d-stats four" aria-label="Ringkasan transaksi">
    <?php foreach ([
        ['grid',  'slate', $totalSemua,     'Total Seluruh Transaksi',  'seluruh riwayat sirkulasi'],
        ['open',  'blue',  $totalDipinjam,  'Sedang Dipinjam Siswa',    'belum dikembalikan'],
        ['check', 'green', $totalKembali,   'Selesai Dikembalikan',     'sudah kembali ke stok'],
        ['clock', 'red',   $totalTerlambat, 'Terlambat Dikembalikan',   'melewati batas kembali'],
    ] as [$ik, $wr, $n, $jd, $sb]): ?>
    <article class="stat t-<?= $wr ?>">
        <div class="stat-ico"><?= ic($ik, 23) ?></div>
        <strong><?= (int) $n ?></strong>
        <h3><?= $jd ?></h3>
        <p><?= $sb ?></p>
    </article>
    <?php endforeach; ?>
</section>

<!-- FILTER -->
<section class="tr-bar" aria-label="Filter transaksi">
    <nav class="pills" aria-label="Filter status">
        <?php foreach ([['', 'Semua'], ['dipinjam', 'Sedang Dipinjam'], ['dikembalikan', 'Sudah Dikembalikan'], ['terlambat', 'Terlambat']] as [$kode, $lbl]): ?>
            <a href="<?= htmlspecialchars($pilHref($kode)) ?>" class="<?= $filterStatus === $kode ? 'on' : '' ?>" <?= $filterStatus === $kode ? 'aria-current="true"' : '' ?>><?= $lbl ?></a>
        <?php endforeach; ?>
    </nav>

    <form action="transaksi.php" method="GET" class="tr-form" autocomplete="off">
        <?php if ($filterStatus !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
        <div class="tr-f has-ico">
            <label for="keyword">Kata kunci</label>
            <svg class="s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="keyword" name="keyword" placeholder="Nama siswa, NIS, judul buku, kode..." value="<?= htmlspecialchars($keyword) ?>" autocomplete="off">
        </div>
        <div class="tr-f">
            <label for="tgl_dari">Dari tanggal</label>
            <input type="date" id="tgl_dari" name="tgl_dari" value="<?= htmlspecialchars($tglDari) ?>">
        </div>
        <div class="tr-f">
            <label for="tgl_sampai">Sampai tanggal</label>
            <input type="date" id="tgl_sampai" name="tgl_sampai" value="<?= htmlspecialchars($tglSampai) ?>">
        </div>
        <div class="tr-act">
            <button type="submit" class="tr-btn">Terapkan</button>
            <?php if ($adaFilter): ?><a href="transaksi.php" class="tr-reset">Reset</a><?php endif; ?>
        </div>
    </form>
</section>

<!-- TABEL -->
<section class="act" aria-labelledby="hTrx">
    <div class="act-top">
        <div class="panel-ico"><?= ic('history') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hTrx">Daftar transaksi</h2>
            <p><?= $adaFilter ? 'Hasil sesuai filter yang dipilih.' : 'Diurutkan dari peminjaman terbaru.' ?></p>
        </div>
        <span class="tr-count">Ditemukan <b><?= count($data) ?></b> transaksi</span>
    </div>
    <?php renderPrintHead($infoCetak + [
        'subUnit'    => htmlspecialchars($pengaturan['nama_perpustakaan']),
        'docBadge'   => 'Dokumen Laporan Resmi',
        'judul'      => 'Laporan Transaksi Sirkulasi Buku',
        'filterText' => $ringkas ? implode('  |  ', $ringkas) : 'Semua transaksi (tanpa filter)',
        'countText'  => 'Total: ' . count($data) . ' transaksi',
    ]); ?>

    <div class="tbl wide">
        <?php if (empty($data)): ?>
            <div class="tr-empty">
                <span><?= ic('history') ?></span>
                <b><?= $adaFilter ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi tercatat' ?></b>
                <?php if ($adaFilter): ?>
                    Coba ubah kata kunci atau rentang tanggal, atau <a href="transaksi.php">reset filter</a>.
                <?php else: ?>
                    Transaksi akan muncul di sini setelah ada peminjaman pertama.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th style="width:38px;text-align:center">No</th><th>Kode Transaksi</th><th>Siswa Peminjam</th><th>Buku</th><th>Tgl Pinjam</th><th>Batas Waktu</th><th>Tgl Kembali</th><th>Keterangan</th><th>Denda</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $row):
                $isAktifDipinjam = $row['status'] === 'dipinjam';
                $isTerlambat = ($isAktifDipinjam && $row['tgl_kembali_rencana'] < $today) || $row['status'] === 'terlambat';

                $kt = '-'; $ktKls = 'none';
                if ($isAktifDipinjam) {
                    if ($today > $row['tgl_kembali_rencana']) {
                        $h = (int) round((strtotime($today) - strtotime($row['tgl_kembali_rencana'])) / 86400);
                        $kt = "Lewat {$h} hari"; $ktKls = 'late';
                    } else {
                        $h = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($today)) / 86400);
                        $kt = "Sisa {$h} hari"; $ktKls = 'run';
                    }
                } elseif ($row['tgl_kembali_aktual']) {
                    if ($row['tgl_kembali_aktual'] > $row['tgl_kembali_rencana']) {
                        $h = (int) round((strtotime($row['tgl_kembali_aktual']) - strtotime($row['tgl_kembali_rencana'])) / 86400);
                        $kt = "Telat {$h} hari"; $ktKls = 'warn';
                    } else {
                        $kt = 'Tepat waktu'; $ktKls = 'ok';
                    }
                }

                if ($isTerlambat)                       { $bd = 'late'; $lb = 'Terlambat'; }
                elseif ($row['status'] === 'dikembalikan') { $bd = 'ok';   $lb = 'Dikembalikan'; }
                else                                    { $bd = 'run';  $lb = 'Dipinjam'; }

                // Hitung denda yang tercatat atau estimasi denda berjalan
                $dendaTercatat = (int) ($row['denda'] ?? 0);
            ?>
                <tr>
                    <td style="text-align:center"><?= $i + 1 ?></td>
                    <td><code><?= htmlspecialchars($row['kode_transaksi']) ?></code></td>
                    <td>
                        <?php if ($row['nama_lengkap']): ?>
                            <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                            <small>NIS <?= htmlspecialchars($row['nis'] ?? '-') ?> &middot; <?= htmlspecialchars($row['kelas'] ?? '-') ?></small>
                        <?php else: ?><span class="gone">[Anggota dihapus]</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['judul'])): ?>
                            <strong><?= htmlspecialchars($row['judul']) ?></strong>
                            <small><?= htmlspecialchars($row['kode_item'] ?? '-') ?></small>
                        <?php else: ?><span class="gone">[Buku / eksemplar dihapus]</span><?php endif; ?>
                    </td>
                    <td class="nw"><?= tglId($row['tgl_pinjam'], $blnId) ?></td>
                    <td class="nw"><?= tglId($row['tgl_kembali_rencana'], $blnId) ?></td>
                    <td class="nw"><?= tglId($row['tgl_kembali_aktual'], $blnId) ?></td>
                    <td><span class="kt <?= $ktKls ?>"><?= $kt ?></span></td>
                    <td class="nw">
                        <?php if ($dendaTercatat > 0): ?>
                            <strong style="color: #C23B3B;"><?= formatRupiah($dendaTercatat) ?></strong>
                        <?php elseif ($isAktifDipinjam && $today > $row['tgl_kembali_rencana']): 
                            $h = (int) round((strtotime($today) - strtotime($row['tgl_kembali_rencana'])) / 86400);
                            $estDenda = $h * $tarifDenda;
                        ?>
                            <span style="color: #C23B3B; font-size: 12px;" title="Estimasi denda berjalan sampai buku dikembalikan">Est. <?= formatRupiah($estDenda) ?></span>
                        <?php else: ?>
                            <span style="color: var(--muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="bd <?= $bd ?>"><?= $lb ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php renderPrintFoot(count($data), $infoCetak['tglCetak'], $infoCetak['jamCetak'], $infoCetak['namaPetugas']); ?>
</section>
<?php require 'includes/footer.php'; ?>