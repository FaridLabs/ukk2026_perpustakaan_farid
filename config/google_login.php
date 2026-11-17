<?php
/**
 * ============================================================================
 * INISIASI REDIRECT LOGIN GOOGLE
 * ============================================================================
 * Mengarahkan siswa ke halaman autentikasi resmi Google.
 */

require_once __DIR__ . '/google_config.php';

$authUrl = getGoogleAuthUrl();
header('Location: ' . $authUrl);
exit;
