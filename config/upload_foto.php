<?php
// ============================================================================
// config/upload_foto.php
// Helper untuk mengelola upload, penghapusan, dan URL foto profil (Siswa & Admin)
// Folder tujuan: <root project>/uploads/profil/
// ============================================================================

// Lokasi penyimpanan foto profil di server
const PROFIL_DIR       = __DIR__ . '/../uploads/profil/';
// Batas maksimal ukuran file (2 MB)
const PROFIL_MAX_BYTES = 2 * 1024 * 1024;
// Lebar maksimal gambar (400 piksel cukup untuk foto avatar bulat yang tajam & ringan)
const PROFIL_MAX_WIDTH = 400;

/**
 * Memproses file upload dari form HTML ($_FILES['foto']).
 * 
 * @param array|null  $file       Data file dari $_FILES['foto']
 * @param string|null $fotoLama   Nama file foto lama di database (jika ada, akan dihapus)
 * @param string      $jenisUser  Prefix nama file ('siswa' atau 'admin')
 * 
 * @return array ['ok' => bool, 'file' => string|null, 'error' => string|null]
 */
function simpanFotoProfil(?array $file, ?string $fotoLama = null, string $jenisUser = 'user'): array
{
    // 1. Jika user tidak memilih file baru, lewati proses upload tanpa error
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => null];
    }

    // 2. Cek apakah ada error bawaan dari PHP saat transfer file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Gagal mengunggah foto (kode error: ' . (int)$file['error'] . ').'];
    }

    // 3. Batasi ukuran file maksimal 2 MB
    if ($file['size'] > PROFIL_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Ukuran foto maksimal 2 MB. Silakan pilih foto yang lebih kecil.'];
    }

    // 4. Validasi format file berdasarkan MIME Type asli (bukan hanya ekstensi nama)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    $ekstensi = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($ekstensi[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'Format foto harus berupa JPG, PNG, atau WEBP.'];
    }

    // 5. Pastikan folder uploads/profil sudah ada
    if (!is_dir(PROFIL_DIR) && !mkdir(PROFIL_DIR, 0755, true)) {
        return ['ok' => false, 'error' => 'Folder penyimpanan foto profil tidak dapat dibuat di server.'];
    }

    // 6. Buat nama file acak yang unik agar tidak menimpa foto user lain
    $namaBaru = $jenisUser . '_' . bin2hex(random_bytes(6)) . '.' . $ekstensi[$mime];
    $tujuan   = PROFIL_DIR . $namaBaru;
    $sukses   = false;

    // 7. Jika ekstensi GD aktif di PHP, perkecil resolusi agar loading web tetap cepat
    if (extension_loaded('gd')) {
        $sukses = perkecilFotoProfil($file['tmp_name'], $tujuan, $mime);
    }

    // 8. Jika GD tidak tersedia atau resize tidak diperlukan, simpan file
    if (!$sukses) {
        if (is_uploaded_file($file['tmp_name'])) {
            $sukses = move_uploaded_file($file['tmp_name'], $tujuan);
        } else {
            $sukses = copy($file['tmp_name'], $tujuan);
        }
    }

    if (!$sukses) {
        return ['ok' => false, 'error' => 'Foto profil gagal disimpan ke folder server.'];
    }

    // 9. Jika user sebelumnya sudah punya foto dan upload baru berhasil, hapus foto lamanya
    if (!empty($fotoLama)) {
        hapusFotoProfil($fotoLama);
    }

    return ['ok' => true, 'file' => $namaBaru];
}

/**
 * Mengecilkan resolusi foto profil jika lebarnya melebihi batas PROFIL_MAX_WIDTH.
 */
function perkecilFotoProfil(string $sumber, string $tujuan, string $mime): bool
{
    $info = @getimagesize($sumber);
    if (!$info || $info[0] <= PROFIL_MAX_WIDTH) {
        return false; // Foto sudah cukup kecil, gunakan move_uploaded_file standar
    }

    $img = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $img = @imagecreatefromjpeg($sumber);
    if ($mime === 'image/png'  && function_exists('imagecreatefrompng'))  $img = @imagecreatefrompng($sumber);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($sumber);
    if (!$img) return false;

    // Hitung proporsi baru menggunakan fungsi bawaan PHP
    $kecil = imagescale($img, PROFIL_MAX_WIDTH);
    imagedestroy($img);
    if (!$kecil) return false;

    $ok = false;
    if ($mime === 'image/jpeg') {
        $ok = imagejpeg($kecil, $tujuan, 85);
    } elseif ($mime === 'image/png') {
        imagesavealpha($kecil, true);
        $ok = imagepng($kecil, $tujuan, 6);
    } elseif (function_exists('imagewebp')) {
        $ok = imagewebp($kecil, $tujuan, 85);
    }

    imagedestroy($kecil);
    return (bool)$ok;
}

/**
 * Menghapus file foto profil dari folder server jika ada.
 * Menggunakan basename() untuk keamanan agar tidak bisa menghapus file di luar folder.
 */
function hapusFotoProfil(?string $nama): void
{
    if (empty($nama)) return;
    $path = PROFIL_DIR . basename($nama);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Menghasilkan URL gambar foto profil yang siap dipakai pada tag <img src="...">.
 * Mengembalikan null jika foto kosong atau file fisiknya tidak ditemukan di server.
 * 
 * @param string|null $nama   Nama file di database (misal: siswa_a1b2c3.jpg)
 * @param string      $prefix Path relatif dari halaman pemanggil (misal: '../uploads/profil/')
 */
function fotoProfilUrl(?string $nama, string $prefix = '../uploads/profil/'): ?string
{
    if (empty($nama)) return null;
    $nama = basename($nama);
    return is_file(PROFIL_DIR . $nama) ? $prefix . rawurlencode($nama) : null;
}
