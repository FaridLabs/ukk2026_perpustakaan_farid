<?php
/**
 * ============================================================================
 * SIDEBAR PORTAL SISWA - PINJAM.IN
 * ============================================================================
 * Menampilkan logo sistem, profil ringkas siswa, menu navigasi utama,
 * indikator buku aktif yang sedang dipinjam, identitas sekolah, dan tombol logout.
 */

// Menentukan halaman aktif berdasarkan nama file PHP yang sedang berjalan
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'], '.php');

// Mengambil profil siswa dari sesi login
$namaSiswa  = $_SESSION['anggota_nama'] ?? 'Siswa Perpustakaan';
$initial    = strtoupper(substr(trim($namaSiswa), 0, 1)) ?: 'S';
$kelasSiswa = $_SESSION['anggota_kelas'] ?? '-';

// Konfigurasi nama sekolah untuk perpustakaan
$namaSekolah = 'SMKN 1 Panyingkiran';
$subSekolah  = 'Perpustakaan Digital Sekolah';

// Menghitung jumlah buku yang sedang dipinjam oleh siswa saat ini (untuk badge angka merah/hijau di menu)
$badgeDipinjam = 0;
if (isset($koneksi) && isset($_SESSION['anggota_id'])) {
    $stmtBadge = $koneksi->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = :id AND status = 'dipinjam'");
    $stmtBadge->execute([':id' => $_SESSION['anggota_id']]);
    $badgeDipinjam = (int) $stmtBadge->fetchColumn();
}
?>
<aside class="sidebar" id="sidebar">

    <!-- 1. IDENTITAS BRAND PINJAM.IN -->
    <a href="dashboard.php" class="sb-brand" title="Menuju Dashboard Siswa">
        <div class="sb-brand-logo">
            <!-- Ikon Buku Digital Pinjam.In -->
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                <path d="M8 7h8"/>
                <path d="M8 11h8"/>
            </svg>
        </div>
        <div class="sb-brand-text">
            <strong>Pinjam.In</strong>
            <span>Perpustakaan Digital</span>
        </div>
    </a>

    <!-- 2. KARTU PROFIL RINGKAS SISWA -->
    <a href="profil.php" class="sb-user" style="text-decoration:none;" title="Kelola Profil Saya">
        <div class="sb-user-avatar"><?= $initial ?></div>
        <div class="sb-user-meta">
            <div class="sb-user-top">
                <strong title="<?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($namaSiswa, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="sb-online">Aktif</span>
            </div>
            <span>Kelas <?= htmlspecialchars($kelasSiswa, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </a>

    <!-- 3. DAFTAR MENU NAVIGASI SISWA -->
    <div class="sidebar-nav">
        <div class="nav-group">
            <div class="nav-group-label">Menu Navigasi</div>
            <ul class="nav-group-list">
                <!-- Menu Dashboard -->
                <li>
                    <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- Menu Katalog Buku -->
                <li>
                    <a href="daftar_buku.php" class="nav-link <?= $currentPage === 'daftar_buku' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <span>Katalog Buku</span>
                    </a>
                </li>

                <!-- Menu Buku Saya (Dilengkapi Badge Indikator Pinjaman Aktif) -->
                <li>
                    <a href="pinjam.php" class="nav-link <?= $currentPage === 'pinjam' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>Buku Saya</span>
                        <?php if ($badgeDipinjam > 0): ?>
                            <span class="sb-badge" title="<?= $badgeDipinjam ?> buku sedang dipinjam"><?= $badgeDipinjam ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Menu Riwayat Peminjaman -->
                <li>
                    <a href="riwayat.php" class="nav-link <?= $currentPage === 'riwayat' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <span>Riwayat Pinjam</span>
                    </a>
                </li>

                <!-- Menu Profil Siswa -->
                <li>
                    <a href="profil.php" class="nav-link <?= $currentPage === 'profil' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20.500c0-4 3.600-6.500 8-6.500s8 2.500 8 6.500"/>
                        </svg>
                        <span>Profil Saya</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- 4. FOOTER SIDEBAR (INFORMASI SEKOLAH & LOGOUT) -->
    <div class="sb-bottom">
        <div class="sb-school">
            <div class="sb-school-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18"/>
                    <path d="M5 21V10"/>
                    <path d="M19 21V10"/>
                    <path d="M9 21v-6h6v6"/>
                    <path d="M12 3 3 9h18z"/>
                </svg>
            </div>
            <div class="sb-school-text">
                <strong><?= htmlspecialchars($namaSekolah, ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars($subSekolah, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <a href="../logout.php" class="sb-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun perpustakaan?')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span>Keluar / Logout</span>
        </a>
    </div>
</aside>
