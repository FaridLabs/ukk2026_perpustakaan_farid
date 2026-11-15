<?php
/**
 * ============================================================================
 * DASHBOARD PORTAL SISWA - PINJAM.IN
 * ============================================================================
 * Halaman utama siswa yang memuat kartu identitas pelajar digital interaktif,
 * pelacak kuota peminjaman (maks. 3 slot), daftar buku yang sedang dipinjam,
 * ringkasan statistik, serta rekomendasi buku perpustakaan.
 */

// 1. Cek keamanan sesi login siswa
require '../config/cek_session_siswa.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';

$anggotaId = (int) $_SESSION['anggota_id'];

// 2. Ambil data profil anggota terbaru langsung dari database
$stmtProfil = $koneksi->prepare("SELECT * FROM anggota WHERE id = :id LIMIT 1");
$stmtProfil->execute([':id' => $anggotaId]);
$profil = $stmtProfil->fetch(PDO::FETCH_ASSOC);

if (!$profil) {
    // Jika data tidak ditemukan di database, redirect ke logout untuk keamanan
    header('Location: ../logout.php');
    exit;
}

$nisSiswa   = $profil['nis'] ?? '-';
$namaSiswa  = $profil['nama_lengkap'] ?? $_SESSION['anggota_nama'];
$kelasSiswa = $profil['kelas'] ?? $_SESSION['anggota_kelas'];
$kodeRfid   = $profil['kode_rfid'] ?? null;

// 3. Query daftar buku yang saat ini SEDANG DIPINJAM oleh siswa
$stmtAktif = $koneksi->prepare("
    SELECT transaksi.*, 
           buku.id AS buku_id,
           buku.judul, 
           buku.pengarang,
           buku.penerbit,
           buku.tahun_terbit,
           buku.cover,
           buku_item.kode_item,
           kategori.nama_kategori
    FROM transaksi
    JOIN buku_item ON transaksi.buku_item_id = buku_item.id
    JOIN buku ON buku_item.buku_id = buku.id
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
    WHERE transaksi.anggota_id = :id AND transaksi.status = 'dipinjam'
    ORDER BY transaksi.tgl_kembali_rencana ASC
");
$stmtAktif->execute([':id' => $anggotaId]);
$listAktif = $stmtAktif->fetchAll(PDO::FETCH_ASSOC);

$totalDipinjam = count($listAktif);
$maksPinjam    = 3; // Batas aturan perpustakaan sekolah: maksimal 3 buku
$slotKosong    = max(0, $maksPinjam - $totalDipinjam);

// 4. Query total seluruh riwayat peminjaman siswa ini
$stmtRiwayat = $koneksi->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = :id");
$stmtRiwayat->execute([':id' => $anggotaId]);
$totalRiwayat = (int) $stmtRiwayat->fetchColumn();

// 5. Query jumlah buku fisik yang berstatus tersedia di seluruh perpustakaan
$totalBukuTersedia = (int) $koneksi->query("SELECT COUNT(*) FROM buku_item WHERE status = 'tersedia'")->fetchColumn();

// 6. Cek apakah ada pinjaman yang telah melewati tanggal batas pengembalian (terlambat)
$hariIni = date('Y-m-d');
$adaTerlambat = false;
foreach ($listAktif as $item) {
    if ($hariIni > $item['tgl_kembali_rencana']) {
        $adaTerlambat = true;
        break;
    }
}

// 7. Ambil 4 rekomendasi buku terbaru untuk inspirasi bacaan siswa
$rekomendasiBuku = $koneksi->query("
    SELECT buku.*, kategori.nama_kategori,
           (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id AND status = 'tersedia') AS stok_tersedia,
           (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id) AS total_fisik
    FROM buku
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
    ORDER BY buku.id DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle   = 'Dashboard Siswa';
$currentPage = 'dashboard';

require 'includes/header.php';
?>

<!-- =========================================================================
     1. HERO SECTION: UCAPAN SELAMAT DATANG & KARTU PELAJAR DIGITAL
     ========================================================================= -->
<div class="sw-hero">
    
    <!-- Kolom Kiri: Sapaan Personal & Pelacak Kuota Pinjaman Siswa -->
    <div class="sw-hero-info">
        <div class="sw-hero-top">
            <div class="sw-greeting-badge">
                <!-- Ikon Bintang Bacaan -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <span>Portal Perpustakaan Digital</span>
            </div>

            <h1 class="sw-hero-title">Halo, <?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="sw-hero-desc">
                Kelas <strong><?= htmlspecialchars($kelasSiswa, ENT_QUOTES, 'UTF-8') ?></strong> &bull; NIS: <strong><?= htmlspecialchars($nisSiswa, ENT_QUOTES, 'UTF-8') ?></strong>. Jelajahi katalog buku dan pantau sirkulasi peminjamanmu secara real-time.
            </p>
        </div>

        <!-- Indikator Kuota Pinjaman (Visual 3 Slot) -->
        <div class="sw-quota-box">
            <div class="sw-quota-head">
                <strong>Kuota Peminjaman Buku</strong>
                <span class="sw-quota-count"><?= $totalDipinjam ?> / <?= $maksPinjam ?> Slot Terpakai</span>
            </div>

            <div class="sw-quota-slots">
                <?php for ($i = 0; $i < $maksPinjam; $i++): ?>
                    <?php if (isset($listAktif[$i])): 
                        $bukuSlot = $listAktif[$i];
                        $isTelat = $hariIni > $bukuSlot['tgl_kembali_rencana'];
                    ?>
                        <div class="sw-slot-pill <?= $isTelat ? 'is-late' : 'is-used' ?>" title="<?= htmlspecialchars($bukuSlot['judul'], ENT_QUOTES, 'UTF-8') ?>">
                            <!-- Ikon Buku Terisi -->
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            </svg>
                            <span>Slot <?= $i + 1 ?>: <?= $isTelat ? 'Terlambat' : 'Dipinjam' ?></span>
                        </div>
                    <?php else: ?>
                        <div class="sw-slot-pill" title="Slot kosong untuk meminjam buku">
                            <!-- Ikon Plus Kosong -->
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Slot <?= $i + 1 ?>: Kosong</span>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Kartu Pelajar RFID Digital Interaktif -->
    <div class="sw-card-container">
        <div class="sw-digital-card">
            <!-- Pola background & watermark buku -->
            <div class="sw-card-pattern" aria-hidden="true"></div>
            <svg class="sw-card-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
            </svg>

            <!-- Bagian Atas Kartu: Logo & Chip EMV + Gelombang RFID -->
            <div class="sw-card-head">
                <div class="sw-card-brand">
                    <div class="sw-card-logo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                        </svg>
                    </div>
                    <div class="sw-card-title">
                        Pinjam.In
                        <small>SMKN 1 PANYINGKIRAN</small>
                    </div>
                </div>

                <div class="sw-card-chip">
                    <!-- Chip EMV Emas -->
                    <div class="sw-emv-chip" title="Smart Chip Kartu"></div>
                    <!-- Simbol NFC / RFID Contactless Wave -->
                    <svg class="sw-rfid-wave" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" title="Kartu Mendukung Tap RFID">
                        <path d="M8.5 16.5a5 5 0 0 1 0-9"/>
                        <path d="M12 19a8.5 8.5 0 0 0 0-14"/>
                        <path d="M15.5 21.5a12 12 0 0 0 0-19"/>
                    </svg>
                </div>
            </div>

            <!-- Bagian Tengah Kartu: Identitas Siswa -->
            <div class="sw-card-body">
                <div class="sw-card-name" title="<?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="sw-card-meta">
                    <span>NIS: <strong><?= htmlspecialchars($nisSiswa, ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <span>&bull;</span>
                    <span>Kelas: <strong><?= htmlspecialchars($kelasSiswa, ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
            </div>

            <!-- Bagian Bawah Kartu: Status UID RFID & Tombol Aksi -->
            <div class="sw-card-foot">
                <div class="sw-card-rfid-uid">
                    <?php if (!empty($kodeRfid)): ?>
                        RFID: <strong><?= htmlspecialchars($kodeRfid, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php else: ?>
                        RFID: <span style="color:#F9A8A8;">Belum Tertaut</span>
                    <?php endif; ?>
                </div>

                <div class="sw-card-actions">
                    <button type="button" class="sw-card-btn" id="btnBukaKartu">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 3 21 3 21 9"/>
                            <polyline points="9 21 3 21 3 15"/>
                            <line x1="21" y1="3" x2="14" y2="10"/>
                            <line x1="3" y1="21" x2="10" y2="14"/>
                        </svg>
                        <span>Perbesar Kartu</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     2. STATISTIK KILAT SISWA
     ========================================================================= -->
<div class="sw-stats-row">
    <!-- Stat 1: Buku yang Sedang Dipinjam -->
    <div class="sw-stat-tile green">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val">
                <?= $totalDipinjam ?> <small>/ <?= $maksPinjam ?> buku</small>
            </div>
            <div class="sw-stat-lbl">Sedang Kamu Bawa</div>
        </div>
    </div>

    <!-- Stat 2: Total Riwayat Peminjaman -->
    <div class="sw-stat-tile blue">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                <polyline points="9 11 12 14 22 4"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val"><?= $totalRiwayat ?> <small>kali</small></div>
            <div class="sw-stat-lbl">Riwayat Peminjaman</div>
        </div>
    </div>

    <!-- Stat 3: Koleksi Fisik Tersedia di Sekolah -->
    <div class="sw-stat-tile orange">
        <div class="sw-stat-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
        </div>
        <div class="sw-stat-data">
            <div class="sw-stat-val"><?= $totalBukuTersedia ?> <small>eksemplar</small></div>
            <div class="sw-stat-lbl">Buku Tersedia di Rak</div>
        </div>
    </div>
</div>

<!-- =========================================================================
     3. BUKU YANG SEDANG DIPINJAM (ACTIVE LOANS PREVIEW)
     ========================================================================= -->
<div style="margin-bottom: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
        <div>
            <h2 style="font-size: 19px; font-weight: 800; color: var(--sw-text); margin: 0 0 4px;">
                Buku yang Sedang Kamu Pinjam
            </h2>
            <p style="font-size: 13.5px; color: var(--sw-text-muted); margin: 0;">
                Kembalikan buku tepat waktu sebelum tanggal batas waktu agar tidak terkena denda keterlambatan.
            </p>
        </div>
        <a href="pinjam.php" style="font-size: 13.5px; font-weight: 700; color: var(--sw-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
            <span>Lihat Semua</span> &rarr;
        </a>
    </div>

    <?php if (empty($listAktif)): ?>
        <!-- Keadaan jika siswa tidak sedang meminjam buku apapun -->
        <div style="background: #FFFFFF; border: 1.5px dashed var(--sw-border); border-radius: var(--sw-radius-md); padding: 36px 20px; text-align: center;">
            <div style="width: 46px; height: 46px; border-radius: 50%; background: var(--sw-primary-soft); color: var(--sw-primary); display: grid; place-items: center; margin: 0 auto 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    <path d="M8 7h8"/>
                    <path d="M8 11h8"/>
                </svg>
            </div>
            <h3 style="font-size: 16px; font-weight: 700; color: var(--sw-text); margin: 0 0 6px;">Tidak Ada Pinjaman Aktif</h3>
            <p style="font-size: 13.5px; color: var(--sw-text-muted); margin: 0 0 16px;">Kamu punya 3 slot kosong untuk meminjam buku bacaan atau buku pelajaran.</p>
            <a href="daftar_buku.php" class="sw-btn-detail" style="background: var(--sw-primary); color: #FFFFFF; border-color: var(--sw-primary); padding: 8px 18px; font-size: 13px;">
                Cari Buku di Katalog &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="sw-active-loans">
            <?php foreach ($listAktif as $row): 
                $terlambat = $hariIni > $row['tgl_kembali_rencana'];
                $sisaHari  = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($hariIni)) / 86400);
                $coverUrl  = coverUrl($row['cover'] ?? null, '../uploads/covers/');
            ?>
                <div class="sw-loan-item <?= $terlambat ? 'late' : '' ?>">
                    <!-- Cover Buku Miniatur -->
                    <div class="sw-loan-cover">
                        <?php if ($coverUrl): ?>
                            <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover <?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php else: ?>
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Informasi Buku & Eksemplar -->
                    <div class="sw-loan-info">
                        <div class="sw-loan-title"><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="sw-loan-chips">
                            <span class="sw-info-chip">Kode Eksemplar: <code><?= htmlspecialchars($row['kode_item'], ENT_QUOTES, 'UTF-8') ?></code></span>
                            <span class="sw-info-chip">Kategori: <?= htmlspecialchars($row['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="sw-info-chip">Dipinjam: <?= htmlspecialchars($row['tgl_pinjam'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <!-- Kolom Status Batas Waktu -->
                    <div class="sw-loan-status-col">
                        <?php if ($terlambat): ?>
                            <div class="sw-due-badge danger">
                                <span>Terlambat <?= abs($sisaHari) ?> Hari</span>
                            </div>
                        <?php elseif ($sisaHari === 0): ?>
                            <div class="sw-due-badge warning">
                                <span>Jatuh Tempo Hari Ini</span>
                            </div>
                        <?php else: ?>
                            <div class="sw-due-badge ok">
                                <span>Sisa <?= $sisaHari ?> Hari Lagi</span>
                            </div>
                        <?php endif; ?>
                        <div class="sw-due-date">
                            Batas: <strong><?= date('d M Y', strtotime($row['tgl_kembali_rencana'])) ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
     4. REKOMENDASI BUKU TERBARU DI PERPUSTAKAAN
     ========================================================================= -->
<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
        <div>
            <h2 style="font-size: 19px; font-weight: 800; color: var(--sw-text); margin: 0 0 4px;">
                Rekomendasi Koleksi Terbaru
            </h2>
            <p style="font-size: 13.5px; color: var(--sw-text-muted); margin: 0;">
                Buku-buku yang baru saja ditambahkan ke katalog perpustakaan sekolah.
            </p>
        </div>
        <a href="daftar_buku.php" style="font-size: 13.5px; font-weight: 700; color: var(--sw-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
            <span>Jelajahi Katalog</span> &rarr;
        </a>
    </div>

    <div class="sw-book-grid">
        <?php foreach ($rekomendasiBuku as $b): 
            $cvUrl     = coverUrl($b['cover'] ?? null, '../uploads/covers/');
            $stokAda   = (int) ($b['stok_tersedia'] ?? 0);
            $totalAda  = (int) ($b['total_fisik'] ?? 0);
            $tersedia  = $stokAda > 0;
            
            // Siapkan JSON data untuk modal interaktif
            $bukuJson = json_encode([
                'id'            => (int) $b['id'],
                'kode_buku'     => (string) $b['kode_buku'],
                'judul'         => (string) $b['judul'],
                'pengarang'     => (string) ($b['pengarang'] ?: '-'),
                'penerbit'      => (string) ($b['penerbit'] ?: '-'),
                'tahun_terbit'  => (string) ($b['tahun_terbit'] ?: '-'),
                'nama_kategori' => (string) ($b['nama_kategori'] ?? 'Umum'),
                'cover_url'     => $cvUrl ?: '',
                'stok_tersedia' => $stokAda,
                'total_fisik'   => $totalAda,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        ?>
            <div class="sw-book-card">
                <div class="sw-book-card-main">
                    <div class="sw-book-cover">
                        <?php if ($cvUrl): ?>
                            <img src="<?= htmlspecialchars($cvUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover <?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <div class="sw-book-meta">
                        <span class="sw-cat-badge" style="background:#EBF7F0; color:#147A50;">
                            <?= htmlspecialchars($b['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h3 class="sw-book-title" title="<?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <p class="sw-book-author">Oleh: <?= htmlspecialchars($b['pengarang'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="sw-book-pub"><?= htmlspecialchars($b['penerbit'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <div class="sw-book-card-foot">
                    <div class="sw-avail-pill <?= $tersedia ? 'ready' : 'empty' ?>">
                        <span class="sw-avail-dot"></span>
                        <span><?= $tersedia ? "{$stokAda} Tersedia di Rak" : 'Sedang Dipinjam' ?></span>
                    </div>

                    <button type="button" class="sw-btn-detail js-lihat-buku" data-buku='<?= htmlspecialchars($bukuJson, ENT_QUOTES, 'UTF-8') ?>'>
                        <span>Detail</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- =========================================================================
     5. MODAL DIALOG: DETAIL BUKU INTERAKTIF
     ========================================================================= -->
<div class="sw-modal-backdrop" id="modalDetailBuku" hidden role="dialog" aria-modal="true" aria-labelledby="modalBukuJudul">
    <div class="sw-modal-dialog">
        <div class="sw-modal-head">
            <h3 id="modalBukuJudul">Detail Buku Perpustakaan</h3>
            <button type="button" class="sw-modal-close" data-sw-close="modalDetailBuku" aria-label="Tutup Dialog">&times;</button>
        </div>

        <div class="sw-modal-body">
            <div class="sw-detail-layout">
                <div class="sw-detail-cover" id="modalCoverWrap">
                    <img id="modalCoverImg" alt="Cover Buku" hidden>
                    <svg id="modalCoverPlaceholder" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    </svg>
                </div>

                <div class="sw-detail-info">
                    <span class="sw-cat-badge" id="modalKatBadge" style="background:#EBF7F0; color:#147A50;">Kategori</span>
                    <h2 id="modalJudulTxt">-</h2>

                    <div class="sw-detail-grid">
                        <div class="sw-detail-dt">Kode Katalog</div>
                        <div class="sw-detail-dd" id="modalKodeTxt">-</div>

                        <div class="sw-detail-dt">Penulis / Pengarang</div>
                        <div class="sw-detail-dd" id="modalPengarangTxt">-</div>

                        <div class="sw-detail-dt">Penerbit</div>
                        <div class="sw-detail-dd" id="modalPenerbitTxt">-</div>

                        <div class="sw-detail-dt">Tahun Terbit</div>
                        <div class="sw-detail-dd" id="modalTahunTxt">-</div>

                        <div class="sw-detail-dt">Ketersediaan Fisik</div>
                        <div class="sw-detail-dd" id="modalStokTxt">-</div>
                    </div>
                </div>
            </div>

            <!-- Banner Petunjuk Peminjaman Siswa -->
            <div class="sw-detail-banner">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <strong>Ingin meminjam buku ini?</strong> Datang langsung ke meja sirkulasi perpustakaan sekolah dan tap kartu pelajarmu di card reader petugas.
                </div>
            </div>
        </div>

        <div class="sw-modal-foot">
            <button type="button" class="sw-btn-modal-close" data-sw-close="modalDetailBuku">Tutup</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     6. MODAL DIALOG: KARTU IDENTITAS PELAJAR DIGITAL (UKURAN BESAR)
     ========================================================================= -->
<div class="sw-modal-backdrop" id="modalKartuDigital" hidden role="dialog" aria-modal="true" aria-labelledby="modalKartuTitle">
    <div class="sw-modal-dialog" style="max-width: 540px;">
        <div class="sw-modal-head">
            <h3 id="modalKartuTitle">Kartu Pelajar Perpustakaan</h3>
            <button type="button" class="sw-modal-close" data-sw-close="modalKartuDigital" aria-label="Tutup Dialog">&times;</button>
        </div>

        <div class="sw-modal-body" style="padding: 24px; text-align: center;">
            <!-- Kartu Siswa Versi Jelas & Tajam -->
            <div class="sw-digital-card" style="box-shadow: 0 16px 36px rgba(10, 48, 33, 0.35); text-align: left; margin-bottom: 20px;">
                <div class="sw-card-pattern" aria-hidden="true"></div>
                <div class="sw-card-head">
                    <div class="sw-card-brand">
                        <div class="sw-card-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            </svg>
                        </div>
                        <div class="sw-card-title">
                            Pinjam.In
                            <small>SMKN 1 PANYINGKIRAN</small>
                        </div>
                    </div>
                    <div class="sw-card-chip">
                        <div class="sw-emv-chip"></div>
                        <svg class="sw-rfid-wave" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8.5 16.5a5 5 0 0 1 0-9"/>
                            <path d="M12 19a8.5 8.5 0 0 0 0-14"/>
                            <path d="M15.5 21.5a12 12 0 0 0 0-19"/>
                        </svg>
                    </div>
                </div>

                <div class="sw-card-body" style="margin: 24px 0 16px;">
                    <div class="sw-card-name" style="font-size: 19px;">
                        <?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="sw-card-meta">
                        <span>NIS: <strong><?= htmlspecialchars($nisSiswa, ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <span>&bull;</span>
                        <span>Kelas: <strong><?= htmlspecialchars($kelasSiswa, ENT_QUOTES, 'UTF-8') ?></strong></span>
                    </div>
                </div>

                <div class="sw-card-foot">
                    <div class="sw-card-rfid-uid">
                        UID RFID: <strong><?= !empty($kodeRfid) ? htmlspecialchars($kodeRfid, ENT_QUOTES, 'UTF-8') : 'Belum Terdaftar' ?></strong>
                    </div>
                    <span style="font-size: 11px; color: #4AE3A1; font-weight: 700; background: rgba(74, 227, 161, 0.15); padding: 3px 8px; border-radius: 99px;">
                        Anggota Resmi
                    </span>
                </div>
            </div>

            <p style="font-size: 13.5px; color: var(--sw-text-muted); margin: 0; line-height: 1.5;">
                Tunjukkan kartu digital ini kepada petugas perpustakaan jika kamu lupa membawa kartu fisik saat meminjam atau mengembalikan buku.
            </p>
        </div>

        <div class="sw-modal-foot">
            <button type="button" class="sw-btn-modal-close" data-sw-close="modalKartuDigital">Tutup</button>
        </div>
    </div>
</div>

<?php
// Script JavaScript interaktif untuk membuka modal kartu dan detail buku
$extraScripts = <<<'HTML'
<script>
(function () {
    // 1. Selector elemen modal
    const modalDetailBuku = document.getElementById('modalDetailBuku');
    const modalKartuDigital = document.getElementById('modalKartuDigital');

    const modalJudulTxt     = document.getElementById('modalJudulTxt');
    const modalBukuJudul    = document.getElementById('modalBukuJudul');
    const modalKodeTxt      = document.getElementById('modalKodeTxt');
    const modalPengarangTxt = document.getElementById('modalPengarangTxt');
    const modalPenerbitTxt  = document.getElementById('modalPenerbitTxt');
    const modalTahunTxt     = document.getElementById('modalTahunTxt');
    const modalStokTxt      = document.getElementById('modalStokTxt');
    const modalKatBadge     = document.getElementById('modalKatBadge');
    const modalCoverImg     = document.getElementById('modalCoverImg');
    const modalCoverHolder  = document.getElementById('modalCoverPlaceholder');

    // 2. Fungsi buka modal detail buku
    function openBukuDetail(buku) {
        if (!buku) return;
        modalBukuJudul.textContent = buku.judul || 'Detail Buku';
        modalJudulTxt.textContent = buku.judul || '-';
        modalKodeTxt.textContent = buku.kode_buku || '-';
        modalPengarangTxt.textContent = buku.pengarang || '-';
        modalPenerbitTxt.textContent = buku.penerbit || '-';
        modalTahunTxt.textContent = buku.tahun_terbit || '-';
        modalKatBadge.textContent = buku.nama_kategori || 'Umum';

        const sAda = buku.stok_tersedia || 0;
        const sTot = buku.total_fisik || 0;
        modalStokTxt.innerHTML = sAda > 0 
            ? `<span style="color:#1B7A55;"><strong>${sAda} eksemplar tersedia</strong> (dari total ${sTot})</span>`
            : `<span style="color:#C23B3B;">Sedang dipinjam semua (0/${sTot})</span>`;

        if (buku.cover_url) {
            modalCoverImg.src = buku.cover_url;
            modalCoverImg.hidden = false;
            if (modalCoverHolder) modalCoverHolder.hidden = true;
        } else {
            modalCoverImg.hidden = true;
            if (modalCoverHolder) modalCoverHolder.hidden = false;
        }

        if (modalDetailBuku) modalDetailBuku.hidden = false;
    }

    // 3. Pasang event listener pada tombol "Detail" buku
    document.addEventListener('click', function (e) {
        const btnBuku = e.target.closest('.js-lihat-buku');
        if (btnBuku) {
            try {
                const data = JSON.parse(btnBuku.dataset.buku || '{}');
                openBukuDetail(data);
            } catch (err) {}
            return;
        }

        // Buka modal kartu pelajar
        if (e.target.closest('#btnBukaKartu')) {
            if (modalKartuDigital) modalKartuDigital.hidden = false;
        }
    });

    // 4. Tutup modal ketika tombol close / backdrop diklik
    document.querySelectorAll('[data-sw-close]').forEach(btn => {
        btn.addEventListener('click', function () {
            const m = document.getElementById(this.dataset.swClose);
            if (m) m.hidden = true;
        });
    });

    [modalDetailBuku, modalKartuDigital].forEach(bd => {
        if (!bd) return;
        bd.addEventListener('click', function (e) {
            if (e.target === bd) bd.hidden = true;
        });
    });

    // 5. Tutup modal dengan tombol Escape keyboard
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (modalDetailBuku && !modalDetailBuku.hidden) modalDetailBuku.hidden = true;
            if (modalKartuDigital && !modalKartuDigital.hidden) modalKartuDigital.hidden = true;
        }
    });
})();
</script>
HTML;

require 'includes/footer.php';
?>
