<?php
require 'cek_session_admin.php';
require 'koneksi.php';
require_once __DIR__ . '/pengaturan_helper.php';

$appConfig = ambilPengaturan($koneksi);
$appNama   = $appConfig['nama_aplikasi'] ?? 'Pinjam.In';

$bukuId  = $_GET['buku_id'] ?? null;
$itemId  = $_GET['item_id'] ?? null;
$kode    = trim($_GET['kode'] ?? '');
$from    = $_GET['from'] ?? '';
$newQty  = isset($_GET['new_qty']) ? (int)$_GET['new_qty'] : null;

$labels   = [];
$bukuInfo = null;

if (!empty($bukuId)) {
    // Mode Struk Buku: Ambil eksemplar buku
    $stmtBuku = $koneksi->prepare("
        SELECT buku.*, kategori.nama_kategori 
        FROM buku 
        LEFT JOIN kategori ON buku.kategori_id = kategori.id 
        WHERE buku.id = :id
    ");
    $stmtBuku->bindParam(':id', $bukuId);
    $stmtBuku->execute();
    $bukuInfo = $stmtBuku->fetch();

    if (!$bukuInfo) {
        header('Location: ../admin/buku.php');
        exit;
    }

    $stmtItems = $koneksi->prepare("
        SELECT * FROM buku_item 
        WHERE buku_id = :buku_id 
        ORDER BY id ASC
    ");
    $stmtItems->bindParam(':buku_id', $bukuId);
    $stmtItems->execute();
    $rawItems = $stmtItems->fetchAll();

    $total = count($rawItems);

    // Jika dipanggil setelah generate baru dan ada parameter new_qty, bisa ambil yang terakhir saja jika diminta
    if ($newQty !== null && $newQty > 0 && $newQty < $total && isset($_GET['only_new'])) {
        $rawItems = array_slice($rawItems, -$newQty);
    }

    foreach ($rawItems as $idx => $it) {
        $labels[] = [
            'id'            => $it['id'],
            'kode_item'     => $it['kode_item'],
            'judul'         => $bukuInfo['judul'],
            'pengarang'     => $bukuInfo['pengarang'] ?? '-',
            'nama_kategori' => $bukuInfo['nama_kategori'] ?? 'Umum',
            'no_fisik'      => $idx + 1,
            'total_fisik'   => $total,
            'buku_id'       => $bukuInfo['id']
        ];
    }

} elseif (!empty($itemId)) {
    // Mode Satuan berdasarkan item_id
    $stmtItem = $koneksi->prepare("
        SELECT buku_item.*, buku.judul, buku.pengarang, kategori.nama_kategori
        FROM buku_item
        JOIN buku ON buku_item.buku_id = buku.id
        LEFT JOIN kategori ON buku.kategori_id = kategori.id
        WHERE buku_item.id = :id
        LIMIT 1
    ");
    $stmtItem->bindParam(':id', $itemId);
    $stmtItem->execute();
    $it = $stmtItem->fetch();

    if (!$it) {
        header('Location: ../admin/buku.php');
        exit;
    }

    // Hitung posisi nomor urut fisik
    $stmtCount = $koneksi->prepare("
        SELECT COUNT(*) AS pos 
        FROM buku_item 
        WHERE buku_id = :buku_id AND id <= :item_id
    ");
    $stmtCount->bindParam(':buku_id', $it['buku_id']);
    $stmtCount->bindParam(':item_id', $it['id']);
    $stmtCount->execute();
    $pos = $stmtCount->fetchColumn() ?: 1;

    $stmtTot = $koneksi->prepare("SELECT COUNT(*) FROM buku_item WHERE buku_id = :buku_id");
    $stmtTot->bindParam(':buku_id', $it['buku_id']);
    $stmtTot->execute();
    $tot = $stmtTot->fetchColumn() ?: 1;

    $bukuInfo = ['judul' => $it['judul'], 'id' => $it['buku_id']];
    $labels[] = [
        'id'            => $it['id'],
        'kode_item'     => $it['kode_item'],
        'judul'         => $it['judul'],
        'pengarang'     => $it['pengarang'] ?? '-',
        'nama_kategori' => $it['nama_kategori'] ?? 'Umum',
        'no_fisik'      => $pos,
        'total_fisik'   => $tot,
        'buku_id'       => $it['buku_id']
    ];

} elseif (!empty($kode)) {
    // Fallback: Mode berdasarkan string kode
    $stmtItem = $koneksi->prepare("
        SELECT buku_item.*, buku.judul, buku.pengarang, kategori.nama_kategori
        FROM buku_item
        JOIN buku ON buku_item.buku_id = buku.id
        LEFT JOIN kategori ON buku.kategori_id = kategori.id
        WHERE buku_item.kode_item = :kode
        LIMIT 1
    ");
    $stmtItem->bindParam(':kode', $kode);
    $stmtItem->execute();
    $it = $stmtItem->fetch();

    if ($it) {
        $bukuInfo = ['judul' => $it['judul'], 'id' => $it['buku_id']];
        $labels[] = [
            'id'            => $it['id'],
            'kode_item'     => $it['kode_item'],
            'judul'         => $it['judul'],
            'pengarang'     => $it['pengarang'] ?? '-',
            'nama_kategori' => $it['nama_kategori'] ?? 'Umum',
            'no_fisik'      => 1,
            'total_fisik'   => 1,
            'buku_id'       => $it['buku_id']
        ];
    } else {
        $labels[] = [
            'id'            => 0,
            'kode_item'     => $kode,
            'judul'         => 'Label Buku',
            'pengarang'     => '-',
            'nama_kategori' => '-',
            'no_fisik'      => 1,
            'total_fisik'   => 1,
            'buku_id'       => 0
        ];
    }
} else {
    header('Location: ../admin/buku.php');
    exit;
}

$backUrl = (!empty($bukuInfo) && !empty($bukuInfo['id'])) 
    ? "../admin/buku_item.php?buku_id=" . $bukuInfo['id'] 
    : "../admin/buku.php";

// ==========================================================
// Khusus tampilan (layout admin) - tidak mempengaruhi logika di atas
// ==========================================================
$lblHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$lblBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$lblNow  = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$lblTgl  = $lblHari[(int)$lblNow->format('w')] . ', ' . $lblNow->format('j') . ' ' . $lblBulan[(int)$lblNow->format('n') - 1] . ' ' . $lblNow->format('Y');

if (!function_exists('lbl_ic')) {
    function lbl_ic($nama, $ukuran = 20) {
        $p = [
            'home'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'loan'    => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h6"/>',
            'return'  => '<path d="M9 14 4 9l5-5"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
            'book'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>',
            'tag'     => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
            'users'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'user'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'menu'    => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
            'calendar'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
            'qr'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v.01"/><path d="M14 20h.01"/><path d="M20 20v.01"/><path d="M17 17v3h3"/>',
            'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'check'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'back'    => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
            'layers'  => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'activity'=> '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        ];
        return '<svg width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$nama] ?? '') . '</svg>';
    }
}

// Sidebar diambil dari admin/includes/sidebar.php, perlu fungsi ic() dan $currentPage
if (!function_exists('ic')) {
    function ic($nama, $ukuran = 20) { return lbl_ic($nama, $ukuran); }
}
$currentPage = 'buku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="../admin/">
    <title>Cetak Label QR Buku - <?= !empty($bukuInfo) ? htmlspecialchars($bukuInfo['judul']) : htmlspecialchars($appNama) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/transition.css?v=1.0">
    <script src="../assets/js/qrcode.min.js"></script>
    <style>
        :root {
            --g-950: #0a3d2a;
            --g-900: #0d4a33;
            --g-800: #11593d;
            --g-700: #1f6f4e;
            --g-600: #16895a;
            --g-400: #34d399;
            --g-100: #dcf5e8;
            --g-50:  #effaf4;
            --bg:    #f4f7f5;
            --line:  #e6eee9;
            --ink:   #10241b;
            --muted: #5f7469;
            --font-ui:   'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --font-mono: 'JetBrains Mono', 'Consolas', 'Courier New', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: var(--font-ui);
            background: var(--bg);
            color: var(--ink);
            line-height: 1.45;
        }
        a { color: inherit; }
        svg { flex-shrink: 0; }

        /* ===================== LAYOUT ===================== */
        .app {
            display: grid;
            grid-template-columns: 264px minmax(0, 1fr);
            min-height: 100vh;
        }
        .main { min-width: 0; display: flex; flex-direction: column; }
        .content { padding: 32px 32px 48px; width: 100%; max-width: 1240px; margin: 0 auto; }

        /* ===================== SIDEBAR ===================== */
        .side {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            color: #d6efe3;
            background: linear-gradient(180deg, var(--g-900) 0%, var(--g-950) 100%);
        }
        .side-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 22px 18px;
            text-decoration: none;
            color: #fff;
        }
        .side-brand svg { width: 46px; height: 38px; }
        .side-brand b { display: block; font-size: 21px; font-weight: 800; letter-spacing: -.01em; line-height: 1.15; }
        .side-brand small { display: block; font-size: 11px; color: rgba(214, 239, 227, .8); }

        .side-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 22px 18px;
        }
        .side-ava {
            width: 48px; height: 48px;
            display: grid; place-items: center;
            border-radius: 50%;
            color: var(--g-900);
            background: #e3f2ea;
        }
        .side-who { min-width: 0; flex: 1; }
        .side-who b {
            display: block;
            font-size: 15.5px; font-weight: 700; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .side-role { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
        .side-role small { font-size: 12px; color: rgba(214, 239, 227, .75); }
        .pill-on {
            padding: 1px 9px;
            border-radius: 9999px;
            font-size: 11px; font-weight: 600;
            color: #c9f5de;
            background: rgba(52, 211, 153, .2);
            border: 1px solid rgba(52, 211, 153, .35);
        }

        .side-nav { padding: 4px 0 12px; }
        .side-label {
            padding: 14px 22px 8px;
            font-size: 11px; font-weight: 600;
            letter-spacing: .06em;
            color: rgba(214, 239, 227, .6);
        }
        .side-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 2px 10px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 15px; font-weight: 500;
            color: #e3f2ea;
            text-decoration: none;
            transition: background .15s ease;
        }
        .side-nav a:hover { background: rgba(255, 255, 255, .07); }
        .side-nav a.active { background: rgba(255, 255, 255, .15); color: #fff; font-weight: 700; }

        .side-foot {
            margin-top: auto;
            padding: 12px 10px 16px;
            border-top: 1px solid rgba(255, 255, 255, .1);
        }
        .side-foot a {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 15px; font-weight: 500;
            color: #e3f2ea;
            text-decoration: none;
        }
        .side-foot a:hover { background: rgba(214, 69, 69, .22); color: #fff; }

        /* ===================== TOPBAR ===================== */
        .top {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: 68px;
            padding: 0 32px;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }
        .top-l { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .burger {
            display: none;
            width: 40px; height: 40px;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--g-50);
            color: var(--g-800);
            cursor: pointer;
        }
        .crumb { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--muted); white-space: nowrap; }
        .crumb i { font-style: normal; }
        .crumb b { color: var(--ink); font-weight: 600; }

        .clock { display: flex; align-items: center; gap: 12px; text-align: left; }
        .clock-ico {
            width: 42px; height: 42px;
            display: grid; place-items: center;
            border-radius: 12px;
            color: var(--g-700);
            background: var(--g-50);
        }
        .clock b { display: block; font-size: 14px; font-weight: 600; line-height: 1.25; }
        .clock small { display: block; font-size: 12px; color: var(--muted); }

        /* ===================== HEADER HALAMAN ===================== */
        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .page-head h1 { font-size: 32px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; }
        .page-head p { margin-top: 6px; font-size: 15px; color: var(--muted); }
        .head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .btn {
            display: inline-flex; align-items: center; gap: 9px;
            height: 46px;
            padding: 0 20px;
            border-radius: 12px;
            font-family: inherit; font-size: 14.5px; font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: filter .15s ease, background .15s ease, transform .15s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            color: #fff;
            background: var(--g-700);
            box-shadow: 0 8px 18px rgba(31, 111, 78, .28);
        }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-ghost {
            color: var(--ink);
            background: #fff;
            border-color: var(--line);
            font-weight: 600;
        }
        .btn-ghost:hover { background: var(--g-50); border-color: #cfe6da; color: var(--g-800); }
        .btn:focus-visible, .pill:focus-visible, .burger:focus-visible {
            outline: 3px solid rgba(52, 211, 153, .55);
            outline-offset: 2px;
        }

        /* ===================== KARTU ===================== */
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 4px 18px rgba(16, 60, 40, .05);
        }

        .banner-info {
            display: flex; align-items: flex-start; gap: 12px;
            margin-bottom: 18px;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px; font-weight: 500;
            color: var(--g-800);
            background: var(--g-50);
            border: 1px solid #bfe6d1;
        }
        .banner-info svg { margin-top: 1px; color: var(--g-700); }

        /* Toolbar */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
            padding: 16px 20px;
            margin-bottom: 18px;
        }
        .toolbar-l { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; margin-right: 6px; }
        .toolbar-title svg { color: var(--g-700); }
        .pill {
            height: 40px;
            padding: 0 18px;
            border: 1px solid var(--line);
            border-radius: 9999px;
            background: #fff;
            color: var(--ink);
            font-family: inherit; font-size: 13.5px; font-weight: 600;
            cursor: pointer;
            transition: all .15s ease;
        }
        .pill:hover { background: var(--g-50); border-color: #cfe6da; }
        .pill.active { background: var(--g-700); border-color: var(--g-700); color: #fff; }

        .toolbar-r { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 12.5px; font-weight: 600;
            color: var(--g-800);
            background: var(--g-50);
            border: 1px solid #d3eadc;
        }

        /* Kartu pratinjau */
        .sheet-card { overflow: hidden; }
        .card-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
        }
        .card-head-l { display: flex; align-items: center; gap: 14px; }
        .card-ico {
            width: 44px; height: 44px;
            display: grid; place-items: center;
            border-radius: 12px;
            color: #fff;
            background: var(--g-700);
        }
        .card-head h3 { font-size: 17px; font-weight: 800; line-height: 1.25; }
        .card-head p { font-size: 13px; color: var(--muted); }
        .card-head-r { font-size: 13.5px; color: var(--muted); }
        .card-head-r b { color: var(--ink); font-weight: 800; }

        .sheet-body { padding: 24px; background: #fbfdfc; }

        .tip {
            display: flex; align-items: flex-start; gap: 10px;
            margin-top: 18px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px; color: var(--muted);
            background: #fff;
            border: 1px solid var(--line);
        }
        .tip svg { margin-top: 1px; color: var(--g-700); }

        /* ===================== GRID LABEL ===================== */
        .labels-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .labels-grid.cols-2 { grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .labels-grid.cols-4 { grid-template-columns: repeat(4, 1fr); gap: 12px; }

        .label-card {
            border: 1.5px dashed #9bb5a8;
            border-radius: 12px;
            padding: 12px 14px;
            display: flex; align-items: center; gap: 12px;
            page-break-inside: avoid; break-inside: avoid;
            position: relative;
            background: #ffffff;
        }
        .cut-mark {
            position: absolute; top: -9px; right: 10px;
            background: #fbfdfc;
            padding: 0 5px;
            font-size: 10px; color: #8aa497;
            font-family: var(--font-mono);
        }
        .label-qr-section {
            flex-shrink: 0; text-align: center;
            background: #fff;
            padding: 6px;
            border-radius: 10px;
            border: 1px solid var(--line);
        }
        .label-qr-section .qr-target { display: flex; justify-content: center; align-items: center; }
        .label-qr-section canvas, .label-qr-section img { display: block; margin: 0 auto; }
        .label-copy-badge {
            margin-top: 4px;
            font-family: var(--font-mono);
            font-size: 10px; font-weight: 700;
            color: var(--g-800);
            background: var(--g-100);
            padding: 2px 8px;
            border-radius: 9999px;
            display: inline-block;
        }
        .label-info-section { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
        .label-lib-brand {
            display: flex; align-items: center; justify-content: space-between; gap: 6px;
            margin-bottom: 2px;
            font-size: 9.5px; font-weight: 800; letter-spacing: .8px;
            color: var(--g-700);
            text-transform: uppercase;
        }
        .label-category-pill {
            max-width: 90px;
            padding: 2px 7px;
            border-radius: 9999px;
            font-size: 8.5px; font-weight: 700; letter-spacing: 0;
            color: var(--g-800);
            background: var(--g-100);
            border: 1px solid #bfe6d1;
            text-transform: none;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .label-book-code {
            display: inline-block; width: fit-content;
            margin: 3px 0 4px;
            padding: 3px 8px;
            font-family: var(--font-mono);
            font-size: 13px; font-weight: 700; letter-spacing: .4px;
            color: var(--ink);
            background: #fff;
            border: 1.5px solid var(--ink);
            border-radius: 6px;
        }
        .label-book-title {
            margin-bottom: 2px;
            font-size: 12px; font-weight: 700; line-height: 1.25;
            color: var(--ink);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .label-book-meta {
            display: flex; align-items: center; gap: 4px;
            font-size: 10.5px; color: var(--muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .labels-grid.cols-2 .label-card { padding: 14px 18px; gap: 16px; }
        .labels-grid.cols-2 .label-book-code { font-size: 15px; padding: 4px 10px; }
        .labels-grid.cols-2 .label-book-title { font-size: 13.5px; }
        .labels-grid.cols-2 .label-book-meta { font-size: 11.5px; }

        .labels-grid.cols-4 .label-card { padding: 8px 10px; gap: 8px; }
        .labels-grid.cols-4 .label-book-code { font-size: 11px; padding: 2px 6px; }
        .labels-grid.cols-4 .label-book-title { font-size: 11px; -webkit-line-clamp: 1; }
        .labels-grid.cols-4 .label-lib-brand { font-size: 8.5px; }

        /* ===================== RESPONSIF ===================== */
        @media (max-width: 1000px) {
            .app { grid-template-columns: minmax(0, 1fr); }
            .side {
                position: fixed; left: 0; top: 0; bottom: 0; z-index: 50;
                width: 264px;
                transform: translateX(-100%);
                transition: transform .2s ease;
            }
            .side.open { transform: translateX(0); box-shadow: 0 0 40px rgba(0, 0, 0, .35); }
            .burger { display: grid; }
            .top { padding: 0 16px; }
            .content { padding: 22px 16px 40px; }
            .page-head h1 { font-size: 26px; }
        }
        @media (max-width: 640px) {
            .crumb { display: none; }
            .labels-grid, .labels-grid.cols-2, .labels-grid.cols-4 { grid-template-columns: 1fr; }
            .sheet-body { padding: 16px; }
        }

        /* ===================== CETAK ===================== */
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            html, body {
                height: auto !important;
                background: #fff !important;
                padding: 0 !important; margin: 0 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .side, .top, .page-head, .banner-info, .toolbar, .tip, .card-head { display: none !important; }
            .app, .main { display: block !important; }
            .content { padding: 0 !important; max-width: none !important; }
            .sheet-card { border: none !important; box-shadow: none !important; border-radius: 0 !important; overflow: visible !important; }
            .sheet-body { padding: 0 !important; background: #fff !important; }
            .labels-grid { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 5mm !important; }
            .labels-grid.cols-2 { grid-template-columns: repeat(2, 1fr) !important; gap: 6mm !important; }
            .labels-grid.cols-4 { grid-template-columns: repeat(4, 1fr) !important; gap: 4mm !important; }
            .label-card {
                background: #fff !important;
                border: 1px dashed #475569 !important;
                page-break-inside: avoid !important; break-inside: avoid !important;
            }
            .label-qr-section { border: 1px solid #cbd5e1 !important; }
            .label-book-code { border: 1.5px solid #000 !important; color: #000 !important; }
            .cut-mark { display: inline-block !important; background: #fff !important; color: #64748b !important; }
        }
    </style>
</head>
<body>
<div class="app">

    <!-- ===================== SIDEBAR (dari admin/includes) ===================== -->
    <?php require __DIR__ . '/../admin/includes/sidebar.php'; ?>

    <div class="main">

        <!-- ===================== TOPBAR ===================== -->
        <header class="top">
            <div class="top-l">
                <button type="button" class="burger" aria-label="Buka menu" aria-controls="sidebar"
                        onclick="document.getElementById('sidebar').classList.toggle('open')"><?= lbl_ic('menu', 22) ?></button>
                <div class="crumb"><span>Admin</span><i>/</i><span>Data Buku</span><i>/</i><b>Cetak Label QR</b></div>
            </div>
            <div class="clock">
                <div class="clock-ico"><?= lbl_ic('calendar') ?></div>
                <div>
                    <b><?= $lblTgl ?></b>
                    <small><span id="clockTime"><?= $lblNow->format('H:i') ?></span> WIB</small>
                </div>
            </div>
        </header>

        <main class="content">

            <!-- ===================== HEADER HALAMAN ===================== -->
            <div class="page-head">
                <div>
                    <h1>Cetak Label QR Buku</h1>
                    <p>
                        <?php if (!empty($bukuInfo)): ?>
                            <?= htmlspecialchars($bukuInfo['judul']) ?> &bull;
                        <?php endif; ?>
                        Format label stiker untuk printer biasa (Kertas A4 / HVS / Kertas Stiker Label).
                    </p>
                </div>
                <div class="head-actions">
                    <a href="../admin/buku.php" class="btn btn-ghost"><?= lbl_ic('back', 18) ?> Ke Data Buku</a>
                    <?php if (!empty($bukuInfo) && !empty($bukuInfo['id'])): ?>
                        <a href="../admin/buku_item.php?buku_id=<?= $bukuInfo['id'] ?>" class="btn btn-ghost"><?= lbl_ic('layers', 18) ?> Kelola Eksemplar</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" onclick="window.print()"><?= lbl_ic('printer', 18) ?> Cetak Label Sekarang</button>
                </div>
            </div>

            <?php if ($from === 'tambah'): ?>
                <div class="banner-info">
                    <?= lbl_ic('check', 20) ?>
                    <div>
                        <strong>Buku berhasil ditambahkan!</strong> Sistem telah membuat <strong><?= count($labels) ?> kode QR unik</strong> untuk setiap eksemplar buku. Silakan cetak label untuk ditempelkan pada fisik buku.
                    </div>
                </div>
            <?php endif; ?>

            <!-- ===================== TOOLBAR ===================== -->
            <div class="card toolbar">
                <div class="toolbar-l">
                    <span class="toolbar-title"><?= lbl_ic('layers', 18) ?> Pilihan Kolom Cetak</span>
                    <button type="button" class="pill" id="btn-col-2" onclick="setLayout(2)">2 Kolom (Besar)</button>
                    <button type="button" class="pill active" id="btn-col-3" onclick="setLayout(3)">3 Kolom (Standar A4)</button>
                    <button type="button" class="pill" id="btn-col-4" onclick="setLayout(4)">4 Kolom (Kompak)</button>
                </div>
                <div class="toolbar-r">
                    <span class="chip"><?= lbl_ic('qr', 15) ?> <?= count($labels) ?> label QR</span>
                    <span class="chip"><?= lbl_ic('book', 15) ?> Kertas A4</span>
                </div>
            </div>

            <!-- ===================== PRATINJAU LABEL ===================== -->
            <div class="card sheet-card">
                <div class="card-head">
                    <div class="card-head-l">
                        <div class="card-ico"><?= lbl_ic('qr', 22) ?></div>
                        <div>
                            <h3>Pratinjau label</h3>
                            <p>Gunting mengikuti garis putus-putus setelah dicetak.</p>
                        </div>
                    </div>
                    <div class="card-head-r"><b><?= count($labels) ?></b> eksemplar siap cetak</div>
                </div>

                <!-- LEMBAR CETAK LABEL A4 -->
                <div class="sheet-body">
                    <div class="labels-grid" id="labels-grid">
                        <?php foreach ($labels as $idx => $lbl): ?>
                            <div class="label-card">
                                <span class="cut-mark">✂ gunting</span>

                                <!-- QR CODE SECTION -->
                                <div class="label-qr-section">
                                    <div class="qr-target" data-code="<?= htmlspecialchars($lbl['kode_item'], ENT_QUOTES) ?>"></div>
                                    <div class="label-copy-badge">
                                        #<?= $lbl['no_fisik'] ?> / <?= $lbl['total_fisik'] ?>
                                    </div>
                                </div>

                                <!-- INFO BUKU SECTION -->
                                <div class="label-info-section">
                                    <div class="label-lib-brand">
                                        <span><?= htmlspecialchars(strtoupper($appNama)) ?></span>
                                        <span class="label-category-pill"><?= htmlspecialchars($lbl['nama_kategori']) ?></span>
                                    </div>
                                    <div class="label-book-code"><?= htmlspecialchars($lbl['kode_item']) ?></div>
                                    <div class="label-book-title" title="<?= htmlspecialchars($lbl['judul']) ?>">
                                        <?= htmlspecialchars($lbl['judul']) ?>
                                    </div>
                                    <div class="label-book-meta">
                                        <?= lbl_ic('user', 11) ?>
                                        <span><?= htmlspecialchars($lbl['pengarang']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="tip">
                <?= lbl_ic('info', 16) ?>
                <div><strong>Tips Printer:</strong> Pada jendela pratinjau cetak browser, pilih <em>Ukuran Kertas: A4</em> dan centang <em>Cetak Gambar Latar (Background graphics)</em> agar tampilan border optimal.</div>
            </div>

        </main>
    </div>
</div>

    <script>
        function setLayout(cols) {
            const grid = document.getElementById('labels-grid');
            const btn2 = document.getElementById('btn-col-2');
            const btn3 = document.getElementById('btn-col-3');
            const btn4 = document.getElementById('btn-col-4');

            grid.classList.remove('cols-2', 'cols-4');
            btn2.classList.remove('active');
            btn3.classList.remove('active');
            btn4.classList.remove('active');

            if (cols === 2) {
                grid.classList.add('cols-2');
                btn2.classList.add('active');
            } else if (cols === 4) {
                grid.classList.add('cols-4');
                btn4.classList.add('active');
            } else {
                btn3.classList.add('active');
            }
        }

        // Render QR Code tajam & proporsional untuk printer standar
        function renderAllQRCodes() {
            const qrElements = document.querySelectorAll('.qr-target');
            const qrSize = 90; // Ukuran proporsional untuk label buku A4

            qrElements.forEach(function (el) {
                const text = el.getAttribute('data-code');
                if (text && (!el.hasChildNodes || !el.hasChildNodes())) {
                    new QRCode(el, {
                        text: text,
                        width: qrSize,
                        height: qrSize,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderAllQRCodes);
        } else {
            renderAllQRCodes();
        }

        // Jam topbar (WIB)
        (function () {
            const el = document.getElementById('clockTime');
            if (!el) return;
            function tick() {
                try {
                    el.textContent = new Intl.DateTimeFormat('id-ID', {
                        hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Jakarta'
                    }).format(new Date()).replace('.', ':');
                } catch (e) {}
            }
            setInterval(tick, 20000);
        })();
    </script>
</body>
</html>