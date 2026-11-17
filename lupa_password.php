<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/pengaturan_helper.php';
$appConfig  = ambilPengaturan($koneksi);
$appNama    = $appConfig['nama_aplikasi'] ?? 'Pinjam.In';
$appSlogan  = $appConfig['slogan_aplikasi'] ?? 'Perpustakaan Digital';
$appSekolah = $appConfig['nama_sekolah'] ?? 'SMKN 1 Panyingkiran';

$infoSiswa = null;
$cariKeyword = trim($_POST['keyword'] ?? '');
$statusPesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cariKeyword)) {
    $stmt = $koneksi->prepare("
        SELECT id, nis, nama_lengkap, kelas, email, google_id 
        FROM anggota 
        WHERE nis = :kw OR username = :kw OR email = :kw
        LIMIT 1
    ");
    $stmt->execute([':kw' => $cariKeyword]);
    $infoSiswa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$infoSiswa) {
        $statusPesan = 'not_found';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bantuan Lupa Password - <?= htmlspecialchars($appNama) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--g:#217350;--g-dark:#1A5C41;--g-text:#1F7A4D;--g-soft:#E7F3EB;--ink:#14232B;--muted:#5F6D73;--line:#D5DCDE;--bad:#A8322B;
--sans:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:var(--sans);color:var(--ink);background:#F7FAF8;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:32px 16px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:24px}
.brand svg{width:42px;height:36px}
.brand-name{font-size:24px;font-weight:700;letter-spacing:-.02em}
.brand-name b{color:var(--g-text)}

.card{width:100%;max-width:540px;background:#fff;border-radius:20px;padding:32px 36px;box-shadow:0 18px 45px -20px rgba(20,35,43,.15),0 2px 8px rgba(20,35,43,.04)}
.card-head{text-align:center;margin-bottom:24px}
.card-title{font-size:22px;font-weight:700;letter-spacing:-.015em;margin:0 0 6px}
.card-sub{color:var(--muted);font-size:14px;margin:0;line-height:1.5}

.field label{display:block;margin-bottom:7px;font-size:14px;font-weight:600}
.field input{width:100%;height:46px;padding:0 14px;border:1.5px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:14.5px;color:var(--ink);transition:border-color .15s}
.field input:focus{outline:none;border-color:var(--g);box-shadow:0 0 0 3px rgba(33,115,80,.12)}

.submit{margin-top:16px;width:100%;height:46px;border:0;border-radius:10px;background:var(--g);color:#fff;font:inherit;font-size:15px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:background .15s}
.submit:hover{background:var(--g-dark)}

.alert{margin-top:18px;padding:12px 14px;border-radius:10px;font-size:13.5px;line-height:1.5}
.alert-error{background:#FBE9E7;color:var(--bad);border:1px solid #F1C7C2}
.alert-info{background:#E8F1FC;color:#1B5FA8;border:1px solid #C4DAF8}

.box-result{margin-top:20px;background:#F8FAF9;border:1px solid #DCE6E1;border-radius:12px;padding:16px}
.box-result h3{margin:0 0 8px;font-size:15px;color:var(--g-dark)}
.box-result p{margin:0 0 12px;font-size:13.5px;color:#475569;line-height:1.5}

.btn-g{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:44px;border:1.5px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:14px;font-weight:600;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.btn-g:hover{background:#F8FAFC}

.divider{margin:24px 0 18px;border-top:1px solid #E6EBEC}
.card-foot{text-align:center;font-size:14px;color:var(--muted);margin:0}
.card-foot a{color:var(--g-text);font-weight:600;text-decoration:none}
.card-foot a:hover{text-decoration:underline}
</style>
</head>
<body>

<a href="login.php" class="brand">
  <svg viewBox="0 0 48 40" aria-hidden="true">
    <path d="M24 15C23 6 29 2 37 3C37 11 32 16 24 15Z" fill="#2E9A67"/>
    <path d="M3 13Q3 11 5.2 11.2Q14 12 24 17.5V36Q14 31 5.2 31Q3 31 3 29Z" fill="#1F6E47"/>
    <path d="M45 13Q45 11 42.8 11.2Q34 12 24 17.5V36Q34 31 42.8 31Q45 31 45 29Z" fill="#2A8A5A"/>
  </svg>
  <span class="brand-name"><?= htmlspecialchars($appNama) ?><b>.In</b></span>
</a>

<main class="card">
  <div class="card-head">
    <h1 class="card-title">Bantuan Pemulihan Akun</h1>
    <p class="card-sub">Masukkan NIS atau Username Anda untuk memeriksa opsi pemulihan akun.</p>
  </div>

  <form action="lupa_password.php" method="POST" autocomplete="off">
    <div class="field">
      <label for="keyword">NIS atau Username Terdaftar</label>
      <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($cariKeyword, ENT_QUOTES, 'UTF-8') ?>" required placeholder="Contoh: 24250223 atau bagas" autofocus>
    </div>
    <button type="submit" class="submit">Periksa Akun</button>
  </form>

  <?php if ($statusPesan === 'not_found'): ?>
    <div class="alert alert-error">Data siswa dengan NIS atau Username tersebut tidak ditemukan. Silakan periksa kembali ketikan Anda atau hubungi petugas perpustakaan.</div>
  <?php elseif ($infoSiswa): ?>
    <div class="box-result">
      <h3>Data Akun Ditemukan!</h3>
      <p>
        Nama: <strong><?= htmlspecialchars($infoSiswa['nama_lengkap']) ?></strong> (Kelas: <?= htmlspecialchars($infoSiswa['kelas']) ?>)<br>
        NIS: <strong><?= htmlspecialchars($infoSiswa['nis']) ?></strong>
      </p>

      <?php if (!empty($infoSiswa['google_id'])): ?>
        <div style="background:#EBF7F0; border:1px solid #BFE7CF; border-radius:8px; padding:12px; margin-bottom:12px; font-size:13px; color:#165E39;">
          <strong>Kabar Baik!</strong> Akun Anda sudah terhubung dengan Google (<em><?= htmlspecialchars($infoSiswa['email']) ?></em>). Anda dapat langsung login tanpa perlu mengingat password!
        </div>
        <a href="config/google_login.php" class="btn-g">
          <svg width="18" height="18" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.65v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.14z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
            <path fill="#FBBC05" d="M5.28 14.27a7.2 7.2 0 0 1 0-4.54V6.58H1.25a11.98 11.98 0 0 0 0 10.84l4.03-3.15z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
          </svg>
          <span>Masuk Langsung dengan Google</span>
        </a>
      <?php else: ?>
        <div style="background:#FFF8EE; border:1px solid #F8DFB4; border-radius:8px; padding:12px; font-size:13px; color:#87540D; line-height:1.5;">
          <strong>Reset Mandiri ke Petugas Perpustakaan:</strong><br>
          Silakan temui pustakawan di meja sirkulasi perpustakaan sekolah dengan membawa kartu pelajar Anda. Petugas dapat me-reset password akun Anda menjadi password default dalam hitungan detik.
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <p class="card-foot">Sudah ingat password? <a href="login.php">Kembali ke Halaman Login</a></p>
</main>

</body>
</html>
