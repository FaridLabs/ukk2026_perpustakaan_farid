<?php
/**
 * ============================================================================
 * PROSES PENDAFTARAN SISWA BARU VIA GOOGLE SIGN-IN
 * ============================================================================
 * Menyimpan identitas siswa (NIS, Kelas, Nama yang sudah disesuaikan) ke database.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Pastikan data temporer Google tersedia
if (empty($_SESSION['google_temp']) || empty($_SESSION['google_temp']['google_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/log_helper.php';

$google      = $_SESSION['google_temp'];
$googleId    = (string)$google['google_id'];
$googleEmail = strtolower(trim((string)$google['email']));
$googleFoto  = trim((string)($google['foto'] ?? ''));

// Ambil input form siswa
$namaLengkap = trim($_POST['nama_lengkap'] ?? '');
$nis         = trim($_POST['nis'] ?? '');
$kelas       = trim($_POST['kelas'] ?? '');
$noHp        = trim($_POST['no_hp'] ?? '');
$alamat      = trim($_POST['alamat'] ?? '');
$username    = trim($_POST['username'] ?? '');
$passwordRaw = trim($_POST['password'] ?? '');

// 1. Validasi field wajib
if (empty($namaLengkap) || empty($nis) || empty($kelas) || empty($username)) {
    header('Location: ../lengkapi_profil_google.php?error=empty');
    exit;
}

try {
    // 2. Cek apakah NIS sudah dipakai siswa lain
    $stmtCekNis = $koneksi->prepare("SELECT COUNT(*) FROM anggota WHERE nis = :nis");
    $stmtCekNis->execute([':nis' => $nis]);
    if ($stmtCekNis->fetchColumn() > 0) {
        header('Location: ../lengkapi_profil_google.php?error=nis_ada');
        exit;
    }

    // 3. Cek apakah Username sudah dipakai
    $stmtCekUser = $koneksi->prepare("SELECT COUNT(*) FROM anggota WHERE username = :u");
    $stmtCekUser->execute([':u' => $username]);
    if ($stmtCekUser->fetchColumn() > 0) {
        header('Location: ../lengkapi_profil_google.php?error=username_ada');
        exit;
    }

    // 4. Unduh & simpan avatar Google ke lokal (jika ada)
    $savedFotoName = null;
    if (!empty($googleFoto)) {
        $ch = curl_init($googleFoto);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $imgData) {
            $destDir = __DIR__ . '/../uploads/profil/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $fileName = 'siswa_g_' . bin2hex(random_bytes(6)) . '.jpg';
            if (file_put_contents($destDir . $fileName, $imgData)) {
                $savedFotoName = $fileName;
            }
        }
    }

    // 5. Password cadangan (jika kosong, default adalah NIS siswa)
    $passwordFinal = !empty($passwordRaw) ? $passwordRaw : $nis;
    $passwordHash  = password_hash($passwordFinal, PASSWORD_BCRYPT);

    // 6. Simpan ke tabel anggota
    $stmtInsert = $koneksi->prepare("
        INSERT INTO anggota (nis, nama_lengkap, kelas, email, no_hp, alamat, foto, username, password, google_id)
        VALUES (:nis, :nama, :kelas, :email, :no_hp, :alamat, :foto, :username, :password, :google_id)
    ");
    $stmtInsert->execute([
        ':nis'       => $nis,
        ':nama'      => $namaLengkap,
        ':kelas'     => $kelas,
        ':email'     => $googleEmail,
        ':no_hp'     => !empty($noHp) ? $noHp : null,
        ':alamat'    => !empty($alamat) ? $alamat : null,
        ':foto'      => $savedFotoName,
        ':username'  => $username,
        ':password'  => $passwordHash,
        ':google_id' => $googleId
    ]);

    $newSiswaId = (int)$koneksi->lastInsertId();

    // 7. Catat aktivitas pendaftaran
    catatLog(
        $koneksi,
        'siswa',
        $newSiswaId,
        $namaLengkap,
        'Daftar Google',
        'Autentikasi',
        "Siswa baru {$namaLengkap} (NIS: {$nis}) mendaftar menggunakan akun Google ({$googleEmail})."
    );

    // 8. Langsung login siswa ke sesi
    $_SESSION['anggota_id']      = $newSiswaId;
    $_SESSION['anggota_nama']    = $namaLengkap;
    $_SESSION['anggota_kelas']   = $kelas;
    $_SESSION['anggota_foto']    = $savedFotoName;
    $_SESSION['siswa_logged_in'] = true;
    $_SESSION['siswa_id']        = $newSiswaId;
    $_SESSION['siswa_nis']       = $nis;
    $_SESSION['siswa_username']  = $username;
    $_SESSION['siswa_nama']      = $namaLengkap;
    $_SESSION['siswa_kelas']     = $kelas;
    $_SESSION['siswa_foto']      = $savedFotoName;
    $_SESSION['role']            = 'siswa';

    // Bersihkan sesi temporer
    unset($_SESSION['google_temp']);

    // Arahkan ke dashboard siswa
    header('Location: ../siswa/dashboard.php?welcome=1');
    exit;

} catch (Exception $e) {
    header('Location: ../lengkapi_profil_google.php?error=system');
    exit;
}
