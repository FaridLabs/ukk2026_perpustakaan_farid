<?php
require '../config/cek_session_admin.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';

$bukuId = $_GET['buku_id'] ?? '';
if (empty($bukuId)) {
    header('Location: buku.php');
    exit;
}

// Data master buku terkait
$stmtBuku = $koneksi->prepare("
    SELECT buku.*, kategori.nama_kategori
    FROM buku
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
    WHERE buku.id = :id
    LIMIT 1
");
$stmtBuku->bindParam(':id', $bukuId);
$stmtBuku->execute();
$buku = $stmtBuku->fetch();

if (!$buku) {
    header('Location: buku.php');
    exit;
}

// Daftar seluruh eksemplar fisik + info peminjam aktif jika sedang dipinjam
$stmtItem = $koneksi->prepare("
    SELECT bi.*,
           tx.kode_transaksi AS pinjam_kode,
           tx.tgl_kembali_rencana AS pinjam_jatuh_tempo,
           a.nama_lengkap AS peminjam_nama,
           a.kelas AS peminjam_kelas
    FROM buku_item bi
    LEFT JOIN transaksi tx
        ON tx.id = (
            SELECT t2.id
            FROM transaksi t2
            WHERE t2.buku_item_id = bi.id
              AND t2.status IN ('dipinjam', 'terlambat')
            ORDER BY t2.id DESC
            LIMIT 1
        )
    LEFT JOIN anggota a ON tx.anggota_id = a.id
    WHERE bi.buku_id = :buku_id
    ORDER BY bi.id ASC
");
$stmtItem->bindParam(':buku_id', $bukuId);
$stmtItem->execute();
$items = $stmtItem->fetchAll();

// Hitung metrik status eksemplar
$totalEksemplar = count($items);
$totalTersedia  = 0;
$totalDipinjam  = 0;
$totalRusak     = 0;
$totalHilang    = 0;

$prefix        = 'BK-' . $buku['id'] . '-';
$maxUrutan     = 0;
$existingCodes = [];

foreach ($items as $it) {
    switch ($it['status']) {
        case 'tersedia':
            $totalTersedia++;
            break;
        case 'dipinjam':
            $totalDipinjam++;
            break;
        case 'rusak':
            $totalRusak++;
            break;
        case 'hilang':
            $totalHilang++;
            break;
    }

    $kode = (string) $it['kode_item'];
    $existingCodes[] = $kode;
    if (strpos($kode, $prefix) === 0) {
        $tail = substr($kode, strlen($prefix));
        if (is_numeric($tail) && (int) $tail > $maxUrutan) {
            $maxUrutan = (int) $tail;
        }
    }
}

$totalLainnya = $totalRusak + $totalHilang;
$nextUrutan   = $maxUrutan + 1;

$pctOk   = $totalEksemplar > 0 ? round(($totalTersedia / $totalEksemplar) * 100, 1) : 0;
$pctLoan = $totalEksemplar > 0 ? round(($totalDipinjam / $totalEksemplar) * 100, 1) : 0;
$pctBad  = $totalEksemplar > 0 ? max(0, round(100 - $pctOk - $pctLoan, 1)) : 0;

// Cover & warna badge kategori (konsisten dengan admin/buku.php)
$coverUrl  = coverUrl($buku['cover'] ?? null, '../uploads/covers/');
$namaKat   = $buku['nama_kategori'] ?? 'Umum';
$warnaKat  = [['#DDF3E8', '#1B7A55'], ['#E3ECFD', '#2F63D8'], ['#FDEBD3', '#B4600A'], ['#ECE5FB', '#7140D0'], ['#FBE1E1', '#C23B3B'], ['#E1F1F4', '#17717F']];
[$katBg, $katFg] = $warnaKat[crc32($namaKat) % count($warnaKat)];

$tahunTampil = in_array((string) ($buku['tahun_terbit'] ?? ''), ['', '0', '0000'], true) ? '-' : $buku['tahun_terbit'];
$errCode     = $_GET['error'] ?? '';
$openManual  = in_array($errCode, ['kosong', 'sudah_ada'], true);

// Helper SVG lokal yang rapi & konsisten dengan shell admin
$biSvg = fn(string $d): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$ikBack  = $biSvg('<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>');
$ikEdit  = $biSvg('<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>');
$ikQr    = $biSvg('<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M14 14h3v3M20.5 14v.5M14 20.5h.5M17 20.5h3.5V17"/>');
$ikCopy  = $biSvg('<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>');
$ikTrash = $biSvg('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>');
$ikDown  = $biSvg('<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>');
$ikAlert = $biSvg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');
$ikSearch = $biSvg('<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/>');
$ikClose = $biSvg('<path d="M18 6 6 18M6 6l12 12"/>');

$pageTitle   = 'Kelola Eksemplar - ' . $buku['judul'];
$currentPage = 'buku';
$extraHead   = '<link rel="stylesheet" href="../assets/css/kelola.css?v=2">'
             . '<link rel="stylesheet" href="../assets/css/buku_item.css?v=1">';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="d-head">
    <div>
        <h1>Kelola Eksemplar</h1>
        <p>Penerbitan serial fisik, pemantauan kondisi koleksi, dan pencetakan stiker label QR.</p>
    </div>
    <div class="d-act">
        <a href="buku.php" class="btn-d"><?= $ikBack ?><span>Kembali ke Buku</span></a>
        <a href="buku.php?edit=<?= (int) $buku['id'] ?>" class="btn-d"><?= $ikEdit ?><span>Edit Judul</span></a>
        <?php if ($totalEksemplar > 0): ?>
            <a href="../config/generate_qr.php?buku_id=<?= (int) $buku['id'] ?>" target="_blank" class="btn-d main">
                <?= ic('printer') ?><span>Cetak Semua QR (<?= $totalEksemplar ?>)</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KARTU IDENTITAS BUKU & RINGKASAN STOK -->
<section class="bi-book" aria-label="Ringkasan judul buku">
    <div class="bi-book-main">
        <div class="bi-cover <?= $coverUrl ? 'has-img' : '' ?>">
            <?php if ($coverUrl): ?>
                <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover <?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <?= ic('book', 26) ?>
            <?php endif; ?>
        </div>
        <div class="bi-info">
            <div class="bi-badges">
                <span class="kat" style="background:<?= $katBg ?>;color:<?= $katFg ?>"><?= htmlspecialchars($namaKat, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($buku['kode_buku'])): ?>
                    <span class="bi-code-master"><?= htmlspecialchars($buku['kode_buku'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <h2><?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="bi-meta">
                <span>Pengarang: <strong><?= htmlspecialchars(!empty($buku['pengarang']) ? $buku['pengarang'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span>Penerbit: <strong><?= htmlspecialchars(!empty($buku['penerbit']) ? $buku['penerbit'] : '-', ENT_QUOTES, 'UTF-8') ?></strong></span>
                <span>Tahun: <strong><?= htmlspecialchars((string) $tahunTampil, ENT_QUOTES, 'UTF-8') ?></strong></span>
            </div>
        </div>
    </div>

    <div class="bi-health">
        <div class="bi-health-top">
            <span>Ketersediaan fisik</span>
            <b><?= $totalTersedia ?> / <?= $totalEksemplar ?> siap pinjam</b>
        </div>
        <div class="bi-bar" role="img" aria-label="Proporsi status eksemplar: <?= $totalTersedia ?> tersedia, <?= $totalDipinjam ?> dipinjam, <?= $totalLainnya ?> rusak atau hilang">
            <?php if ($totalEksemplar > 0): ?>
                <?php if ($pctOk > 0): ?><span class="bi-seg ok" style="width:<?= $pctOk ?>%"></span><?php endif; ?>
                <?php if ($pctLoan > 0): ?><span class="bi-seg loan" style="width:<?= $pctLoan ?>%"></span><?php endif; ?>
                <?php if ($pctBad > 0): ?><span class="bi-seg bad" style="width:<?= $pctBad ?>%"></span><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="bi-kpis" id="biKpis">
            <button type="button" class="bi-kpi is-active" data-filter="all">
                <span class="bi-kpi-val"><i class="bi-dot"></i><?= $totalEksemplar ?></span>
                <span class="bi-kpi-lbl">Total Fisik</span>
            </button>
            <button type="button" class="bi-kpi" data-filter="tersedia">
                <span class="bi-kpi-val"><i class="bi-dot ok"></i><?= $totalTersedia ?></span>
                <span class="bi-kpi-lbl">Tersedia</span>
            </button>
            <button type="button" class="bi-kpi" data-filter="dipinjam">
                <span class="bi-kpi-val"><i class="bi-dot loan"></i><?= $totalDipinjam ?></span>
                <span class="bi-kpi-lbl">Dipinjam</span>
            </button>
            <button type="button" class="bi-kpi <?= $totalLainnya > 0 ? 'has-alert' : '' ?>" data-filter="bermasalah">
                <span class="bi-kpi-val"><i class="bi-dot <?= $totalLainnya > 0 ? 'bad' : 'warn' ?>"></i><?= $totalLainnya ?></span>
                <span class="bi-kpi-lbl">Rusak / Hilang</span>
            </button>
        </div>
    </div>
</section>

<!-- NOTIFIKASI -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'generate'): ?>
    <div class="kc-al ok bi-al" role="status">
        <?= ic('check') ?>
        <div>
            Berhasil menerbitkan <strong><?= htmlspecialchars($_GET['jumlah'] ?? '1', ENT_QUOTES, 'UTF-8') ?></strong> kode QR eksemplar fisik baru.
            <a href="../config/generate_qr.php?buku_id=<?= (int) $buku['id'] ?>" target="_blank" style="margin-left:8px;">Cetak lembar stiker label QR</a>
        </div>
    </div>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'manual'): ?>
    <div class="kc-al ok bi-al" role="status">
        <?= ic('check') ?>
        <div>Berhasil mendaftarkan eksemplar dengan kode manual <code><?= htmlspecialchars($_GET['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>.</div>
    </div>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'status'): ?>
    <div class="kc-al ok bi-al" role="status"><?= ic('check') ?><div>Status kondisi eksemplar berhasil diperbarui.</div></div>
<?php elseif (isset($_GET['success']) && $_GET['success'] === 'hapus'): ?>
    <div class="kc-al ok bi-al" role="status"><?= ic('check') ?><div>Eksemplar fisik telah dihapus dari inventaris.</div></div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="kc-al ok bi-al" role="status"><?= ic('check') ?><div>Data eksemplar berhasil diperbarui.</div></div>
<?php endif; ?>

<?php if ($errCode === 'sudah_ada'): ?>
    <div class="kc-al err bi-al" role="alert">
        <?= $ikAlert ?>
        <div>Kode barcode <code><?= htmlspecialchars($_GET['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></code> sudah terdaftar pada buku "<strong><?= htmlspecialchars($_GET['judul_ada'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>". Gunakan kode unik lain.</div>
    </div>
<?php elseif ($errCode === 'kosong'): ?>
    <div class="kc-al err bi-al" role="alert">
        <?= $ikAlert ?>
        <div>Kode eksemplar manual tidak boleh kosong.</div>
    </div>
<?php elseif ($errCode === 'dipinjam'): ?>
    <div class="kc-al err bi-al" role="alert">
        <?= $ikAlert ?>
        <div>Eksemplar tidak dapat dihapus karena sedang dalam status dipinjam.</div>
    </div>
<?php elseif ($errCode === 'ada_transaksi'): ?>
    <div class="kc-al err bi-al" role="alert">
        <?= $ikAlert ?>
        <div>Eksemplar tidak dapat dihapus karena memiliki riwayat sirkulasi peminjaman. Jika kondisi buku tidak layak lagi, Anda dapat mengubah statusnya menjadi <strong>Rusak</strong> atau <strong>Hilang</strong>.</div>
    </div>
<?php elseif ($errCode === 'system'): ?>
    <div class="kc-al err bi-al" role="alert">
        <?= $ikAlert ?>
        <div>Terjadi gangguan sistem saat menerbitkan eksemplar. Silakan coba lagi.</div>
    </div>
<?php endif; ?>

<!-- WORKBENCH: PENERBITAN EKSEMPLAR BARU -->
<section class="bi-gen" aria-labelledby="hTerbit">
    <div class="bi-gen-head">
        <div class="bi-gen-title">
            <div class="panel-ico"><?= $ikQr ?></div>
            <div>
                <h2 id="hTerbit">Tambah Eksemplar &amp; Kode QR</h2>
                <p>Terbitkan nomor seri otomatis berurutan atau daftarkan barcode pabrikan yang sudah ada pada buku.</p>
            </div>
        </div>
        <div class="bi-mode-tabs" role="tablist" aria-label="Metode penambahan eksemplar">
            <button type="button" role="tab" id="tabAuto" aria-controls="panelAuto" aria-selected="<?= $openManual ? 'false' : 'true' ?>">Generate Otomatis</button>
            <button type="button" role="tab" id="tabManual" aria-controls="panelManual" aria-selected="<?= $openManual ? 'true' : 'false' ?>">Barcode Manual</button>
        </div>
    </div>

    <!-- MODE A: GENERATE OTOMATIS -->
    <div class="bi-gen-panel" id="panelAuto" role="tabpanel" aria-labelledby="tabAuto" <?= $openManual ? 'hidden' : '' ?>>
        <form action="../config/proses_buku_item.php" method="POST" id="form-generate-qr" autocomplete="off">
            <input type="hidden" name="action" value="generate_qr">
            <input type="hidden" name="buku_id" value="<?= (int) $buku['id'] ?>">

            <div class="bi-gen-body">
                <div>
                    <label class="bi-qty-lbl" for="input-jumlah">Jumlah Eksemplar Tambahan</label>
                    <div class="bi-controls-row">
                        <div class="bi-stepper">
                            <button type="button" id="btnMinus" aria-label="Kurangi jumlah">&minus;</button>
                            <input type="number" name="jumlah" id="input-jumlah" value="1" min="1" max="100" required autocomplete="off">
                            <button type="button" id="btnPlus" aria-label="Tambah jumlah">+</button>
                        </div>
                        <div class="bi-presets" aria-label="Pilihan cepat jumlah">
                            <button type="button" class="bi-preset is-on" data-qty="1">+1</button>
                            <button type="button" class="bi-preset" data-qty="2">+2</button>
                            <button type="button" class="bi-preset" data-qty="5">+5</button>
                            <button type="button" class="bi-preset" data-qty="10">+10</button>
                            <button type="button" class="bi-preset" data-qty="20">+20</button>
                        </div>
                    </div>
                    <div class="bi-submit-row">
                        <button type="submit" id="btn-generate" class="bi-btn-submit">
                            <?= $ikQr ?>
                            <span>Terbitkan <span id="label-btn-qty">1</span> Kode QR</span>
                        </button>
                        <span class="bi-hint">Batas maksimal 100 eksemplar per proses.</span>
                    </div>
                </div>

                <div class="bi-preview" aria-live="polite">
                    <div>
                        <div class="bi-preview-lbl">Pratinjau Penomoran</div>
                        <div class="bi-serial-range" id="serialPreview" style="margin-top:10px;">
                            <span class="bi-serial-tag"><?= htmlspecialchars($prefix . str_pad((string) $nextUrutan, 3, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <div class="bi-preview-foot">
                        <span>Total fisik setelah terbit</span>
                        <b id="totalAfterPreview"><?= $totalEksemplar ?> &rarr; <?= $totalEksemplar + 1 ?> fisik</b>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- MODE B: INPUT BARCODE MANUAL -->
    <div class="bi-gen-panel" id="panelManual" role="tabpanel" aria-labelledby="tabManual" <?= $openManual ? '' : 'hidden' ?>>
        <form action="../config/proses_buku_item.php" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="tambah_manual">
            <input type="hidden" name="buku_id" value="<?= (int) $buku['id'] ?>">

            <div class="bi-gen-body">
                <div>
                    <label class="bi-qty-lbl" for="input-kode-manual">Kode Barcode / Serial Fisik Unik</label>
                    <div class="bi-manual-row">
                        <input type="text" name="kode_item" id="input-kode-manual" class="bi-manual-input" maxlength="30" required placeholder="Scan barcode atau ketik kode, mis. 97860203..." value="<?= $errCode === 'sudah_ada' ? htmlspecialchars($_GET['kode'] ?? '', ENT_QUOTES, 'UTF-8') : '' ?>" autocomplete="off">
                        <button type="submit" class="bi-btn-submit"><?= ic('plus') ?><span>Daftarkan Kode</span></button>
                    </div>
                    <div class="bi-submit-row" style="margin-top:10px;">
                        <span class="bi-hint">Cocok jika buku sudah memiliki stiker barcode bawaan penerbit. Maksimal 30 karakter.</span>
                    </div>
                </div>

                <div class="bi-preview">
                    <div class="bi-preview-lbl">Pemeriksaan Kode Lokal</div>
                    <div class="bi-manual-status idle" id="manualCheckStatus" style="margin-top:8px;">
                        Menunggu input kode barcode untuk diperiksa.
                    </div>
                    <div class="bi-preview-foot">
                        <span>Saran format sistem</span>
                        <code class="bi-code" style="font-size:12px;padding:2px 8px;"><?= htmlspecialchars($prefix . str_pad((string) $nextUrutan, 3, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- DAFTAR EKSEMPLAR FISIK -->
<section class="act" id="daftarEksemplar" aria-labelledby="hDaftar">
    <div class="act-top">
        <div class="panel-ico"><?= ic('bookmark') ?></div>
        <div class="panel-h" style="display:block;">
            <h2 id="hDaftar">Daftar Eksemplar Fisik &amp; Kode QR</h2>
            <p>Klik ikon QR untuk pratinjau ukuran penuh, unduh gambar PNG, atau cetak satuan.</p>
        </div>
        <div class="kc-meta" id="biCountMeta"><b><?= $totalEksemplar ?></b> dari <b><?= $totalEksemplar ?></b> eksemplar</div>
    </div>

    <?php if (!empty($items)): ?>
        <div class="bi-filter-bar">
            <div class="bi-search-box">
                <?= $ikSearch ?>
                <input type="text" id="biSearch" placeholder="Cari kode serial atau nama peminjam..." aria-label="Cari kode eksemplar" autocomplete="off">
                <button type="button" class="bi-search-clear" id="biSearchClear" aria-label="Bersihkan pencarian">&times;</button>
            </div>
            <div class="kc-chips" id="biChips" role="group" aria-label="Filter status eksemplar">
                <button type="button" data-filter="all" aria-pressed="true">Semua (<?= $totalEksemplar ?>)</button>
                <button type="button" data-filter="tersedia" aria-pressed="false">Tersedia (<?= $totalTersedia ?>)</button>
                <button type="button" data-filter="dipinjam" aria-pressed="false">Dipinjam (<?= $totalDipinjam ?>)</button>
                <button type="button" data-filter="rusak" aria-pressed="false">Rusak (<?= $totalRusak ?>)</button>
                <button type="button" data-filter="hilang" aria-pressed="false">Hilang (<?= $totalHilang ?>)</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="tbl bi-tbl">
        <?php if (empty($items)): ?>
            <div class="kc-empty">
                <b>Belum ada eksemplar fisik terdaftar</b>
                Gunakan panel <strong>Tambah Eksemplar &amp; Kode QR</strong> di atas untuk menerbitkan salinan pertama buku ini.
            </div>
        <?php else: ?>
            <table id="tabelEksemplar">
                <thead>
                    <tr>
                        <th style="width:18%;">Eksemplar</th>
                        <th style="width:36%;">QR &amp; Kode Serial</th>
                        <th style="width:24%;">Status &amp; Keterangan</th>
                        <th style="text-align:right;width:22%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item):
                        $kodeEsc = htmlspecialchars($item['kode_item'], ENT_QUOTES, 'UTF-8');
                        $status  = $item['status'];
                        $tglAdd  = !empty($item['created_at']) ? date('d M Y', strtotime($item['created_at'])) : '-';
                        $searchHaystack = strtolower($item['kode_item'] . ' ' . ($item['peminjam_nama'] ?? '') . ' ' . ($item['pinjam_kode'] ?? ''));
                    ?>
                        <tr data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars($searchHaystack, ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <div class="bi-num">
                                    <span class="bi-idx">Eksemplar #<?= $i + 1 ?></span>
                                </div>
                                <small>Terbit <?= htmlspecialchars($tglAdd, ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td>
                                <div class="bi-qr-cell">
                                    <button type="button"
                                            class="bi-mini-qr js-open-qr"
                                            data-kode="<?= $kodeEsc ?>"
                                            data-id="<?= (int) $item['id'] ?>"
                                            data-urut="<?= $i + 1 ?>"
                                            title="Pratinjau &amp; unduh QR <?= $kodeEsc ?>"
                                            aria-label="Lihat QR <?= $kodeEsc ?>"></button>
                                    <div class="bi-code-wrap">
                                        <span class="bi-code"><?= $kodeEsc ?></span>
                                        <button type="button"
                                                class="bi-copy-btn js-copy-kode"
                                                data-kode="<?= $kodeEsc ?>"
                                                title="Salin kode ke clipboard">
                                            <?= $ikCopy ?><span>Salin</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="bi-status-wrap">
                                    <?php if ($status === 'dipinjam'): ?>
                                        <span class="bi-pill-loan"><i class="bi-dot loan"></i>Sedang Dipinjam</span>
                                        <?php if (!empty($item['peminjam_nama'])): ?>
                                            <span class="bi-borrower">
                                                Oleh <strong><?= htmlspecialchars($item['peminjam_nama'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!empty($item['pinjam_jatuh_tempo'])): ?>
                                                    &bull; Tempo <?= htmlspecialchars(date('d M Y', strtotime($item['pinjam_jatuh_tempo'])), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bi-borrower">Status dikunci selama transaksi aktif.</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form action="../config/proses_buku_item.php" method="POST" autocomplete="off">
                                            <input type="hidden" name="action" value="ubah_status">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="buku_id" value="<?= (int) $buku['id'] ?>">
                                            <select name="status"
                                                    class="bi-status-select st-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="Ubah status eksemplar <?= $kodeEsc ?>"
                                                    onchange="this.form.submit()">
                                                <option value="tersedia" <?= $status === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                                <option value="rusak" <?= $status === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                                                <option value="hilang" <?= $status === 'hilang' ? 'selected' : '' ?>>Hilang</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <div class="kb-row">
                                    <button type="button"
                                            class="kb green js-open-qr"
                                            data-kode="<?= $kodeEsc ?>"
                                            data-id="<?= (int) $item['id'] ?>"
                                            data-urut="<?= $i + 1 ?>">
                                        <?= $ikQr ?><span>Lihat QR</span>
                                    </button>
                                    <a href="../config/generate_qr.php?item_id=<?= (int) $item['id'] ?>"
                                       target="_blank"
                                       class="kb ico"
                                       title="Cetak stiker label eksemplar ini"
                                       aria-label="Cetak label <?= $kodeEsc ?>">
                                        <?= ic('printer', 16) ?>
                                    </a>
                                    <?php if ($status !== 'dipinjam'): ?>
                                        <button type="button"
                                                class="kb ico danger js-open-hapus"
                                                data-href="../config/proses_buku_item.php?action=hapus&amp;id=<?= (int) $item['id'] ?>&amp;buku_id=<?= (int) $buku['id'] ?>"
                                                data-kode="<?= $kodeEsc ?>"
                                                title="Hapus eksemplar ini"
                                                aria-label="Hapus <?= $kodeEsc ?>">
                                            <?= $ikTrash ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="kb ico btn-disabled" title="Tidak dapat dihapus saat dipinjam" aria-hidden="true">
                                            <?= $ikTrash ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="kc-empty" id="biFilterEmpty" hidden>
                <b>Tidak ada eksemplar yang cocok</b>
                Coba ubah filter status atau bersihkan kata kunci pencarian.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL PRATINJAU & UNDUH STIKER QR -->
<div class="bi-modal-backdrop" id="modalQr" hidden role="dialog" aria-modal="true" aria-labelledby="modalQrTitle">
    <div class="bi-modal">
        <div class="bi-modal-h">
            <h3 id="modalQrTitle">Pratinjau Label QR</h3>
            <button type="button" class="bi-modal-close" data-close-modal="modalQr" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <div class="bi-modal-b">
            <div class="bi-sticker">
                <div class="bi-sticker-brand"><?= htmlspecialchars($appNama ?? 'Pinjam.In') ?> &middot; Inventaris Fisik</div>
                <div class="bi-sticker-title" id="qrModalBook"><?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="bi-qr-stage" id="qrStage"></div>
                <div class="bi-sticker-code" id="qrModalCode">-</div>
            </div>
        </div>
        <div class="bi-modal-f">
            <button type="button" class="kb" id="btnModalCopy"><?= $ikCopy ?><span>Salin Kode</span></button>
            <button type="button" class="kb" id="btnModalDownload"><?= $ikDown ?><span>Unduh PNG</span></button>
            <a href="#" target="_blank" class="bi-btn-primary" id="btnModalPrint"><?= ic('printer', 16) ?><span>Cetak</span></a>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="bi-modal-backdrop" id="modalHapus" hidden role="dialog" aria-modal="true" aria-labelledby="modalHapusTitle">
    <div class="bi-modal" style="max-width:390px;">
        <div class="bi-modal-h">
            <h3 id="modalHapusTitle">Hapus Eksemplar Fisik</h3>
            <button type="button" class="bi-modal-close" data-close-modal="modalHapus" aria-label="Tutup dialog"><?= $ikClose ?></button>
        </div>
        <div class="bi-modal-b">
            <p class="bi-del-msg">
                Apakah Anda yakin ingin menghapus eksemplar <code id="hapusKodeTarget">-</code>? Tindakan ini menghapus kode QR secara permanen dari sistem.
            </p>
        </div>
        <div class="bi-modal-f">
            <button type="button" class="kb" data-close-modal="modalHapus">Batal</button>
            <a href="#" class="bi-btn-danger" id="btnConfirmHapus">Ya, Hapus</a>
        </div>
    </div>
</div>

<?php
$cfgJson = json_encode([
    'appName'       => $appNama ?? 'Pinjam.In',
    'prefix'        => $prefix,
    'nextUrutan'    => $nextUrutan,
    'totalSaatIni'  => $totalEksemplar,
    'existingCodes' => array_values($existingCodes),
    'bookTitle'     => $buku['judul'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$extraScripts = '<script src="../assets/js/qrcode.min.js"></script>' . "\n" . <<<HTML
<script>
(function () {
    const CFG = {$cfgJson};

    // 1. Tab Mode: Generate Otomatis vs Barcode Manual
    const tabAuto    = document.getElementById('tabAuto');
    const tabManual  = document.getElementById('tabManual');
    const panelAuto  = document.getElementById('panelAuto');
    const panelManual = document.getElementById('panelManual');

    function setTab(mode) {
        const isAuto = mode === 'auto';
        tabAuto.setAttribute('aria-selected', isAuto ? 'true' : 'false');
        tabManual.setAttribute('aria-selected', isAuto ? 'false' : 'true');
        panelAuto.hidden = !isAuto;
        panelManual.hidden = isAuto;
        if (!isAuto) {
            const inp = document.getElementById('input-kode-manual');
            if (inp) setTimeout(() => inp.focus(), 40);
        }
    }

    if (tabAuto && tabManual) {
        tabAuto.addEventListener('click', () => setTab('auto'));
        tabManual.addEventListener('click', () => setTab('manual'));
    }

    // 2. Stepper & Live Preview Penomoran Serial
    const inputJumlah = document.getElementById('input-jumlah');
    const labelBtnQty = document.getElementById('label-btn-qty');
    const serialPreview = document.getElementById('serialPreview');
    const totalAfterPreview = document.getElementById('totalAfterPreview');
    const presetBtns = document.querySelectorAll('.bi-preset');
    const btnMinus = document.getElementById('btnMinus');
    const btnPlus = document.getElementById('btnPlus');

    function pad3(n) {
        return String(n).padStart(3, '0');
    }

    function syncQtyUI(val) {
        let qty = parseInt(val, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > 100) qty = 100;

        if (labelBtnQty) labelBtnQty.textContent = qty;

        presetBtns.forEach(btn => {
            btn.classList.toggle('is-on', parseInt(btn.dataset.qty, 10) === qty);
        });

        if (serialPreview) {
            const startCode = CFG.prefix + pad3(CFG.nextUrutan);
            const endCode   = CFG.prefix + pad3(CFG.nextUrutan + qty - 1);
            serialPreview.innerHTML = '';

            const s1 = document.createElement('span');
            s1.className = 'bi-serial-tag';
            s1.textContent = startCode;
            serialPreview.appendChild(s1);

            if (qty > 1) {
                const sep = document.createElement('span');
                sep.className = 'bi-serial-sep';
                sep.textContent = qty === 2 ? '&' : 's.d.';
                serialPreview.appendChild(sep);

                const s2 = document.createElement('span');
                s2.className = 'bi-serial-tag';
                s2.textContent = endCode;
                serialPreview.appendChild(s2);
            }
        }

        if (totalAfterPreview) {
            totalAfterPreview.textContent = CFG.totalSaatIni + ' \u2192 ' + (CFG.totalSaatIni + qty) + ' fisik';
        }
    }

    function applyQty(qty) {
        let clamped = Math.max(1, Math.min(100, parseInt(qty, 10) || 1));
        if (inputJumlah) inputJumlah.value = clamped;
        syncQtyUI(clamped);
    }

    if (btnMinus) btnMinus.addEventListener('click', () => applyQty((parseInt(inputJumlah.value, 10) || 1) - 1));
    if (btnPlus)  btnPlus.addEventListener('click', () => applyQty((parseInt(inputJumlah.value, 10) || 1) + 1));

    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => applyQty(btn.dataset.qty));
    });

    if (inputJumlah) {
        inputJumlah.addEventListener('input', () => syncQtyUI(inputJumlah.value));
        inputJumlah.addEventListener('blur', () => applyQty(inputJumlah.value));
        syncQtyUI(inputJumlah.value);
    }

    // 3. Pemeriksaan Duplikat Lokal pada Tab Barcode Manual
    const inputManual = document.getElementById('input-kode-manual');
    const manualStatus = document.getElementById('manualCheckStatus');
    const existingSet = new Set((CFG.existingCodes || []).map(c => String(c).trim().toLowerCase()));

    function checkManualCode() {
        if (!inputManual || !manualStatus) return;
        const val = inputManual.value.trim();
        manualStatus.className = 'bi-manual-status';
        if (!val) {
            manualStatus.classList.add('idle');
            manualStatus.textContent = 'Menunggu input kode barcode untuk diperiksa.';
            return;
        }
        if (existingSet.has(val.toLowerCase())) {
            manualStatus.classList.add('dup');
            manualStatus.textContent = 'Kode sudah digunakan oleh eksemplar lain pada buku ini.';
        } else {
            manualStatus.classList.add('ok');
            manualStatus.textContent = 'Format kode unik pada buku ini dan siap disimpan.';
        }
    }

    if (inputManual) {
        inputManual.addEventListener('input', checkManualCode);
        checkManualCode();
    }

    // 4. Render Thumbnail QR Mini di Setiap Baris Tabel
    if (typeof QRCode === 'function') {
        document.querySelectorAll('.bi-mini-qr[data-kode]').forEach(el => {
            const kode = el.dataset.kode;
            if (!kode || el.firstChild) return;
            try {
                new QRCode(el, {
                    text: kode,
                    width: 64,
                    height: 64,
                    colorDark: '#14232b',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {}
        });
    }

    // 5. Filter Status & Pencarian Instan pada Tabel
    const rows = Array.from(document.querySelectorAll('#tabelEksemplar tbody tr'));
    const kpiBtns = document.querySelectorAll('#biKpis .bi-kpi');
    const chipBtns = document.querySelectorAll('#biChips button[data-filter]');
    const searchInput = document.getElementById('biSearch');
    const searchClear = document.getElementById('biSearchClear');
    const countMeta = document.getElementById('biCountMeta');
    const emptyBox = document.getElementById('biFilterEmpty');
    const tblEl = document.getElementById('tabelEksemplar');
    let activeFilter = 'all';

    function runFilter() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        if (searchClear) searchClear.classList.toggle('show', q.length > 0);

        let shown = 0;
        rows.forEach(tr => {
            const st = tr.dataset.status || '';
            const hay = tr.dataset.search || '';
            let okStatus = true;
            if (activeFilter === 'bermasalah') {
                okStatus = (st === 'rusak' || st === 'hilang');
            } else if (activeFilter !== 'all') {
                okStatus = (st === activeFilter);
            }
            const okQuery = q === '' || hay.includes(q);
            const show = okStatus && okQuery;
            tr.hidden = !show;
            if (show) shown++;
        });

        if (countMeta) {
            countMeta.innerHTML = '<b>' + shown + '</b> dari <b>' + rows.length + '</b> eksemplar';
        }
        if (tblEl) tblEl.hidden = rows.length > 0 && shown === 0;
        if (emptyBox) emptyBox.hidden = !(rows.length > 0 && shown === 0);
    }

    function setFilter(nextFilter, scrollIfTriggered) {
        activeFilter = nextFilter || 'all';
        chipBtns.forEach(btn => {
            btn.setAttribute('aria-pressed', btn.dataset.filter === activeFilter ? 'true' : 'false');
        });
        kpiBtns.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.filter === activeFilter);
        });
        runFilter();
        if (scrollIfTriggered && tblEl) {
            const sec = document.getElementById('daftarEksemplar');
            if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    chipBtns.forEach(btn => btn.addEventListener('click', () => setFilter(btn.dataset.filter, false)));
    kpiBtns.forEach(btn => btn.addEventListener('click', () => setFilter(btn.dataset.filter, true)));

    if (searchInput) {
        searchInput.addEventListener('input', runFilter);
    }
    if (searchClear) {
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            runFilter();
        });
    }

    // 6. Salin Kode ke Clipboard + Umpan Balik Visual
    function copyText(text, btnEl) {
        if (!text) return;
        const done = () => {
            if (!btnEl) return;
            const span = btnEl.querySelector('span');
            const orig = span ? span.textContent : '';
            btnEl.classList.add('copied');
            if (span) span.textContent = 'Tersalin';
            setTimeout(() => {
                btnEl.classList.remove('copied');
                if (span && orig) span.textContent = orig;
            }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => {});
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
    }

    document.addEventListener('click', e => {
        const copyBtn = e.target.closest('.js-copy-kode');
        if (copyBtn) {
            copyText(copyBtn.dataset.kode, copyBtn);
        }
    });

    // 7. Modal Pratinjau QR & Unduh PNG Stiker
    const modalQr    = document.getElementById('modalQr');
    const qrStage    = document.getElementById('qrStage');
    const qrModalTitle = document.getElementById('modalQrTitle');
    const qrModalCode  = document.getElementById('qrModalCode');
    const btnModalCopy = document.getElementById('btnModalCopy');
    const btnModalDown = document.getElementById('btnModalDownload');
    const btnModalPrint = document.getElementById('btnModalPrint');
    let currentQrKode = '';

    function openQrModal(kode, id, urut) {
        if (!modalQr || !qrStage) return;
        currentQrKode = kode;
        qrStage.innerHTML = '';
        if (qrModalTitle) qrModalTitle.textContent = 'Stiker QR \u2014 Eksemplar #' + urut;
        if (qrModalCode) qrModalCode.textContent = kode;
        if (btnModalPrint) btnModalPrint.href = '../config/generate_qr.php?item_id=' + encodeURIComponent(id);

        if (typeof QRCode === 'function') {
            new QRCode(qrStage, {
                text: kode,
                width: 196,
                height: 196,
                colorDark: '#14232b',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
        modalQr.hidden = false;
    }

    function downloadStickerPng() {
        if (!qrStage || !currentQrKode) return;
        const srcCanvas = qrStage.querySelector('canvas');
        const srcImg = qrStage.querySelector('img');
        const source = srcCanvas || srcImg;
        if (!source) return;

        const out = document.createElement('canvas');
        out.width = 480;
        out.height = 560;
        const ctx = out.getContext('2d');

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, out.width, out.height);

        ctx.strokeStyle = '#d3dfd8';
        ctx.lineWidth = 4;
        ctx.strokeRect(12, 12, out.width - 24, out.height - 24);

        ctx.fillStyle = '#217350';
        ctx.font = '700 18px "Plus Jakarta Sans", Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText((CFG.appName || 'PINJAM.IN').toUpperCase() + '  \u2022  LABEL INVENTARIS', out.width / 2, 54);

        const judul = (CFG.bookTitle || '').trim();
        const judulClip = judul.length > 34 ? judul.slice(0, 32) + '...' : judul;
        ctx.fillStyle = '#14232b';
        ctx.font = '700 22px "Plus Jakarta Sans", Arial, sans-serif';
        ctx.fillText(judulClip, out.width / 2, 92);

        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(source, 80, 122, 320, 320);

        ctx.fillStyle = '#14232b';
        ctx.font = '700 28px "JetBrains Mono", Consolas, monospace';
        ctx.fillText(currentQrKode, out.width / 2, 496);

        const a = document.createElement('a');
        a.download = 'QR-' + currentQrKode.replace(/[^A-Za-z0-9._-]/g, '_') + '.png';
        a.href = out.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    document.addEventListener('click', e => {
        const trigger = e.target.closest('.js-open-qr');
        if (trigger) {
            openQrModal(trigger.dataset.kode, trigger.dataset.id, trigger.dataset.urut);
        }
    });

    if (btnModalCopy) btnModalCopy.addEventListener('click', () => copyText(currentQrKode, btnModalCopy));
    if (btnModalDown) btnModalDown.addEventListener('click', downloadStickerPng);

    // 8. Modal Konfirmasi Hapus
    const modalHapus = document.getElementById('modalHapus');
    const hapusKodeTarget = document.getElementById('hapusKodeTarget');
    const btnConfirmHapus = document.getElementById('btnConfirmHapus');

    document.addEventListener('click', e => {
        const delBtn = e.target.closest('.js-open-hapus');
        if (delBtn && modalHapus) {
            if (hapusKodeTarget) hapusKodeTarget.textContent = delBtn.dataset.kode || '-';
            if (btnConfirmHapus) btnConfirmHapus.href = delBtn.dataset.href || '#';
            modalHapus.hidden = false;
        }
    });

    // Tutup modal via tombol tutup, klik backdrop, atau Escape
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.closeModal);
            if (target) target.hidden = true;
        });
    });

    [modalQr, modalHapus].forEach(m => {
        if (!m) return;
        m.addEventListener('click', e => {
            if (e.target === m) m.hidden = true;
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (modalQr && !modalQr.hidden) modalQr.hidden = true;
            if (modalHapus && !modalHapus.hidden) modalHapus.hidden = true;
        }
    });
})();
</script>
HTML;

require 'includes/footer.php';
?>
