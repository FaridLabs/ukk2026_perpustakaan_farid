<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';

$keyword = trim($_GET['keyword'] ?? '');

$sql = "
    SELECT kategori.*, COUNT(buku.id) AS total_buku
    FROM kategori
    LEFT JOIN buku ON buku.kategori_id = kategori.id
";
$params = [];
if (!empty($keyword)) {
    $sql .= " WHERE kategori.nama_kategori LIKE :kw";
    $params[':kw'] = "%$keyword%";
}
$sql .= " GROUP BY kategori.id ORDER BY kategori.nama_kategori ASC";

$stmt = $koneksi->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$data = $stmt->fetchAll();

// Hitung total buku di seluruh kategori
$sumBuku = array_sum(array_map(fn($r) => (int) $r['total_buku'], $data));

// Ambil data edit jika dibuka via ?edit=ID
$editData = null;
if (!empty($_GET['edit'])) {
    $stmt = $koneksi->prepare("
        SELECT kategori.*, COUNT(buku.id) AS total_buku
        FROM kategori
        LEFT JOIN buku ON buku.kategori_id = kategori.id
        WHERE kategori.id = :id
        GROUP BY kategori.id
        LIMIT 1
    ");
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $editData = $stmt->fetch() ?: null;
}

$errCode = $_GET['error'] ?? '';
$errMsg  = '';
if ($errCode === 'empty') {
    $errMsg = 'Nama kategori tidak boleh dibiarkan kosong.';
} elseif ($errCode === 'ada_buku') {
    $errMsg = 'Kategori tidak dapat dihapus karena masih terkait dengan data buku aktif. Pindahkan kategori buku tersebut terlebih dahulu.';
}

$initialModalPayload = null;
if ($editData) {
    $initialModalPayload = [
        'mode'          => 'edit',
        'id'            => (int) $editData['id'],
        'nama_kategori' => (string) $editData['nama_kategori'],
        'total_buku'    => (int) $editData['total_buku'],
        'error'         => $errCode === 'empty' ? $errMsg : '',
    ];
} elseif ($errCode === 'empty') {
    $initialModalPayload = [
        'mode'  => 'tambah',
        'error' => $errMsg,
    ];
}

$warnaKat  = [['#DDF3E8', '#1B7A55'], ['#E3ECFD', '#2F63D8'], ['#FDEBD3', '#B4600A'], ['#ECE5FB', '#7140D0'], ['#FBE1E1', '#C23B3B'], ['#E1F1F4', '#17717F']];
$warnaDari = fn(string $n) => $warnaKat[crc32($n) % count($warnaKat)];

$svgK    = fn(string $d): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$ikEdit  = $svgK('<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>');
$ikHapus = $svgK('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>');
$ikClose = $svgK('<path d="M18 6 6 18M6 6l12 12"/>');
$ikAlert = $svgK('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
$ikTag   = $svgK('<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7.5" cy="7.5" r=".75"/>');

$pageTitle   = 'Kelola Kategori Buku';
$currentPage = 'kategori';
$extraHead   = '<link rel="stylesheet" href="../assets/css/kelola.css?v=4">';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-head">
    <div>
        <h1>Kelola Kategori Buku</h1>
        <p>Klasifikasi dan pengelompokan kategori koleksi buku pada katalog perpustakaan.</p>
    </div>
    <div class="d-act">
        <button type="button" class="btn-d main" id="btnTambahKategori">
            <?= ic('plus') ?><span>Tambah Kategori</span>
        </button>
    </div>
</div>

<!-- NOTIFIKASI -->
<?php if ($errCode === 'ada_buku'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif (!empty($errMsg) && !$initialModalPayload): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Data kategori berhasil diperbarui.</div></div>
<?php endif; ?>

<!-- SEARCH BAR -->
<div class="kc-bar">
    <form action="kategori.php" method="GET" class="kc-search" autocomplete="off">
        <div class="ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="keyword" placeholder="Cari nama kategori koleksi..." value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" aria-label="Cari kategori" autocomplete="off">
        </div>
        <button type="submit">Cari</button>
        <?php if (!empty($keyword)): ?><a href="kategori.php" class="kc-reset">Reset</a><?php endif; ?>
    </form>
    <div class="kc-meta">
        <b><?= count($data) ?></b> kategori &middot; <b><?= $sumBuku ?></b> judul buku terkait
    </div>
</div>

<!-- DAFTAR KATEGORI -->
<section class="act" aria-labelledby="hDaftar">
    <div class="act-top">
        <div class="panel-ico"><?= ic('tag') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hDaftar">Daftar Kategori Koleksi</h2>
            <p><?= $keyword !== '' ? 'Hasil pencarian "' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '".' : 'Kategori membantu siswa dan petugas menemukan buku berdasarkan bidang ilmu.' ?></p>
        </div>
        <div class="kc-meta" id="meta">
            <b><?= count($data) ?></b> kategori terdaftar
        </div>
    </div>

    <div class="tbl kc">
        <?php if (empty($data)): ?>
            <div class="kc-empty">
                <b><?= $keyword !== '' ? 'Tidak ada kategori yang cocok' : 'Belum ada kategori terdaftar' ?></b>
                <?php if ($keyword !== ''): ?>
                    Coba kata kunci lain atau <a href="kategori.php">reset pencarian</a>.
                <?php else: ?>
                    Tambahkan kategori pertama lewat tombol <a href="#" id="linkEmptyTambah">Tambah Kategori</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table id="tabelKategori">
            <thead>
                <tr>
                    <th style="width:60px;">No</th>
                    <th style="width:50%;">Nama Kategori Koleksi</th>
                    <th style="width:28%;">Jumlah Buku Terkait</th>
                    <th style="text-align:right;width:16%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $row):
                $nama = $row['nama_kategori'];
                $totBuku = (int) $row['total_buku'];
                [$bgKat, $fgKat] = $warnaDari($nama);

                $rowPayload = json_encode([
                    'mode'          => 'edit',
                    'id'            => (int) $row['id'],
                    'nama_kategori' => (string) $nama,
                    'total_buku'    => $totBuku,
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="kat" style="background:<?= $bgKat ?>;color:<?= $fgKat ?>;font-weight:700;">
                                <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php if ($totBuku > 0): ?>
                            <a href="buku.php?keyword=<?= urlencode($nama) ?>" class="kb" style="height:28px;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;gap:6px;" title="Lihat buku dalam kategori ini">
                                <strong><?= $totBuku ?></strong> judul buku &rarr;
                            </a>
                        <?php else: ?>
                            <span style="font-size:12.5px;color:var(--muted);">Belum ada buku</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div class="kb-row">
                            <button type="button"
                                    class="kb ico js-edit-kategori"
                                    data-kategori="<?= htmlspecialchars($rowPayload, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Edit nama kategori"
                                    aria-label="Edit <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>">
                                <?= $ikEdit ?>
                            </button>
                            <?php if ($totBuku > 0): ?>
                                <button type="button"
                                        class="kb ico"
                                        style="opacity:0.45;cursor:not-allowed;"
                                        title="Kategori tidak dapat dihapus karena masih menampung <?= $totBuku ?> buku"
                                        onclick="alert('Kategori \'<?= htmlspecialchars(addslashes($nama)) ?>\' masih terkait dengan <?= $totBuku ?> buku. Pindahkan kategori buku tersebut terlebih dahulu sebelum menghapus.');">
                                    <?= $ikHapus ?>
                                </button>
                            <?php else: ?>
                                <button type="button"
                                        class="kb ico danger js-hapus-kategori"
                                        data-href="../config/proses_kategori.php?action=hapus&amp;id=<?= (int) $row['id'] ?>"
                                        data-nama="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                                        title="Hapus kategori"
                                        aria-label="Hapus <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $ikHapus ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL TAMBAH & EDIT KATEGORI -->
<div class="bm-backdrop" id="modalKategori" hidden role="dialog" aria-modal="true" aria-labelledby="bmTitle">
    <div class="bm-dialog bm-sm">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico" id="bmHeadIco"><?= $ikTag ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmTitle">Tambah Kategori Baru</h2>
                    <p id="bmSubtitle">Masukkan nama kategori untuk mengelompokkan koleksi buku.</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalKategori" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>

        <form action="../config/proses_kategori.php" method="POST" class="bm-form" id="bmFormKategori" autocomplete="off">
            <input type="hidden" name="action" id="bmAction" value="tambah">
            <input type="hidden" name="id" id="bmId" value="">

            <div class="bm-body">
                <div class="bm-err-inline" id="bmInlineErr" hidden role="alert">
                    <?= $ikAlert ?>
                    <span id="bmInlineErrTxt"></span>
                </div>

                <div class="bm-form-col">
                    <div class="bm-field">
                        <label class="bm-lbl" for="nama_kategori">Nama Kategori <em>*</em></label>
                        <input type="text" id="nama_kategori" name="nama_kategori" required placeholder="Contoh: Novel, Sains, Teknologi, Sejarah..." autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="bm-foot">
                <span class="bm-foot-hint"><em>*</em> Wajib diisi</span>
                <div class="bm-foot-actions">
                    <button type="button" class="kc-cancel" data-bm-close="modalKategori">Batal</button>
                    <button type="submit" class="kc-save" id="bmSubmitBtn">Tambah Kategori</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS KATEGORI -->
<div class="bm-backdrop" id="modalHapusKategori" hidden role="dialog" aria-modal="true" aria-labelledby="bmDelTitle">
    <div class="bm-dialog bm-sm">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico danger"><?= $ikHapus ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmDelTitle">Hapus Kategori</h2>
                    <p>Konfirmasi penghapusan kategori</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalHapusKategori" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <div class="bm-body">
            <p class="bm-del-text">
                Apakah Anda yakin ingin menghapus kategori <strong id="bmDelNama">-</strong>?
            </p>
        </div>
        <div class="bm-foot">
            <div class="bm-foot-actions" style="margin-left:auto;">
                <button type="button" class="kc-cancel" data-bm-close="modalHapusKategori">Batal</button>
                <a href="#" class="bm-btn-danger" id="bmDelConfirm">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<?php
$initialKatJson = json_encode($initialModalPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ikTagJson      = json_encode($ikTag);
$ikEditJson     = json_encode($ikEdit);

$extraScripts = <<<HTML
<script>
(function () {
    const INITIAL_KAT = {$initialKatJson};
    const SVG_TAG     = {$ikTagJson};
    const SVG_EDIT    = {$ikEditJson};

    const modalKategori = document.getElementById('modalKategori');
    const modalHapus    = document.getElementById('modalHapusKategori');
    const bmForm        = document.getElementById('bmFormKategori');
    const bmTitle       = document.getElementById('bmTitle');
    const bmSubtitle    = document.getElementById('bmSubtitle');
    const bmHeadIco     = document.getElementById('bmHeadIco');
    const bmAction      = document.getElementById('bmAction');
    const bmId          = document.getElementById('bmId');
    const bmSubmitBtn   = document.getElementById('bmSubmitBtn');
    const inpNama       = document.getElementById('nama_kategori');
    const bmInlineErr   = document.getElementById('bmInlineErr');
    const bmInlineErrTxt= document.getElementById('bmInlineErrTxt');

    function lockBody() {
        const anyOpen = (modalKategori && !modalKategori.hidden) || (modalHapus && !modalHapus.hidden);
        document.body.classList.toggle('bm-lock', anyOpen);
    }

    function cleanUrlParams() {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit') || url.searchParams.has('error')) {
                url.searchParams.delete('edit');
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url.toString());
            }
        } catch (e) {}
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

    function openKategoriModal(opts) {
        const cfg = opts || {};
        const isEdit = cfg.mode === 'edit';

        bmForm.reset();
        showErr(cfg.error || '');

        if (isEdit) {
            bmAction.value = 'edit';
            bmId.value = cfg.id || '';
            bmTitle.textContent = 'Edit Nama Kategori';
            bmSubtitle.textContent = 'Perbarui nama kelompok klasifikasi koleksi buku.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_EDIT;
            inpNama.value = cfg.nama_kategori || '';
            bmSubmitBtn.textContent = 'Simpan Perubahan';
        } else {
            bmAction.value = 'tambah';
            bmId.value = '';
            bmTitle.textContent = 'Tambah Kategori Baru';
            bmSubtitle.textContent = 'Masukkan nama kategori untuk mengelompokkan koleksi buku.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_TAG;
            bmSubmitBtn.textContent = 'Tambah Kategori';
        }

        modalKategori.hidden = false;
        lockBody();
        setTimeout(() => inpNama.focus(), 40);
    }

    function closeModal(el) {
        if (!el) return;
        el.hidden = true;
        lockBody();
        cleanUrlParams();
    }

    // Trigger Tambah Kategori
    const btnTambah = document.getElementById('btnTambahKategori');
    const linkEmpty = document.getElementById('linkEmptyTambah');
    if (btnTambah) btnTambah.addEventListener('click', () => openKategoriModal({ mode: 'tambah' }));
    if (linkEmpty) linkEmpty.addEventListener('click', (e) => {
        e.preventDefault();
        openKategoriModal({ mode: 'tambah' });
    });

    // Delegasi Edit & Hapus
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.js-edit-kategori');
        if (editBtn) {
            try {
                const payload = JSON.parse(editBtn.dataset.kategori || '{}');
                openKategoriModal(payload);
            } catch (err) {}
            return;
        }

        const delBtn = e.target.closest('.js-hapus-kategori');
        if (delBtn && modalHapus) {
            const delNama = document.getElementById('bmDelNama');
            const delConf = document.getElementById('bmDelConfirm');
            if (delNama) delNama.textContent = delBtn.dataset.nama || '-';
            if (delConf) delConf.href = delBtn.dataset.href || '#';
            modalHapus.hidden = false;
            lockBody();
        }
    });

    // Tutup dialog
    document.querySelectorAll('[data-bm-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(document.getElementById(btn.dataset.bmClose)));
    });

    [modalKategori, modalHapus].forEach(backdrop => {
        if (!backdrop) return;
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal(backdrop);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalHapus && !modalHapus.hidden) closeModal(modalHapus);
            else if (modalKategori && !modalKategori.hidden) closeModal(modalKategori);
        }
    });

    // Buka otomatis jika ada ?edit=ID atau ?error=
    if (INITIAL_KAT) {
        openKategoriModal(INITIAL_KAT);
    }
})();
</script>
HTML;

require 'includes/footer.php';
?>
