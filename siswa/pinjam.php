<?php
/**
 * ============================================================================
 * BUKU PINJAMAN SAYA - PINJAM.IN
 * ============================================================================
 * Menampilkan daftar buku fisik perpustakaan yang sedang dipinjam oleh siswa.
 * Dilengkapi fitur hitung mundur batas pengembalian (countdown jatuh tempo),
 * peringatan keterlambatan visual, serta panduan langkah pengembalian buku.
 */

// 1. Cek keamanan sesi login siswa
require '../config/cek_session_siswa.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';

$anggotaId   = (int) $_SESSION['anggota_id'];
$batasPinjam = 3; // Batas maksimal peminjaman sesuai SOP perpustakaan sekolah
$hariIni     = date('Y-m-d');

// 2. Query seluruh transaksi peminjaman yang masih berstatus 'dipinjam' oleh siswa ini
$stmt = $koneksi->prepare("
    SELECT transaksi.*, 
           buku.judul, 
           buku.kode_buku, 
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
$stmt->execute([':id' => $anggotaId]);
$listPinjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDipinjam = count($listPinjaman);

// 3. Pengecekan apakah ada buku yang melewati batas jatuh tempo (terlambat)
$adaTerlambat = false;
$jumlahTerlambat = 0;
foreach ($listPinjaman as $p) {
    if ($hariIni > $p['tgl_kembali_rencana']) {
        $adaTerlambat = true;
        $jumlahTerlambat++;
    }
}

$pageTitle   = 'Buku Pinjaman Saya';
$currentPage = 'pinjam';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="page-header-wrap" style="margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Buku Pinjaman Saya
        </h1>
        <p class="page-subtitle" style="font-size: 14.5px; color: var(--sw-text-muted); margin: 0;">
            Pantau status batas waktu dan kembalikan buku fisik tepat waktu ke meja sirkulasi perpustakaan.
        </p>
    </div>

    <!-- Tombol Navigasi ke Katalog -->
    <div>
        <a href="daftar_buku.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <span>Cari Buku Lain</span>
        </a>
    </div>
</div>

<!-- =========================================================================
     1. ALERT & NOTIFIKASI STATUS SIRKULASI
     ========================================================================= -->
<?php if ($adaTerlambat): ?>
    <!-- Peringatan Keterlambatan Merah -->
    <div style="background: var(--sw-danger-soft); border: 1.5px solid #F5C2C2; border-radius: var(--sw-radius-md); padding: 16px 20px; display: flex; align-items: flex-start; gap: 14px; margin-bottom: 22px; color: var(--sw-danger);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div>
            <strong style="font-size: 15px; display: block; margin-bottom: 4px;">Perhatian: Ada <?= $jumlahTerlambat ?> Buku yang Telah Melewati Batas Waktu!</strong>
            <span style="font-size: 13.5px; line-height: 1.45; color: #9B2C2C;">
                Harap segera bawa buku ke perpustakaan untuk dikembalikan kepada petugas agar tidak dikenakan sanksi penangguhan peminjaman.
            </span>
        </div>
    </div>
<?php endif; ?>

<?php if ($totalDipinjam >= $batasPinjam): ?>
    <!-- Notifikasi Kuota Maksimal Tercapai -->
    <div style="background: var(--sw-warning-soft); border: 1.5px solid #F8D7B0; border-radius: var(--sw-radius-md); padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 22px; color: var(--sw-warning); font-size: 13.5px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div>
            <strong>Slot Maksimal Tercapai (<?= $totalDipinjam ?>/<?= $batasPinjam ?>):</strong> Kamu telah menggunakan seluruh kuota peminjaman. Kembalikan salah satu buku terlebih dahulu untuk meminjam buku baru.
        </div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     2. DAFTAR BUKU YANG SEDANG DIPINJAM (KARTU PINJAMAN INTERAKTIF)
     ========================================================================= -->
<?php if (empty($listPinjaman)): ?>
    <!-- Keadaan Kosong (Tidak Ada Buku yang Sedang Dipinjam) -->
    <div style="background: #FFFFFF; border: 1.5px dashed var(--sw-border); border-radius: var(--sw-radius-lg); padding: 56px 24px; text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--sw-primary-soft); color: var(--sw-primary); display: grid; place-items: center; margin: 0 auto 16px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                <polyline points="9 11 12 14 22 4"/>
            </svg>
        </div>
        <h2 style="font-size: 20px; font-weight: 800; color: var(--sw-text); margin: 0 0 8px;">
            Tidak Ada Buku yang Sedang Dipinjam
        </h2>
        <p style="font-size: 14px; color: var(--sw-text-muted); margin: 0 0 22px; max-width: 440px; margin-inline: auto;">
            Kamu memiliki <strong><?= $batasPinjam ?> slot kosong</strong> yang bisa digunakan untuk meminjam buku bacaan atau bahan materi belajar sekolah.
        </p>
        <a href="daftar_buku.php" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;">
            Jelajahi Katalog Buku &rarr;
        </a>
    </div>
<?php else: ?>
    <!-- Daftar Kartu Buku Pinjaman -->
    <div class="sw-active-loans">
        <?php foreach ($listPinjaman as $i => $row): 
            $terlambat = $hariIni > $row['tgl_kembali_rencana'];
            $selisihHari = (int) round((strtotime($row['tgl_kembali_rencana']) - strtotime($hariIni)) / 86400);
            $cvUrl = coverUrl($row['cover'] ?? null, '../uploads/covers/');

            // Format tanggal yang ramah dibaca
            $tglPinjamFmt   = date('d M Y', strtotime($row['tgl_pinjam']));
            $tglKembaliFmt  = date('d M Y', strtotime($row['tgl_kembali_rencana']));
        ?>
            <div class="sw-loan-item <?= $terlambat ? 'late' : '' ?>">
                <!-- Cover Buku -->
                <div class="sw-loan-cover">
                    <?php if ($cvUrl): ?>
                        <img src="<?= htmlspecialchars($cvUrl, ENT_QUOTES, 'UTF-8') ?>" 
                             alt="Cover <?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>" 
                             loading="lazy">
                    <?php else: ?>
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                        </svg>
                    <?php endif; ?>
                </div>

                <!-- Informasi Detail Pinjaman -->
                <div class="sw-loan-info">
                    <div class="sw-loan-title"><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="sw-loan-chips">
                        <span class="sw-info-chip">
                            Kode Eksemplar Fisik: <code><?= htmlspecialchars($row['kode_item'], ENT_QUOTES, 'UTF-8') ?></code>
                        </span>
                        <span class="sw-info-chip">
                            Kategori: <?= htmlspecialchars($row['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="sw-info-chip">
                            Penulis: <?= htmlspecialchars($row['pengarang'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div style="font-size: 13px; color: var(--sw-text-muted); display: flex; gap: 16px; flex-wrap: wrap;">
                        <span>Tanggal Dipinjam: <strong><?= $tglPinjamFmt ?></strong></span>
                        <span>Batas Terakhir Kembali: <strong><?= $tglKembaliFmt ?></strong></span>
                    </div>
                </div>

                <!-- Kolom Status Batas Waktu & Countdown -->
                <div class="sw-loan-status-col">
                    <?php if ($terlambat): ?>
                        <div class="sw-due-badge danger">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>Terlambat <?= abs($selisihHari) ?> Hari</span>
                        </div>
                    <?php elseif ($selisihHari === 0): ?>
                        <div class="sw-due-badge warning">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>Jatuh Tempo Hari Ini</span>
                        </div>
                    <?php elseif ($selisihHari <= 2): ?>
                        <div class="sw-due-badge warning">
                            <span>Sisa <?= $selisihHari ?> Hari Lagi</span>
                        </div>
                    <?php else: ?>
                        <div class="sw-due-badge ok">
                            <span>Sisa <?= $selisihHari ?> Hari Lagi</span>
                        </div>
                    <?php endif; ?>

                    <div class="sw-due-date">
                        Batas: <strong><?= $tglKembaliFmt ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- =========================================================================
     3. PANDUAN LANGKAH PENGEMBALIAN BUKU
     ========================================================================= -->
<div class="sw-return-guide">
    <div class="sw-return-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--sw-primary);">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        <span>Langkah Mudah Mengembalikan Buku ke Perpustakaan</span>
    </div>

    <div class="sw-steps-row">
        <!-- Langkah 1 -->
        <div class="sw-step-box">
            <div class="sw-step-num">1</div>
            <div class="sw-step-txt">
                <strong>Bawa Buku Fisik</strong>
                <p>Pastikan buku dalam kondisi baik, bersih, dan stiker kode barcode/QR di buku masih utuh.</p>
            </div>
        </div>

        <!-- Langkah 2 -->
        <div class="sw-step-box">
            <div class="sw-step-num">2</div>
            <div class="sw-step-txt">
                <strong>Kunjungi Meja Sirkulasi</strong>
                <p>Serahkan buku dan tap kartu pelajar RFID-mu pada pembaca kartu di meja petugas.</p>
            </div>
        </div>

        <!-- Langkah 3 -->
        <div class="sw-step-box">
            <div class="sw-step-num">3</div>
            <div class="sw-step-txt">
                <strong>Selesai &amp; Kuota Kembali</strong>
                <p>Petugas memindai QR buku, status pinjaman otomatis selesai, dan slot kuotamu terbuka kembali.</p>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
