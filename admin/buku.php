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

// Jika dibuka via ?edit=ID (mis. dari buku_item.php atau redirect validasi cover)
$editData = null;
if (!empty($_GET['edit'])) {
    $stmt = $koneksi->prepare("
        SELECT buku.*,
            (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id) AS total_eksemplar,
            (SELECT COUNT(*) FROM buku_item WHERE buku_item.buku_id = buku.id AND status = 'tersedia') AS total_tersedia
        FROM buku
        WHERE buku.id = :id
        LIMIT 1
    ");
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $editData = $stmt->fetch() ?: null;
}

$errCode  = $_GET['error'] ?? '';
$errMsg   = '';
if ($errCode === 'empty') {
    $errMsg = 'Judul buku dan kategori wajib diisi lengkap.';
} elseif ($errCode === 'cover') {
    $errMsg = $_GET['pesan'] ?? 'Cover gagal diunggah. Gunakan JPG, PNG, atau WEBP maksimal 2 MB.';
} elseif ($errCode === 'import') {
    $errMsg = $_GET['msg'] ?? 'Gagal mengimpor file CSV.';
}

$pageTitle   = 'Data Koleksi Buku';
$currentPage = 'buku';

$coverCss = <<<'CSS'
<style>
.bk{display:flex;align-items:center;gap:12px}
.bk-cv{flex:none;width:44px;height:60px;display:grid;place-items:center;overflow:hidden;color:#7a9488;background:#e8f1ec;border-radius:4px 7px 7px 4px;box-shadow:inset 3px 0 0 rgba(0,0,0,.12)}
.bk-cv img{width:100%;height:100%;object-fit:cover;display:block}
.bk-cv svg{width:20px;height:20px}
.bk-info{min-width:0}
</style>
CSS;
$extraHead = '<link rel="stylesheet" href="../assets/css/kelola.css?v=3">' . $coverCss;

// Ringkasan hasil yang sedang tampil
$sumJudul = count($data);
$sumFisik = array_sum(array_map(fn($r) => (int) $r['total_eksemplar'], $data));
$sumAda   = array_sum(array_map(fn($r) => (int) $r['total_tersedia'], $data));

// Kategori yang muncul di hasil (untuk chip filter) + warna konsisten per kategori
$katTampil = [];
foreach ($data as $r) {
    $katTampil[$r['nama_kategori'] ?? 'Umum'] = true;
}
$katTampil = array_keys($katTampil);
$warnaKat  = [['#DDF3E8', '#1B7A55'], ['#E3ECFD', '#2F63D8'], ['#FDEBD3', '#B4600A'], ['#ECE5FB', '#7140D0'], ['#FBE1E1', '#C23B3B'], ['#E1F1F4', '#17717F']];
$warnaDari = fn(string $n) => $warnaKat[crc32($n) % count($warnaKat)];

$svgK     = fn(string $d) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$ikEdit   = $svgK('<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>');
$ikHapus  = $svgK('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>');
$ikQr     = $svgK('<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M14 14h3v3M20.5 14v.5M14 20.5h.5M17 20.5h3.5V17"/>');
$ikBuku   = $svgK('<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h8M8 11h8"/>');
$ikClose  = $svgK('<path d="M18 6 6 18M6 6l12 12"/>');
$ikUpload = $svgK('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>');
$ikAlert  = $svgK('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');

// Siapkan payload awal untuk modal jika dibuka via URL (?edit= atau ?error=empty|cover)
$initialModalPayload = null;
if ($editData) {
    $initialModalPayload = [
        'mode'            => 'edit',
        'id'              => (int) $editData['id'],
        'kode_buku'       => (string) ($editData['kode_buku'] ?? ''),
        'judul'           => (string) ($editData['judul'] ?? ''),
        'pengarang'       => (string) ($editData['pengarang'] ?? ''),
        'penerbit'        => (string) ($editData['penerbit'] ?? ''),
        'tahun_terbit'    => in_array((string) ($editData['tahun_terbit'] ?? ''), ['', '0', '0000'], true) ? '' : (string) $editData['tahun_terbit'],
        'kategori_id'     => (string) ($editData['kategori_id'] ?? ''),
        'cover_url'       => coverUrl($editData['cover'] ?? null, '../uploads/covers/') ?? '',
        'total_eksemplar' => (int) ($editData['total_eksemplar'] ?? 0),
        'total_tersedia'  => (int) ($editData['total_tersedia'] ?? 0),
        'error'           => $errMsg,
    ];
} elseif ($errMsg !== '' && $errCode !== 'import') {
    $initialModalPayload = [
        'mode'  => 'tambah',
        'error' => $errMsg,
    ];
}

require 'includes/header.php';
?>
<div class="d-head">
    <div>
        <h1>Kelola Data Buku</h1>
        <p>Daftarkan judul buku baru, kelola eksemplar fisik, dan cetak label barcode/QR.</p>
    </div>
    <div class="d-act" style="display:flex; gap:8px;">
        <button type="button" class="btn-d" id="btnImportBuku" style="background:var(--card,#fff); border:1px solid var(--line,#e2e8f0); color:var(--text,#1e293b); font-weight:600;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span>Import CSV</span>
        </button>
        <button type="button" class="btn-d main" id="btnTambahBuku"><?= ic('plus') ?><span>Tambah Judul Buku</span></button>
    </div>
</div>

<?php if ($errCode === 'import'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif ($errCode === 'empty'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif ($errCode === 'cover'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif ($errCode === 'ada_item'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div>Judul ini tidak bisa dihapus karena masih memiliki eksemplar fisik. Hapus semua eksemplar terlebih dahulu di menu Kelola QR.</div></div>
<?php elseif ($errCode === 'system'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div>Terjadi kesalahan pada sistem saat menyimpan data buku.</div></div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'import'): ?>
    <div class="kc-al ok" role="status">
        <?= ic('check') ?>
        <div>Berhasil mengimpor <strong><?= htmlspecialchars($_GET['jumlah'] ?? '0', ENT_QUOTES, 'UTF-8') ?></strong> judul buku baru beserta eksemplar fisik ke koleksi.</div>
    </div>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'tambah'): ?>
    <div class="kc-al ok" role="status">
        <?= ic('check') ?>
        <div>
            Judul buku baru berhasil ditambahkan beserta <strong><?= htmlspecialchars($_GET['jumlah'] ?? '0', ENT_QUOTES, 'UTF-8') ?></strong> kode QR eksemplar fisik.
            <?php if (!empty($_GET['id'])): ?>
                <a href="buku_item.php?buku_id=<?= urlencode($_GET['id']) ?>">Kelola Eksemplar</a> &bull;
                <a href="../config/generate_qr.php?buku_id=<?= urlencode($_GET['id']) ?>">Cetak Stiker Label QR</a>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'hapus'): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Judul buku berhasil dihapus dari koleksi.</div></div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Data buku berhasil diperbarui.</div></div>
<?php endif; ?>

<!-- CARI + FILTER KATEGORI -->
<div class="kc-bar">
    <form action="buku.php" method="GET" class="kc-search" autocomplete="off">
        <div class="ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="keyword" placeholder="Cari judul atau pengarang buku..." value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" aria-label="Cari buku" autocomplete="off">
        </div>
        <button type="submit">Cari</button>
        <?php if (!empty($keyword)): ?><a href="buku.php" class="kc-reset">Reset</a><?php endif; ?>
    </form>
    <?php if (count($katTampil) > 1): ?>
    <div class="kc-chips" id="chips" role="group" aria-label="Filter kategori">
        <button type="button" data-kat="" aria-pressed="true">Semua</button>
        <?php foreach ($katTampil as $kn): ?><button type="button" data-kat="<?= htmlspecialchars($kn, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false"><?= htmlspecialchars($kn, ENT_QUOTES, 'UTF-8') ?></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- DAFTAR BUKU -->
<section class="act" aria-labelledby="hDaftar">
    <div class="act-top">
        <div class="panel-ico"><?= ic('book') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hDaftar">Daftar koleksi</h2>
            <p><?= $keyword !== '' ? 'Hasil pencarian "' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '".' : 'Buku terbaru ditampilkan paling atas.' ?></p>
        </div>
        <div class="kc-meta" id="meta"><b><?= $sumJudul ?></b> judul &middot; <b><?= $sumFisik ?></b> eksemplar &middot; <b><?= $sumAda ?></b> tersedia</div>
    </div>

    <div class="tbl kc">
        <?php if (empty($data)): ?>
            <div class="kc-empty">
                <b><?= $keyword !== '' ? 'Tidak ada buku yang cocok' : 'Belum ada data buku' ?></b>
                <?php if ($keyword !== ''): ?>
                    Coba kata kunci lain atau <a href="buku.php">reset pencarian</a>.
                <?php else: ?>
                    Tambahkan judul pertama lewat tombol <a href="#" id="linkEmptyTambah">Tambah Judul Buku</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table id="tabelBuku">
            <thead><tr><th>Judul Buku</th><th>Kategori</th><th>Eksemplar</th><th style="text-align:right">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row):
                $namaKat = $row['nama_kategori'] ?? 'Umum';
                [$wb, $wf] = $warnaDari($namaKat);
                $tot = (int) $row['total_eksemplar'];
                $ada = (int) $row['total_tersedia'];
                $pct = $tot > 0 ? round($ada / $tot * 100) : 0;
                $avKls = $tot === 0 ? 'empty' : ($ada === 0 ? 'none' : '');
                $tahun = in_array((string) $row['tahun_terbit'], ['', '0', '0000'], true) ? '' : (string) $row['tahun_terbit'];
                $meta  = array_filter([$row['pengarang'] ?: '', $row['penerbit'] ?: '', $tahun]);
                $cvUrl = coverUrl($row['cover'] ?? null, '../uploads/covers/');

                $rowPayload = json_encode([
                    'mode'            => 'edit',
                    'id'              => (int) $row['id'],
                    'kode_buku'       => (string) ($row['kode_buku'] ?? ''),
                    'judul'           => (string) ($row['judul'] ?? ''),
                    'pengarang'       => (string) ($row['pengarang'] ?? ''),
                    'penerbit'        => (string) ($row['penerbit'] ?? ''),
                    'tahun_terbit'    => $tahun,
                    'kategori_id'     => (string) ($row['kategori_id'] ?? ''),
                    'cover_url'       => $cvUrl ?? '',
                    'total_eksemplar' => $tot,
                    'total_tersedia'  => $ada,
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
                <tr data-kat="<?= htmlspecialchars($namaKat, ENT_QUOTES, 'UTF-8') ?>">
                    <td>
                        <div class="bk">
                            <div class="bk-cv">
                                <?php if ($cvUrl): ?>
                                    <img src="<?= htmlspecialchars($cvUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover <?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" width="44" height="60">
                                <?php else: ?>
                                    <?= $ikBuku ?>
                                <?php endif; ?>
                            </div>
                            <div class="bk-info">
                                <div class="tt"><strong><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></strong><code><?= htmlspecialchars($row['kode_buku'], ENT_QUOTES, 'UTF-8') ?></code></div>
                                <span class="sub"><?= $meta ? htmlspecialchars(implode(' · ', $meta), ENT_QUOTES, 'UTF-8') : 'Data pengarang belum diisi' ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="kat" style="--b:<?= $wb ?>;--f:<?= $wf ?>"><?= htmlspecialchars($namaKat, ENT_QUOTES, 'UTF-8') ?></span></td>
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
                            <a href="buku_item.php?buku_id=<?= (int) $row['id'] ?>" class="kb">Kelola QR</a>
                            <a href="../config/generate_qr.php?buku_id=<?= (int) $row['id'] ?>" class="kb green"><?= $ikQr ?>Cetak QR</a>
                            <button type="button"
                                    class="kb ico js-edit-buku"
                                    data-book="<?= htmlspecialchars($rowPayload, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Edit buku"
                                    aria-label="Edit <?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= $ikEdit ?>
                            </button>
                            <button type="button"
                                    class="kb ico danger js-hapus-buku"
                                    data-href="../config/proses_buku.php?action=hapus&amp;id=<?= (int) $row['id'] ?>"
                                    data-judul="<?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>"
                                    title="Hapus buku"
                                    aria-label="Hapus <?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= $ikHapus ?>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL TAMBAH & EDIT BUKU -->
<div class="bm-backdrop" id="modalBuku" hidden role="dialog" aria-modal="true" aria-labelledby="bmTitle">
    <div class="bm-dialog">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico" id="bmHeadIco"><?= $ikBuku ?></div>
                <div class="bm-head-txt">
                    <div class="bm-head-top">
                        <h2 id="bmTitle">Tambah Judul Buku</h2>
                        <span class="bm-kode" id="bmKodeChip" hidden></span>
                    </div>
                    <p id="bmSubtitle">Lengkapi metadata bibliografi dan jumlah eksemplar awal.</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalBuku" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>

        <form action="../config/proses_buku.php" method="POST" enctype="multipart/form-data" class="bm-form" id="bmForm" autocomplete="off">
            <input type="hidden" name="action" id="bmAction" value="tambah">
            <input type="hidden" name="id" id="bmId" value="">

            <div class="bm-body">
                <div class="bm-err-inline" id="bmInlineErr" hidden role="alert">
                    <?= $ikAlert ?>
                    <span id="bmInlineErrTxt"></span>
                </div>

                <div class="bm-layout">
                    <!-- KIRI: COVER STUDIO -->
                    <div class="bm-cover-col">
                        <label class="bm-lbl" for="cover">Cover Buku</label>

                        <div class="bm-cover-box" id="bmCoverBox" tabindex="0" role="button" aria-label="Pilih atau jatuhkan gambar cover buku">
                            <div class="bm-cover-empty" id="bmCoverEmpty">
                                <div class="bm-cover-empty-ico"><?= $ikUpload ?></div>
                                <b>Unggah Cover</b>
                                <span>Seret gambar ke sini atau klik untuk memilih</span>
                            </div>
                            <img id="bmCoverImg" alt="Pratinjau cover buku" hidden>
                            <span class="bm-cover-hover">Klik untuk ganti cover</span>
                            <span class="bm-cover-badge" id="bmRemovedBadge" hidden>Akan dihapus</span>
                        </div>

                        <input type="file" id="cover" name="cover" class="bm-file-hidden" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <div class="bm-cover-actions">
                            <button type="button" class="bm-btn-upload" id="bmPickCover"><?= $ikUpload ?><span>Pilih / Ganti Cover</span></button>

                            <div class="bm-file-pill" id="bmFilePill" hidden>
                                <span id="bmFileName">cover.jpg</span>
                                <button type="button" class="bm-file-clear" id="bmClearFile" title="Batalkan file terpilih" aria-label="Batalkan file terpilih">&times;</button>
                            </div>

                            <label class="bm-del-switch" id="bmDelCoverWrap" hidden>
                                <input type="checkbox" name="hapus_cover" id="hapus_cover" value="1">
                                <span>Hapus cover saat ini</span>
                            </label>

                            <p class="bm-cover-hint">JPG, PNG, atau WEBP &middot; Maks. 2 MB &middot; Rasio 3:4 (600 &times; 800 px).</p>
                        </div>
                    </div>

                    <!-- KANAN: BIBLIOGRAFI & STOK -->
                    <div class="bm-form-col">
                        <div class="bm-field">
                            <label class="bm-lbl" for="judul">Judul Buku <em>*</em></label>
                            <input type="text" id="judul" name="judul" required placeholder="Masukkan judul buku secara lengkap..." autocomplete="off">
                        </div>

                        <div class="bm-row2">
                            <div class="bm-field">
                                <label class="bm-lbl" for="pengarang">Pengarang / Penulis</label>
                                <input type="text" id="pengarang" name="pengarang" placeholder="Nama penulis..." autocomplete="off">
                            </div>
                            <div class="bm-field">
                                <label class="bm-lbl" for="penerbit">Penerbit</label>
                                <input type="text" id="penerbit" name="penerbit" placeholder="Nama penerbit..." autocomplete="off">
                            </div>
                        </div>

                        <div class="bm-row2">
                            <div class="bm-field">
                                <label class="bm-lbl" for="tahun_terbit">Tahun Terbit</label>
                                <input type="number" id="tahun_terbit" name="tahun_terbit" min="1900" max="2100" placeholder="Contoh: 2024" autocomplete="off">
                            </div>
                            <div class="bm-field">
                                <label class="bm-lbl" for="kategori_id">Kategori Buku <em>*</em></label>
                                <select id="kategori_id" name="kategori_id" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategoriList as $kat): ?>
                                        <option value="<?= (int) $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Eksemplar Awal (Mode Tambah) -->
                        <div class="bm-copies" id="bmCopiesWrap">
                            <div class="bm-copies-top">
                                <label class="bm-lbl" for="jumlah_eksemplar" style="margin-bottom:0;">Jumlah Eksemplar Awal</label>
                                <div class="bm-copies-ctrl">
                                    <div class="bm-stepper">
                                        <button type="button" id="bmQtyMinus" aria-label="Kurangi jumlah eksemplar">&minus;</button>
                                        <input type="number" id="jumlah_eksemplar" name="jumlah_eksemplar" min="0" max="100" value="1" required autocomplete="off">
                                        <button type="button" id="bmQtyPlus" aria-label="Tambah jumlah eksemplar">+</button>
                                    </div>
                                    <div class="bm-qchips" id="bmQtyPresets" aria-label="Pilihan cepat eksemplar">
                                        <button type="button" data-qty="0">0</button>
                                        <button type="button" data-qty="1" class="is-on">1</button>
                                        <button type="button" data-qty="3">3</button>
                                        <button type="button" data-qty="5">5</button>
                                        <button type="button" data-qty="10">10</button>
                                    </div>
                                </div>
                            </div>
                            <p class="bm-copies-info" id="copiesInfo">
                                Sistem akan menerbitkan <b>1</b> kode QR unik, siap dicetak dan ditempel pada buku fisik.
                            </p>
                        </div>

                        <!-- Info Stok (Mode Edit) -->
                        <div class="bm-stock-note" id="bmEditStockNote" hidden>
                            <span id="bmStockSummary">Inventaris fisik: <b>0</b> tersedia dari <b>0</b> eksemplar</span>
                            <a href="#" id="bmLinkKelolaQr">Kelola Eksemplar &amp; QR &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bm-foot">
                <span class="bm-foot-hint"><em>*</em> Wajib diisi</span>
                <div class="bm-foot-actions">
                    <button type="button" class="kc-cancel" data-bm-close="modalBuku">Batal</button>
                    <button type="submit" class="kc-save" id="bmSubmitBtn">Tambah Buku &amp; Terbitkan QR</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS BUKU -->
<div class="bm-backdrop" id="modalHapusBuku" hidden role="dialog" aria-modal="true" aria-labelledby="bmDelTitle">
    <div class="bm-dialog bm-sm">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico danger"><?= $ikHapus ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmDelTitle">Hapus Judul Buku</h2>
                    <p>Konfirmasi penghapusan koleksi</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalHapusBuku" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <div class="bm-body">
            <p class="bm-del-text">
                Apakah Anda yakin ingin menghapus judul buku <strong id="bmDelJudul">-</strong>? Pastikan semua eksemplar fisiknya telah dihapus terlebih dahulu.
            </p>
        </div>
        <div class="bm-foot">
            <div class="bm-foot-actions">
                <button type="button" class="kc-cancel" data-bm-close="modalHapusBuku">Batal</button>
                <a href="#" class="bm-btn-danger" id="bmDelConfirm">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL IMPORT CSV -->
<div class="bm-backdrop" id="modalImportCsv" hidden role="dialog" aria-modal="true" aria-labelledby="bmImportTitle">
    <div class="bm-dialog bm-sm">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico"><?= $ikUpload ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmImportTitle">Import Buku dari CSV</h2>
                    <p>Unggah spreadsheet untuk menambah banyak buku sekaligus.</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalImportCsv" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <form action="../config/proses_import_buku.php" method="POST" enctype="multipart/form-data" class="bm-form" autocomplete="off">
            <div class="bm-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:13px; line-height:1.5; color:#475569;">
                    <strong style="color:#1e293b; display:block; margin-bottom:4px;">Petunjuk Pengisian CSV:</strong>
                    Format kolom harus sesuai template: <em>judul, pengarang, penerbit, tahun_terbit, kategori, jumlah_eksemplar</em>.
                    <div style="margin-top:8px;">
                        <a href="../config/proses_import_buku.php?template=1" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:6px 12px; background:#e0f2fe; color:#0284c7; border:none; text-decoration:none; border-radius:6px; font-weight:600;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download Template CSV
                        </a>
                    </div>
                </div>

                <div class="bm-field">
                    <label class="bm-lbl" for="file_csv">Pilih File CSV <em>*</em></label>
                    <input type="file" id="file_csv" name="file_csv" accept=".csv,text/csv" required style="padding:8px; border:1px dashed #cbd5e1; width:100%; border-radius:6px; background:#fff;">
                </div>
            </div>
            <div class="bm-foot">
                <span class="bm-foot-hint">Maks. 5 MB (.csv)</span>
                <div class="bm-foot-actions">
                    <button type="button" class="kc-cancel" data-bm-close="modalImportCsv">Batal</button>
                    <button type="submit" class="kc-save">Unggah &amp; Proses Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$initialJson = json_encode($initialModalPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ikBukuJson  = json_encode($ikBuku);
$ikEditJson  = json_encode($ikEdit);

$extraScripts = <<<HTML
<script>
(function () {
    const INITIAL_MODAL = {$initialJson};
    const SVG_BUKU = {$ikBukuJson};
    const SVG_EDIT = {$ikEditJson};

    const modalBuku    = document.getElementById('modalBuku');
    const modalHapus   = document.getElementById('modalHapusBuku');
    const modalImport  = document.getElementById('modalImportCsv');
    const btnImportBuku = document.getElementById('btnImportBuku');
    const bmForm       = document.getElementById('bmForm');
    const bmTitle      = document.getElementById('bmTitle');
    const bmSubtitle   = document.getElementById('bmSubtitle');
    const bmHeadIco    = document.getElementById('bmHeadIco');
    const bmKodeChip   = document.getElementById('bmKodeChip');
    const bmAction     = document.getElementById('bmAction');
    const bmId         = document.getElementById('bmId');
    const bmSubmitBtn  = document.getElementById('bmSubmitBtn');
    const bmInlineErr  = document.getElementById('bmInlineErr');
    const bmInlineErrTxt = document.getElementById('bmInlineErrTxt');

    const inpJudul     = document.getElementById('judul');
    const inpPengarang = document.getElementById('pengarang');
    const inpPenerbit  = document.getElementById('penerbit');
    const inpTahun     = document.getElementById('tahun_terbit');
    const selKategori  = document.getElementById('kategori_id');

    const bmCopiesWrap   = document.getElementById('bmCopiesWrap');
    const inpJml         = document.getElementById('jumlah_eksemplar');
    const copiesInfo     = document.getElementById('copiesInfo');
    const qtyMinus       = document.getElementById('bmQtyMinus');
    const qtyPlus        = document.getElementById('bmQtyPlus');
    const qtyPresets     = document.querySelectorAll('#bmQtyPresets button');

    const bmEditStockNote = document.getElementById('bmEditStockNote');
    const bmStockSummary  = document.getElementById('bmStockSummary');
    const bmLinkKelolaQr  = document.getElementById('bmLinkKelolaQr');

    // Cover controls
    const inpCover      = document.getElementById('cover');
    const bmCoverBox    = document.getElementById('bmCoverBox');
    const bmCoverEmpty  = document.getElementById('bmCoverEmpty');
    const bmCoverImg    = document.getElementById('bmCoverImg');
    const bmRemovedBadge = document.getElementById('bmRemovedBadge');
    const bmPickCover   = document.getElementById('bmPickCover');
    const bmFilePill    = document.getElementById('bmFilePill');
    const bmFileName    = document.getElementById('bmFileName');
    const bmClearFile   = document.getElementById('bmClearFile');
    const bmDelWrap     = document.getElementById('bmDelCoverWrap');
    const chkDelCover   = document.getElementById('hapus_cover');

    let savedCoverUrl = '';

    function formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showErr(msg) {
        if (!bmInlineErr || !bmInlineErrTxt) return;
        if (msg) {
            bmInlineErrTxt.textContent = msg;
            bmInlineErr.hidden = false;
        } else {
            bmInlineErr.hidden = true;
            bmInlineErrTxt.textContent = '';
        }
    }

    function renderCoverPreview(srcUrl) {
        if (srcUrl) {
            bmCoverImg.src = srcUrl;
            bmCoverImg.hidden = false;
            bmCoverEmpty.hidden = true;
            bmCoverBox.classList.add('has-img');
        } else {
            bmCoverImg.removeAttribute('src');
            bmCoverImg.hidden = true;
            bmCoverEmpty.hidden = false;
            bmCoverBox.classList.remove('has-img');
        }
    }

    function syncRemoveCoverVisual() {
        const removing = Boolean(chkDelCover && !bmDelWrap.hidden && chkDelCover.checked);
        bmCoverBox.classList.toggle('is-removed', removing);
        if (bmRemovedBadge) bmRemovedBadge.hidden = !removing;
    }

    function resetSelectedFile() {
        if (inpCover) inpCover.value = '';
        if (bmFilePill) bmFilePill.hidden = true;
        renderCoverPreview(savedCoverUrl);
        syncRemoveCoverVisual();
    }

    function applyFilePreview(file) {
        if (!file) {
            resetSelectedFile();
            return;
        }
        if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
            showErr('Format cover harus JPG, PNG, atau WEBP.');
            resetSelectedFile();
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showErr('Ukuran gambar cover maksimal 2 MB.');
            resetSelectedFile();
            return;
        }
        showErr('');
        if (chkDelCover) chkDelCover.checked = false;
        syncRemoveCoverVisual();

        const reader = new FileReader();
        reader.onload = function (ev) {
            renderCoverPreview(ev.target.result);
            if (bmFileName) bmFileName.textContent = file.name + ' (' + formatSize(file.size) + ')';
            if (bmFilePill) bmFilePill.hidden = false;
        };
        reader.readAsDataURL(file);
    }

    function syncCopiesUI(val) {
        let n = parseInt(val, 10);
        if (isNaN(n) || n < 0) n = 0;
        if (n > 100) n = 100;
        if (inpJml) inpJml.value = n;

        qtyPresets.forEach(btn => {
            btn.classList.toggle('is-on', parseInt(btn.dataset.qty, 10) === n);
        });

        if (copiesInfo) {
            copiesInfo.innerHTML = n === 0
                ? 'Tidak ada eksemplar fisik dibuat sekarang. Bisa ditambahkan kapan saja di menu <b>Kelola QR</b>.'
                : 'Sistem akan menerbitkan <b>' + n + '</b> kode QR unik, siap dicetak dan ditempel pada buku fisik.';
        }

        if (bmAction && bmAction.value === 'tambah' && bmSubmitBtn) {
            bmSubmitBtn.textContent = n > 0 ? 'Tambah Buku & Terbitkan QR' : 'Simpan Judul Buku';
        }
    }

    function lockBody() {
        const anyOpen = (modalBuku && !modalBuku.hidden) || (modalHapus && !modalHapus.hidden) || (modalImport && !modalImport.hidden);
        document.body.classList.toggle('bm-lock', anyOpen);
    }

    function cleanUrlIfModalParams() {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit') || url.searchParams.has('error') || url.searchParams.has('pesan')) {
                url.searchParams.delete('edit');
                url.searchParams.delete('error');
                url.searchParams.delete('pesan');
                window.history.replaceState({}, '', url.toString());
            }
        } catch (e) {}
    }

    function openBookModal(opts) {
        const cfg = opts || {};
        const isEdit = cfg.mode === 'edit';

        bmForm.reset();
        showErr(cfg.error || '');
        if (bmFilePill) bmFilePill.hidden = true;

        if (isEdit) {
            bmAction.value = 'edit';
            bmId.value = cfg.id || '';
            bmTitle.textContent = 'Edit Data Buku';
            bmSubtitle.textContent = 'Perbarui detail bibliografi atau ganti gambar cover koleksi.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_EDIT;
            if (bmKodeChip) {
                bmKodeChip.textContent = cfg.kode_buku || '';
                bmKodeChip.hidden = !cfg.kode_buku;
            }

            inpJudul.value     = cfg.judul || '';
            inpPengarang.value = cfg.pengarang || '';
            inpPenerbit.value  = cfg.penerbit || '';
            inpTahun.value     = cfg.tahun_terbit || '';
            selKategori.value  = cfg.kategori_id || '';

            savedCoverUrl = cfg.cover_url || '';
            renderCoverPreview(savedCoverUrl);

            if (bmDelWrap && chkDelCover) {
                chkDelCover.checked = false;
                bmDelWrap.hidden = !savedCoverUrl;
            }
            syncRemoveCoverVisual();

            bmCopiesWrap.hidden = true;
            if (inpJml) inpJml.required = false;

            bmEditStockNote.hidden = false;
            const ada = cfg.total_tersedia ?? 0;
            const tot = cfg.total_eksemplar ?? 0;
            if (bmStockSummary) {
                bmStockSummary.innerHTML = 'Stok fisik: <b>' + ada + '</b> tersedia dari <b>' + tot + '</b> eksemplar';
            }
            if (bmLinkKelolaQr) {
                bmLinkKelolaQr.href = 'buku_item.php?buku_id=' + encodeURIComponent(cfg.id || '');
            }
            bmSubmitBtn.textContent = 'Simpan Perubahan';
        } else {
            bmAction.value = 'tambah';
            bmId.value = '';
            bmTitle.textContent = 'Tambah Judul Buku';
            bmSubtitle.textContent = 'Lengkapi metadata bibliografi dan atur jumlah eksemplar fisik awal.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_BUKU;
            if (bmKodeChip) bmKodeChip.hidden = true;

            savedCoverUrl = '';
            renderCoverPreview('');
            if (bmDelWrap && chkDelCover) {
                chkDelCover.checked = false;
                bmDelWrap.hidden = true;
            }
            syncRemoveCoverVisual();

            bmEditStockNote.hidden = true;
            bmCopiesWrap.hidden = false;
            if (inpJml) inpJml.required = true;
            syncCopiesUI(1);
        }

        modalBuku.hidden = false;
        lockBody();
        setTimeout(() => inpJudul.focus(), 40);
    }

    function closeModal(el) {
        if (!el) return;
        el.hidden = true;
        lockBody();
        if (el === modalBuku) cleanUrlIfModalParams();
    }

    // Trigger Tambah Buku
    const btnTambah = document.getElementById('btnTambahBuku');
    const linkEmptyTambah = document.getElementById('linkEmptyTambah');
    if (btnTambah) btnTambah.addEventListener('click', () => openBookModal({ mode: 'tambah' }));
    if (linkEmptyTambah) linkEmptyTambah.addEventListener('click', e => {
        e.preventDefault();
        openBookModal({ mode: 'tambah' });
    });

    // Trigger Import CSV
    if (btnImportBuku && modalImport) {
        btnImportBuku.addEventListener('click', () => {
            modalImport.hidden = false;
            lockBody();
        });
    }

    // Trigger Edit & Hapus Buku dari tabel
    document.addEventListener('click', e => {
        const editBtn = e.target.closest('.js-edit-buku');
        if (editBtn) {
            try {
                const payload = JSON.parse(editBtn.dataset.book || '{}');
                openBookModal(payload);
            } catch (err) {}
            return;
        }

        const delBtn = e.target.closest('.js-hapus-buku');
        if (delBtn && modalHapus) {
            const delJudul = document.getElementById('bmDelJudul');
            const delConfirm = document.getElementById('bmDelConfirm');
            if (delJudul) delJudul.textContent = delBtn.dataset.judul || 'buku ini';
            if (delConfirm) delConfirm.href = delBtn.dataset.href || '#';
            modalHapus.hidden = false;
            lockBody();
        }
    });

    // Tutup modal
    document.querySelectorAll('[data-bm-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(document.getElementById(btn.dataset.bmClose)));
    });
    [modalBuku, modalHapus, modalImport].forEach(backdrop => {
        if (!backdrop) return;
        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) closeModal(backdrop);
        });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (modalHapus && !modalHapus.hidden) closeModal(modalHapus);
            else if (modalImport && !modalImport.hidden) closeModal(modalImport);
            else if (modalBuku && !modalBuku.hidden) closeModal(modalBuku);
        }
    });

    // Cover picker, drag & drop, dan switch hapus cover
    if (bmPickCover) bmPickCover.addEventListener('click', () => inpCover && inpCover.click());
    if (bmCoverBox) {
        bmCoverBox.addEventListener('click', () => inpCover && inpCover.click());
        bmCoverBox.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                inpCover && inpCover.click();
            }
        });
        ['dragenter', 'dragover'].forEach(evt => {
            bmCoverBox.addEventListener(evt, e => {
                e.preventDefault();
                bmCoverBox.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            bmCoverBox.addEventListener(evt, e => {
                e.preventDefault();
                bmCoverBox.classList.remove('is-drag');
            });
        });
        bmCoverBox.addEventListener('drop', e => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length || !inpCover) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                inpCover.files = dt.files;
                applyFilePreview(files[0]);
            } catch (err) {
                applyFilePreview(files[0]);
            }
        });
    }
    if (inpCover) {
        inpCover.addEventListener('change', () => applyFilePreview(inpCover.files[0]));
    }
    if (bmClearFile) {
        bmClearFile.addEventListener('click', () => resetSelectedFile());
    }
    if (chkDelCover) {
        chkDelCover.addEventListener('change', () => {
            if (chkDelCover.checked && inpCover && inpCover.files.length) {
                resetSelectedFile();
                chkDelCover.checked = true;
            }
            syncRemoveCoverVisual();
        });
    }

    // Stepper & preset jumlah eksemplar awal
    if (qtyMinus) qtyMinus.addEventListener('click', () => syncCopiesUI((parseInt(inpJml.value, 10) || 0) - 1));
    if (qtyPlus)  qtyPlus.addEventListener('click', () => syncCopiesUI((parseInt(inpJml.value, 10) || 0) + 1));
    qtyPresets.forEach(btn => btn.addEventListener('click', () => syncCopiesUI(btn.dataset.qty)));
    if (inpJml) {
        inpJml.addEventListener('input', () => syncCopiesUI(inpJml.value));
        inpJml.addEventListener('blur', () => syncCopiesUI(inpJml.value));
    }

    // Filter kategori tabel
    const chips = document.querySelectorAll('#chips button');
    const rows  = document.querySelectorAll('#tabelBuku tbody tr');
    const meta  = document.getElementById('meta');
    const awal  = meta ? meta.innerHTML : '';
    chips.forEach(c => {
        c.addEventListener('click', () => {
            const kat = c.dataset.kat;
            let n = 0;
            chips.forEach(x => x.setAttribute('aria-pressed', x === c ? 'true' : 'false'));
            rows.forEach(r => {
                const cocok = !kat || r.dataset.kat === kat;
                r.hidden = !cocok;
                if (cocok) n++;
            });
            if (meta) meta.innerHTML = kat ? '<b>' + n + '</b> judul dalam kategori ini' : awal;
        });
    });

    // Buka modal otomatis jika ada ?edit= atau validasi redirect ?error=
    if (INITIAL_MODAL) {
        openBookModal(INITIAL_MODAL);
    }
})();
</script>
HTML;
?>
<?php require 'includes/footer.php'; ?>
