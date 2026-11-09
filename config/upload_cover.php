<?php
// =========================================
// config/upload_cover.php
// Helper cover buku: simpan, hapus, dan ambil URL
// Folder penyimpanan: <root project>/uploads/covers/
// =========================================

const COVER_DIR       = __DIR__ . '/../uploads/covers/';
const COVER_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const COVER_MAX_WIDTH = 600;             // piksel, gambar lebih lebar akan diperkecil (jika GD aktif)

/**
 * Simpan cover dari $_FILES['cover'].
 *
 * Return:
 *  ['ok' => true,  'file' => null]            -> tidak ada file baru diunggah
 *  ['ok' => true,  'file' => 'cover_xxx.jpg'] -> berhasil, simpan nama ini ke kolom buku.cover
 *  ['ok' => false, 'error' => 'pesan']        -> gagal
 *
 * Jika $coverLama diisi dan upload baru berhasil, file lama otomatis dihapus.
 */
function simpanCover(?array $file, ?string $coverLama = null): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload cover gagal (kode ' . (int)$file['error'] . ').'];
    }
    if ($file['size'] > COVER_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Ukuran cover maksimal 2 MB.'];
    }

    // Cek tipe file dari isinya, bukan dari nama/ekstensi yang dikirim browser
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ekstensi[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'Format cover harus JPG, PNG, atau WEBP.'];
    }

    if (!is_dir(COVER_DIR) && !mkdir(COVER_DIR, 0755, true)) {
        return ['ok' => false, 'error' => 'Folder uploads/covers tidak bisa dibuat.'];
    }

    $nama   = 'cover_' . bin2hex(random_bytes(8)) . '.' . $ekstensi[$mime];
    $tujuan = COVER_DIR . $nama;
    $tersimpan = false;

    // Perkecil gambar besar supaya halaman katalog tetap ringan (butuh ekstensi GD)
    if (extension_loaded('gd')) {
        $tersimpan = perkecilCover($file['tmp_name'], $tujuan, $mime);
    }
    if (!$tersimpan) {
        $tersimpan = move_uploaded_file($file['tmp_name'], $tujuan);
    }
    if (!$tersimpan) {
        return ['ok' => false, 'error' => 'Cover gagal disimpan ke server.'];
    }

    if (!empty($coverLama)) {
        hapusCover($coverLama);
    }
    return ['ok' => true, 'file' => $nama];
}

function perkecilCover(string $sumber, string $tujuan, string $mime): bool
{
    $info = @getimagesize($sumber);
    if (!$info || $info[0] <= COVER_MAX_WIDTH) {
        return false; // tidak perlu diperkecil, pakai move_uploaded_file
    }

    $img = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $img = @imagecreatefromjpeg($sumber);
    if ($mime === 'image/png'  && function_exists('imagecreatefrompng'))  $img = @imagecreatefrompng($sumber);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($sumber);
    if (!$img) return false;

    $kecil = imagescale($img, COVER_MAX_WIDTH);
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

/** Hapus file cover dari server (aman: hanya nama file, tanpa path). */
function hapusCover(?string $nama): void
{
    if (empty($nama)) return;
    $path = COVER_DIR . basename($nama);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * URL cover untuk ditampilkan di HTML, atau null jika belum ada / file hilang.
 * $prefix = path dari halaman pemanggil ke folder covers:
 *   admin & siswa : '../uploads/covers/'
 *   index.php     : 'uploads/covers/'
 */
function coverUrl(?string $nama, string $prefix): ?string
{
    if (empty($nama)) return null;
    $nama = basename($nama);
    return is_file(COVER_DIR . $nama) ? $prefix . rawurlencode($nama) : null;
}