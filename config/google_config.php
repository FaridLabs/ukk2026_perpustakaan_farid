<?php
/**
 * ============================================================================
 * KONFIGURASI GOOGLE OAUTH 2.0 (SIGN-IN DENGAN GOOGLE)
 * ============================================================================
 * Kredensial resmi Google Cloud Console untuk autentikasi siswa.
 */

// Memuat kredensial lokal jika ada (disimpan di luar repositori publik)
if (file_exists(__DIR__ . '/google_keys.local.php')) {
    require_once __DIR__ . '/google_keys.local.php';
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}

// Endpoint OAuth Google
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Mendapatkan URL redirect callback yang dinamis
 */
function getGoogleRedirectUri(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Default URI pada lingkungan lokal
    return $protocol . $host . '/ukk2026_perpustakaan_farid/config/google_callback.php';
}

/**
 * Membuat tautan login Google dengan perlindungan token CSRF
 */
function getGoogleAuthUrl(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Token CSRF untuk keamanan autentikasi
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id'             => GOOGLE_CLIENT_ID,
        'redirect_uri'          => getGoogleRedirectUri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'access_type'           => 'online',
        'prompt'                => 'select_account'
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Menukar authorization code dengan Access Token via cURL ke Google
 */
function exchangeCodeForGoogleToken(string $code): ?array
{
    $postData = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => getGoogleRedirectUri(),
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // Aman untuk lingkungan XAMPP lokal
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * Mengambil profil data diri pengguna dari Google menggunakan Access Token
 */
function getGoogleUserProfile(string $accessToken): ?array
{
    $ch = curl_init(GOOGLE_USERINFO_URL);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}
