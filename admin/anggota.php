<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';

$keyword = trim($_GET['keyword'] ?? '');

$sql = "
    SELECT anggota.*,
           (SELECT COUNT(*) FROM transaksi WHERE transaksi.anggota_id = anggota.id AND transaksi.status = 'dipinjam') AS pinjaman_aktif
    FROM anggota 
    WHERE 1=1
";
$params = [];

if (!empty($keyword)) {
    $sql .= " AND (nama_lengkap LIKE :kw OR nis LIKE :kw OR kelas LIKE :kw OR alamat LIKE :kw OR username LIKE :kw OR kode_rfid LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

$sql .= " ORDER BY nama_lengkap ASC";

$stmt = $koneksi->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$data = $stmt->fetchAll();

// Hitung metrik anggota untuk filter chip dan ringkasan
$totalSiswa    = count($data);
$totalBebas    = 0;
$totalMeminjam = 0;
$totalRfid     = 0;

foreach ($data as $r) {
    if ((int) $r['pinjaman_aktif'] > 0) {
        $totalMeminjam++;
    } else {
        $totalBebas++;
    }
    if (!empty($r['kode_rfid'])) {
        $totalRfid++;
    }
}

// Data edit jika dibuka langsung via ?edit=ID
$editData = null;
if (!empty($_GET['edit'])) {
    $stmt = $koneksi->prepare("
        SELECT anggota.*,
               (SELECT COUNT(*) FROM transaksi WHERE transaksi.anggota_id = anggota.id AND transaksi.status = 'dipinjam') AS pinjaman_aktif
        FROM anggota WHERE id = :id LIMIT 1
    ");
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $editData = $stmt->fetch() ?: null;
}

// Mode pairing RFID jika dibuka langsung via ?tap_rfid=ID
$rfidTarget = null;
if (!empty($_GET['tap_rfid'])) {
    $stmt = $koneksi->prepare("SELECT id, nis, nama_lengkap, kelas, kode_rfid FROM anggota WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $_GET['tap_rfid']);
    $stmt->execute();
    $rfidTarget = $stmt->fetch() ?: null;
}

$errCode = $_GET['error'] ?? '';
$errMsg  = '';
if ($errCode === 'empty') {
    $errMsg = 'Semua field yang bertanda wajib harus diisi lengkap.';
} elseif ($errCode === 'nis_ada') {
    $errMsg = 'NIS tersebut sudah terdaftar di sistem. Gunakan NIS unik.';
} elseif ($errCode === 'username_ada') {
    $errMsg = 'Username sudah dipakai oleh anggota lain. Silakan pilih username berbeda.';
} elseif ($errCode === 'rfid_ada') {
    $errMsg = 'Kartu RFID ini sudah terdaftar untuk siswa lain. Setiap kartu fisik harus unik.';
} elseif ($errCode === 'sedang_meminjam') {
    $errMsg = 'Anggota tidak dapat dihapus karena masih meminjam buku. Selesaikan pengembalian buku terlebih dahulu.';
} elseif ($errCode === 'ada_transaksi') {
    $errMsg = 'Anggota tidak dapat dihapus karena memiliki riwayat pinjaman aktif.';
}

// Inisialisasi payload modal
$initialModalPayload = null;
if ($editData) {
    $initialModalPayload = [
        'mode'           => 'edit',
        'id'             => (int) $editData['id'],
        'nis'            => (string) $editData['nis'],
        'nama_lengkap'   => (string) $editData['nama_lengkap'],
        'kelas'          => (string) $editData['kelas'],
        'no_hp'          => (string) ($editData['no_hp'] ?? ''),
        'alamat'         => (string) ($editData['alamat'] ?? ''),
        'username'       => (string) $editData['username'],
        'kode_rfid'      => (string) ($editData['kode_rfid'] ?? ''),
        'pinjaman_aktif' => (int) ($editData['pinjaman_aktif'] ?? 0),
        'error'          => in_array($errCode, ['empty', 'nis_ada', 'username_ada'], true) ? $errMsg : '',
    ];
} elseif (in_array($errCode, ['empty', 'nis_ada', 'username_ada'], true)) {
    $initialModalPayload = [
        'mode'  => 'tambah',
        'error' => $errMsg,
    ];
}

$initialRfidPayload = null;
if ($rfidTarget) {
    $initialRfidPayload = [
        'id'           => (int) $rfidTarget['id'],
        'nama_lengkap' => (string) $rfidTarget['nama_lengkap'],
        'nis'          => (string) $rfidTarget['nis'],
        'kode_rfid'    => (string) ($rfidTarget['kode_rfid'] ?? ''),
        'error'        => $errCode === 'rfid_ada' ? $errMsg : '',
    ];
}

$svgK    = fn(string $d): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$ikEdit  = $svgK('<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>');
$ikHapus = $svgK('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>');
$ikClose = $svgK('<path d="M18 6 6 18M6 6l12 12"/>');
$ikAlert = $svgK('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
$ikRfid  = $svgK('<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 9h4M14 12h4M14 15h2"/>');
$ikUser  = $svgK('<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>');

if (!function_exists('avatarInisial')) {
    function avatarInisial(string $nama): string {
        $words = preg_split('/\s+/', trim($nama));
        $in = '';
        if (!empty($words[0])) $in .= mb_substr($words[0], 0, 1);
        if (isset($words[1]) && !empty($words[1])) $in .= mb_substr($words[1], 0, 1);
        return strtoupper($in ?: 'A');
    }
}

$pageTitle   = 'Kelola Anggota Perpustakaan';
$currentPage = 'anggota';
$extraHead   = '<link rel="stylesheet" href="../assets/css/kelola.css?v=4">';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-head">
    <div>
        <h1>Kelola Data Anggota</h1>
        <p>Pendaftaran siswa, akun peminjaman, pairing kartu RFID fisik, dan monitoring status pinjaman aktif.</p>
    </div>
    <div class="d-act">
        <button type="button" class="btn-d main" id="btnTambahAnggota">
            <?= ic('plus') ?><span>Tambah Anggota Siswa</span>
        </button>
    </div>
</div>

<!-- NOTIFIKASI HALAMAN -->
<?php if ($errCode === 'sedang_meminjam'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif ($errCode === 'ada_transaksi'): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php elseif (!empty($errMsg) && !$initialModalPayload && !$initialRfidPayload): ?>
    <div class="kc-al err" role="alert"><?= $ikAlert ?><div><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div></div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'hapus'): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Data anggota berhasil dihapus dari sistem.</div></div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="kc-al ok" role="status"><?= ic('check') ?><div>Data anggota berhasil diperbarui.</div></div>
<?php endif; ?>

<!-- CARI + FILTER STATUS ANGGOTA -->
<div class="kc-bar">
    <form action="anggota.php" method="GET" class="kc-search" autocomplete="off">
        <div class="ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="keyword" placeholder="Cari nama siswa, NIS, kelas, atau alamat..." value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" aria-label="Cari anggota" autocomplete="off">
        </div>
        <button type="submit">Cari</button>
        <?php if (!empty($keyword)): ?><a href="anggota.php" class="kc-reset">Reset</a><?php endif; ?>
    </form>

    <div class="kc-chips" id="chips" role="group" aria-label="Filter status anggota">
        <button type="button" data-filter="all" aria-pressed="true">Semua (<?= $totalSiswa ?>)</button>
        <button type="button" data-filter="bebas" aria-pressed="false">Bebas Pinjaman (<?= $totalBebas ?>)</button>
        <button type="button" data-filter="meminjam" aria-pressed="false">Sedang Meminjam (<?= $totalMeminjam ?>)</button>
        <button type="button" data-filter="rfid" aria-pressed="false">RFID Aktif (<?= $totalRfid ?>)</button>
    </div>
</div>

<!-- DAFTAR ANGGOTA -->
<section class="act" aria-labelledby="hDaftar">
    <div class="act-top">
        <div class="panel-ico"><?= ic('users') ?></div>
        <div class="panel-h" style="display:block">
            <h2 id="hDaftar">Daftar Anggota Perpustakaan</h2>
            <p><?= $keyword !== '' ? 'Hasil pencarian "' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '".' : 'Siswa dapat meminjam buku menggunakan NIS atau kartu RFID fisik.' ?></p>
        </div>
        <div class="kc-meta" id="meta">
            <b><?= $totalSiswa ?></b> siswa &middot; <b><?= $totalMeminjam ?></b> meminjam &middot; <b><?= $totalRfid ?></b> RFID aktif
        </div>
    </div>

    <div class="tbl kc">
        <?php if (empty($data)): ?>
            <div class="kc-empty">
                <b><?= $keyword !== '' ? 'Tidak ada anggota yang cocok' : 'Belum ada anggota terdaftar' ?></b>
                <?php if ($keyword !== ''): ?>
                    Coba kata kunci lain atau <a href="anggota.php">reset pencarian</a>.
                <?php else: ?>
                    Daftarkan siswa pertama lewat tombol <a href="#" id="linkEmptyTambah">Tambah Anggota Siswa</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table id="tabelAnggota">
            <thead>
                <tr>
                    <th style="width:30%;">Siswa &amp; Identitas</th>
                    <th style="width:14%;">Kelas</th>
                    <th style="width:20%;">Alamat</th>
                    <th style="width:14%;">Kartu RFID Fisik</th>
                    <th style="width:12%;">Status Pinjaman</th>
                    <th style="text-align:right;width:10%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $row):
                $pinjamAktif = (int) $row['pinjaman_aktif'];
                $hasRfid     = !empty($row['kode_rfid']);
                $inisial     = avatarInisial($row['nama_lengkap']);

                $rowPayload = json_encode([
                    'mode'           => 'edit',
                    'id'             => (int) $row['id'],
                    'nis'            => (string) $row['nis'],
                    'nama_lengkap'   => (string) $row['nama_lengkap'],
                    'kelas'          => (string) $row['kelas'],
                    'no_hp'          => (string) ($row['no_hp'] ?? ''),
                    'alamat'         => (string) ($row['alamat'] ?? ''),
                    'username'       => (string) $row['username'],
                    'kode_rfid'      => (string) ($row['kode_rfid'] ?? ''),
                    'pinjaman_aktif' => $pinjamAktif,
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

                $rfidPayload = json_encode([
                    'id'           => (int) $row['id'],
                    'nama_lengkap' => (string) $row['nama_lengkap'],
                    'nis'          => (string) $row['nis'],
                    'kode_rfid'    => (string) ($row['kode_rfid'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
                <tr data-status="<?= $pinjamAktif > 0 ? 'meminjam' : 'bebas' ?>" data-rfid="<?= $hasRfid ? 'ada' : 'belum' ?>">
                    <td>
                        <div class="usr-cell">
                            <div class="usr-av"><?= htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="usr-info">
                                <div class="usr-name">
                                    <strong><?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <code><?= htmlspecialchars($row['nis'], ENT_QUOTES, 'UTF-8') ?></code>
                                </div>
                                <div class="usr-sub">
                                    <span>User: <b><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></b></span>
                                    <?php if (!empty($row['no_hp'])): ?>
                                        &bull; <span><?= htmlspecialchars($row['no_hp'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="kat" style="background:#eaf1ee;color:#204b39;"><?= htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td>
                        <?php if (!empty($row['alamat'])): ?>
                            <span style="font-size:13px;color:var(--ink);line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;" title="<?= htmlspecialchars($row['alamat'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($row['alamat'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:13px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasRfid): ?>
                            <div style="display:inline-flex;align-items:center;gap:6px;">
                                <code style="font-size:12px;font-weight:700;background:#eff6f2;color:var(--g);border:1px solid #cfe4d8;padding:3px 8px;border-radius:6px;">
                                    <?= htmlspecialchars($row['kode_rfid'], ENT_QUOTES, 'UTF-8') ?>
                                </code>
                                <button type="button" class="kb ico js-tap-rfid" data-rfid="<?= htmlspecialchars($rfidPayload, ENT_QUOTES, 'UTF-8') ?>" title="Ganti Kartu RFID" aria-label="Ganti Kartu RFID <?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>" style="width:28px;height:28px;">
                                    <?= $ikRfid ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="button" class="kb green js-tap-rfid" data-rfid="<?= htmlspecialchars($rfidPayload, ENT_QUOTES, 'UTF-8') ?>" style="height:28px;padding:0 9px;font-size:12px;">
                                <?= $ikRfid ?><span>+ Tap RFID</span>
                            </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pinjamAktif > 0): ?>
                            <span class="kat" style="background:#fdebd3;color:#b4600a;font-weight:700;">
                                Meminjam (<?= $pinjamAktif ?> buku)
                            </span>
                        <?php else: ?>
                            <span class="kat" style="background:#ddf3e8;color:#1b7a55;font-weight:700;">
                                Bebas Pinjaman
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div class="kb-row">
                            <button type="button"
                                    class="kb ico js-edit-anggota"
                                    data-member="<?= htmlspecialchars($rowPayload, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Edit data anggota"
                                    aria-label="Edit <?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= $ikEdit ?>
                            </button>
                            <?php if ($pinjamAktif > 0): ?>
                                <button type="button"
                                        class="kb ico"
                                        style="opacity:0.45;cursor:not-allowed;"
                                        title="Tidak dapat dihapus saat masih meminjam buku"
                                        onclick="alert('Anggota <?= htmlspecialchars(addslashes($row['nama_lengkap'])) ?> masih memiliki <?= $pinjamAktif ?> buku pinjaman aktif. Selesaikan pengembalian terlebih dahulu.');">
                                    <?= $ikHapus ?>
                                </button>
                            <?php else: ?>
                                <button type="button"
                                        class="kb ico danger js-hapus-anggota"
                                        data-href="../config/proses_anggota.php?action=hapus&amp;id=<?= (int) $row['id'] ?>"
                                        data-nama="<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-nis="<?= htmlspecialchars($row['nis'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Hapus data anggota"
                                        aria-label="Hapus <?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $ikHapus ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="kc-empty" id="filterEmptyMsg" hidden>
            <b>Tidak ada anggota yang cocok dengan filter</b>
            Silakan pilih filter lain atau gunakan pencarian nama siswa.
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL TAMBAH & EDIT ANGGOTA -->
<div class="bm-backdrop" id="modalAnggota" hidden role="dialog" aria-modal="true" aria-labelledby="bmTitle">
    <div class="bm-dialog bm-md">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico" id="bmHeadIco"><?= $ikUser ?></div>
                <div class="bm-head-txt">
                    <div class="bm-head-top">
                        <h2 id="bmTitle">Tambah Anggota Siswa</h2>
                        <span class="bm-kode" id="bmNisChip" hidden></span>
                    </div>
                    <p id="bmSubtitle">Lengkapi identitas siswa untuk membuat akun peminjaman perpustakaan.</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalAnggota" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>

        <form action="../config/proses_anggota.php" method="POST" class="bm-form" id="bmFormAnggota" autocomplete="off">
            <input type="hidden" name="action" id="bmAction" value="tambah">
            <input type="hidden" name="id" id="bmId" value="">

            <div class="bm-body">
                <div class="bm-err-inline" id="bmInlineErr" hidden role="alert">
                    <?= $ikAlert ?>
                    <span id="bmInlineErrTxt"></span>
                </div>

                <div class="bm-form-col">
                    <div class="bm-row2">
                        <div class="bm-field">
                            <label class="bm-lbl" for="nis">NIS (Nomor Induk Siswa) <em>*</em></label>
                            <input type="text" id="nis" name="nis" required placeholder="Contoh: 2425001" autocomplete="off">
                        </div>
                        <div class="bm-field">
                            <label class="bm-lbl" for="nama_lengkap">Nama Lengkap Siswa <em>*</em></label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" required placeholder="Nama lengkap siswa..." autocomplete="off">
                        </div>
                    </div>

                    <div class="bm-row2">
                        <div class="bm-field">
                            <label class="bm-lbl" for="kelas">Kelas <em>*</em></label>
                            <input type="text" id="kelas" name="kelas" required placeholder="Contoh: XII RPL 1" autocomplete="off">
                        </div>
                        <div class="bm-field">
                            <label class="bm-lbl" for="no_hp">No. HP / WhatsApp</label>
                            <input type="text" id="no_hp" name="no_hp" placeholder="Contoh: 08123456789" autocomplete="off">
                        </div>
                    </div>

                    <div class="bm-row2">
                        <div class="bm-field">
                            <label class="bm-lbl" for="username">Username Login <em>*</em></label>
                            <input type="text" id="username" name="username" required placeholder="Username akun siswa..." autocomplete="off">
                        </div>
                        <div class="bm-field">
                            <label class="bm-lbl" for="password">Password <em id="passReqStar">*</em></label>
                            <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="off">
                            <small id="passHint" style="display:block;margin-top:4px;font-size:12px;color:var(--muted);" hidden>Kosongkan bila tidak ingin mengubah password.</small>
                        </div>
                    </div>

                    <div class="bm-field">
                        <label class="bm-lbl" for="alamat">Alamat Lengkap</label>
                        <textarea id="alamat" name="alamat" rows="2" placeholder="Masukkan alamat lengkap siswa (jalan, RT/RW, desa/kecamatan)..." autocomplete="off"></textarea>
                    </div>

                    <div class="bm-stock-note" id="bmMemberInfoNote" hidden>
                        <span id="bmMemberSummary">Status RFID: <b>Belum terpasang</b></span>
                        <a href="#" id="bmLinkTapRfidModal">Pairing Kartu RFID &rarr;</a>
                    </div>
                </div>
            </div>

            <div class="bm-foot">
                <span class="bm-foot-hint"><em>*</em> Wajib diisi</span>
                <div class="bm-foot-actions">
                    <button type="button" class="kc-cancel" data-bm-close="modalAnggota">Batal</button>
                    <button type="submit" class="kc-save" id="bmSubmitBtn">Tambah Anggota Siswa</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PAIRING KARTU RFID FISIK -->
<div class="bm-backdrop" id="modalRfid" hidden role="dialog" aria-modal="true" aria-labelledby="bmRfidTitle">
    <div class="bm-dialog bm-sm" style="max-width:440px;">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico"><?= $ikRfid ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmRfidTitle">Pairing Kartu RFID</h2>
                    <p>Hubungkan kartu fisik pada siswa</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalRfid" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>

        <form action="../config/proses_anggota.php" method="POST" id="formRfid" autocomplete="off">
            <input type="hidden" name="action" value="set_rfid">
            <input type="hidden" name="id" id="rfidAnggotaId" value="">

            <div class="bm-body">
                <div class="bm-err-inline" id="bmRfidErr" hidden role="alert">
                    <?= $ikAlert ?>
                    <span id="bmRfidErrTxt"></span>
                </div>

                <div class="bm-rfid-box">
                    <div class="bm-rfid-target">
                        Anggota: <strong id="rfidSiswaNama">-</strong><br>
                        <span style="font-size:12.5px;color:var(--muted);">NIS: <code id="rfidSiswaNis">-</code></span>
                    </div>

                    <label class="bm-lbl" for="rfidInput" style="text-align:left;">Kode Serial Kartu RFID</label>
                    <input type="text" name="kode_rfid" id="rfidInput" class="bm-rfid-input" placeholder="Tempelkan kartu RFID..." required autocomplete="off">
                    <p class="bm-rfid-hint">Tempelkan kartu pada card reader USB. Input akan terisi dan tersimpan otomatis saat kartu di-tap.</p>
                </div>
            </div>

            <div class="bm-foot">
                <div class="bm-foot-actions" style="width:100%;justify-content:space-between;">
                    <button type="button" class="kc-cancel" data-bm-close="modalRfid">Batal</button>
                    <button type="submit" class="kc-save">Simpan Kartu</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS ANGGOTA -->
<div class="bm-backdrop" id="modalHapusAnggota" hidden role="dialog" aria-modal="true" aria-labelledby="bmDelTitle">
    <div class="bm-dialog bm-sm">
        <div class="bm-head">
            <div class="bm-head-main">
                <div class="bm-ico danger"><?= $ikHapus ?></div>
                <div class="bm-head-txt">
                    <h2 id="bmDelTitle">Hapus Data Anggota</h2>
                    <p>Konfirmasi penghapusan siswa</p>
                </div>
            </div>
            <button type="button" class="bm-close" data-bm-close="modalHapusAnggota" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <div class="bm-body">
            <p class="bm-del-text">
                Apakah Anda yakin ingin menghapus anggota <strong id="bmDelNama">-</strong> (NIS: <code id="bmDelNis">-</code>)? Akun peminjaman siswa ini akan dihapus secara permanen.
            </p>
        </div>
        <div class="bm-foot">
            <div class="bm-foot-actions" style="margin-left:auto;">
                <button type="button" class="kc-cancel" data-bm-close="modalHapusAnggota">Batal</button>
                <a href="#" class="bm-btn-danger" id="bmDelConfirm">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<?php
$initialMemberJson = json_encode($initialModalPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$initialRfidJson   = json_encode($initialRfidPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ikUserJson        = json_encode($ikUser);
$ikEditJson        = json_encode($ikEdit);

$extraScripts = <<<HTML
<script>
(function () {
    const INITIAL_MEMBER = {$initialMemberJson};
    const INITIAL_RFID   = {$initialRfidJson};
    const SVG_USER       = {$ikUserJson};
    const SVG_EDIT       = {$ikEditJson};

    // Modal Anggota elements
    const modalAnggota   = document.getElementById('modalAnggota');
    const modalRfid      = document.getElementById('modalRfid');
    const modalHapus     = document.getElementById('modalHapusAnggota');

    const bmForm         = document.getElementById('bmFormAnggota');
    const bmTitle        = document.getElementById('bmTitle');
    const bmSubtitle     = document.getElementById('bmSubtitle');
    const bmHeadIco      = document.getElementById('bmHeadIco');
    const bmNisChip      = document.getElementById('bmNisChip');
    const bmAction       = document.getElementById('bmAction');
    const bmId           = document.getElementById('bmId');
    const bmSubmitBtn    = document.getElementById('bmSubmitBtn');
    const bmInlineErr    = document.getElementById('bmInlineErr');
    const bmInlineErrTxt = document.getElementById('bmInlineErrTxt');

    const inpNis         = document.getElementById('nis');
    const inpNama        = document.getElementById('nama_lengkap');
    const inpKelas       = document.getElementById('kelas');
    const inpNoHp        = document.getElementById('no_hp');
    const inpUsername    = document.getElementById('username');
    const inpPassword    = document.getElementById('password');
    const inpAlamat      = document.getElementById('alamat');
    const passReqStar    = document.getElementById('passReqStar');
    const passHint       = document.getElementById('passHint');

    const bmMemberInfoNote = document.getElementById('bmMemberInfoNote');
    const bmMemberSummary  = document.getElementById('bmMemberSummary');
    const bmLinkTapRfidModal = document.getElementById('bmLinkTapRfidModal');

    // RFID Modal elements
    const rfidAnggotaId  = document.getElementById('rfidAnggotaId');
    const rfidSiswaNama  = document.getElementById('rfidSiswaNama');
    const rfidSiswaNis   = document.getElementById('rfidSiswaNis');
    const rfidInput      = document.getElementById('rfidInput');
    const bmRfidErr      = document.getElementById('bmRfidErr');
    const bmRfidErrTxt   = document.getElementById('bmRfidErrTxt');

    function lockBody() {
        const anyOpen = (modalAnggota && !modalAnggota.hidden) ||
                        (modalRfid && !modalRfid.hidden) ||
                        (modalHapus && !modalHapus.hidden);
        document.body.classList.toggle('bm-lock', anyOpen);
    }

    function cleanUrlParams() {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit') || url.searchParams.has('tap_rfid') || url.searchParams.has('error')) {
                url.searchParams.delete('edit');
                url.searchParams.delete('tap_rfid');
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

    function openMemberModal(opts) {
        const cfg = opts || {};
        const isEdit = cfg.mode === 'edit';

        bmForm.reset();
        showErr(cfg.error || '');

        if (isEdit) {
            bmAction.value = 'edit';
            bmId.value = cfg.id || '';
            bmTitle.textContent = 'Edit Data Anggota';
            bmSubtitle.textContent = 'Perbarui detail identitas siswa atau perbarui akun login perpustakaan.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_EDIT;
            if (bmNisChip) {
                bmNisChip.textContent = 'NIS: ' + (cfg.nis || '-');
                bmNisChip.hidden = false;
            }

            inpNis.value      = cfg.nis || '';
            inpNama.value     = cfg.nama_lengkap || '';
            inpKelas.value    = cfg.kelas || '';
            inpNoHp.value     = cfg.no_hp || '';
            inpUsername.value = cfg.username || '';
            inpPassword.value = '';
            inpPassword.required = false;
            if (passReqStar) passReqStar.hidden = true;
            if (passHint) passHint.hidden = false;
            inpAlamat.value   = cfg.alamat || '';

            bmMemberInfoNote.hidden = false;
            const rfidVal = cfg.kode_rfid ? '<b>' + cfg.kode_rfid + '</b>' : '<em>Belum terpasang</em>';
            const pinjamVal = (cfg.pinjaman_aktif > 0) ? ' &bull; Sedang meminjam <b>' + cfg.pinjaman_aktif + ' buku</b>' : '';
            if (bmMemberSummary) {
                bmMemberSummary.innerHTML = 'Kartu RFID: ' + rfidVal + pinjamVal;
            }
            if (bmLinkTapRfidModal) {
                bmLinkTapRfidModal.onclick = (e) => {
                    e.preventDefault();
                    closeModal(modalAnggota);
                    openRfidModal({ id: cfg.id, nama_lengkap: cfg.nama_lengkap, nis: cfg.nis, kode_rfid: cfg.kode_rfid });
                };
            }

            bmSubmitBtn.textContent = 'Simpan Perubahan';
        } else {
            bmAction.value = 'tambah';
            bmId.value = '';
            bmTitle.textContent = 'Tambah Anggota Siswa';
            bmSubtitle.textContent = 'Lengkapi identitas siswa untuk membuat akun peminjaman perpustakaan.';
            if (bmHeadIco) bmHeadIco.innerHTML = SVG_USER;
            if (bmNisChip) bmNisChip.hidden = true;

            inpPassword.required = true;
            if (passReqStar) passReqStar.hidden = false;
            if (passHint) passHint.hidden = true;

            bmMemberInfoNote.hidden = true;
            bmSubmitBtn.textContent = 'Tambah Anggota Siswa';
        }

        modalAnggota.hidden = false;
        lockBody();
        setTimeout(() => (isEdit ? inpNama.focus() : inpNis.focus()), 40);
    }

    function openRfidModal(opts) {
        const cfg = opts || {};
        if (!modalRfid) return;

        if (rfidAnggotaId) rfidAnggotaId.value = cfg.id || '';
        if (rfidSiswaNama) rfidSiswaNama.textContent = cfg.nama_lengkap || '-';
        if (rfidSiswaNis)  rfidSiswaNis.textContent = cfg.nis || '-';
        if (rfidInput) {
            rfidInput.value = cfg.kode_rfid || '';
        }

        if (bmRfidErr && bmRfidErrTxt) {
            if (cfg.error) {
                bmRfidErrTxt.textContent = cfg.error;
                bmRfidErr.hidden = false;
            } else {
                bmRfidErr.hidden = true;
                bmRfidErrTxt.textContent = '';
            }
        }

        modalRfid.hidden = false;
        lockBody();
        if (rfidInput) {
            setTimeout(() => {
                rfidInput.focus();
                rfidInput.select();
            }, 60);
        }
    }

    function closeModal(el) {
        if (!el) return;
        el.hidden = true;
        lockBody();
        cleanUrlParams();
    }

    // Trigger Tambah Anggota
    const btnTambah = document.getElementById('btnTambahAnggota');
    const linkEmpty = document.getElementById('linkEmptyTambah');
    if (btnTambah) btnTambah.addEventListener('click', () => openMemberModal({ mode: 'tambah' }));
    if (linkEmpty) linkEmpty.addEventListener('click', (e) => {
        e.preventDefault();
        openMemberModal({ mode: 'tambah' });
    });

    // Delegasi klik tabel: Edit, Tap RFID, Hapus
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.js-edit-anggota');
        if (editBtn) {
            try {
                const payload = JSON.parse(editBtn.dataset.member || '{}');
                openMemberModal(payload);
            } catch (err) {}
            return;
        }

        const rfidBtn = e.target.closest('.js-tap-rfid');
        if (rfidBtn) {
            try {
                const payload = JSON.parse(rfidBtn.dataset.rfid || '{}');
                openRfidModal(payload);
            } catch (err) {}
            return;
        }

        const delBtn = e.target.closest('.js-hapus-anggota');
        if (delBtn && modalHapus) {
            const delNama = document.getElementById('bmDelNama');
            const delNis  = document.getElementById('bmDelNis');
            const delConf = document.getElementById('bmDelConfirm');
            if (delNama) delNama.textContent = delBtn.dataset.nama || '-';
            if (delNis)  delNis.textContent  = delBtn.dataset.nis || '-';
            if (delConf) delConf.href = delBtn.dataset.href || '#';
            modalHapus.hidden = false;
            lockBody();
        }
    });

    // Tutup dialog
    document.querySelectorAll('[data-bm-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(document.getElementById(btn.dataset.bmClose)));
    });

    [modalAnggota, modalRfid, modalHapus].forEach(backdrop => {
        if (!backdrop) return;
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal(backdrop);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalHapus && !modalHapus.hidden) closeModal(modalHapus);
            else if (modalRfid && !modalRfid.hidden) closeModal(modalRfid);
            else if (modalAnggota && !modalAnggota.hidden) closeModal(modalAnggota);
        }
    });

    // Auto submit jika RFID reader mengirimkan Enter
    if (rfidInput) {
        rfidInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('formRfid');
                if (form && rfidInput.value.trim() !== '') {
                    form.submit();
                }
            }
        });
    }

    // Filter Chips interaktif
    const chips = document.querySelectorAll('#chips button');
    const rows  = document.querySelectorAll('#tabelAnggota tbody tr');
    const meta  = document.getElementById('meta');
    const empty = document.getElementById('filterEmptyMsg');
    const awalMeta = meta ? meta.innerHTML : '';

    chips.forEach(c => {
        c.addEventListener('click', () => {
            const filter = c.dataset.filter || 'all';
            chips.forEach(x => x.setAttribute('aria-pressed', x === c ? 'true' : 'false'));

            let count = 0;
            rows.forEach(r => {
                const st = r.dataset.status;
                const rf = r.dataset.rfid;
                let cocok = false;
                if (filter === 'all') cocok = true;
                else if (filter === 'bebas') cocok = (st === 'bebas');
                else if (filter === 'meminjam') cocok = (st === 'meminjam');
                else if (filter === 'rfid') cocok = (rf === 'ada');

                r.hidden = !cocok;
                if (cocok) count++;
            });

            if (empty) empty.hidden = (count > 0);
            if (meta) {
                meta.innerHTML = filter === 'all'
                    ? awalMeta
                    : 'Menampilkan <b>' + count + '</b> anggota sesuai filter';
            }
        });
    });

    // Buka modal secara otomatis jika ada parameter query
    if (INITIAL_MEMBER) {
        openMemberModal(INITIAL_MEMBER);
    } else if (INITIAL_RFID) {
        openRfidModal(INITIAL_RFID);
    }
})();
</script>
HTML;

require 'includes/footer.php';
?>
