<?php
/**
 * ============================================================================
 * HALAMAN PROFIL ADMINISTRATOR - PINJAM.IN
 * ============================================================================
 * Halaman untuk melihat dan mengelola profil akun petugas / admin perpustakaan.
 * Memungkinkan pembaruan nama lengkap serta pembaruan kata sandi admin
 * dengan verifikasi kata sandi lama untuk keamanan maksimal.
 */

// 1. Verifikasi sesi login khusus admin
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/log_helper.php';

$adminId = (int) $_SESSION['admin_id'];

// 2. Ambil data admin saat ini dari database
$stmtAdmin = $koneksi->prepare("SELECT * FROM admin WHERE id = :id LIMIT 1");
$stmtAdmin->execute([':id' => $adminId]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: ../logout.php');
    exit;
}

// 3. Proses form POST pembaruan profil & password
$pesanSukses = '';
$pesanError  = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $namaLengkap  = trim($_POST['nama_lengkap'] ?? '');
    $passLama     = trim($_POST['password_lama'] ?? '');
    $passBaru     = trim($_POST['password_baru'] ?? '');
    $passKonf     = trim($_POST['password_konfirmasi'] ?? '');

    // Validasi nama tidak boleh kosong
    if (empty($namaLengkap)) {
        header('Location: profil.php?error=nama_kosong');
        exit;
    }

    $ubahPassword = false;
    $hashPassword = null;

    // Jika admin mengisi kolom password baru
    if (!empty($passBaru)) {
        // Wajib verifikasi password lama
        if (empty($passLama) || !password_verify($passLama, $admin['password'])) {
            header('Location: profil.php?error=pass_lama_salah');
            exit;
        }

        // Validasi panjang karakter minimal 6
        if (strlen($passBaru) < 6) {
            header('Location: profil.php?error=pass_pendek');
            exit;
        }

        // Validasi kesamaan konfirmasi password
        if ($passBaru !== $passKonf) {
            header('Location: profil.php?error=pass_beda');
            exit;
        }

        $ubahPassword = true;
        $hashPassword = password_hash($passBaru, PASSWORD_DEFAULT);
    }

    try {
        if ($ubahPassword) {
            $stmtUpdate = $koneksi->prepare("
                UPDATE admin 
                SET nama_lengkap = :nama, password = :pass 
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nama' => $namaLengkap,
                ':pass' => $hashPassword,
                ':id'   => $adminId,
            ]);
        } else {
            $stmtUpdate = $koneksi->prepare("
                UPDATE admin 
                SET nama_lengkap = :nama 
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nama' => $namaLengkap,
                ':id'   => $adminId,
            ]);
        }

        // Perbarui sesi admin
        $_SESSION['admin_nama'] = $namaLengkap;

        // Catat ke log aktivitas sistem
        catatLog(
            $koneksi,
            'admin',
            $adminId,
            $namaLengkap,
            'update_profil',
            'profil',
            'Administrator memperbarui data profil' . ($ubahPassword ? ' dan mengganti password' : '')
        );

        header('Location: profil.php?success=update');
        exit;
    } catch (PDOException $e) {
        header('Location: profil.php?error=sistem');
        exit;
    }
}

// 4. Hitung ringkasan statistik sistem untuk kartu profil admin
$totalBuku      = (int) $koneksi->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$totalEksemplar = (int) $koneksi->query("SELECT COUNT(*) FROM buku_item")->fetchColumn();
$totalAnggota   = (int) $koneksi->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
$totalSirkulasi = (int) $koneksi->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();

// Parsing pesan feedback URL
$errCode = $_GET['error'] ?? '';
$sucCode = $_GET['success'] ?? '';

if ($sucCode === 'update') {
    $pesanSukses = 'Data profil administrator berhasil diperbarui.';
}
if ($errCode === 'nama_kosong') {
    $pesanError = 'Nama lengkap administrator tidak boleh dibiarkan kosong.';
} elseif ($errCode === 'pass_lama_salah') {
    $pesanError = 'Password lama yang Anda masukkan tidak cocok / salah.';
} elseif ($errCode === 'pass_pendek') {
    $pesanError = 'Password baru minimal harus terdiri dari 6 karakter.';
} elseif ($errCode === 'pass_beda') {
    $pesanError = 'Konfirmasi password baru tidak cocok.';
} elseif ($errCode === 'sistem') {
    $pesanError = 'Terjadi kesalahan sistem saat memperbarui data profil.';
}

$pageTitle   = 'Profil Admin';
$currentPage = 'profil';
$extraHead   = '<link rel="stylesheet" href="../assets/css/kelola.css?v=4">';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-head">
    <div>
        <h1>Profil Administrator</h1>
        <p>Kelola data nama akun petugas perpustakaan dan pembaruan kata sandi keamanan login.</p>
    </div>
</div>

<!-- NOTIFIKASI -->
<?php if (!empty($pesanSukses)): ?>
    <div class="kc-al ok" role="status">
        <?= ic('check') ?>
        <div><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($pesanError)): ?>
    <div class="kc-al err" role="alert">
        <?= ic('clock') ?>
        <div><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     LAYOUT 2-KOLOM: INFO KIRI & FORM EDIT KANAN
     ========================================================================= -->
<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start; margin-top: 16px;">
    
    <!-- KOLOM KIRI: KARTU IDENTITAS ADMINISTRATOR -->
    <div style="background: #FFFFFF; border: 1px solid var(--line); border-radius: 18px; padding: 28px 24px; box-shadow: 0 4px 14px rgba(20,35,43,.04); text-align: center;">
        <div style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, #1B7A55 0%, #2FBF84 100%); color: #FFFFFF; display: grid; place-items: center; margin: 0 auto 16px; box-shadow: 0 10px 22px rgba(27,122,85,.25);">
            <?= ic('user', 36) ?>
        </div>

        <h2 style="font-size: 19px; font-weight: 800; color: var(--ink); margin: 0 0 4px; line-height: 1.25;">
            <?= htmlspecialchars($admin['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="font-size: 13px; color: var(--muted); margin: 0 0 16px;">
            @<?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <span style="display: inline-block; padding: 4px 14px; border-radius: 99px; background: #DDF3E8; color: #1B7A55; font-size: 12px; font-weight: 700; margin-bottom: 22px;">
            Administrator Perpustakaan
        </span>

        <!-- Ringkasan Statistik Yang Dikelola Admin -->
        <div style="border-top: 1px solid var(--line); padding-top: 18px; text-align: left; display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Username Petugas</span>
                <span style="font-weight: 700; color: var(--ink); font-family: monospace;">@<?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Koleksi Judul Buku</span>
                <span style="font-weight: 700; color: var(--ink);"><?= $totalBuku ?> Judul</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Total Eksemplar Fisik</span>
                <span style="font-weight: 700; color: var(--ink);"><?= $totalEksemplar ?> Buku</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Anggota Siswa</span>
                <span style="font-weight: 700; color: var(--ink);"><?= $totalAnggota ?> Siswa</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Total Transaksi</span>
                <span style="font-weight: 700; color: #1B7A55;"><?= $totalSirkulasi ?> Transaksi</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Tanggal Terdaftar</span>
                <span style="color: var(--muted); font-size: 12px;"><?= date('d M Y', strtotime($admin['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: FORMULIR PEMBARUAN PROFIL ADMIN -->
    <div style="background: #FFFFFF; border: 1px solid var(--line); border-radius: 18px; padding: 28px; box-shadow: 0 4px 14px rgba(20,35,43,.04);">
        <form action="profil.php" method="POST" autocomplete="off">
            
            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--ink); margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--line);">
                1. Data Akun Administrator
            </h3>

            <!-- Username (Readonly) -->
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                    Username Admin
                </label>
                <input type="text" 
                       value="<?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?>" 
                       disabled 
                       style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #F4F7F5; color: #889892; cursor: not-allowed; font-family: monospace;">
                <small style="display: block; margin-top: 4px; font-size: 11.5px; color: var(--muted);">Username digunakan untuk proses autentikasi login petugas.</small>
            </div>

            <!-- Nama Lengkap -->
            <div style="margin-bottom: 24px;">
                <label for="nama_lengkap" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                    Nama Lengkap Petugas <span style="color:#C23B3B;">*</span>
                </label>
                <input type="text" 
                       id="nama_lengkap" 
                       name="nama_lengkap" 
                       required 
                       value="<?= htmlspecialchars($admin['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>" 
                       style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-family: inherit; font-size: 14.5px; color: var(--ink);">
            </div>

            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--ink); margin: 0 0 12px; padding-bottom: 12px; border-bottom: 1px solid var(--line);">
                2. Pembaruan Kata Sandi (Opsional)
            </h3>
            <p style="font-size: 13px; color: var(--muted); margin: 0 0 16px;">
                Kosongkan bidang berikut apabila Anda tidak ingin mengubah kata sandi akun administrator saat ini.
            </p>

            <!-- Password Lama -->
            <div style="margin-bottom: 16px;">
                <label for="password_lama" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                    Password Saat Ini (Password Lama)
                </label>
                <input type="password" 
                       id="password_lama" 
                       name="password_lama" 
                       placeholder="Masukkan password lama untuk konfirmasi..." 
                       style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-family: inherit; font-size: 14px; color: var(--ink);">
            </div>

            <!-- Password Baru & Konfirmasi -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 26px;">
                <div>
                    <label for="password_baru" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Password Baru
                    </label>
                    <input type="password" 
                           id="password_baru" 
                           name="password_baru" 
                           minlength="6" 
                           placeholder="Minimal 6 karakter" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-family: inherit; font-size: 14px; color: var(--ink);">
                </div>

                <div>
                    <label for="password_konfirmasi" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Ulangi Password Baru
                    </label>
                    <input type="password" 
                           id="password_konfirmasi" 
                           name="password_konfirmasi" 
                           minlength="6" 
                           placeholder="Ketik ulang password baru..." 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-family: inherit; font-size: 14px; color: var(--ink);">
                </div>
            </div>

            <!-- Tombol Aksi Simpan -->
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="submit" class="btn-d main" style="padding: 11px 26px; font-size: 14.5px; font-weight: 700;">
                    <?= ic('check') ?><span>Simpan Perubahan Profil</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
