<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/pengaturan_helper.php';
require_once '../config/log_helper.php';
require_once '../config/pagination_helper.php';

// Proses pelunasan denda kas perpustakaan jika ada siswa yang baru membayar denda
if (isset($_GET['lunasi']) && !empty($_GET['lunasi'])) {
    $trxId = (int)$_GET['lunasi'];
    $stmtCek = $koneksi->prepare("
        SELECT t.id, t.kode_transaksi, t.denda, a.nama_lengkap 
        FROM transaksi t 
        JOIN anggota a ON t.anggota_id = a.id 
        WHERE t.id = :id AND t.denda > 0 AND t.status_denda = 'belum_lunas'
    ");
    $stmtCek->execute([':id' => $trxId]);
    $trxData = $stmtCek->fetch();

    if ($trxData) {
        $stmtUp = $koneksi->prepare("UPDATE transaksi SET status_denda = 'lunas' WHERE id = :id");
        $stmtUp->execute([':id' => $trxId]);

        catatLog(
            $koneksi,
            'admin',
            isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            $_SESSION['admin_nama'] ?? 'Administrator',
            'Lunasi Denda',
            'Transaksi',
            "Menerima pelunasan kas denda sebesar " . formatRupiah((int)$trxData['denda']) . " dari {$trxData['nama_lengkap']} (Kode: {$trxData['kode_transaksi']})."
        );
    }
    header('Location: transaksi.php?success=lunas');
    exit;
}

$pengaturan   = ambilPengaturan($koneksi);
$tarifDenda   = (int) ($pengaturan['denda_per_hari'] ?? 1000);

$keyword      = trim($_GET['keyword'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$tglDari      = trim($_GET['tgl_dari'] ?? '');
$tglSampai    = trim($_GET['tgl_sampai'] ?? '');
$pageAktif    = max(1, (int)($_GET['page'] ?? 1));
$today        = date('Y-m-d');

// Normalisasi nomor HP ke format internasional Indonesia
function formatNomorWA(?string $nomor): string
{
    $nomor = preg_replace('/\D/', '', $nomor ?? '');

    if ($nomor === '') {
        return '';
    }

    if (strpos($nomor, '0') === 0) {
        $nomor = '62' . substr($nomor, 1);
    } elseif (strpos($nomor, '8') === 0) {
        $nomor = '62' . $nomor;
    }

    return $nomor;
}

// Statistik ringkas transaksi
$totalSemua     = $koneksi->query("SELECT COUNT(*) FROM transaksi")->fetchColumn() ?? 0;
$totalDipinjam  = $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE status = 'dipinjam'")->fetchColumn() ?? 0;
$totalKembali   = $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE status = 'dikembalikan'")->fetchColumn() ?? 0;
$totalTerlambat = $koneksi->query("
    SELECT COUNT(*) FROM transaksi 
    WHERE status = 'terlambat' OR (status = 'dipinjam' AND tgl_kembali_rencana < CURDATE())
")->fetchColumn() ?? 0;

$whereSql = "";
$params = [];

if (!empty($keyword)) {
    $whereSql .= " AND (anggota.nama_lengkap LIKE :kw OR anggota.nis LIKE :kw OR buku.judul LIKE :kw OR buku_item.kode_item LIKE :kw OR transaksi.kode_transaksi LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

if (!empty($filterStatus)) {
    if ($filterStatus === 'terlambat') {
        $whereSql .= " AND (transaksi.status = 'terlambat' OR (transaksi.status = 'dipinjam' AND transaksi.tgl_kembali_rencana < CURDATE()))";
    } elseif ($filterStatus === 'dipinjam') {
        $whereSql .= " AND transaksi.status = 'dipinjam'";
    } elseif ($filterStatus === 'dikembalikan') {
        $whereSql .= " AND (transaksi.status = 'dikembalikan' OR (transaksi.status = 'terlambat' AND transaksi.tgl_kembali_aktual IS NOT NULL))";
    } else {
        $whereSql .= " AND transaksi.status = :status";
        $params[':status'] = $filterStatus;
    }
}

if (!empty($tglDari)) {
    $whereSql .= " AND transaksi.tgl_pinjam >= :tgl_dari";
    $params[':tgl_dari'] = $tglDari;
}
if (!empty($tglSampai)) {
    $whereSql .= " AND transaksi.tgl_pinjam <= :tgl_sampai";
    $params[':tgl_sampai'] = $tglSampai;
}

// Hitung total denda sesuai filter, mencakup seluruh halaman
$sqlTotalDenda = "
    SELECT COALESCE(SUM(transaksi.denda), 0)
    FROM transaksi
    LEFT JOIN anggota ON transaksi.anggota_id = anggota.id
    LEFT JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    LEFT JOIN buku ON buku_item.buku_id = buku.id
    WHERE 1=1 $whereSql
";

$stmtTotalDenda = $koneksi->prepare($sqlTotalDenda);

foreach ($params as $key => $val) {
    $stmtTotalDenda->bindValue($key, $val);
}

$stmtTotalDenda->execute();
$totalDenda = (int) $stmtTotalDenda->fetchColumn();

// Hitung total transaksi sesuai filter untuk pagination
$sqlCount = "
    SELECT COUNT(*)
    FROM transaksi
    LEFT JOIN anggota ON transaksi.anggota_id = anggota.id
    LEFT JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    LEFT JOIN buku ON buku_item.buku_id = buku.id
    WHERE 1=1 $whereSql
";
$stmtCount = $koneksi->prepare($sqlCount);
foreach ($params as $key => $val) {
    $stmtCount->bindValue($key, $val);
}
$stmtCount->execute();
$totalTransaksi = (int) $stmtCount->fetchColumn();

// Hitung pagination (10 transaksi per halaman)
$pagination = hitungPagination($totalTransaksi, 10, $pageAktif);

$sql = "
    SELECT transaksi.*, anggota.nama_lengkap, anggota.nis, anggota.kelas, anggota.no_hp, buku.judul, buku_item.kode_item
    FROM transaksi
    LEFT JOIN anggota ON transaksi.anggota_id = anggota.id
    LEFT JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    LEFT JOIN buku ON buku_item.buku_id = buku.id
    WHERE 1=1 $whereSql
    ORDER BY transaksi.tgl_pinjam DESC, transaksi.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $koneksi->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll();

$pageTitle   = 'Riwayat Transaksi';
$currentPage = 'transaksi';
$extraHead   = '<link rel="stylesheet" href="../assets/css/tabel.css?v=3"><link rel="stylesheet" href="../assets/css/pagination.css?v=1">';

$blnId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
if (!function_exists('tglId')) {
    // Format tanggal Indonesia ringkas (contoh: 16 Nov 2026)
    function tglId(?string $t, array $b): string
    {
        if (!$t) return '-';
        $ts = strtotime($t);
        return date('j', $ts) . ' ' . $b[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    }
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
        <a href="export_transaksi.php<?= $adaFilter ? '?' . http_build_query($_GET) : '' ?>" class="btn-d" style="background:#fff; border:1px solid var(--line); font-weight:600; text-decoration:none;" title="Unduh data dalam format CSV / Excel">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            <span>Ekspor Excel (.xls)</span>
        </a>
        <button type="button" onclick="cetakLaporan('Laporan_Transaksi_PinjamIn_<?= date('Y-m-d') ?>')" class="btn-d main" title="Cetak laporan atau pilih Simpan sebagai PDF"><?= ic('printer') ?>Cetak / Export PDF</button>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'lunas'): ?>
    <div class="kc-al ok" role="status" style="margin-bottom:18px;">
        <?= ic('check') ?>
        <div>Pelunasan denda kas perpustakaan berhasil dicatat ke sistem.</div>
    </div>
<?php endif; ?>

<!-- RINGKASAN -->
<section class="d-stats four" aria-label="Ringkasan transaksi"
    style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
    <?php foreach (
        [
            ['grid',  'slate', $totalSemua,     'Total Seluruh Transaksi',  'seluruh riwayat sirkulasi'],
            ['open',  'blue',  $totalDipinjam,  'Sedang Dipinjam Siswa',    'belum dikembalikan'],
            ['check', 'green', $totalKembali,   'Selesai Dikembalikan',     'sudah kembali ke stok'],
            ['clock', 'red',   $totalTerlambat, 'Terlambat Dikembalikan',   'melewati batas kembali'],
            ['dollar-sign', 'red', $totalDenda, 'Total Denda Tercatat', 'akumulasi denda sesuai filter'],
        ] as [$ik, $wr, $n, $jd, $sb]
    ): ?>
        <article class="stat t-<?= $wr ?>">
            <div class="stat-ico">
                <?php if ($jd === 'Total Denda Tercatat'): ?>
                    <svg width="23" height="23" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                        <path d="M2 10h20"/>
                        <path d="M12 14v2"/>
                        <path d="M9 14h6"/>
                    </svg>
                <?php else: ?>
                    <?= ic($ik, 23) ?>
                <?php endif; ?>
            </div>
            <strong>
                <?= $jd === 'Total Denda Tercatat'
                    ? formatRupiah((int) $n)
                    : (int) $n ?>
            </strong>
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
            <svg class="s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7.5" />
                <path d="m21 21-4.3-4.3" />
            </svg>
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
                    <tr>
                        <th style="width:38px;text-align:center">No</th>
                        <th>Kode Transaksi</th>
                        <th>Siswa Peminjam</th>
                        <th>Buku</th>
                        <th>Tgl Pinjam</th>
                        <th>Batas Waktu</th>
                        <th>Tgl Kembali</th>
                        <th>Keterangan</th>
                        <th>Total Denda</th>
                        <th>Status</th>
                        <th>Hubungi WA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $i => $row):
                        $isAktifDipinjam = $row['status'] === 'dipinjam';
                        $isTerlambat = ($isAktifDipinjam && $row['tgl_kembali_rencana'] < $today) || $row['status'] === 'terlambat';

                        $kt = '-';
                        $ktKls = 'none';
                        if ($isAktifDipinjam) {
                            if ($today > $row['tgl_kembali_rencana']) {
                                $h = (int) round((strtotime($today) - strtotime($row['tgl_kembali_rencana'])) / 86400);
                                $kt = "Lewat {$h} hari";
                                $ktKls = 'late';
                            } else {
                                $h = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($today)) / 86400);
                                $kt = "Sisa {$h} hari";
                                $ktKls = 'run';
                            }
                        } elseif ($row['tgl_kembali_aktual']) {
                            if ($row['tgl_kembali_aktual'] > $row['tgl_kembali_rencana']) {
                                $h = (int) round((strtotime($row['tgl_kembali_aktual']) - strtotime($row['tgl_kembali_rencana'])) / 86400);
                                $kt = "Telat {$h} hari";
                                $ktKls = 'warn';
                            } else {
                                $kt = 'Tepat waktu';
                                $ktKls = 'ok';
                            }
                        }

                        if ($isTerlambat) {
                            $bd = 'late';
                            $lb = 'Terlambat';
                        } elseif ($row['status'] === 'dikembalikan') {
                            $bd = 'ok';
                            $lb = 'Dikembalikan';
                        } else {
                            $bd = 'run';
                            $lb = 'Dipinjam';
                        }

                        // Hitung denda yang tercatat atau estimasi denda berjalan
                        $dendaTercatat = (int) ($row['denda'] ?? 0);
                    ?>
                        <tr>
                            <td style="text-align:center"><?= $pagination['from'] + $i ?></td>
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
                                    <strong style="color: #C23B3B; display:block;"><?= formatRupiah($dendaTercatat) ?></strong>
                                    <?php if (($row['status_denda'] ?? 'lunas') === 'belum_lunas'): ?>
                                        <span style="display:inline-block; font-size:10.5px; font-weight:700; color:#B4600A; background:#FDEBD3; padding:2px 6px; border-radius:4px; margin-top:2px;">Belum Lunas</span>
                                        <a href="transaksi.php?lunasi=<?= (int)$row['id'] ?>" class="btn-d" style="font-size:11px; padding:2px 8px; margin-top:4px; display:inline-block; text-decoration:none;" onclick="return confirm('Konfirmasi: Siswa telah membayar uang denda sebesar <?= formatRupiah($dendaTercatat) ?>?')">Tandai Lunas</a>
                                    <?php else: ?>
                                        <span style="display:inline-block; font-size:10.5px; font-weight:700; color:#1B7A55; background:#DDF3E8; padding:2px 6px; border-radius:4px; margin-top:2px;">Lunas</span>
                                    <?php endif; ?>
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
                            <td>
                                <?php
                                $nomorWA = formatNomorWA($row['no_hp'] ?? '');

                                if ($nomorWA !== ''):
                                    $pesanWA = "Halo {$row['nama_lengkap']}, "
                                        . "kami dari perpustakaan ingin menginformasikan "
                                        . "mengenai transaksi peminjaman buku Anda. "
                                        . "Kode transaksi: {$row['kode_transaksi']}. "
                                        . "Judul buku: {$row['judul']}. "
                                        . "Batas pengembalian: "
                                        . tglId($row['tgl_kembali_rencana'], $blnId)
                                        . ". Terima kasih.";

                                    $linkWA = "https://wa.me/{$nomorWA}?text="
                                        . urlencode($pesanWA);
                                ?>
                                    <a href="<?= htmlspecialchars($linkWA, ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn-d"
                                        style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:5px;
                                        background:#25D366;
                                        color:#fff;
                                        border:0;
                                        text-decoration:none;
                                        font-size:11px;
                                        padding:6px 9px;
                                        white-space:nowrap;
                                    ">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            width="16"
                                            height="16"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true">
                                            <path d="M20.52 3.48A11.87 11.87 0 0 0 12.05 0C5.47 0 .12 5.35.12 11.93c0 2.1.55 4.15 1.6 5.96L0 24l6.27-1.65a11.9 11.9 0 0 0 5.78 1.47h.01c6.58 0 11.93-5.35 11.93-11.93a11.87 11.87 0 0 0-3.47-8.41ZM12.06 21.8a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.72.98.99-3.63-.24-.37a9.86 9.86 0 0 1-1.52-5.26c0-5.46 4.45-9.91 9.91-9.91a9.86 9.86 0 0 1 7.01 2.9 9.86 9.86 0 0 1 2.9 7.01c0 5.46-4.45 9.91-9.91 9.91Zm5.44-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.46-.89-.79-1.49-1.76-1.67-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.21 5.09 4.5.71.31 1.26.5 1.69.64.71.23 1.35.2 1.86.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
                                        </svg>
                                        Hubungi WA
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:11px;">
                                        Nomor belum tersedia
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                        <tr>
                            <td colspan="8" style="text-align:right; font-weight:700; padding:10px;">
                                TOTAL DENDA KESELURUHAN:
                            </td>
                            <td class="nw" style="font-weight:700; color:#C23B3B; padding:10px;">
                                <?= formatRupiah($totalDenda) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>

                    </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?= renderPagination($pagination) ?>
    <?php renderPrintFoot($totalTransaksi, $infoCetak['tglCetak'], $infoCetak['jamCetak'], $infoCetak['namaPetugas']); ?>
</section>
<?php require 'includes/footer.php'; ?>