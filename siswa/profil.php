<?php
/**
 * ============================================================================
 * HALAMAN PROFIL SISWA - PINJAM.IN
 * ============================================================================
 * Halaman untuk melihat dan memperbarui data profil siswa (nama lengkap, foto profil,
 * kontak WhatsApp, alamat domisili, dan pembaruan password akun).
 * Dilengkapi validasi keamanan, upload foto aman, dan pencatatan riwayat perubahan ke tabel log.
 */

// 1. Cek keamanan sesi login siswa
require '../config/cek_session_siswa.php';
require '../config/koneksi.php';
require_once '../config/log_helper.php';
require_once '../config/upload_foto.php';

$anggotaId = (int) $_SESSION['anggota_id'];

// 2. Proses formulir POST ketika siswa menyimpan perubahan profil
$pesanSukses = '';
$pesanError  = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $noHp        = trim($_POST['no_hp'] ?? '');
    $alamat      = trim($_POST['alamat'] ?? '');
    $passBaru    = trim($_POST['password_baru'] ?? '');
    $passKonf    = trim($_POST['password_konfirmasi'] ?? '');
    $hapusFoto   = isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1';

    // Validasi input nama wajib diisi
    if (empty($namaLengkap)) {
        header('Location: profil.php?error=nama_kosong');
        exit;
    }

    // Ambil data foto yang sedang tersimpan saat ini di database
    $stmtCekFoto = $koneksi->prepare("SELECT foto FROM anggota WHERE id = :id LIMIT 1");
    $stmtCekFoto->execute([':id' => $anggotaId]);
    $fotoLama = $stmtCekFoto->fetchColumn() ?: null;
    $fotoFinal = $fotoLama;

    // A. Proses Upload Foto Profil Baru jika ada file yang dipilih
    if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $hasilUpload = simpanFotoProfil($_FILES['foto'], $fotoLama, 'siswa');
        if (!$hasilUpload['ok']) {
            // Jika validasi ukuran/format gagal, kembalikan pesan error ke siswa
            header('Location: profil.php?error=upload_gagal&msg=' . urlencode($hasilUpload['error']));
            exit;
        }
        $fotoFinal = $hasilUpload['file'];
    } elseif ($hapusFoto) {
        // B. Jika siswa mencentang opsi hapus foto saat ini
        hapusFotoProfil($fotoLama);
        $fotoFinal = null;
    }

    // C. Validasi jika siswa ingin mengganti password login
    $ubahPassword = false;
    $hashPassword = null;
    if (!empty($passBaru)) {
        if (strlen($passBaru) < 6) {
            header('Location: profil.php?error=pass_pendek');
            exit;
        }
        if ($passBaru !== $passKonf) {
            header('Location: profil.php?error=pass_beda');
            exit;
        }
        $ubahPassword = true;
        $hashPassword = password_hash($passBaru, PASSWORD_DEFAULT);
    }

    try {
        if ($ubahPassword) {
            // Simpan nama, kontak, alamat, foto profil, dan password baru
            $stmtUpdate = $koneksi->prepare("
                UPDATE anggota 
                SET nama_lengkap = :nama, no_hp = :hp, alamat = :alamat, foto = :foto, password = :pass
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nama'   => $namaLengkap,
                ':hp'     => $noHp,
                ':alamat' => $alamat,
                ':foto'   => $fotoFinal,
                ':pass'   => $hashPassword,
                ':id'     => $anggotaId,
            ]);
        } else {
            // Simpan tanpa mengubah password
            $stmtUpdate = $koneksi->prepare("
                UPDATE anggota 
                SET nama_lengkap = :nama, no_hp = :hp, alamat = :alamat, foto = :foto
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nama'   => $namaLengkap,
                ':hp'     => $noHp,
                ':alamat' => $alamat,
                ':foto'   => $fotoFinal,
                ':id'     => $anggotaId,
            ]);
        }

        // Perbarui sesi login siswa agar avatar dan nama di sidebar/topbar langsung terupdate
        $_SESSION['anggota_nama'] = $namaLengkap;
        $_SESSION['anggota_foto'] = $fotoFinal;

        // Catat ke log aktivitas sistem
        catatLog(
            $koneksi,
            'siswa',
            $anggotaId,
            $namaLengkap,
            'update_profil',
            'profil',
            'Siswa memperbarui informasi profil akun' . ($ubahPassword ? ' dan mengganti password' : '')
        );

        header('Location: profil.php?success=update');
        exit;
    } catch (PDOException $e) {
        header('Location: profil.php?error=sistem');
        exit;
    }
}

// 3. Ambil data profil terbaru dari database
$stmt = $koneksi->prepare("SELECT * FROM anggota WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $anggotaId]);
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profil) {
    header('Location: ../logout.php');
    exit;
}

// URL foto profil untuk ditampilkan (null jika belum pasang foto)
$urlFotoProfil = !empty($profil['foto']) ? fotoProfilUrl($profil['foto'], '../uploads/profil/') : null;

// 4. Hitung ringkasan transaksi siswa ini untuk badge profil
$totalPinjam = (int) $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE anggota_id = {$anggotaId}")->fetchColumn();
$aktifPinjam = (int) $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE anggota_id = {$anggotaId} AND status = 'dipinjam'")->fetchColumn();

// Parsing pesan notifikasi dari URL query
$errCode = $_GET['error'] ?? '';
$sucCode = $_GET['success'] ?? '';
$errMsg  = $_GET['msg'] ?? '';

if ($sucCode === 'update') {
    $pesanSukses = 'Data profil dan foto akun kamu berhasil disimpan dan diperbarui.';
}
if ($errCode === 'nama_kosong') {
    $pesanError = 'Nama lengkap tidak boleh dibiarkan kosong.';
} elseif ($errCode === 'pass_pendek') {
    $pesanError = 'Password baru minimal harus terdiri dari 6 karakter.';
} elseif ($errCode === 'pass_beda') {
    $pesanError = 'Konfirmasi password tidak cocok dengan password baru.';
} elseif ($errCode === 'upload_gagal') {
    $pesanError = !empty($errMsg) ? htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') : 'Gagal mengunggah foto profil.';
} elseif ($errCode === 'sistem') {
    $pesanError = 'Terjadi kendala pada sistem saat menyimpan data.';
}

$pageTitle   = 'Profil Saya';
$currentPage = 'profil';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="page-header-wrap" style="margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Profil Anggota Siswa
        </h1>
        <p class="page-subtitle" style="font-size: 14.5px; color: var(--sw-text-muted); margin: 0;">
            Kelola data identitas akun, foto profil, nomor WhatsApp, alamat domisili, dan kata sandi login.
        </p>
    </div>
</div>

<!-- =========================================================================
     1. NOTIFIKASI SUKSES / ERROR
     ========================================================================= -->
<?php if (!empty($pesanSukses)): ?>
    <div style="background: var(--sw-success-soft); border: 1px solid #BFE3D0; border-radius: var(--sw-radius-md); padding: 14px 18px; margin-bottom: 22px; color: var(--sw-success); display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <div><?= htmlspecialchars($pesanSukses, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($pesanError)): ?>
    <div style="background: var(--sw-danger-soft); border: 1px solid #F8B4B4; border-radius: var(--sw-radius-md); padding: 14px 18px; margin-bottom: 22px; color: var(--sw-danger); display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div><?= htmlspecialchars($pesanError, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     2. FORMULIR LENGKAP PROFIL (MENGGABUNGKAN UPLOAD FOTO & DATA PRIBADI)
     ========================================================================= -->
<form action="profil.php" method="POST" enctype="multipart/form-data" autocomplete="off">
    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">
        
        <!-- KOLOM KIRI: KARTU IDENTITAS & FOTO PROFIL -->
        <div style="background: #FFFFFF; border: 1px solid var(--sw-border); border-radius: var(--sw-radius-lg); padding: 26px; box-shadow: var(--sw-shadow-sm); text-align: center;">
            
            <!-- Lingkaran Avatar / Foto Profil -->
            <div id="swAvatarPreview" style="width: 86px; height: 86px; border-radius: 50%; background: linear-gradient(135deg, #157A50 0%, #23A06B 100%); color: #FFFFFF; font-size: 32px; font-weight: 800; display: grid; place-items: center; margin: 0 auto 14px; box-shadow: 0 10px 22px rgba(21, 122, 80, 0.28); overflow: hidden; position: relative;">
                <?php if ($urlFotoProfil): ?>
                    <img src="<?= htmlspecialchars($urlFotoProfil, ENT_QUOTES, 'UTF-8') ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span id="swAvatarInitial"><?= strtoupper(substr(trim($profil['nama_lengkap']), 0, 1)) ?: 'S' ?></span>
                <?php endif; ?>
            </div>

            <!-- Tombol Pilih Foto Baru -->
            <div style="margin-bottom: 16px;">
                <label for="inputFotoSiswa" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 15px; border-radius: 99px; background: var(--sw-primary-soft); color: var(--sw-primary-dark); font-size: 12.5px; font-weight: 700; cursor: pointer; border: 1px solid rgba(21, 122, 80, 0.2); transition: all 0.2s ease;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Ganti Foto</span>
                </label>
                <!-- Input file disembunyikan agar tampilan lebih rapi dan dikontrol oleh tombol di atas -->
                <input type="file" 
                       id="inputFotoSiswa" 
                       name="foto" 
                       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                       style="display: none;">
                
                <small style="display: block; margin-top: 6px; font-size: 11.5px; color: var(--sw-text-subtle);">
                    Format JPG, PNG, WEBP &middot; Maks. 2 MB
                </small>

                <!-- Checkbox Hapus Foto jika user sudah memiliki foto profil -->
                <?php if (!empty($profil['foto'])): ?>
                    <label style="display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: var(--sw-danger); cursor: pointer;">
                        <input type="checkbox" name="hapus_foto" value="1" id="checkHapusFoto">
                        <span>Hapus foto saat ini</span>
                    </label>
                <?php endif; ?>
            </div>

            <h2 style="font-size: 18.5px; font-weight: 800; color: var(--sw-text); margin: 0 0 4px; line-height: 1.25;">
                <?= htmlspecialchars($profil['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p style="font-size: 13px; color: var(--sw-text-muted); margin: 0 0 14px;">
                Kelas <?= htmlspecialchars($profil['kelas'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <span style="display: inline-block; padding: 4px 12px; border-radius: 99px; background: var(--sw-primary-soft); color: var(--sw-primary-dark); font-size: 12px; font-weight: 700; margin-bottom: 18px;">
                Anggota Siswa Resmi
            </span>

            <!-- Ringkasan Spesifikasi Akun -->
            <div style="border-top: 1px solid var(--sw-border); padding-top: 16px; text-align: left; display: flex; flex-direction: column; gap: 11px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Nomor Induk (NIS)</span>
                    <span style="font-weight: 700; color: var(--sw-text); font-family: var(--sw-mono);"><?= htmlspecialchars($profil['nis'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Username</span>
                    <span style="font-weight: 600; color: var(--sw-text);">@<?= htmlspecialchars($profil['username'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Kartu RFID</span>
                    <span>
                        <?php if (!empty($profil['kode_rfid'])): ?>
                            <code style="background:var(--sw-primary-soft); color:var(--sw-primary-dark); padding:2px 6px; border-radius:4px; font-family:var(--sw-mono); font-size:11.5px;"><?= htmlspecialchars($profil['kode_rfid'], ENT_QUOTES, 'UTF-8') ?></code>
                        <?php else: ?>
                            <span style="color:#C23B3B; font-size:12px;">Belum Ditautkan</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Total Peminjaman</span>
                    <span style="font-weight: 700; color: var(--sw-primary);"><?= $totalPinjam ?> buku</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Sedang Dipinjam</span>
                    <span style="font-weight: 700; color: <?= $aktifPinjam > 0 ? 'var(--sw-warning)' : 'var(--sw-success)' ?>;"><?= $aktifPinjam ?> buku</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--sw-text-muted);">Terdaftar Sejak</span>
                    <span style="color: var(--sw-text-muted); font-size: 12px;"><?= date('d M Y', strtotime($profil['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: FORMULIR DATA PRIBADI & PASSWORD -->
        <div style="background: #FFFFFF; border: 1px solid var(--sw-border); border-radius: var(--sw-radius-lg); padding: 28px; box-shadow: var(--sw-shadow-sm);">
            
            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--sw-text); margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--sw-border);">
                1. Data Pribadi Siswa
            </h3>

            <!-- Baris 1: NIS & Kelas (Terkunci oleh pihak sekolah) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        Nomor Induk Siswa (NIS)
                    </label>
                    <input type="text" 
                           value="<?= htmlspecialchars($profil['nis'], ENT_QUOTES, 'UTF-8') ?>" 
                           disabled 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #F4F7F5; color: var(--sw-text-subtle); cursor: not-allowed; font-family: var(--sw-mono);">
                    <small style="display: block; margin-top: 4px; font-size: 11.5px; color: var(--sw-text-subtle);">Data resmi terikat dengan data sekolah.</small>
                </div>

                <div>
                    <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        Kelas Siswa
                    </label>
                    <input type="text" 
                           value="<?= htmlspecialchars($profil['kelas'], ENT_QUOTES, 'UTF-8') ?>" 
                           disabled 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #F4F7F5; color: var(--sw-text-subtle); cursor: not-allowed;">
                    <small style="display: block; margin-top: 4px; font-size: 11.5px; color: var(--sw-text-subtle);">Hubungi petugas perpustakaan jika pindah kelas.</small>
                </div>
            </div>

            <!-- Baris 2: Nama Lengkap -->
            <div style="margin-bottom: 16px;">
                <label for="nama_lengkap" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                    Nama Lengkap <span style="color:#C23B3B;">*</span>
                </label>
                <input type="text" 
                       id="nama_lengkap" 
                       name="nama_lengkap" 
                       required 
                       value="<?= htmlspecialchars($profil['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>" 
                       style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #FAFDFB; font-family: inherit; font-size: 14.5px; color: var(--sw-text);">
            </div>

            <!-- Baris 3: Username & Nomor HP -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        Username Login
                    </label>
                    <input type="text" 
                           value="<?= htmlspecialchars($profil['username'], ENT_QUOTES, 'UTF-8') ?>" 
                           disabled 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #F4F7F5; color: var(--sw-text-subtle); cursor: not-allowed;">
                </div>

                <div>
                    <label for="no_hp" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        No. WhatsApp / HP
                    </label>
                    <input type="text" 
                           id="no_hp" 
                           name="no_hp" 
                           placeholder="Contoh: 08123456789" 
                           value="<?= htmlspecialchars($profil['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #FAFDFB; font-family: inherit; font-size: 14.5px; color: var(--sw-text);">
                </div>
            </div>

            <!-- Baris 4: Alamat Domisili -->
            <div style="margin-bottom: 24px;">
                <label for="alamat" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                    Alamat Lengkap Tempat Tinggal
                </label>
                <textarea id="alamat" 
                          name="alamat" 
                          rows="3" 
                          placeholder="Masukkan alamat domisili atau tempat tinggal siswa..." 
                          style="width: 100%; padding: 10px 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #FAFDFB; font-family: inherit; font-size: 14.5px; color: var(--sw-text); line-height: 1.45;"><?= htmlspecialchars($profil['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Section 2: Ganti Password (Opsional) -->
            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--sw-text); margin: 0 0 14px; padding-bottom: 12px; border-bottom: 1px solid var(--sw-border);">
                2. Keamanan &amp; Kata Sandi
            </h3>
            <p style="font-size: 13px; color: var(--sw-text-muted); margin: 0 0 16px;">
                Kosongkan kedua kolom di bawah ini jika kamu tidak ingin mengganti password login akun perpustakaan.
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 26px;">
                <div>
                    <label for="password_baru" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        Password Baru
                    </label>
                    <input type="password" 
                           id="password_baru" 
                           name="password_baru" 
                           minlength="6" 
                           placeholder="Minimal 6 karakter" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #FAFDFB; font-family: inherit; font-size: 14px; color: var(--sw-text);">
                </div>

                <div>
                    <label for="password_konfirmasi" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--sw-text); margin-bottom: 6px;">
                        Ulangi Password Baru
                    </label>
                    <input type="password" 
                           id="password_konfirmasi" 
                           name="password_konfirmasi" 
                           minlength="6" 
                           placeholder="Ketik ulang password..." 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--sw-border); border-radius: var(--sw-radius-sm); background: #FAFDFB; font-family: inherit; font-size: 14px; color: var(--sw-text);">
                </div>
            </div>

            <!-- Tombol Simpan -->
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="submit" class="btn btn-primary" style="padding: 11px 26px; font-size: 14.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Simpan Perubahan Profil</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// ============================================================================
// PREVIEW LANGSUNG FOTO PROFIL SAAT FILE DIPILIH
// ============================================================================
// Membaca file gambar dari komputer pengguna menggunakan FileReader API bawaan browser
// sehingga siswa bisa langsung melihat tampilan fotonya sebelum mengklik tombol simpan.
const inputFotoSiswa  = document.getElementById('inputFotoSiswa');
const swAvatarPreview = document.getElementById('swAvatarPreview');
const checkHapusFoto  = document.getElementById('checkHapusFoto');

if (inputFotoSiswa && swAvatarPreview) {
    inputFotoSiswa.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (file) {
            // Validasi sederhana tipe file di sisi browser
            if (!file.type.match('image.*')) {
                alert('Silakan pilih file gambar yang valid (JPG, PNG, atau WEBP).');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                // Tampilkan foto di dalam lingkaran avatar
                swAvatarPreview.innerHTML = '<img src="' + e.target.result + '" alt="Preview Foto" style="width: 100%; height: 100%; object-fit: cover;">';
                // Jika sebelumnya centang hapus foto aktif, otomatis batalkan karena user memilih foto baru
                if (checkHapusFoto) {
                    checkHapusFoto.checked = false;
                }
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>

<?php require 'includes/footer.php'; ?>
