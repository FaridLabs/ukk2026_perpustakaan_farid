<?php
/**
 * ============================================================================
 * KATALOG BUKU SISWA - PINJAM.IN
 * ============================================================================
 * Menampilkan katalog seluruh koleksi buku perpustakaan yang dapat dijelajahi siswa.
 * Dilengkapi fitur pencarian kata kunci, filter kategori interaktif (pil navigasi),
 * status ketersediaan stok fisik di rak, dan modal detail buku lengkap.
 */

// 1. Cek keamanan sesi login siswa
require '../config/cek_session_siswa.php';
require '../config/koneksi.php';
require_once '../config/upload_cover.php';

// 2. Ambil parameter pencarian dan filter kategori dari URL (GET)
$keyword    = trim($_GET['keyword'] ?? '');
$kategoriId = trim($_GET['kategori'] ?? '');

// 3. Susun query SQL untuk mengambil data buku beserta kalkulasi stok fisik
$sql = "
    SELECT buku.*, 
           kategori.nama_kategori,
           COUNT(buku_item.id) AS total_eksemplar,
           SUM(CASE WHEN buku_item.status = 'tersedia' THEN 1 ELSE 0 END) AS total_tersedia
    FROM buku
    LEFT JOIN kategori ON buku.kategori_id = kategori.id
    LEFT JOIN buku_item ON buku_item.buku_id = buku.id
    WHERE 1=1
";

$params = [];

// Filter berdasarkan kata kunci judul, pengarang, atau penerbit
if (!empty($keyword)) {
    $sql .= " AND (buku.judul LIKE :kw OR buku.pengarang LIKE :kw OR buku.penerbit LIKE :kw OR buku.kode_buku LIKE :kw)";
    $params[':kw'] = "%{$keyword}%";
}

// Filter berdasarkan kategori yang dipilih
if (!empty($kategoriId)) {
    $sql .= " AND buku.kategori_id = :kat";
    $params[':kat'] = $kategoriId;
}

$sql .= " GROUP BY buku.id ORDER BY buku.judul ASC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$dataBuku = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Ambil daftar seluruh kategori beserta jumlah bukunya untuk filter pill
$kategoriList = $koneksi->query("
    SELECT kategori.*, COUNT(buku.id) AS total_buku
    FROM kategori
    LEFT JOIN buku ON buku.kategori_id = kategori.id
    GROUP BY kategori.id
    ORDER BY kategori.nama_kategori ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Total seluruh judul yang sesuai filter
$totalHasil = count($dataBuku);

$pageTitle   = 'Katalog Koleksi Buku';
$currentPage = 'daftar_buku';

require 'includes/header.php';
?>

<!-- HEADER HALAMAN -->
<div class="page-header-wrap" style="margin-bottom: 24px;">
    <div>
        <h1 style="font-size: 26px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Katalog Buku Perpustakaan
        </h1>
        <p class="page-subtitle" style="font-size: 14.5px; color: var(--sw-text-muted); margin: 0;">
            Temukan buku pelajaran, karya sastra, referensi ilmiah, dan ensiklopedia yang siap dipinjam di sekolah.
        </p>
    </div>
</div>

<!-- =========================================================================
     1. TOOLBAR PENCARIAN & FILTER KATEGORI (INTERAKTIF)
     ========================================================================= -->
<div class="sw-toolbar">
    <!-- Form Pencarian Kata Kunci -->
    <form action="daftar_buku.php" method="GET" class="sw-search-form" autocomplete="off">
        <?php if (!empty($kategoriId)): ?>
            <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategoriId, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>

        <div class="sw-search-field">
            <!-- Ikon Kaca Pembesar -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" 
                   name="keyword" 
                   id="inpKeyword"
                   class="sw-search-input" 
                   placeholder="Cari judul buku, nama pengarang, topik, atau penerbit..." 
                   value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 20px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <span>Cari</span>
        </button>

        <?php if (!empty($keyword) || !empty($kategoriId)): ?>
            <a href="daftar_buku.php" class="sw-btn-detail" style="height: 44px; padding: 0 16px; font-size: 13.5px;" title="Reset filter dan pencarian">
                Reset
            </a>
        <?php endif; ?>
    </form>

    <!-- Filter Pil Kategori Koleksi (Interaktif) -->
    <div class="sw-cat-pills" role="navigation" aria-label="Filter Berdasarkan Kategori">
        <!-- Opsi Semua Kategori -->
        <a href="daftar_buku.php<?= !empty($keyword) ? '?keyword=' . urlencode($keyword) : '' ?>" 
           class="sw-cat-pill <?= empty($kategoriId) ? 'is-active' : '' ?>">
            Semua Koleksi
        </a>

        <!-- Daftar Kategori Dinamis -->
        <?php foreach ($kategoriList as $kat): 
            $isActive = ($kategoriId == $kat['id']);
            $paramsUrl = ['kategori' => $kat['id']];
            if (!empty($keyword)) $paramsUrl['keyword'] = $keyword;
            $urlKat = 'daftar_buku.php?' . http_build_query($paramsUrl);
        ?>
            <a href="<?= htmlspecialchars($urlKat, ENT_QUOTES, 'UTF-8') ?>" 
               class="sw-cat-pill <?= $isActive ? 'is-active' : '' ?>">
                <?= htmlspecialchars($kat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                <span style="opacity: 0.7; font-size: 11px;">(<?= (int) $kat['total_buku'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- =========================================================================
     2. GRID KATALOG KOLEKSI BUKU
     ========================================================================= -->
<?php if (empty($dataBuku)): ?>
    <!-- Tampilan Keadaan Kosong (Jika Buku Tidak Ditemukan) -->
    <div style="background: #FFFFFF; border: 1.5px dashed var(--sw-border); border-radius: var(--sw-radius-md); padding: 56px 24px; text-align: center; margin-top: 12px;">
        <div style="width: 52px; height: 52px; border-radius: 50%; background: var(--sw-primary-soft); color: var(--sw-primary); display: grid; place-items: center; margin: 0 auto 16px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
        <h3 style="font-size: 18px; font-weight: 800; color: var(--sw-text); margin: 0 0 6px;">
            Buku Tidak Ditemukan
        </h3>
        <p style="font-size: 14px; color: var(--sw-text-muted); margin: 0 0 20px; max-width: 480px; margin-inline: auto;">
            Tidak ada buku yang cocok dengan pencarian "<strong><?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?></strong>". Coba periksa ejaan judul buku atau pilih kategori yang berbeda.
        </p>
        <a href="daftar_buku.php" class="btn btn-primary" style="font-size: 13.5px; padding: 10px 22px;">
            Lihat Seluruh Koleksi Buku
        </a>
    </div>
<?php else: ?>
    <!-- Info Ringkas Jumlah Hasil -->
    <div style="margin-bottom: 16px; font-size: 13.5px; color: var(--sw-text-muted); display: flex; justify-content: space-between; align-items: center;">
        <span>Menampilkan <strong><?= $totalHasil ?></strong> judul buku perpustakaan</span>
        <?php if (!empty($keyword)): ?>
            <span>Hasil pencarian: "<em><?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?></em>"</span>
        <?php endif; ?>
    </div>

    <!-- Grid Kartu Koleksi Buku -->
    <div class="sw-book-grid">
        <?php foreach ($dataBuku as $buku): 
            $cvUrl     = coverUrl($buku['cover'] ?? null, '../uploads/covers/');
            $stokTersedia = (int) ($buku['total_tersedia'] ?? 0);
            $totalFisik   = (int) ($buku['total_eksemplar'] ?? 0);
            $tersedia     = $stokTersedia > 0;

            // Mempersiapkan payload JSON aman untuk modal popup interaktif
            $payloadJson = json_encode([
                'id'            => (int) $buku['id'],
                'kode_buku'     => (string) $buku['kode_buku'],
                'judul'         => (string) $buku['judul'],
                'pengarang'     => (string) ($buku['pengarang'] ?: '-'),
                'penerbit'      => (string) ($buku['penerbit'] ?: '-'),
                'tahun_terbit'  => (string) ($buku['tahun_terbit'] ?: '-'),
                'nama_kategori' => (string) ($buku['nama_kategori'] ?? 'Umum'),
                'cover_url'     => $cvUrl ?: '',
                'stok_tersedia' => $stokTersedia,
                'total_fisik'   => $totalFisik,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        ?>
            <div class="sw-book-card">
                <div class="sw-book-card-main">
                    <!-- Efek Visual Cover Buku 3D -->
                    <div class="sw-book-cover">
                        <?php if ($cvUrl): ?>
                            <img src="<?= htmlspecialchars($cvUrl, ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="Cover <?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?>" 
                                 loading="lazy">
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Metadata Koleksi Buku -->
                    <div class="sw-book-meta">
                        <span class="sw-cat-badge" style="background:#EBF7F0; color:#147A50;">
                            <?= htmlspecialchars($buku['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <h3 class="sw-book-title" title="<?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($buku['judul'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="sw-book-author">
                            <strong>Penulis:</strong> <?= htmlspecialchars($buku['pengarang'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="sw-book-pub">
                            <?= htmlspecialchars($buku['penerbit'] ?: '-', ENT_QUOTES, 'UTF-8') ?> 
                            (<?= htmlspecialchars($buku['tahun_terbit'] ?: '-', ENT_QUOTES, 'UTF-8') ?>)
                        </p>
                    </div>
                </div>

                <!-- Bagian Bawah Kartu: Status Stok Fisik & Tombol Interaktif -->
                <div class="sw-book-card-foot">
                    <div class="sw-avail-pill <?= $tersedia ? 'ready' : 'empty' ?>">
                        <span class="sw-avail-dot"></span>
                        <span>
                            <?php if ($tersedia): ?>
                                <strong><?= $stokTersedia ?></strong> dari <?= $totalFisik ?> tersedia
                            <?php else: ?>
                                Sedang dipinjam (0/<?= $totalFisik ?>)
                            <?php endif; ?>
                        </span>
                    </div>

                    <button type="button" 
                            class="sw-btn-detail js-buka-modal-buku" 
                            data-buku='<?= htmlspecialchars($payloadJson, ENT_QUOTES, 'UTF-8') ?>'>
                        <span>Detail Buku</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- =========================================================================
     3. MODAL POPUP: DETAIL BUKU LENGKAP & PETUNJUK PEMINJAMAN
     ========================================================================= -->
<div class="sw-modal-backdrop" id="modalDetailBuku" hidden role="dialog" aria-modal="true" aria-labelledby="modalJudulUtama">
    <div class="sw-modal-dialog">
        <div class="sw-modal-head">
            <h3 id="modalJudulUtama">Detail Informasi Buku</h3>
            <button type="button" class="sw-modal-close" data-sw-close="modalDetailBuku" aria-label="Tutup Dialog">&times;</button>
        </div>

        <div class="sw-modal-body">
            <div class="sw-detail-layout">
                <!-- Cover Buku Ukuran Besar -->
                <div class="sw-detail-cover">
                    <img id="mCoverImg" alt="Cover Buku" hidden>
                    <svg id="mCoverPlaceholder" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    </svg>
                </div>

                <!-- Spesifikasi Informasi Buku -->
                <div class="sw-detail-info">
                    <span class="sw-cat-badge" id="mKatBadge" style="background:#EBF7F0; color:#147A50;">Kategori</span>
                    <h2 id="mJudulTxt" style="font-size: 20px; font-weight: 800; color: var(--sw-text); margin: 0 0 10px;">-</h2>

                    <div class="sw-detail-grid">
                        <div class="sw-detail-dt">Kode Buku</div>
                        <div class="sw-detail-dd" id="mKodeTxt">-</div>

                        <div class="sw-detail-dt">Penulis / Pengarang</div>
                        <div class="sw-detail-dd" id="mPengarangTxt">-</div>

                        <div class="sw-detail-dt">Penerbit</div>
                        <div class="sw-detail-dd" id="mPenerbitTxt">-</div>

                        <div class="sw-detail-dt">Tahun Terbit</div>
                        <div class="sw-detail-dd" id="mTahunTxt">-</div>

                        <div class="sw-detail-dt">Stok Fisik di Rak</div>
                        <div class="sw-detail-dd" id="mStokTxt">-</div>
                    </div>
                </div>
            </div>

            <!-- Petunjuk Peminjaman Siswa -->
            <div class="sw-detail-banner">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <strong>Cara Meminjam Buku Ini:</strong><br>
                    Kunjungi meja sirkulasi perpustakaan sekolah dengan membawa buku ini dan tap kartu RFID pelajarmu. Petugas akan memindai barcode buku dan batas pengembalian adalah <strong>7 hari</strong> ke depan.
                </div>
            </div>
        </div>

        <div class="sw-modal-foot">
            <button type="button" class="sw-btn-modal-close" data-sw-close="modalDetailBuku">Tutup</button>
        </div>
    </div>
</div>

<?php
// Script JavaScript untuk interaktivitas modal detail buku
$extraScripts = <<<'HTML'
<script>
(function () {
    const modal = document.getElementById('modalDetailBuku');
    const mCoverImg = document.getElementById('mCoverImg');
    const mCoverPlaceholder = document.getElementById('mCoverPlaceholder');
    const mKatBadge = document.getElementById('mKatBadge');
    const mJudulTxt = document.getElementById('mJudulTxt');
    const mKodeTxt = document.getElementById('mKodeTxt');
    const mPengarangTxt = document.getElementById('mPengarangTxt');
    const mPenerbitTxt = document.getElementById('mPenerbitTxt');
    const mTahunTxt = document.getElementById('mTahunTxt');
    const mStokTxt = document.getElementById('mStokTxt');

    function tampilkanDetailBuku(b) {
        if (!b) return;

        mJudulTxt.textContent = b.judul || '-';
        mKodeTxt.textContent = b.kode_buku || '-';
        mPengarangTxt.textContent = b.pengarang || '-';
        mPenerbitTxt.textContent = b.penerbit || '-';
        mTahunTxt.textContent = b.tahun_terbit || '-';
        mKatBadge.textContent = b.nama_kategori || 'Umum';

        const ada = b.stok_tersedia || 0;
        const tot = b.total_fisik || 0;
        mStokTxt.innerHTML = ada > 0 
            ? `<span style="color:#1B7A55;"><strong>${ada} eksemplar tersedia</strong> (dari ${tot} buku fisik)</span>`
            : `<span style="color:#C23B3B;">Sedang dipinjam semua (0/${tot})</span>`;

        if (b.cover_url) {
            mCoverImg.src = b.cover_url;
            mCoverImg.hidden = false;
            mCoverPlaceholder.hidden = true;
        } else {
            mCoverImg.hidden = true;
            mCoverPlaceholder.hidden = false;
        }

        if (modal) modal.hidden = false;
    }

    // Klik tombol detail buku
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-buka-modal-buku');
        if (btn) {
            try {
                const data = JSON.parse(btn.dataset.buku || '{}');
                tampilkanDetailBuku(data);
            } catch (err) {}
        }
    });

    // Tutup modal
    document.querySelectorAll('[data-sw-close]').forEach(b => {
        b.addEventListener('click', () => { if (modal) modal.hidden = true; });
    });

    if (modal) {
        modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal && !modal.hidden) modal.hidden = true;
    });
})();
</script>
HTML;

require 'includes/footer.php';
?>
