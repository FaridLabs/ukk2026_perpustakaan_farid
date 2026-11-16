<?php
/**
 * ============================================================================
 * RIWAYAT PEMINJAMAN SISWA - PINJAM.IN
 * ============================================================================
 * Menampilkan catatan histori seluruh peminjaman dan pengembalian buku siswa.
 * Dilengkapi fitur filter tab status (Semua, Dipinjam, Selesai, Terlambat),
 * pencarian judul/kode eksemplar, kalkulasi durasi baca, serta ringkasan kepatuhan.
 */

// 1. Cek keamanan sesi login siswa
require '../config/cek_session_siswa.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';
require_once '../config/pengaturan_helper.php';

$anggotaId = (int) $_SESSION['anggota_id'];

// 2. Ambil parameter filter dan kata kunci dari URL
$tabFilter = trim($_GET['tab'] ?? 'semua');
$keyword   = trim($_GET['keyword'] ?? '');
$hariIni   = date('Y-m-d');

// 3. Query dasar riwayat transaksi peminjaman milik siswa ini
$sql = "
    SELECT transaksi.*, 
           buku.judul, 
           buku.cover,
           buku.pengarang,
           buku_item.kode_item,
           kategori.nama_kategori
    FROM transaksi
    LEFT JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    LEFT JOIN buku ON buku_item.buku_id = buku.id
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
    WHERE transaksi.anggota_id = :id
";

$params = [':id' => $anggotaId];

// Filter pencarian berdasarkan judul buku atau kode item
if (!empty($keyword)) {
    $sql .= " AND (buku.judul LIKE :kw OR buku_item.kode_item LIKE :kw)";
    $params[':kw'] = "%{$keyword}%";
}

// Filter berdasarkan tab status
if ($tabFilter === 'dipinjam') {
    $sql .= " AND transaksi.status = 'dipinjam'";
} elseif ($tabFilter === 'kembali') {
    $sql .= " AND transaksi.status = 'dikembalikan'";
} elseif ($tabFilter === 'terlambat') {
    $sql .= " AND (transaksi.status = 'terlambat' OR (transaksi.status = 'dipinjam' AND transaksi.tgl_kembali_rencana < :today))";
    $params[':today'] = $hariIni;
}

$sql .= " ORDER BY transaksi.tgl_pinjam DESC, transaksi.id DESC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$dataRiwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Hitung metrik ringkasan kepatuhan siswa
$stmtTotal = $koneksi->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = :id");
$stmtTotal->execute([':id' => $anggotaId]);
$totalSemua = (int) $stmtTotal->fetchColumn();

$stmtAktif = $koneksi->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = :id AND status = 'dipinjam'");
$stmtAktif->execute([':id' => $anggotaId]);
$totalDipinjam = (int) $stmtAktif->fetchColumn();

$stmtKembali = $koneksi->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = :id AND status = 'dikembalikan'");
$stmtKembali->execute([':id' => $anggotaId]);
$totalKembali = (int) $stmtKembali->fetchColumn();

$stmtTelat = $koneksi->prepare("
    SELECT COUNT(*) FROM transaksi 
    WHERE anggota_id = :id 
      AND (status = 'terlambat' OR (status = 'dipinjam' AND tgl_kembali_rencana < CURDATE()))
");
$stmtTelat->execute([':id' => $anggotaId]);
$totalTelat = (int) $stmtTelat->fetchColumn();

// Hitung persentase ketepatan waktu
$persenTepat = ($totalKembali + $totalTelat) > 0 
    ? (int) round(($totalKembali / ($totalKembali + $totalTelat)) * 100) 
    : 100;

$pageTitle   = 'Riwayat Peminjaman Buku';
$currentPage = 'riwayat';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="page-header-wrap" style="margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Riwayat Peminjaman Buku
        </h1>
        <p class="page-subtitle" style="font-size: 14.5px; color: var(--sw-text-muted); margin: 0;">
            Arsip lengkap seluruh judul buku perpustakaan yang pernah dan sedang kamu pinjam di sekolah.
        </p>
    </div>
</div>

<!-- =========================================================================
     1. KARTU STATISTIK KEPATUHAN & SIRKULASI SISWA
     ========================================================================= -->
<div class="sw-stats-row">
    <!-- Stat 1: Total Peminjaman Seumur Hidup -->
    <div class="sw-stat-tile green">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                <polyline points="9 11 12 14 22 4"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val"><?= $totalSemua ?> <small>buku</small></div>
            <div class="sw-stat-lbl">Total Seluruh Transaksi</div>
        </div>
    </div>

    <!-- Stat 2: Selesai Dikembalikan -->
    <div class="sw-stat-tile blue">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val"><?= $totalKembali ?> <small>kali</small></div>
            <div class="sw-stat-lbl">Selesai Dikembalikan</div>
        </div>
    </div>

    <!-- Stat 3: Skor Ketepatan Waktu -->
    <div class="sw-stat-tile orange">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val"><?= $persenTepat ?>%</div>
            <div class="sw-stat-lbl">Tingkat Pengembalian Tepat Waktu</div>
        </div>
    </div>
</div>

<!-- =========================================================================
     2. TOOLBAR FILTER & PENCARIAN RIWAYAT
     ========================================================================= -->
<div class="sw-toolbar">
    <!-- Filter Tab Status Sirkulasi -->
    <div class="sw-filter-tabs">
        <?php
        $tabs = [
            'semua'     => ['Semua Transaksi', $totalSemua],
            'dipinjam'  => ['Sedang Dipinjam', $totalDipinjam],
            'kembali'   => ['Sudah Kembali', $totalKembali],
            'terlambat' => ['Terlambat', $totalTelat],
        ];

        foreach ($tabs as $key => [$label, $count]):
            $isActive = ($tabFilter === $key);
            $queryUrl = ['tab' => $key];
            if (!empty($keyword)) $queryUrl['keyword'] = $keyword;
            $urlTab = 'riwayat.php?' . http_build_query($queryUrl);
        ?>
            <a href="<?= htmlspecialchars($urlTab, ENT_QUOTES, 'UTF-8') ?>" 
               class="sw-tab-btn <?= $isActive ? 'is-active' : '' ?>">
                <span><?= $label ?></span>
                <span class="sw-tab-count"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Input Pencarian Riwayat -->
    <form action="riwayat.php" method="GET" class="sw-search-form" autocomplete="off">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tabFilter, ENT_QUOTES, 'UTF-8') ?>">
        
        <div class="sw-search-field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" 
                   name="keyword" 
                   class="sw-search-input" 
                   placeholder="Cari berdasarkan judul buku atau kode eksemplar..." 
                   value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 20px; font-size: 14px; font-weight: 700;">
            Cari
        </button>

        <?php if (!empty($keyword) || $tabFilter !== 'semua'): ?>
            <a href="riwayat.php" class="sw-btn-detail" style="height: 44px; padding: 0 16px;">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- =========================================================================
     3. TABEL DATA RIWAYAT TRANSAKSI SISWA
     ========================================================================= -->
<?php if (empty($dataRiwayat)): ?>
    <!-- Tampilan Keadaan Kosong -->
    <div style="background: #FFFFFF; border: 1.5px dashed var(--sw-border); border-radius: var(--sw-radius-md); padding: 56px 20px; text-align: center;">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--sw-primary-soft); color: var(--sw-primary); display: grid; place-items: center; margin: 0 auto 14px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <h3 style="font-size: 17px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Tidak Ada Catatan Riwayat
        </h3>
        <p style="font-size: 13.5px; color: var(--sw-text-muted); margin: 0 0 16px;">
            Belum ada riwayat transaksi yang cocok dengan filter yang dipilih.
        </p>
        <a href="daftar_buku.php" class="btn btn-primary" style="font-size: 13.5px;">
            Jelajahi Katalog Buku &rarr;
        </a>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="table-data">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">No</th>
                    <th>Judul Buku &amp; Pengarang</th>
                    <th>Kode Eksemplar</th>
                    <th>Tanggal Pinjam</th>
                    <th>Batas Waktu</th>
                    <th>Tanggal Kembali</th>
                    <th>Status Sirkulasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dataRiwayat as $i => $row): 
                    $terlambat = ($row['status'] === 'terlambat') || ($row['status'] === 'dipinjam' && $row['tgl_kembali_rencana'] < $hariIni);
                    $cvUrl = coverUrl($row['cover'] ?? null, '../uploads/covers/');

                    // Hitung durasi peminjaman (hari)
                    $tglMulai = strtotime($row['tgl_pinjam']);
                    $tglSelesai = $row['tgl_kembali_aktual'] ? strtotime($row['tgl_kembali_aktual']) : time();
                    $durasiHari = max(1, (int) round(($tglSelesai - $tglMulai) / 86400));
                ?>
                    <tr>
                        <td style="text-align: center; color: var(--sw-text-muted); font-size: 13px;">
                            <?= $i + 1 ?>
                        </td>

                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 48px; border-radius: 4px; overflow: hidden; background: var(--sw-primary-soft); flex-shrink: 0; display: grid; place-items: center; color: var(--sw-primary);">
                                    <?php if ($cvUrl): ?>
                                        <img src="<?= htmlspecialchars($cvUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0;">
                                    <strong style="color: var(--sw-text); font-size: 14.5px; display: block; margin-bottom: 2px;">
                                        <?= htmlspecialchars($row['judul'] ?: '[Buku Dihapus]', ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                    <span style="font-size: 12px; color: var(--sw-text-muted);">
                                        <?= htmlspecialchars($row['pengarang'] ?: '-', ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($row['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <code><?= htmlspecialchars($row['kode_item'] ?? '-', ENT_QUOTES, 'UTF-8') ?></code>
                        </td>

                        <td style="font-size: 13px; color: var(--sw-text-muted);">
                            <?= date('d M Y', strtotime($row['tgl_pinjam'])) ?>
                        </td>

                        <td style="font-size: 13px; color: var(--sw-text-muted);">
                            <?= date('d M Y', strtotime($row['tgl_kembali_rencana'])) ?>
                        </td>

                        <td style="font-size: 13px;">
                            <?php if ($row['tgl_kembali_aktual']): ?>
                                <span style="color: var(--sw-success); font-weight: 600;">
                                    <?= date('d M Y', strtotime($row['tgl_kembali_aktual'])) ?>
                                </span>
                                <small style="display: block; color: var(--sw-text-muted); font-size: 11px;">(<?= $durasiHari ?> hari)</small>
                            <?php else: ?>
                                <span style="color: var(--sw-text-subtle);">&mdash;</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($row['status'] === 'dikembalikan'): ?>
                                <span class="badge-info" style="background:#DDF3E8; color:#1B7A55;">
                                    Tepat Waktu
                                </span>
                            <?php elseif ($terlambat): 
                                $dendaRow = (int) ($row['denda'] ?? 0);
                            ?>
                                <span class="badge-danger">
                                    <?= $row['status'] === 'dipinjam' ? 'Terlambat (Aktif)' : 'Kembali Terlambat' ?>
                                </span>
                                <?php if ($dendaRow > 0): ?>
                                    <small style="display: block; color: var(--sw-danger); font-size: 11px; font-weight: 700; margin-top: 3px;">
                                        Denda: <?= formatRupiah($dendaRow) ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge-info" style="background:#E3ECFD; color:#2F63D8;">
                                    Sedang Dipinjam
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
