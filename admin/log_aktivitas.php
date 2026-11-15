<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';

$keyword     = trim($_GET['keyword'] ?? '');
$filterRole  = $_GET['role'] ?? '';
$filterModul = trim($_GET['modul'] ?? '');
$tglDari     = trim($_GET['tgl_dari'] ?? '');
$tglSampai   = trim($_GET['tgl_sampai'] ?? '');
$today       = date('Y-m-d');

$tabelSiap = true;
$totalSemua = 0;
$totalHariIni = 0;
$totalAdmin = 0;
$totalSiswa = 0;
$daftarModul = [];
$data = [];

try {
    $totalSemua   = (int) ($koneksi->query("SELECT COUNT(*) FROM log_aktivitas")->fetchColumn() ?: 0);
    $totalHariIni = (int) ($koneksi->query("SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0);
    $totalAdmin   = (int) ($koneksi->query("SELECT COUNT(*) FROM log_aktivitas WHERE role = 'admin'")->fetchColumn() ?: 0);
    $totalSiswa   = (int) ($koneksi->query("SELECT COUNT(*) FROM log_aktivitas WHERE role = 'siswa'")->fetchColumn() ?: 0);

    $daftarModul = $koneksi->query("SELECT DISTINCT modul FROM log_aktivitas ORDER BY modul ASC")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT * FROM log_aktivitas WHERE 1=1";
    $params = [];

    if ($keyword !== '') {
        $sql .= " AND (nama_pelaku LIKE :kw OR aksi LIKE :kw OR modul LIKE :kw OR keterangan LIKE :kw OR ip_address LIKE :kw)";
        $params[':kw'] = "%{$keyword}%";
    }

    if (in_array($filterRole, ['admin', 'siswa', 'sistem'], true)) {
        $sql .= " AND role = :role";
        $params[':role'] = $filterRole;
    } else {
        $filterRole = '';
    }

    if ($filterModul !== '') {
        $sql .= " AND modul = :modul";
        $params[':modul'] = $filterModul;
    }

    if ($tglDari !== '') {
        $sql .= " AND DATE(created_at) >= :tgl_dari";
        $params[':tgl_dari'] = $tglDari;
    }
    if ($tglSampai !== '') {
        $sql .= " AND DATE(created_at) <= :tgl_sampai";
        $params[':tgl_sampai'] = $tglSampai;
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

    $stmt = $koneksi->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $data = $stmt->fetchAll();
} catch (PDOException $e) {
    $tabelSiap = false;
}

$pageTitle   = 'Log Aktivitas';
$currentPage = 'log_aktivitas';
$extraHead   = '<link rel="stylesheet" href="../assets/css/tabel.css?v=3">'
    . '<style>
        .tr-form.has-modul { grid-template-columns: minmax(200px, 2fr) minmax(145px, 1fr) minmax(145px, 1fr) minmax(145px, 1fr) auto; }
        .tr-f select { width:100%!important; height:44px!important; padding:0 12px!important; border:1.5px solid #cbd6d0!important; border-radius:10px!important; background:#fafcfb!important; font-family:inherit!important; font-size:14px!important; color:var(--ink)!important; box-shadow:none!important; }
        .tr-f select:focus { outline:none; border-color:var(--g)!important; background:#fff!important; box-shadow:0 0 0 4px rgba(33,115,80,.14)!important; }
        .bd.warn { background:#fdebd3; color:#b4600a; }
        .bd.sys { background:#eef2f6; color:#475569; }
        @media (max-width: 1100px) { .tr-form.has-modul { grid-template-columns: 1fr 1fr; } }
      </style>';

$blnId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtWaktuLog(?string $dt, array $b): string {
    if (!$dt) return '-';
    $ts = strtotime($dt);
    return date('j', $ts) . ' ' . $b[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) . ', ' . date('H:i:s', $ts);
}
require_once 'includes/print_report.php';
$infoCetak = infoCetakWib();

$adaFilter = $keyword !== '' || $filterRole !== '' || $filterModul !== '' || $tglDari !== '' || $tglSampai !== '';
$pertahankan = array_filter([
    'keyword'    => $keyword,
    'modul'      => $filterModul,
    'tgl_dari'   => $tglDari,
    'tgl_sampai' => $tglSampai,
], fn($v) => $v !== '');
$pilHref = fn(string $rl) => 'log_aktivitas.php' . ($pertahankan || $rl !== '' ? '?' . http_build_query($rl !== '' ? $pertahankan + ['role' => $rl] : $pertahankan) : '');

$labelRole = ['admin' => 'Admin', 'siswa' => 'Siswa', 'sistem' => 'Sistem'];
$ringkas = [];
if ($filterRole !== '')  $ringkas[] = 'Peran: ' . ($labelRole[$filterRole] ?? $filterRole);
if ($filterModul !== '') $ringkas[] = 'Modul: ' . $filterModul;
if ($keyword !== '')     $ringkas[] = 'Kata kunci: "' . $keyword . '"';
if ($tglDari !== '')     $ringkas[] = 'Dari ' . $tglDari;
if ($tglSampai !== '')   $ringkas[] = 'Sampai ' . $tglSampai;

require 'includes/header.php';
?>
<div class="d-head">
    <div>
        <h1>Log Aktivitas Sistem</h1>
        <p>Rekam jejak aktivitas login, pengelolaan data, dan transaksi perpustakaan.</p>
    </div>
    <div class="d-btn-row">
        <button type="button" onclick="cetakLaporan('Laporan_Log_Aktivitas_PinjamIn_<?= date('Y-m-d') ?>')" class="btn-d main" title="Cetak log atau pilih Simpan sebagai PDF"><?= ic('printer') ?>Cetak / Export PDF</button>
    </div>
</div>

<?php if (!$tabelSiap): ?>
    <div class="alert alert-error" style="margin-top: 20px;">
        Tabel <code>log_aktivitas</code> belum tersedia di database. Silakan impor ulang <code>pinjam_in.sql</code> terlebih dahulu.
    </div>
<?php endif; ?>

<!-- RINGKASAN -->
<section class="d-stats four" aria-label="Ringkasan log aktivitas">
    <?php foreach ([
        ['activity', 'slate', $totalSemua,   'Total Log Tercatat', 'seluruh riwayat aktivitas'],
        ['clock',    'green', $totalHariIni, 'Aktivitas Hari Ini', 'tercatat tanggal ini'],
        ['user',     'blue',  $totalAdmin,   'Aktivitas Admin',    'aksi petugas perpustakaan'],
        ['users',    'purple',$totalSiswa,   'Aktivitas Siswa',    'aksi anggota perpustakaan'],
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
<section class="tr-bar" aria-label="Filter log aktivitas">
    <nav class="pills" aria-label="Filter peran">
        <?php foreach ([['', 'Semua'], ['admin', 'Admin'], ['siswa', 'Siswa'], ['sistem', 'Sistem']] as [$kode, $lbl]): ?>
            <a href="<?= htmlspecialchars($pilHref($kode)) ?>" class="<?= $filterRole === $kode ? 'on' : '' ?>" <?= $filterRole === $kode ? 'aria-current="true"' : '' ?>><?= $lbl ?></a>
        <?php endforeach; ?>
    </nav>

    <form action="log_aktivitas.php" method="GET" class="tr-form has-modul" autocomplete="off">
        <?php if ($filterRole !== ''): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>"><?php endif; ?>
        <div class="tr-f has-ico">
            <label for="keyword">Kata kunci</label>
            <svg class="s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="keyword" name="keyword" placeholder="Nama pelaku, aksi, keterangan, IP..." value="<?= htmlspecialchars($keyword) ?>" autocomplete="off">
        </div>
        <div class="tr-f">
            <label for="modul">Modul</label>
            <select id="modul" name="modul">
                <option value="">Semua Modul</option>
                <?php
                $modulPilihan = array_unique(array_merge(['Autentikasi', 'Transaksi', 'Buku', 'Eksemplar', 'Kategori', 'Anggota'], $daftarModul));
                sort($modulPilihan);
                foreach ($modulPilihan as $m):
                ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $filterModul === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
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
            <?php if ($adaFilter): ?><a href="log_aktivitas.php" class="tr-reset">Reset</a><?php endif; ?>
        </div>
    </form>
</section>

<!-- TABEL LOG -->
<section class="act" aria-labelledby="hLog">
    <div class="act-top">
        <div class="panel-ico"><?= ic('activity') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hLog">Daftar Log Aktivitas</h2>
            <p><?= $adaFilter ? 'Hasil sesuai filter yang dipilih.' : 'Menampilkan maksimal 200 aktivitas terbaru.' ?></p>
        </div>
        <span class="tr-count">Ditampilkan <b><?= count($data) ?></b> log</span>
    </div>

    <?php renderPrintHead($infoCetak + [
        'subUnit'    => 'Laporan Audit & Pemantauan Aktivitas Pengguna Sistem',
        'docBadge'   => 'Dokumen Log Audit Resmi',
        'judul'      => 'Laporan Log Aktivitas Sistem',
        'filterText' => $ringkas ? implode('  |  ', $ringkas) : 'Semua aktivitas (tanpa filter)',
        'countText'  => 'Total: ' . count($data) . ' log ditampilkan',
    ]); ?>

    <div class="tbl wide">
        <?php if (empty($data)): ?>
            <div class="tr-empty">
                <span><?= ic('activity') ?></span>
                <b><?= $adaFilter ? 'Tidak ada log yang cocok' : 'Belum ada log aktivitas tercatat' ?></b>
                <?php if ($adaFilter): ?>
                    Coba ubah kata kunci, modul, atau rentang tanggal, atau <a href="log_aktivitas.php">reset filter</a>.
                <?php else: ?>
                    Aktivitas login, pengelolaan buku, anggota, dan transaksi akan otomatis tercatat di sini.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:38px;text-align:center">No</th>
                    <th>Waktu (WIB)</th>
                    <th>Pelaku</th>
                    <th>Peran</th>
                    <th>Modul</th>
                    <th>Aksi</th>
                    <th>Keterangan</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $row):
                $roleClass = $row['role'] === 'admin' ? 'run' : ($row['role'] === 'siswa' ? 'ok' : 'sys');
                $aksiLow   = strtolower($row['aksi']);
                if (strpos($aksiLow, 'hapus') !== false || strpos($aksiLow, 'gagal') !== false) {
                    $aksiClass = 'late';
                } elseif (strpos($aksiLow, 'edit') !== false || strpos($aksiLow, 'ubah') !== false || strpos($aksiLow, 'rfid') !== false) {
                    $aksiClass = 'warn';
                } elseif (strpos($aksiLow, 'logout') !== false) {
                    $aksiClass = 'sys';
                } elseif (strpos($aksiLow, 'tambah') !== false || strpos($aksiLow, 'daftar') !== false || strpos($aksiLow, 'login') !== false || strpos($aksiLow, 'generate') !== false || strpos($aksiLow, 'kembali') !== false) {
                    $aksiClass = 'ok';
                } else {
                    $aksiClass = 'run';
                }
            ?>
                <tr>
                    <td style="text-align:center"><?= $i + 1 ?></td>
                    <td class="nw"><?= htmlspecialchars(fmtWaktuLog($row['created_at'], $blnId)) ?></td>
                    <td><strong><?= htmlspecialchars($row['nama_pelaku']) ?></strong></td>
                    <td><span class="bd <?= $roleClass ?>"><?= htmlspecialchars(ucfirst($row['role'])) ?></span></td>
                    <td class="nw"><?= htmlspecialchars($row['modul']) ?></td>
                    <td><span class="bd <?= $aksiClass ?>"><?= htmlspecialchars($row['aksi']) ?></span></td>
                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                    <td class="nw"><code><?= htmlspecialchars($row['ip_address'] ?? '-') ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php renderPrintFoot(count($data), $infoCetak['tglCetak'], $infoCetak['jamCetak'], $infoCetak['namaPetugas']); ?>
</section>
<?php require 'includes/footer.php'; ?>
