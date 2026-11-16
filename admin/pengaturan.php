<?php
/**
 * ============================================================================
 * HALAMAN PENGATURAN SISTEM PERPUSTAKAAN - PINJAM.IN
 * ============================================================================
 * Halaman untuk mengelola konfigurasi aturan operasional perpustakaan:
 * 1. Batas kuota buku yang dapat dipinjam oleh setiap siswa.
 * 2. Batas durasi masa peminjaman buku (dalam hari).
 * 3. Besaran tarif denda keterlambatan pengembalian buku per hari.
 * 4. Identitas resmi perpustakaan / nama sekolah untuk kop dokumen cetak.
 */

// 1. Cek keamanan sesi admin
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/log_helper.php';
require_once '../config/pengaturan_helper.php';

$adminId   = (int) $_SESSION['admin_id'];
$adminNama = $_SESSION['admin_nama'] ?? 'Administrator';

$pesanSukses = '';
$pesanError  = '';

// 2. Proses simpan pengaturan saat form disubmit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $maksPinjam       = (int) ($_POST['maks_pinjam_buku'] ?? 3);
    $lamaPinjam       = (int) ($_POST['lama_pinjam_hari'] ?? 7);
    $dendaPerHari     = (int) ($_POST['denda_per_hari'] ?? 1000);
    $namaAplikasi     = trim($_POST['nama_aplikasi'] ?? '');
    $sloganAplikasi   = trim($_POST['slogan_aplikasi'] ?? '');
    $namaSekolah      = trim($_POST['nama_sekolah'] ?? '');
    $namaPerpustakaan = trim($_POST['nama_perpustakaan'] ?? '');

    // Validasi sederhana batasan angka dan kelengkapan teks
    if ($maksPinjam < 1 || $maksPinjam > 20) {
        $pesanError = 'Batas kuota peminjaman harus di antara 1 sampai 20 buku.';
    } elseif ($lamaPinjam < 1 || $lamaPinjam > 90) {
        $pesanError = 'Batas durasi pinjam harus di antara 1 sampai 90 hari.';
    } elseif ($dendaPerHari < 0) {
        $pesanError = 'Tarif denda keterlambatan tidak boleh bernilai negatif.';
    } elseif (empty($namaAplikasi)) {
        $pesanError = 'Nama aplikasi / brand tidak boleh dibiarkan kosong.';
    } elseif (empty($namaSekolah)) {
        $pesanError = 'Nama sekolah / instansi tidak boleh dibiarkan kosong.';
    } elseif (empty($namaPerpustakaan)) {
        $pesanError = 'Nama resmi perpustakaan tidak boleh dibiarkan kosong.';
    } else {
        // Simpan pembaruan ke database
        simpanPengaturan($koneksi, [
            'maks_pinjam_buku'  => $maksPinjam,
            'lama_pinjam_hari'  => $lamaPinjam,
            'denda_per_hari'    => $dendaPerHari,
            'nama_aplikasi'     => $namaAplikasi,
            'slogan_aplikasi'   => $sloganAplikasi,
            'nama_sekolah'      => $namaSekolah,
            'nama_perpustakaan' => $namaPerpustakaan,
        ]);

        // Catat perubahan konfigurasi ke tabel log aktivitas
        catatLog(
            $koneksi,
            'admin',
            $adminId,
            $adminNama,
            'update_pengaturan',
            'pengaturan',
            "Memperbarui pengaturan: App '{$namaAplikasi}', Sekolah '{$namaSekolah}', kuota {$maksPinjam} buku, durasi {$lamaPinjam} hari, denda " . formatRupiah($dendaPerHari) . "/hari."
        );

        header('Location: pengaturan.php?success=1');
        exit;
    }
}

// 3. Ambil data pengaturan terkini dari database
$cfg = ambilPengaturan($koneksi);

if (isset($_GET['success'])) {
    $pesanSukses = 'Pengaturan sistem perpustakaan berhasil disimpan dan langsung diterapkan ke seluruh modul.';
}

$pageTitle   = 'Pengaturan Sistem';
$currentPage = 'pengaturan';
$extraHead   = '<link rel="stylesheet" href="../assets/css/kelola.css?v=4">';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-head">
    <div>
        <h1>Pengaturan Aturan Perpustakaan</h1>
        <p>Kelola batas kuota peminjaman siswa, durasi masa pinjam buku, tarif denda keterlambatan, dan identitas sekolah.</p>
    </div>
</div>

<!-- NOTIFIKASI SUKSES / ERROR -->
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
     LAYOUT 2-KOLOM: OVERVIEW ATURAN KIRI & FORM INPUT KANAN
     ========================================================================= -->
<div style="display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start; margin-top: 16px;">
    
    <!-- KOLOM KIRI: KARTU RINGKASAN ATURAN AKTIF -->
    <div style="background: #FFFFFF; border: 1px solid var(--line); border-radius: 18px; padding: 26px 22px; box-shadow: 0 4px 14px rgba(20,35,43,.04);">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line);">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #E6F5EE; color: #1B7A55; display: grid; place-items: center;">
                <?= ic('settings', 24) ?>
            </div>
            <div>
                <h2 style="font-size: 16px; font-weight: 800; color: var(--ink); margin: 0;">Aturan yang Berlaku</h2>
                <small style="color: var(--muted); font-size: 12px;">Parameter sistem aktif saat ini</small>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px;">
            <!-- Ringkasan Brand & Identitas Sekolah (Live Preview) -->
            <div style="background: #EBF7F0; border: 1.5px dashed #217350; border-radius: 12px; padding: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 11px; font-weight: 700; color: #1B7A55; text-transform: uppercase; letter-spacing: .04em;">Preview Identitas</span>
                    <span class="pill-on" style="font-size: 10px; padding: 1px 6px;">Aktif</span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: var(--ink); line-height: 1.2;" id="lblPreviewApp"><?= htmlspecialchars($cfg['nama_aplikasi'] ?? 'Pinjam.In') ?></div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;" id="lblPreviewSlogan"><?= htmlspecialchars($cfg['slogan_aplikasi'] ?? 'Perpustakaan Digital') ?></div>
                <div style="font-size: 12px; font-weight: 600; color: #1B7A55; margin-top: 8px; padding-top: 6px; border-top: 1px dashed rgba(33,115,80,.25);" id="lblPreviewSekolah"><?= htmlspecialchars($cfg['nama_sekolah'] ?? 'SMKN 1 Panyingkiran') ?></div>
            </div>

            <!-- Ringkasan Kuota Pinjam -->
            <div style="background: #F8FAF9; border: 1px solid var(--line); border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="color: #1B7A55;"><?= ic('book', 20) ?></div>
                    <div>
                        <div style="font-size: 12px; color: var(--muted);">Batas Kuota Pinjam</div>
                        <strong style="font-size: 14.5px; color: var(--ink);"><span id="lblPreviewKuota"><?= (int)$cfg['maks_pinjam_buku'] ?></span> Buku</strong>
                    </div>
                </div>
                <span class="pill-on" style="font-size: 11px;">Maksimal</span>
            </div>

            <!-- Ringkasan Durasi Peminjaman -->
            <div style="background: #F8FAF9; border: 1px solid var(--line); border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="color: #1B7A55;"><?= ic('clock', 20) ?></div>
                    <div>
                        <div style="font-size: 12px; color: var(--muted);">Batas Durasi Pinjam</div>
                        <strong style="font-size: 14.5px; color: var(--ink);"><span id="lblPreviewDurasi"><?= (int)$cfg['lama_pinjam_hari'] ?></span> Hari</strong>
                    </div>
                </div>
                <span class="pill-on" style="font-size: 11px;">Jatuh Tempo</span>
            </div>

            <!-- Ringkasan Tarif Denda -->
            <div style="background: #F8FAF9; border: 1px solid var(--line); border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="color: #1B7A55;"><?= ic('tag', 20) ?></div>
                    <div>
                        <div style="font-size: 12px; color: var(--muted);">Tarif Denda Telat</div>
                        <strong style="font-size: 14.5px; color: #C23B3B;"><span id="lblPreviewDenda"><?= formatRupiah($cfg['denda_per_hari']) ?></span><small style="color:var(--muted);font-weight:normal;"> / hari</small></strong>
                    </div>
                </div>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 99px; background: #FDE8E8; color: #C23B3B; font-weight: 700;">Per Buku</span>
            </div>
        </div>

        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); line-height: 1.5;">
            <strong>Catatan Operasional:</strong><br>
            Perubahan batas kuota, durasi, dan denda langsung memengaruhi transaksi baru dan kalkulasi sirkulasi di portal admin maupun portal siswa.
        </div>
    </div>

    <!-- KOLOM KANAN: FORMULIR PENGATURAN -->
    <div style="background: #FFFFFF; border: 1px solid var(--line); border-radius: 18px; padding: 28px; box-shadow: 0 4px 14px rgba(20,35,43,.04);">
        <form action="pengaturan.php" method="POST" autocomplete="off" id="formPengaturan">
            
            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--ink); margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--line);">
                1. Kebijakan Sirkulasi &amp; Peminjaman
            </h3>

            <!-- Baris 1: Batas Kuota Buku & Durasi Pinjam -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
                <div>
                    <label for="inpMaksPinjam" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Maksimal Buku Dipinjam Siswa <span style="color:#C23B3B;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="number" 
                               id="inpMaksPinjam" 
                               name="maks_pinjam_buku" 
                               min="1" 
                               max="20" 
                               required 
                               value="<?= (int)$cfg['maks_pinjam_buku'] ?>" 
                               style="width: 100%; height: 44px; padding: 0 54px 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 15px; font-weight: 700; color: var(--ink);">
                        <span style="position: absolute; right: 14px; top: 12px; font-size: 13px; color: var(--muted); pointer-events: none;">Buku</span>
                    </div>
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Siswa tidak dapat meminjam jika sudah mencapai batas ini.
                    </small>
                </div>

                <div>
                    <label for="inpLamaPinjam" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Batas Durasi Masa Pinjam <span style="color:#C23B3B;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="number" 
                               id="inpLamaPinjam" 
                               name="lama_pinjam_hari" 
                               min="1" 
                               max="90" 
                               required 
                               value="<?= (int)$cfg['lama_pinjam_hari'] ?>" 
                               style="width: 100%; height: 44px; padding: 0 54px 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 15px; font-weight: 700; color: var(--ink);">
                        <span style="position: absolute; right: 14px; top: 12px; font-size: 13px; color: var(--muted); pointer-events: none;">Hari</span>
                    </div>
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Tanggal jatuh tempo dihitung: tanggal pinjam + jumlah hari ini.
                    </small>
                </div>
            </div>

            <!-- Baris 2: Tarif Denda Keterlambatan -->
            <div style="margin-bottom: 26px;">
                <label for="inpDenda" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                    Tarif Denda Keterlambatan (Per Hari / Buku) <span style="color:#C23B3B;">*</span>
                </label>
                <div style="position: relative; max-width: 320px;">
                    <span style="position: absolute; left: 14px; top: 12px; font-size: 14px; font-weight: 700; color: var(--ink); pointer-events: none;">Rp</span>
                    <input type="number" 
                           id="inpDenda" 
                           name="denda_per_hari" 
                           min="0" 
                           step="500" 
                           required 
                           value="<?= (int)$cfg['denda_per_hari'] ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px 0 42px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 15px; font-weight: 700; color: var(--ink);">
                </div>
                <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                    Dikenakan per hari per eksemplar jika melewati batas waktu. Masukkan <code>0</code> jika sekolah memberlakukan bebas denda.
                </small>
            </div>

            <h3 style="font-size: 16.5px; font-weight: 800; color: var(--ink); margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--line);">
                2. Identitas Brand &amp; Sekolah
            </h3>

            <!-- Baris 3: Nama Brand Aplikasi & Slogan -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
                <div>
                    <label for="inpNamaApp" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Nama Aplikasi / Brand <span style="color:#C23B3B;">*</span>
                    </label>
                    <input type="text" 
                           id="inpNamaApp" 
                           name="nama_aplikasi" 
                           required 
                           value="<?= htmlspecialchars($cfg['nama_aplikasi'] ?? 'Pinjam.In', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 14.5px; font-weight: 700; color: var(--ink);">
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Muncul pada judul tab browser, sidebar admin &amp; siswa, login, dan landing page.
                    </small>
                </div>

                <div>
                    <label for="inpSloganApp" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Slogan / Sub-Nama Aplikasi
                    </label>
                    <input type="text" 
                           id="inpSloganApp" 
                           name="slogan_aplikasi" 
                           value="<?= htmlspecialchars($cfg['slogan_aplikasi'] ?? 'Perpustakaan Digital', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 14.5px; color: var(--ink);">
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Teks pelengkap di bawah nama aplikasi pada header sidebar dan login.
                    </small>
                </div>
            </div>

            <!-- Baris 4: Nama Sekolah & Nama Resmi Perpustakaan -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 26px;">
                <div>
                    <label for="inpNamaSekolah" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Nama Sekolah / Lembaga <span style="color:#C23B3B;">*</span>
                    </label>
                    <input type="text" 
                           id="inpNamaSekolah" 
                           name="nama_sekolah" 
                           required 
                           value="<?= htmlspecialchars($cfg['nama_sekolah'] ?? 'SMKN 1 Panyingkiran', ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 14.5px; color: var(--ink);">
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Muncul di footer sidebar siswa, landing page, dan kop surat cetak.
                    </small>
                </div>

                <div>
                    <label for="inpNamaPerpus" style="display: block; font-size: 13.5px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">
                        Nama Unit Perpustakaan Dokumen <span style="color:#C23B3B;">*</span>
                    </label>
                    <input type="text" 
                           id="inpNamaPerpus" 
                           name="nama_perpustakaan" 
                           required 
                           value="<?= htmlspecialchars($cfg['nama_perpustakaan'], ENT_QUOTES, 'UTF-8') ?>" 
                           style="width: 100%; height: 44px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 9px; background: #FFFFFF; font-size: 14.5px; color: var(--ink);">
                    <small style="display: block; margin-top: 5px; font-size: 11.5px; color: var(--muted);">
                        Nama lengkap yang tercetak pada kop dokumen cetak atau ekspor PDF.
                    </small>
                </div>
            </div>

            <!-- Tombol Aksi Simpan -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 14px; border-top: 1px solid var(--line);">
                <button type="submit" class="btn-d main" style="padding: 11px 26px; font-size: 14.5px; font-weight: 700;">
                    <?= ic('check') ?><span>Simpan Aturan Perpustakaan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================================
// LIVE PREVIEW DINAMIS SAAT INPUT PENGATURAN DIKETIK
// ============================================================================
const inpMaksPinjam    = document.getElementById('inpMaksPinjam');
const inpLamaPinjam    = document.getElementById('inpLamaPinjam');
const inpDenda         = document.getElementById('inpDenda');
const inpNamaApp       = document.getElementById('inpNamaApp');
const inpSloganApp     = document.getElementById('inpSloganApp');
const inpNamaSekolah   = document.getElementById('inpNamaSekolah');

const lblPreviewKuota   = document.getElementById('lblPreviewKuota');
const lblPreviewDurasi  = document.getElementById('lblPreviewDurasi');
const lblPreviewDenda   = document.getElementById('lblPreviewDenda');
const lblPreviewApp     = document.getElementById('lblPreviewApp');
const lblPreviewSlogan  = document.getElementById('lblPreviewSlogan');
const lblPreviewSekolah = document.getElementById('lblPreviewSekolah');

function formatRupiahJs(angka) {
    return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
}

if (inpMaksPinjam && lblPreviewKuota) {
    inpMaksPinjam.addEventListener('input', () => {
        lblPreviewKuota.textContent = inpMaksPinjam.value || '0';
    });
}

if (inpLamaPinjam && lblPreviewDurasi) {
    inpLamaPinjam.addEventListener('input', () => {
        lblPreviewDurasi.textContent = inpLamaPinjam.value || '0';
    });
}

if (inpDenda && lblPreviewDenda) {
    inpDenda.addEventListener('input', () => {
        lblPreviewDenda.textContent = formatRupiahJs(inpDenda.value);
    });
}

if (inpNamaApp && lblPreviewApp) {
    inpNamaApp.addEventListener('input', () => {
        lblPreviewApp.textContent = inpNamaApp.value || 'Pinjam.In';
    });
}

if (inpSloganApp && lblPreviewSlogan) {
    inpSloganApp.addEventListener('input', () => {
        lblPreviewSlogan.textContent = inpSloganApp.value || 'Perpustakaan Digital';
    });
}

if (inpNamaSekolah && lblPreviewSekolah) {
    inpNamaSekolah.addEventListener('input', () => {
        lblPreviewSekolah.textContent = inpNamaSekolah.value || 'SMKN 1 Panyingkiran';
    });
}
</script>

<?php require 'includes/footer.php'; ?>
