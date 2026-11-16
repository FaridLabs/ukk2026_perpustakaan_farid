<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/pengaturan_helper.php';

// Reset → ganti anggota
if (isset($_GET['reset'])) {
    unset($_SESSION['admin_scan_anggota_id']);
    header('Location: peminjaman.php');
    exit;
}

// Ambil aturan dinamis batas kuota, durasi pinjam, dan denda
$pengaturan  = ambilPengaturan($koneksi);
$batasPinjam = (int) ($pengaturan['maks_pinjam_buku'] ?? 3);
$lamaPinjam  = (int) ($pengaturan['lama_pinjam_hari'] ?? 7);

$anggota      = null;
$listDipinjam = [];
$jumlahAktif  = 0;

// Jika anggota sudah di-scan (ada di session)
if (isset($_SESSION['admin_scan_anggota_id'])) {
    $anggotaId = $_SESSION['admin_scan_anggota_id'];

    $stmtA = $koneksi->prepare("SELECT * FROM anggota WHERE id = :id LIMIT 1");
    $stmtA->bindParam(':id', $anggotaId);
    $stmtA->execute();
    $anggota = $stmtA->fetch();

    if (!$anggota) {
        unset($_SESSION['admin_scan_anggota_id']);
        header('Location: peminjaman.php?error=anggota_hilang');
        exit;
    }

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

$today       = date('Y-m-d');
$pageTitle   = 'Peminjaman Buku';
$currentPage = 'peminjaman';

$blnId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function tglId(string $t, array $b): string {
    $ts = strtotime($t);
    return date('j', $ts) . ' ' . $b[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

$extraHead = '<link rel="stylesheet" href="../assets/css/scan.css?v=2">';

require 'includes/header.php';

$cekIkon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
?>
<div class="d-head">
    <div>
        <h1>Peminjaman Buku</h1>
        <p>Alur peminjaman cepat berbasis RFID Kartu Anggota dan Barcode/QR Buku.</p>
    </div>
</div>

<!-- LANGKAH -->
<ol class="pm-steps" aria-label="Langkah peminjaman">
    <li class="pm-step <?= $anggota ? 'done' : 'active' ?>" <?= !$anggota ? 'aria-current="step"' : '' ?>>
        <span class="n"><?= $anggota ? $cekIkon : '1' ?></span>
        <div><small>Langkah 1</small><b>Scan Kartu Anggota / NIS</b></div>
    </li>
    <li class="pm-line <?= $anggota ? 'on' : '' ?>" aria-hidden="true"></li>
    <li class="pm-step <?= $anggota ? 'active' : '' ?>" <?= $anggota ? 'aria-current="step"' : '' ?>>
        <span class="n">2</span>
        <div><small>Langkah 2</small><b>Scan QR Buku Fisik</b></div>
    </li>
</ol>

<!-- NOTIFIKASI -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'pinjam'): ?>
    <div class="pm-alert ok" role="status">
        <?= ic('check') ?>
        <div>Peminjaman berhasil dicatat. Buku "<strong><?= htmlspecialchars($_GET['judul'] ?? '') ?></strong>" dipinjamkan, batas pengembalian <?= $lamaPinjam ?> hari ke depan.</div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])):
    $judulError = htmlspecialchars($_GET['judul'] ?? 'Buku ini');
    $errMap = [
        'tidak_ditemukan'      => 'Kartu anggota tidak dikenali. Pastikan RFID atau NIS sudah terdaftar di data anggota.',
        'buku_tidak_ditemukan' => 'Kode QR/barcode buku tidak ditemukan di sistem.',
        'batas'                => 'Anggota telah mencapai batas maksimal peminjaman (' . $batasPinjam . ' buku). Kembalikan buku terlebih dahulu.',
        'sudah_pinjam_judul'   => '<strong>Cegah Dobel Scan:</strong> Siswa ini sudah meminjam 1 eksemplar buku "' . $judulError . '". Sistem otomatis menolak peminjaman ganda buku yang sama.',
        'stok_habis'           => 'Semua eksemplar fisik buku "' . $judulError . '" saat ini sedang dipinjam oleh siswa lain.',
        'sedang_dipinjam'      => 'Buku "' . $judulError . '" berstatus sedang dipinjam dan belum dikembalikan.',
        'status_tidak_valid'   => 'Status fisik buku tidak tersedia untuk dipinjam (rusak atau hilang).',
        'system'               => 'Terjadi kendala pada sistem. Silakan ulangi proses.',
        'anggota_hilang'       => 'Data anggota tidak ditemukan. Silakan scan ulang.',
        'empty'                => 'Input scan tidak boleh kosong.',
    ];
?>
    <div class="pm-alert err" role="alert">
        <?= ic('clock') ?>
        <div><?= $errMap[$_GET['error']] ?? 'Terjadi kesalahan pada transaksi peminjaman.' ?></div>
    </div>
<?php endif; ?>

<?php if (!$anggota): ?>
    <!-- LANGKAH 1: SCAN KARTU ANGGOTA -->
    <div class="pm-wrap">
        <section class="pm-scan" aria-labelledby="hKartu">
            <div class="pm-reader" aria-hidden="true"><i></i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/></svg></div>
            <div class="pm-body">
                <h2 id="hKartu">Scan Kartu RFID / Ketik NIS Anggota</h2>
                <p>Tempelkan kartu pelajar di pembaca RFID. Tidak punya kartu? Ketik NIS siswa lalu tekan Enter.</p>
                <form action="../config/proses_scan_anggota_admin.php" method="POST" id="form-kartu" autocomplete="off">
                    <div class="pm-field">
                        <div class="pm-in" id="wrap-kartu">
                            <i></i>
                            <input type="text" name="kode_kartu" id="input-kartu" autofocus autocomplete="off" aria-label="Kode kartu RFID atau NIS" placeholder="Scan kartu RFID / ketik NIS siswa...">
                        </div>
                        <button type="submit" id="btn-kartu" class="pm-go">Cari Anggota <?= ic('chev', 18) ?></button>
                    </div>
                </form>
                <p class="pm-status" id="status-kartu" role="status">Siap. Kursor sudah ada di kolom ini, langsung scan kartu.</p>
            </div>
        </section>

        <aside class="pm-rules" aria-label="Aturan peminjaman">
            <h3>Aturan peminjaman</h3>
            <ul>
                <li><span><?= $batasPinjam ?></span><div><b>Maksimal <?= $batasPinjam ?> buku aktif</b><small>per anggota, sampai ada yang dikembalikan.</small></div></li>
                <li><span><?= $lamaPinjam ?></span><div><b><?= $lamaPinjam ?> hari masa pinjam</b><small>dihitung dari tanggal peminjaman.</small></div></li>
                <li><span>Rp</span><div><b>Denda keterlambatan</b><small><?= formatRupiah($pengaturan['denda_per_hari']) ?> per hari per buku.</small></div></li>
                <li><span>1</span><div><b>Satu eksemplar per judul</b><small>judul yang sama tidak bisa dipinjam dua kali oleh siswa yang sama.</small></div></li>
            </ul>
        </aside>
    </div>

<?php else: ?>
    <!-- LANGKAH 2: ANGGOTA TERVERIFIKASI -->
    <?php
        $kata    = preg_split('/\s+/', trim($anggota['nama_lengkap']));
        $inisial = mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
        $penuh   = $jumlahAktif >= $batasPinjam;
    ?>
    <section class="pm-member" aria-label="Anggota terverifikasi">
        <div class="pm-ava" aria-hidden="true"><?= htmlspecialchars($inisial) ?></div>
        <div class="pm-who">
            <h2><?= htmlspecialchars($anggota['nama_lengkap']) ?></h2>
            <p>
                <span>NIS <b><?= htmlspecialchars($anggota['nis']) ?></b></span>
                <span>Kelas <b><?= htmlspecialchars($anggota['kelas']) ?></b></span>
                <?php if ($anggota['kode_rfid']): ?><span>RFID <code><?= htmlspecialchars($anggota['kode_rfid']) ?></code></span><?php endif; ?>
            </p>
        </div>
        <div class="pm-quota">
            <small><b><?= $jumlahAktif ?> / <?= $batasPinjam ?></b> slot pinjam terpakai</small>
            <div class="pm-slots <?= $penuh ? 'full' : '' ?>" aria-hidden="true">
                <?php for ($s = 0; $s < $batasPinjam; $s++): ?><i class="<?= $s < $jumlahAktif ? 'on' : '' ?>"></i><?php endfor; ?>
            </div>
        </div>
        <a href="peminjaman.php?reset=1" class="pm-swap"><?= ic('return', 16) ?>Ganti Anggota</a>
    </section>

    <?php if ($penuh): ?>
        <div class="pm-warn" role="alert">
            <?= ic('clock') ?>
            <div>
                Anggota ini sudah mencapai <strong>batas maksimal peminjaman (<?= $batasPinjam ?> buku)</strong>. Siswa tidak dapat meminjam buku tambahan sebelum mengembalikan salah satu buku pinjaman.
                <br><a href="pengembalian.php">Menuju halaman pengembalian &rarr;</a>
            </div>
        </div>
    <?php else: ?>
        <section class="pm-scan pm-single" aria-labelledby="hBuku">
            <div class="pm-reader pm-qr" aria-hidden="true"><em></em><b></b></div>
            <div class="pm-body">
                <h2 id="hBuku">Scan Kode QR / Barcode Eksemplar Buku</h2>
                <p>Kartu terverifikasi, sisa kuota <strong><?= $batasPinjam - $jumlahAktif ?> buku</strong>. Arahkan scanner ke stiker QR yang tertempel pada fisik buku.</p>
                <form action="../config/proses_transaksi_admin.php" method="POST" id="form-buku" autocomplete="off">
                    <div class="pm-field">
                        <div class="pm-in" id="wrap-buku">
                            <i></i>
                            <input type="text" name="kode_item" id="input-buku" autofocus autocomplete="off" aria-label="Kode item buku" placeholder="Scan QR kode item buku (Contoh: BK-...)">
                        </div>
                        <button type="submit" id="btn-buku" class="pm-go">Proses Pinjam <?= ic('chev', 18) ?></button>
                    </div>
                </form>
                <p class="pm-status" id="status-buku" role="status">Siap. Scan stiker QR buku yang ingin dipinjam.</p>
            </div>
        </section>
    <?php endif; ?>

    <!-- PINJAMAN AKTIF -->
    <section class="act pm-list" aria-labelledby="hAktif">
        <div class="act-top">
            <div class="panel-ico"><?= ic('book') ?></div>
            <div class="panel-h" style="display:block">
                <h2 id="hAktif">Pinjaman aktif <?= htmlspecialchars($kata[0]) ?></h2>
                <p><?= $jumlahAktif ?> buku belum dikembalikan.</p>
            </div>
        </div>
        <div class="tbl">
            <?php if (empty($listDipinjam)): ?>
                <p class="pm-empty">Belum ada pinjaman aktif. Buku yang dipinjam hari ini akan muncul di sini.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Judul Buku</th><th>Tanggal Pinjam</th><th>Batas Pengembalian</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($listDipinjam as $row):
                    $sisa = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($today)) / 86400);
                    if ($sisa < 0)      { $kls = 'late'; $lbl = 'Terlambat ' . abs($sisa) . ' hari'; }
                    elseif ($sisa === 0) { $kls = 'soon'; $lbl = 'Jatuh tempo hari ini'; }
                    elseif ($sisa === 1) { $kls = 'soon'; $lbl = 'Sisa 1 hari'; }
                    else                 { $kls = 'ok';   $lbl = 'Sisa ' . $sisa . ' hari'; }
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['judul']) ?></strong><small><?= htmlspecialchars($row['kode_item']) ?></small></td>
                        <td><?= tglId($row['tgl_pinjam'], $blnId) ?></td>
                        <td><?= tglId($row['tgl_kembali_rencana'], $blnId) ?></td>
                        <td><span class="pm-b <?= $kls ?>"><?= $lbl ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<script src="../assets/js/scan.js"></script>
<script>
pasangScan('kartu', 'Siap. Kursor sudah ada di kolom ini, langsung scan kartu.');
pasangScan('buku', 'Siap. Scan stiker QR buku yang ingin dipinjam.');
</script>
<?php require 'includes/footer.php'; ?>