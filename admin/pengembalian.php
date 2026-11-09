<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';

// Cek apakah ada anggota yang di-scan
$anggota = null;
$listDipinjam = [];
$jumlahAktif = 0;

// Reset scan anggota jika diminta
if (isset($_GET['reset'])) {
    unset($_SESSION['admin_kembali_anggota_id']);
    header('Location: pengembalian.php');
    exit;
}

if (isset($_SESSION['admin_kembali_anggota_id'])) {
    $anggotaId = $_SESSION['admin_kembali_anggota_id'];

    $stmtA = $koneksi->prepare("SELECT * FROM anggota WHERE id = :id LIMIT 1");
    $stmtA->bindParam(':id', $anggotaId);
    $stmtA->execute();
    $anggota = $stmtA->fetch();

    if (!$anggota) {
        unset($_SESSION['admin_kembali_anggota_id']);
        header('Location: pengembalian.php?error=anggota_hilang');
        exit;
    }

    // Ambil daftar buku yang sedang dipinjam anggota ini
    $stmtD = $koneksi->prepare("
        SELECT transaksi.*, buku.judul, buku_item.kode_item
        FROM transaksi
        JOIN buku_item ON transaksi.buku_item_id = buku_item.id
        JOIN buku ON buku_item.buku_id = buku.id
        WHERE transaksi.anggota_id = :id AND transaksi.status = 'dipinjam'
        ORDER BY transaksi.tgl_kembali_rencana ASC
    ");
    $stmtD->bindParam(':id', $anggotaId);
    $stmtD->execute();
    $listDipinjam = $stmtD->fetchAll();
    $jumlahAktif  = count($listDipinjam);
}

// Statistik ringkas sirkulasi
$statAktif = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'dipinjam'")->fetch()['total'] ?? 0;
$statTerlambat = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'dipinjam' AND tgl_kembali_rencana < CURDATE()")->fetch()['total'] ?? 0;

$today       = date('Y-m-d');
$pageTitle   = 'Pengembalian Buku';
$currentPage = 'pengembalian';

$blnId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function tglId(string $t, array $b): string {
    $ts = strtotime($t);
    return date('j', $ts) . ' ' . $b[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

$extraHead = '<link rel="stylesheet" href="../assets/css/scan.css?v=2">';

require 'includes/header.php';

$ikonCek   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
$ikonKartu = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/></svg>';
?>
<div class="d-head">
    <div>
        <h1>Pengembalian Buku</h1>
        <p>Verifikasi dan catat pengembalian buku pinjaman siswa ke perpustakaan.</p>
    </div>
    <div class="pm-stats" aria-label="Status sirkulasi">
        <div class="pm-stat"><span><?= ic('book') ?></span><div><small>Sedang Dipinjam</small><b><?= (int) $statAktif ?></b></div></div>
        <div class="pm-stat late"><span><?= ic('clock') ?></span><div><small>Terlambat</small><b><?= (int) $statTerlambat ?></b></div></div>
    </div>
</div>

<!-- LANGKAH -->
<ol class="pm-steps" aria-label="Langkah pengembalian">
    <li class="pm-step <?= $anggota ? 'done' : 'active' ?>" <?= !$anggota ? 'aria-current="step"' : '' ?>>
        <span class="n"><?= $anggota ? $ikonCek : '1' ?></span>
        <div><small>Langkah 1</small><b>Scan Kartu Siswa</b></div>
    </li>
    <li class="pm-line <?= $anggota ? 'on' : '' ?>" aria-hidden="true"></li>
    <li class="pm-step <?= $anggota ? 'active' : '' ?>" <?= $anggota ? 'aria-current="step"' : '' ?>>
        <span class="n">2</span>
        <div><small>Langkah 2</small><b>Scan / Pilih Buku Dikembalikan</b></div>
    </li>
</ol>

<!-- NOTIFIKASI SUKSES / ERROR -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'kembali'): ?>
    <div class="pm-alert ok" role="status">
        <?= ic('check') ?>
        <div>
            <strong>Pengembalian berhasil.</strong> Buku "<strong><?= htmlspecialchars($_GET['judul'] ?? '') ?></strong>"
            (kode <code><?= htmlspecialchars($_GET['kode_item'] ?? '') ?></code>) atas nama
            <strong><?= htmlspecialchars($_GET['nama'] ?? '') ?></strong> sudah dicatat kembali ke stok perpustakaan.
            <?php if (isset($_GET['terlambat'])): ?>
                <span class="late">Catatan: pengembalian ini terlambat <?= htmlspecialchars($_GET['hari'] ?? '') ?> hari dari batas waktu.</span>
            <?php else: ?>
                <span class="ontime">Buku dikembalikan tepat waktu.</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])):
    $peminjamErr = htmlspecialchars($_GET['peminjam'] ?? 'anggota lain');
    $judulErr    = htmlspecialchars($_GET['judul'] ?? 'Buku ini');
    $errMap = [
        'tidak_ditemukan'     => 'Data anggota atau buku tidak ditemukan di sistem. Pastikan kartu RFID / NIS sudah terdaftar.',
        'bukan_dipinjam'      => 'Buku "' . $judulErr . '" tidak sedang berstatus dipinjam (stok sudah tersedia di perpustakaan).',
        'bukan_milik_anggota' => 'Buku "' . $judulErr . '" tidak sedang dipinjam oleh anggota ini, melainkan oleh ' . $peminjamErr . '.',
        'anggota_hilang'      => 'Data anggota tidak ditemukan. Silakan scan ulang kartu anggota.',
        'empty'               => 'Input scan tidak boleh kosong.',
        'system'              => 'Terjadi kesalahan sistem saat memproses pengembalian buku.',
    ];
?>
    <div class="pm-alert err" role="alert">
        <?= ic('clock') ?>
        <div><?= $errMap[$_GET['error']] ?? 'Terjadi kesalahan pada proses pengembalian.' ?></div>
    </div>
<?php endif; ?>

<?php if (!$anggota): ?>
    <!-- LANGKAH 1: IDENTIFIKASI SISWA -->
    <div class="pm-wrap">
        <section class="pm-scan" aria-labelledby="hKartu">
            <div class="pm-reader" aria-hidden="true"><i></i><?= $ikonKartu ?></div>
            <div class="pm-body">
                <h2 id="hKartu">Identifikasi Siswa yang Mengembalikan Buku</h2>
                <p>Tap kartu RFID siswa pada card reader. Tidak punya kartu? Ketik NIS siswa lalu tekan Enter.</p>
                <form action="../config/proses_scan_anggota_admin.php" method="POST" id="form-kartu" autocomplete="off">
                    <input type="hidden" name="redirect" value="pengembalian.php">
                    <div class="pm-field">
                        <div class="pm-in" id="wrap-kartu">
                            <i></i>
                            <input type="text" name="kode_kartu" id="input-kartu" autofocus autocomplete="off" aria-label="Kode kartu RFID atau NIS" placeholder="Scan kartu RFID / ketik NIS siswa...">
                        </div>
                        <button type="submit" id="btn-kartu" class="pm-go">Cari Siswa <?= ic('chev', 18) ?></button>
                    </div>
                </form>
                <p class="pm-status" id="status-kartu" role="status">Siap. Kursor sudah ada di kolom ini, langsung scan kartu.</p>
            </div>
        </section>

        <aside class="pm-rules" aria-label="Alur pengembalian">
            <h3>Alur pengembalian</h3>
            <ul>
                <li><span>1</span><div><b>Identifikasi siswa</b><small>scan kartu RFID atau ketik NIS.</small></div></li>
                <li><span>2</span><div><b>Scan QR buku</b><small>atau tekan tombol "Kembalikan" pada daftar pinjaman siswa.</small></div></li>
                <li><span>3</span><div><b>Stok otomatis kembali</b><small>keterlambatan dicatat dan ditampilkan setelah pengembalian.</small></div></li>
            </ul>
        </aside>
    </div>

<?php else: ?>
    <!-- LANGKAH 2: SISWA DITEMUKAN -->
    <?php
        $kata    = preg_split('/\s+/', trim($anggota['nama_lengkap']));
        $inisial = mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
        $nTerlambat = 0;
        foreach ($listDipinjam as $r) { if ($today > $r['tgl_kembali_rencana']) $nTerlambat++; }
    ?>
    <section class="pm-member" aria-label="Siswa terverifikasi">
        <div class="pm-ava" aria-hidden="true"><?= htmlspecialchars($inisial) ?></div>
        <div class="pm-who">
            <h2><?= htmlspecialchars($anggota['nama_lengkap']) ?></h2>
            <p>
                <span>NIS <b><?= htmlspecialchars($anggota['nis']) ?></b></span>
                <span>Kelas <b><?= htmlspecialchars($anggota['kelas']) ?></b></span>
            </p>
        </div>
        <div class="pm-quota">
            <small><b><?= $jumlahAktif ?></b> buku belum dikembalikan</small>
            <?php if ($nTerlambat > 0): ?><small class="pm-hl"><?= $nTerlambat ?> sudah terlambat</small><?php endif; ?>
        </div>
        <a href="pengembalian.php?reset=1" class="pm-swap"><?= ic('return', 16) ?>Ganti Siswa</a>
    </section>

    <?php if ($jumlahAktif === 0): ?>
        <section class="pm-done" role="status">
            <span><?= $ikonCek ?></span>
            <div>
                <h2>Semua beres, tidak ada pinjaman aktif</h2>
                <p>Siswa <strong><?= htmlspecialchars($anggota['nama_lengkap']) ?></strong> saat ini tidak memiliki pinjaman buku yang tertunggak. Semua buku sudah dikembalikan.</p>
                <a href="pengembalian.php?reset=1" class="pm-ret">Scan Siswa Lain <?= ic('chev', 15) ?></a>
            </div>
        </section>
    <?php else: ?>
        <section class="pm-scan pm-single" aria-labelledby="hKembali">
            <div class="pm-reader pm-qr" aria-hidden="true"><em></em><b></b></div>
            <div class="pm-body">
                <h2 id="hKembali">Scan QR Buku yang Diserahkan</h2>
                <p>Arahkan scanner ke label QR pada buku yang diserahkan siswa untuk pengembalian instan.</p>
                <form action="../config/proses_kembalikan_admin.php" method="POST" id="form-kembali" autocomplete="off">
                    <div class="pm-field">
                        <div class="pm-in" id="wrap-kembali">
                            <i></i>
                            <input type="text" name="kode_item" id="input-kembali" autofocus autocomplete="off" aria-label="Kode item buku yang dikembalikan" placeholder="Scan kode QR buku yang dikembalikan...">
                        </div>
                        <button type="submit" id="btn-kembali" class="pm-go">Proses Pengembalian <?= ic('chev', 18) ?></button>
                    </div>
                </form>
                <p class="pm-status" id="status-kembali" role="status">Siap. Scan stiker QR buku yang dikembalikan.</p>
            </div>
        </section>

        <!-- DAFTAR PINJAMAN SISWA -->
        <section class="act pm-list" aria-labelledby="hDaftar">
            <div class="act-top">
                <div class="panel-ico"><?= ic('book') ?></div>
                <div class="panel-h" style="display:block">
                    <h2 id="hDaftar">Pinjaman aktif <?= htmlspecialchars($kata[0]) ?></h2>
                    <p>Scan QR di atas, atau tekan "Kembalikan" pada buku yang bersangkutan.</p>
                </div>
            </div>
            <div class="tbl">
                <table>
                    <thead><tr><th>Judul Buku</th><th>Tanggal Pinjam</th><th>Batas Waktu</th><th>Status</th><th style="text-align:right">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($listDipinjam as $row):
                        $sisa = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($today)) / 86400);
                        if ($sisa < 0)       { $kls = 'late'; $lbl = 'Terlambat ' . abs($sisa) . ' hari'; }
                        elseif ($sisa === 0) { $kls = 'soon'; $lbl = 'Jatuh tempo hari ini'; }
                        elseif ($sisa === 1) { $kls = 'soon'; $lbl = 'Sisa 1 hari'; }
                        else                 { $kls = 'ok';   $lbl = 'Sisa ' . $sisa . ' hari'; }
                        $pesan = "Konfirmasi pengembalian buku '" . $row['judul'] . "'?";
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['judul']) ?></strong><small><?= htmlspecialchars($row['kode_item']) ?></small></td>
                            <td><?= tglId($row['tgl_pinjam'], $blnId) ?></td>
                            <td><?= tglId($row['tgl_kembali_rencana'], $blnId) ?></td>
                            <td><span class="pm-b <?= $kls ?>"><?= $lbl ?></span></td>
                            <td style="text-align:right">
                                <a href="../config/proses_kembalikan_admin.php?transaksi_id=<?= (int) $row['id'] ?>" class="pm-ret"
                                   onclick="return confirm(<?= htmlspecialchars(json_encode($pesan, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                                    <?= ic('return', 15) ?>Kembalikan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<script src="../assets/js/scan.js"></script>
<script>
pasangScan('kartu', 'Siap. Kursor sudah ada di kolom ini, langsung scan kartu.');
pasangScan('kembali', 'Siap. Scan stiker QR buku yang dikembalikan.');
</script>
<?php require 'includes/footer.php'; ?>