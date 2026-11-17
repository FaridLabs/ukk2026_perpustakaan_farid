<?php
/**
 * ============================================================================
 * CALLBACK HANDLER LOGIN GOOGLE OAUTH 2.0
 * ============================================================================
 * Menangkap respon resmi dari Google, memverifikasi akun siswa, dan mengarahkan
 * ke dashboard (jika sudah terdaftar) atau ke form pelengkap data diri (jika baru).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/google_config.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/log_helper.php';

// 1. Tangani jika user membatalkan login di halaman Google
if (!empty($_GET['error'])) {
    header('Location: ../login.php?error=google_batal');
    exit;
}

// 2. Verifikasi parameter code dan proteksi token CSRF State
$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || empty($state) || empty($_SESSION['google_oauth_state']) || $state !== $_SESSION['google_oauth_state']) {
    header('Location: ../login.php?error=google_state');
    exit;
}

// Hapus state setelah divalidasi agar tidak bisa dipakai ulang
unset($_SESSION['google_oauth_state']);

// 3. Tukar kode otorisasi dengan Access Token ke server Google
$tokenData = exchangeCodeForGoogleToken($code);
if (!$tokenData || empty($tokenData['access_token'])) {
    header('Location: ../login.php?error=google_token');
    exit;
}

// 4. Ambil profil pengguna dari Google API
$profile = getGoogleUserProfile($tokenData['access_token']);
if (!$profile || empty($profile['sub']) || empty($profile['email'])) {
    header('Location: ../login.php?error=google_profile');
    exit;
}

$googleId = (string)$profile['sub'];
$email    = strtolower(trim((string)$profile['email']));
$name     = trim((string)($profile['name'] ?? ''));
$picture  = trim((string)($profile['picture'] ?? ''));

try {
    // 5. Cek apakah google_id ini sudah terhubung ke data anggota siswa
    $stmtGid = $koneksi->prepare("SELECT * FROM anggota WHERE google_id = :gid LIMIT 1");
    $stmtGid->execute([':gid' => $googleId]);
    $siswa = $stmtGid->fetch(PDO::FETCH_ASSOC);

    // 6. Jika belum ada google_id, cek apakah email siswa sudah pernah didaftarkan
    if (!$siswa && !empty($email)) {
        $stmtMail = $koneksi->prepare("SELECT * FROM anggota WHERE email = :email LIMIT 1");
        $stmtMail->execute([':email' => $email]);
        $siswa = $stmtMail->fetch(PDO::FETCH_ASSOC);

        // Jika email cocok, langsung tautkan google_id ke akun siswa yang sudah ada
        if ($siswa) {
            $stmtUp = $koneksi->prepare("UPDATE anggota SET google_id = :gid WHERE id = :id");
            $stmtUp->execute([':gid' => $googleId, ':id' => $siswa['id']]);

            catatLog(
                $koneksi,
                'siswa',
                (int)$siswa['id'],
                $siswa['nama_lengkap'],
                'Tautkan Google',
                'Autentikasi',
                "Menautkan akun Google ({$email}) ke profil siswa {$siswa['nama_lengkap']}."
            );
        }
    }

    // KONDISI A: Siswa sudah terdaftar dan siap login
    if ($siswa) {
        // Set sesi login siswa
        $_SESSION['anggota_id']      = (int)$siswa['id'];
        $_SESSION['anggota_nama']    = $siswa['nama_lengkap'];
        $_SESSION['anggota_kelas']   = $siswa['kelas'];
        $_SESSION['anggota_foto']    = $siswa['foto'] ?? null;
        $_SESSION['siswa_logged_in'] = true;
        $_SESSION['siswa_id']        = (int)$siswa['id'];
        $_SESSION['siswa_nis']       = $siswa['nis'];
        $_SESSION['siswa_username']  = $siswa['username'];
        $_SESSION['siswa_nama']      = $siswa['nama_lengkap'];
        $_SESSION['siswa_kelas']     = $siswa['kelas'];
        $_SESSION['siswa_foto']      = $siswa['foto'] ?? null;
        $_SESSION['role']            = 'siswa';

        catatLog(
            $koneksi,
            'siswa',
            (int)$siswa['id'],
            $siswa['nama_lengkap'],
            'Login Google',
            'Autentikasi',
            "Siswa {$siswa['nama_lengkap']} berhasil login menggunakan akun Google ({$email})."
        );

        header('Location: ../siswa/dashboard.php');
        exit;
    }

    // KONDISI B: Siswa baru pertama kali masuk via Google
    // Simpan data Google ke sesi sementara untuk dilengkapi pada form pendaftaran
    $_SESSION['google_temp'] = [
        'google_id' => $googleId,
        'email'     => $email,
        'nama'      => $name,
        'foto'      => $picture,
    ];

    header('Location: ../lengkapi_profil_google.php');
    exit;

} catch (Exception $e) {
    header('Location: ../login.php?error=system');
    exit;
}
