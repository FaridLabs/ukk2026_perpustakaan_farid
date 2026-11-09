<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';

$kategoriList = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

$keyword = trim($_GET['keyword'] ?? '');

$sql = "
    SELECT buku.*, kategori.nama_kategori,
        (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id) AS total_eksemplar,
        (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id AND status = 'tersedia') AS total_tersedia
    FROM buku
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
";

if (!empty($keyword)) {
    $sql .= " WHERE judul LIKE :keyword OR pengarang LIKE :keyword";
    $stmt = $koneksi->prepare($sql . " ORDER BY buku.id DESC");
    $stmt->bindValue(':keyword', "%$keyword%");
    $stmt->execute();
} else {
    $stmt = $koneksi->query($sql . " ORDER BY buku.id DESC");
}
$data = $stmt->fetchAll();

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $koneksi->prepare("SELECT * FROM buku WHERE id = :id");
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $editData = $stmt->fetch();
}
$editCoverUrl = $editData ? coverUrl($editData['cover'] ?? null, '../uploads/covers/') : null;

$pageTitle   = 'Data Koleksi Buku';
$currentPage = 'buku';

$coverCss = <<<'CSS'
<style>
.kc-cover{display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap}
.kc-cover-prev{width:120px;aspect-ratio:3/4;flex:none;display:grid;place-items:center;padding:8px;overflow:hidden;text-align:center;font-size:12px;color:#6b8478;background:#f3f8f5;border:1.5px dashed #9bb5a8;border-radius:6px 10px 10px 6px}
.kc-cover-prev.has-img{padding:0;border-style:solid;border-color:#e3ece7;box-shadow:0 10px 18px -10px rgba(16,60,40,.45)}
.kc-cover-prev img{width:100%;height:100%;object-fit:cover;display:block}
.kc-cover-ctl{flex:1;min-width:220px}
.kc-cover-ctl input[type=file]{width:100%;padding:10px;border:1px solid #e3ece7;border-radius:10px;background:#fff;font:inherit;font-size:13.5px}
.kc-cover-ctl small{display:block;margin-top:8px;font-size:12.5px;color:#5f7469}
.kc-cover-del{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:#a8322b;cursor:pointer}
.kc-cover-del input{width:auto;margin:0}
.bk{display:flex;align-items:center;gap:12px}
.bk-cv{flex:none;width:44px;height:60px;display:grid;place-items:center;overflow:hidden;color:#7a9488;background:#e8f1ec;border-radius:4px 7px 7px 4px;box-shadow:inset 3px 0 0 rgba(0,0,0,.12)}
.bk-cv img{width:100%;height:100%;object-fit:cover;display:block}
.bk-cv svg{width:20px;height:20px}
.bk-info{min-width:0}
</style>
CSS;
$extraHead = '<link rel="stylesheet" href="../assets/css/kelola.css?v=1">' . $coverCss;

// Ringkasan hasil yang sedang tampil
$sumJudul = count($data);
$sumFisik = array_sum(array_map(fn($r) => (int) $r['total_eksemplar'], $data));
$sumAda   = array_sum(array_map(fn($r) => (int) $r['total_tersedia'], $data));

// Kategori yang muncul di hasil (untuk chip filter) + warna konsisten per kategori
$katTampil = [];
foreach ($data as $r) { $katTampil[$r['nama_kategori'] ?? 'Umum'] = true; }
$katTampil = array_keys($katTampil);
$warnaKat  = [['#DDF3E8', '#1B7A55'], ['#E3ECFD', '#2F63D8'], ['#FDEBD3', '#B4600A'], ['#ECE5FB', '#7140D0'], ['#FBE1E1', '#C23B3B'], ['#E1F1F4', '#17717F']];
$warnaDari = fn(string $n) => $warnaKat[crc32($n) % count($warnaKat)];

$svgK    = fn(string $d) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$ikEdit  = $svgK('<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>');
$ikHapus = $svgK('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>');
$ikQr    = $svgK('<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M14 14h3v3M20.5 14v.5M14 20.5h.5M17 20.5h3.5V17"/>');
$ikBuku  = $svgK('<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h8M8 11h8"/>');

$formTerbuka = $editData || (($_GET['error'] ?? '') === 'empty') || (($_GET['error'] ?? '') === 'cover');

require 'includes/header.php';
?>
<div class="d-head">
    <div>
        <h1>Kelola Data Buku</h1>
        <p>Daftarkan judul buku baru, kelola eksemplar fisik, dan cetak label barcode/QR.</p>
    </div>
    <?php if (!$editData): ?>
    <div class="d-act">
        <button type="button" class="btn-d main" id="btnForm" aria-controls="formBuku" aria-expanded="<?= $formTerbuka ? 'true' : 'false' ?>"><?= ic('plus') ?><span><?= $formTerbuka ? 'Tutup Formulir' : 'Tambah Judul Buku' ?></span></button>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'empty'): ?>
    <div class="kc-al err" role="alert"><?= ic('clock') ?><div>Semua field yang bertanda wajib harus diisi lengkap.</div></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'cover'): ?>
    <div class="kc-al err" role="alert"><?= ic('clock') ?><div><?= htmlspecialchars($_GET['pesan'] ?? 'Cover gagal diunggah. Gunakan JPG, PNG, atau WEBP maksimal 2 MB.') ?></div></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'ada_item'): ?>
    <div class="kc-al err" role="alert"><?= ic('clock') ?><div>Judul ini tidak bisa dihapus karena masih memiliki eksemplar fisik. Hapus semua eksemplar terlebih dahulu di menu Kelola QR.</div></div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'system'): ?>
    <div class="kc-al err" role="alert"><?= ic('clock') ?><div>Terjadi kesalahan pada sistem saat menyimpan data buku.</div></div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'tambah'): ?>
    <div class="kc-al ok" role="status">
        <?= ic('check') ?>
        <div>
            Judul buku baru berhasil ditambahkan beserta <strong><?= htmlspecialchars($_GET['jumlah'] ?? '0') ?></strong> kode QR eksemplar fisik.
            <?php if (!empty($_GET['id'])): ?>
                <a href="buku_item.php?buku_id=<?= htmlspecialchars($_GET['id']) ?>">Kelola Eksemplar</a> &bull;
                <a href="../config/generate_qr.php?buku_id=<?= htmlspecialchars($_GET['id']) ?>">Cetak Stiker Label QR</a>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Data buku berhasil diperbarui.</div></div>
<?php endif; ?>

<!-- FORM TAMBAH / EDIT BUKU -->
<section class="kc-form" id="formBuku" <?= $formTerbuka ? '' : 'hidden' ?> aria-labelledby="hForm">
    <h2 id="hForm"><?= $editData ? 'Edit Data Judul Buku' : 'Tambah Judul Buku Baru' ?></h2>
    <p>Isi metadata buku di bawah ini. Eksemplar fisik dan kode QR unik akan dibuat otomatis.</p>

    <form action="../config/proses_buku.php" method="POST" enctype="multipart/form-data" autocomplete="off">
        <?php if ($editData): ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="tambah">
        <?php endif; ?>

        <div class="kc-grid">
            <div class="kc-f full">
                <label for="judul">Judul Buku <em>*</em></label>
                <input type="text" id="judul" name="judul" value="<?= $editData ? htmlspecialchars($editData['judul']) : '' ?>" required placeholder="Masukkan judul buku secara lengkap..." autocomplete="off">
            </div>
            <div class="kc-f">
                <label for="pengarang">Pengarang / Penulis</label>
                <input type="text" id="pengarang" name="pengarang" value="<?= $editData ? htmlspecialchars($editData['pengarang']) : '' ?>" placeholder="Nama penulis / pengarang..." autocomplete="off">
            </div>
            <div class="kc-f">
                <label for="penerbit">Penerbit</label>
                <input type="text" id="penerbit" name="penerbit" value="<?= $editData ? htmlspecialchars($editData['penerbit']) : '' ?>" placeholder="Nama penerbit buku..." autocomplete="off">
            </div>
            <div class="kc-f">
                <label for="tahun_terbit">Tahun Terbit</label>
                <input type="number" id="tahun_terbit" name="tahun_terbit" min="1900" max="2100" value="<?= $editData ? $editData['tahun_terbit'] : '' ?>" placeholder="Contoh: 2024" autocomplete="off">
            </div>
            <div class="kc-f">
                <label for="kategori_id">Kategori Buku <em>*</em></label>
                <select id="kategori_id" name="kategori_id" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= ($editData && $editData['kategori_id'] == $kat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- COVER BUKU -->
            <div class="kc-f full">
                <label for="cover">Cover Buku</label>
                <div class="kc-cover">
                    <div class="kc-cover-prev <?= $editCoverUrl ? 'has-img' : '' ?>" id="coverPrev">
                        <?php if ($editCoverUrl): ?>
                            <img src="<?= htmlspecialchars($editCoverUrl) ?>" alt="Cover saat ini">
                        <?php else: ?>
                            Belum ada cover
                        <?php endif; ?>
                    </div>
                    <div class="kc-cover-ctl">
                        <input type="file" id="cover" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <small>Opsional. Format JPG, PNG, atau WEBP, maksimal 2 MB. Disarankan rasio potret (3:4), misalnya 600 x 800 px.</small>
                        <?php if ($editCoverUrl): ?>
                            <label class="kc-cover-del"><input type="checkbox" name="hapus_cover" value="1"> Hapus cover saat ini</label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!$editData): ?>
            <div class="kc-copies">
                <div class="kc-f">
                    <label for="jumlah_eksemplar">Jumlah eksemplar awal</label>
                    <input type="number" id="jumlah_eksemplar" name="jumlah_eksemplar" min="0" max="100" value="1" required autocomplete="off">
                </div>
                <p id="copiesInfo">Sistem akan menerbitkan <b>1</b> kode QR unik, siap dicetak dan ditempel pada buku fisik.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="kc-actions">
            <button type="submit" class="kc-save"><?= $editData ? 'Simpan Perubahan' : 'Tambah Buku &amp; Terbitkan QR' ?></button>
            <?php if ($editData): ?>
                <a href="buku.php" class="kc-cancel">Batal</a>
            <?php else: ?>
                <button type="button" class="kc-cancel" id="btnBatal" style="cursor:pointer;font-family:inherit">Batal</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- CARI + FILTER KATEGORI -->
<div class="kc-bar">
    <form action="buku.php" method="GET" class="kc-search" autocomplete="off">
        <div class="ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="keyword" placeholder="Cari judul atau pengarang buku..." value="<?= htmlspecialchars($keyword) ?>" aria-label="Cari buku" autocomplete="off">
        </div>
        <button type="submit">Cari</button>
        <?php if (!empty($keyword)): ?><a href="buku.php" class="kc-reset">Reset</a><?php endif; ?>
    </form>
    <?php if (count($katTampil) > 1): ?>
    <div class="kc-chips" id="chips" role="group" aria-label="Filter kategori">
        <button type="button" data-kat="" aria-pressed="true">Semua</button>
        <?php foreach ($katTampil as $kn): ?><button type="button" data-kat="<?= htmlspecialchars($kn) ?>" aria-pressed="false"><?= htmlspecialchars($kn) ?></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- DAFTAR BUKU -->
<section class="act" aria-labelledby="hDaftar">
    <div class="act-top">
        <div class="panel-ico"><?= ic('book') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hDaftar">Daftar koleksi</h2>
            <p><?= $keyword !== '' ? 'Hasil pencarian "' . htmlspecialchars($keyword) . '".' : 'Buku terbaru ditampilkan paling atas.' ?></p>
        </div>
        <div class="kc-meta" id="meta"><b><?= $sumJudul ?></b> judul &middot; <b><?= $sumFisik ?></b> eksemplar &middot; <b><?= $sumAda ?></b> tersedia</div>
    </div>

    <div class="tbl kc">
        <?php if (empty($data)): ?>
            <div class="kc-empty">
                <b><?= $keyword !== '' ? 'Tidak ada buku yang cocok' : 'Belum ada data buku' ?></b>
                <?php if ($keyword !== ''): ?>Coba kata kunci lain atau <a href="buku.php">reset pencarian</a>.<?php else: ?>Tambahkan judul pertama lewat tombol "Tambah Judul Buku".<?php endif; ?>
            </div>
        <?php else: ?>
        <table id="tabelBuku">
            <thead><tr><th>Judul Buku</th><th>Kategori</th><th>Eksemplar</th><th style="text-align:right">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row):
                $namaKat = $row['nama_kategori'] ?? 'Umum';
                [$wb, $wf] = $warnaDari($namaKat);
                $tot = (int) $row['total_eksemplar']; $ada = (int) $row['total_tersedia'];
                $pct = $tot > 0 ? round($ada / $tot * 100) : 0;
                $avKls = $tot === 0 ? 'empty' : ($ada === 0 ? 'none' : '');
                $tahun = in_array((string) $row['tahun_terbit'], ['', '0', '0000'], true) ? '' : $row['tahun_terbit'];
                $meta  = array_filter([$row['pengarang'] ?: '', $row['penerbit'] ?: '', $tahun]);
                $pesanHapus = "Apakah Anda yakin ingin menghapus judul buku '" . $row['judul'] . "'?";
                $cvUrl = coverUrl($row['cover'] ?? null, '../uploads/covers/');
            ?>
                <tr data-kat="<?= htmlspecialchars($namaKat) ?>">
                    <td>
                        <div class="bk">
                            <div class="bk-cv">
                                <?php if ($cvUrl): ?>
                                    <img src="<?= htmlspecialchars($cvUrl) ?>" alt="Cover <?= htmlspecialchars($row['judul']) ?>" loading="lazy" width="44" height="60">
                                <?php else: ?>
                                    <?= $ikBuku ?>
                                <?php endif; ?>
                            </div>
                            <div class="bk-info">
                                <div class="tt"><strong><?= htmlspecialchars($row['judul']) ?></strong><code><?= htmlspecialchars($row['kode_buku']) ?></code></div>
                                <span class="sub"><?= $meta ? htmlspecialchars(implode(' · ', $meta)) : 'Data pengarang belum diisi' ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="kat" style="--b:<?= $wb ?>;--f:<?= $wf ?>"><?= htmlspecialchars($namaKat) ?></span></td>
                    <td>
                        <div class="av <?= $avKls ?>">
                            <?php if ($tot === 0): ?>
                                <span>Belum ada eksemplar</span>
                            <?php else: ?>
                                <span><strong><?= $ada ?></strong> tersedia dari <?= $tot ?> fisik</span>
                                <i><b style="width:<?= $pct ?>%"></b></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="kb-row">
                            <a href="buku_item.php?buku_id=<?= $row['id'] ?>" class="kb">Kelola QR</a>
                            <a href="../config/generate_qr.php?buku_id=<?= $row['id'] ?>" class="kb green"><?= $ikQr ?>Cetak QR</a>
                            <a href="buku.php?edit=<?= $row['id'] ?>" class="kb ico" title="Edit buku" aria-label="Edit <?= htmlspecialchars($row['judul']) ?>"><?= $ikEdit ?></a>
                            <a href="../config/proses_buku.php?action=hapus&id=<?= $row['id'] ?>" class="kb ico danger" title="Hapus buku" aria-label="Hapus <?= htmlspecialchars($row['judul']) ?>"
                               onclick="return confirm(<?= htmlspecialchars(json_encode($pesanHapus, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"><?= $ikHapus ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    // Buka / tutup formulir tambah buku
    var btn = document.getElementById('btnForm'), form = document.getElementById('formBuku'), batal = document.getElementById('btnBatal');
    function atur(buka) {
        form.hidden = !buka;
        if (btn) { btn.setAttribute('aria-expanded', buka ? 'true' : 'false'); btn.querySelector('span').textContent = buka ? 'Tutup Formulir' : 'Tambah Judul Buku'; }
        if (buka) { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); document.getElementById('judul').focus({ preventScroll: true }); }
    }
    if (btn) btn.addEventListener('click', function () { atur(form.hidden); });
    if (batal) batal.addEventListener('click', function () { atur(false); });
    <?php if ($editData): ?>form.scrollIntoView({ block: 'start' });<?php endif; ?>

    // Pratinjau jumlah QR yang akan diterbitkan
    var jml = document.getElementById('jumlah_eksemplar'), info = document.getElementById('copiesInfo');
    if (jml && info) jml.addEventListener('input', function () {
        var n = Math.max(0, parseInt(jml.value, 10) || 0);
        info.innerHTML = n === 0 ? 'Tidak ada eksemplar yang dibuat sekarang. Eksemplar bisa ditambah nanti lewat <b>Kelola QR</b>.'
            : 'Sistem akan menerbitkan <b>' + n + '</b> kode QR unik, siap dicetak dan ditempel pada buku fisik.';
    });

    // Pratinjau cover sebelum diunggah + validasi sederhana di browser
    var fc = document.getElementById('cover'), pv = document.getElementById('coverPrev');
    if (fc && pv) {
        var asli = pv.innerHTML, asliCls = pv.className;
        var pulihkan = function () { pv.innerHTML = asli; pv.className = asliCls; };
        fc.addEventListener('change', function () {
            var f = fc.files[0];
            if (!f) { pulihkan(); return; }
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { alert('Format cover harus JPG, PNG, atau WEBP.'); fc.value = ''; pulihkan(); return; }
            if (f.size > 2 * 1024 * 1024) { alert('Ukuran cover maksimal 2 MB.'); fc.value = ''; pulihkan(); return; }
            var rd = new FileReader();
            rd.onload = function (e) {
                pv.className = 'kc-cover-prev has-img';
                pv.innerHTML = '';
                var im = new Image(); im.src = e.target.result; im.alt = 'Pratinjau cover';
                pv.appendChild(im);
            };
            rd.readAsDataURL(f);
        });
    }

    // Filter kategori (di halaman ini saja)
    var chips = document.querySelectorAll('#chips button'), rows = document.querySelectorAll('#tabelBuku tbody tr'), meta = document.getElementById('meta');
    var awal = meta ? meta.innerHTML : '';
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            var kat = c.dataset.kat, n = 0;
            chips.forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
            rows.forEach(function (r) { var cocok = !kat || r.dataset.kat === kat; r.hidden = !cocok; if (cocok) n++; });
            meta.innerHTML = kat ? '<b>' + n + '</b> judul dalam kategori ini' : awal;
        });
    });
})();
</script>
<?php require 'includes/footer.php'; ?>