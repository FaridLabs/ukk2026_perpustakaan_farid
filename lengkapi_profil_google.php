<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan ada data temporer dari Google
if (empty($_SESSION['google_temp']) || empty($_SESSION['google_temp']['google_id'])) {
    header('Location: login.php');
    exit;
}

$google = $_SESSION['google_temp'];
$googleNama  = $google['nama'] ?? '';
$googleEmail = $google['email'] ?? '';
$googleFoto  = $google['foto'] ?? '';

// Auto-suggest username dari email (misal: "budi.santoso@gmail.com" -> "budi.santoso")
$suggestUsername = preg_replace('/[^a-zA-Z0-9._]/', '', strstr($googleEmail, '@', true) ?: $googleNama);
$suggestUsername = strtolower(substr($suggestUsername, 0, 30));

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/pengaturan_helper.php';
$appConfig  = ambilPengaturan($koneksi);
$appNama    = $appConfig['nama_aplikasi'] ?? 'Pinjam.In';
$appSlogan  = $appConfig['slogan_aplikasi'] ?? 'Perpustakaan Digital';
$appSekolah = $appConfig['nama_sekolah'] ?? 'SMKN 1 Panyingkiran';

$pesanError = [
    'empty'         => 'Nama lengkap, NIS, kelas, dan username wajib diisi.',
    'nis_ada'       => 'Nomor Induk Siswa (NIS) ini sudah terdaftar atas nama siswa lain.',
    'username_ada'  => 'Username telah digunakan, silakan pilih username lain.',
    'system'        => 'Terjadi kesalahan sistem saat menyimpan profil.',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="no-referrer">
<title>Lengkapi Profil Siswa - <?= htmlspecialchars($appNama) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--g:#217350;--g-dark:#1A5C41;--g-text:#1F7A4D;--g-soft:#E7F3EB;--ink:#14232B;--muted:#5F6D73;--line:#D5DCDE;--bad:#A8322B;
--sans:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:var(--sans);color:var(--ink);background:#F7FAF8;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:24px 16px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:24px}
.brand svg{width:42px;height:36px}
.brand-name{font-size:24px;font-weight:700;letter-spacing:-.02em}
.brand-name b{color:var(--g-text)}

.card{width:100%;max-width:620px;background:#fff;border-radius:20px;padding:32px 36px;box-shadow:0 18px 45px -20px rgba(20,35,43,.15),0 2px 8px rgba(20,35,43,.04)}
.card-head{text-align:center;margin-bottom:20px}
.card-title{font-size:24px;font-weight:700;letter-spacing:-.015em;margin:0 0 6px}
.card-sub{color:var(--muted);font-size:14.5px;margin:0}

/* Badge Google Account */
.g-pill{display:flex;align-items:center;gap:12px;background:#F1F8F4;border:1px solid #C8E6D5;border-radius:14px;padding:10px 14px;margin-bottom:22px}
.g-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.1)}
.g-info{min-width:0;flex:1}
.g-info b{display:block;font-size:14px;color:var(--g-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.g-info span{display:block;font-size:12.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.g-tag{background:var(--g);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:99px;white-space:nowrap}

.alert{margin-bottom:18px;padding:12px 14px;border-radius:10px;font-size:14px;font-weight:500;line-height:1.45;background:#FBE9E7;color:var(--bad);border:1px solid #F1C7C2}

.row{display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:16px}
.row.two{grid-template-columns:1fr 1fr}

.field label{display:block;margin-bottom:6px;font-size:13.5px;font-weight:600}
.field label em{color:var(--bad);font-style:normal;margin-left:2px}
.field input,.field textarea{width:100%;height:46px;padding:0 14px;border:1.5px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:14.5px;color:var(--ink);transition:border-color .15s,box-shadow .15s}
.field textarea{height:72px;padding:10px 14px;resize:vertical}
.field input:focus,.field textarea:focus{outline:none;border-color:var(--g);box-shadow:0 0 0 3px rgba(33,115,80,.12)}
.field small{display:block;margin-top:4px;font-size:12px;color:var(--muted)}

.submit{margin-top:24px;width:100%;height:48px;border:0;border-radius:10px;background:var(--g);color:#fff;font:inherit;font-size:16px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:10px;cursor:pointer;transition:background .15s}
.submit:hover{background:var(--g-dark)}

.cancel{display:block;text-align:center;margin-top:14px;font-size:13.5px;color:var(--muted);text-decoration:none}
.cancel:hover{color:var(--ink);text-decoration:underline}

@media(max-width:540px){
  .card{padding:24px 20px}
  .row.two{grid-template-columns:1fr}
}
</style>
</head>
<body>

<a href="index.php" class="brand">
  <svg viewBox="0 0 48 40" aria-hidden="true">
    <path d="M24 15C23 6 29 2 37 3C37 11 32 16 24 15Z" fill="#2E9A67"/>
    <path d="M3 13Q3 11 5.2 11.2Q14 12 24 17.5V36Q14 31 5.2 31Q3 31 3 29Z" fill="#1F6E47"/>
    <path d="M45 13Q45 11 42.8 11.2Q34 12 24 17.5V36Q34 31 42.8 31Q45 31 45 29Z" fill="#2A8A5A"/>
  </svg>
  <span class="brand-name"><?= htmlspecialchars($appNama) ?><b>.In</b></span>
</a>

<main class="card">
  <div class="card-head">
    <h1 class="card-title">Selesaikan Pendaftaran Akun</h1>
    <p class="card-sub">Periksa kesesuaian nama dan lengkapi data sekolah untuk kartu perpustakaan Anda.</p>
  </div>

  <!-- Identitas Google Terhubung -->
  <div class="g-pill">
    <?php if ($googleFoto): ?>
      <img src="<?= htmlspecialchars($googleFoto, ENT_QUOTES, 'UTF-8') ?>" 
           alt="Foto Google" 
           class="g-avatar" 
           referrerpolicy="no-referrer"
           onerror="this.style.display='none'; document.getElementById('avatarFallback').style.display='grid';">
      <div id="avatarFallback" class="g-avatar" style="display:none; background:#217350; color:#fff; place-items:center; font-weight:700;">
        <?= htmlspecialchars(strtoupper(substr($googleNama ?: 'S', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php else: ?>
      <div class="g-avatar" style="background:#217350; color:#fff; display:grid; place-items:center; font-weight:700;">
        <?= htmlspecialchars(strtoupper(substr($googleNama ?: 'S', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <div class="g-info">
      <b>Akun Google Terverifikasi</b>
      <span><?= htmlspecialchars($googleEmail, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <span class="g-tag">Terhubung</span>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div class="alert" role="alert">
      <?= htmlspecialchars($pesanError[$_GET['error']] ?? 'Periksa data input Anda lalu coba kembali.', ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form action="config/proses_google_register.php" method="POST" autocomplete="off">
    <!-- NAMA LENGKAP: Terisi otomatis dari Google tapi BISA DIUBAH jika nama Google adalah nama panggilan -->
    <div class="field" style="margin-bottom:16px;">
      <label for="nama_lengkap">Nama Lengkap (Sesuai Ijazah / Raport) <em>*</em></label>
      <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($googleNama, ENT_QUOTES, 'UTF-8') ?>" required placeholder="Masukkan nama lengkap resmi...">
      <small>Ubah nama di atas jika nama akun Google Anda belum sesuai nama lengkap resmi sekolah.</small>
    </div>

    <div class="row two">
      <div class="field">
        <label for="nis">NIS (Nomor Induk Siswa) <em>*</em></label>
        <input type="text" id="nis" name="nis" required placeholder="Contoh: 24250123" inputmode="numeric" autofocus>
      </div>
      <div class="field">
        <label for="kelas">Kelas <em>*</em></label>
        <input type="text" id="kelas" name="kelas" required placeholder="Contoh: XII RPL 1">
      </div>
    </div>

    <div class="row two">
      <div class="field">
        <label for="no_hp">No. WhatsApp / HP</label>
        <input type="text" id="no_hp" name="no_hp" placeholder="Contoh: 08123456789" inputmode="tel">
      </div>
      <div class="field">
        <label for="username">Username Perpustakaan <em>*</em></label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($suggestUsername, ENT_QUOTES, 'UTF-8') ?>" required placeholder="Username unik...">
      </div>
    </div>

    <div class="field" style="margin-bottom:16px;">
      <label for="alamat">Alamat Tinggal</label>
      <textarea id="alamat" name="alamat" placeholder="Alamat domisili siswa..."></textarea>
    </div>

    <div class="field">
      <label for="password">Password Cadangan (Opsional)</label>
      <input type="password" id="password" name="password" placeholder="Buat password jika ingin bisa login manual tanpa Google...">
      <small>Jika dikosongkan, password awal otomatis disamakan dengan NIS Anda.</small>
    </div>

    <button type="submit" class="submit">
      Simpan &amp; Masuk ke Perpustakaan
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
    <a href="login.php" class="cancel">Batalkan &amp; Kembali ke Halaman Login</a>
  </form>
</main>

</body>
</html>
