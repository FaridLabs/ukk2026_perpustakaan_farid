<?php
require_once __DIR__ . '/../../config/upload_foto.php';

$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'], '.php');
$sbNamaAdmin = $_SESSION['admin_nama'] ?? 'Administrator';

// Mengambil foto profil admin (dari sesi atau query database)
$fotoAdmin = $_SESSION['admin_foto'] ?? null;
if ($fotoAdmin === null && isset($koneksi) && isset($_SESSION['admin_id'])) {
    $stmtFotoAdmin = $koneksi->prepare("SELECT foto FROM admin WHERE id = :id LIMIT 1");
    $stmtFotoAdmin->execute([':id' => $_SESSION['admin_id']]);
    $fotoAdmin = $stmtFotoAdmin->fetchColumn() ?: '';
    $_SESSION['admin_foto'] = $fotoAdmin;
}
$urlFotoAdminSidebar = !empty($fotoAdmin) ? fotoProfilUrl($fotoAdmin, '../uploads/profil/') : null;

$sbMenu = [
    'MENU UTAMA' => [
        ['dashboard',    'dashboard.php',    'home',    'Dashboard'],
        ['peminjaman',   'peminjaman.php',   'loan',    'Peminjaman Buku'],
        ['pengembalian', 'pengembalian.php', 'return',  'Pengembalian Buku'],
        ['transaksi',    'transaksi.php',    'history', 'Riwayat Transaksi'],
    ],
    'KOLEKSI & ANGGOTA' => [
        ['buku',     'buku.php',     'book',  'Data Buku'],
        ['kategori', 'kategori.php', 'tag',   'Kategori Buku'],
        ['anggota',  'anggota.php',  'users', 'Kelola Anggota'],
    ],
    'SISTEM' => [
        ['log_aktivitas', 'log_aktivitas.php', 'activity', 'Log Aktivitas'],
        ['pengaturan',    'pengaturan.php',    'settings', 'Pengaturan'],
        ['profil',        'profil.php',        'user',     'Profil Admin'],
    ],
];
// Halaman turunan yang tetap menandai menu induknya
$sbAlias = ['buku_item' => 'buku'];
$sbAktif = $sbAlias[$currentPage] ?? $currentPage;
?>
<aside class="side" id="sidebar" aria-label="Menu admin">
    <a href="dashboard.php" class="side-brand" aria-label="Pinjam.In, dashboard">
        <svg viewBox="0 0 48 40" aria-hidden="true">
            <path d="M24 15C23 6 29 2 37 3C37 11 32 16 24 15Z" fill="#5FD39B"/>
            <path d="M3 13Q3 11 5.2 11.2Q14 12 24 17.5V36Q14 31 5.2 31Q3 31 3 29Z" fill="#E3F2EA"/>
            <path d="M45 13Q45 11 42.8 11.2Q34 12 24 17.5V36Q34 31 42.8 31Q45 31 45 29Z" fill="#B7DEC9"/>
        </svg>
        <span><b>Pinjam.In</b><small>Perpustakaan Digital Sekolah</small></span>
    </a>

    <a href="profil.php" class="side-user" style="text-decoration:none;" title="Kelola Profil Administrator">
        <div class="side-ava" style="overflow:hidden; display:grid; place-items:center;">
            <?php if ($urlFotoAdminSidebar): ?>
                <img src="<?= htmlspecialchars($urlFotoAdminSidebar, ENT_QUOTES, 'UTF-8') ?>" alt="Foto Admin" style="width:100%; height:100%; object-fit:cover; border-radius:inherit;">
            <?php else: ?>
                <?= ic('user', 24) ?>
            <?php endif; ?>
        </div>
        <div class="side-who">
            <b><?= htmlspecialchars($sbNamaAdmin) ?></b>
            <div class="side-role"><small>Administrator</small><span class="pill-on">Online</span></div>
        </div>
    </a>

    <nav class="side-nav">
        <?php foreach ($sbMenu as $sbJudul => $sbGrup): ?>
            <div class="side-label"><?= htmlspecialchars($sbJudul) ?></div>
            <?php foreach ($sbGrup as [$sbKode, $sbHref, $sbIkon, $sbLabel]): ?>
                <a href="<?= $sbHref ?>" class="<?= $sbAktif === $sbKode ? 'active' : '' ?>" <?= $sbAktif === $sbKode ? 'aria-current="page"' : '' ?>>
                    <?= ic($sbIkon) ?><span><?= $sbLabel ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="side-foot">
        <a href="../logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
            <?= ic('logout') ?><span>Logout</span>
        </a>
    </div>
</aside>