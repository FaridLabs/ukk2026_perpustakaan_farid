<?php
// Helper tampilan kop resmi dan tanda tangan untuk laporan cetak / export PDF

function infoCetakWib(): array
{
    $blnPanjang = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $nowWib = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    return [
        'tglCetak'    => $nowWib->format('j') . ' ' . $blnPanjang[(int) $nowWib->format('n') - 1] . ' ' . $nowWib->format('Y'),
        'jamCetak'    => $nowWib->format('H:i'),
        'namaPetugas' => $_SESSION['admin_nama'] ?? 'Administrator',
    ];
}

function renderPrintHead(array $cfg): void
{
    global $appNama, $appSlogan, $appSekolah;

    $tglCetak    = $cfg['tglCetak'] ?? '';
    $jamCetak    = $cfg['jamCetak'] ?? '';
    $namaPetugas = $cfg['namaPetugas'] ?? 'Administrator';
    $subUnit     = $cfg['subUnit'] ?? 'Sistem Informasi Perpustakaan Sekolah';
    $docBadge    = $cfg['docBadge'] ?? 'Dokumen Laporan Resmi';
    $judul       = $cfg['judul'] ?? 'Laporan Sistem';
    $filterText  = $cfg['filterText'] ?? 'Semua data';
    $countText   = $cfg['countText'] ?? '0 data';

    $namaApp     = $cfg['appNama'] ?? ($appNama ?? 'Pinjam.In');
    $sloganApp   = $cfg['appSlogan'] ?? ($appSlogan ?? 'Perpustakaan Digital');
    $sekolahApp  = $cfg['appSekolah'] ?? ($appSekolah ?? 'SMKN 1 Panyingkiran');
    ?>
    <div class="print-head">
        <div class="ph-kop">
            <div class="ph-brand">
                <div class="ph-logo">
                    <svg viewBox="0 0 48 40" aria-hidden="true">
                        <path d="M24 15C23 6 29 2 37 3C37 11 32 16 24 15Z" fill="#5FD39B"/>
                        <path d="M3 13Q3 11 5.2 11.2Q14 12 24 17.5V36Q14 31 5.2 31Q3 31 3 29Z" fill="#E3F2EA"/>
                        <path d="M45 13Q45 11 42.8 11.2Q34 12 24 17.5V36Q34 31 42.8 31Q45 31 45 29Z" fill="#B7DEC9"/>
                    </svg>
                </div>
                <div class="ph-org">
                    <strong><?= htmlspecialchars(strtoupper($namaApp) . ' — ' . strtoupper($sloganApp)) ?></strong>
                    <span><?= htmlspecialchars($sekolahApp) ?></span>
                    <small><?= htmlspecialchars($subUnit) ?></small>
                </div>
            </div>
            <div class="ph-meta">
                <b><?= htmlspecialchars($docBadge) ?></b>
                <div>Dicetak: <strong><?= htmlspecialchars($tglCetak) ?>, <?= htmlspecialchars($jamCetak) ?> WIB</strong></div>
                <div>Petugas: <strong><?= htmlspecialchars($namaPetugas) ?></strong></div>
            </div>
        </div>
        <div class="ph-title">
            <div>
                <h1><?= htmlspecialchars($judul) ?></h1>
                <p>Filter aktif: <?= htmlspecialchars($filterText) ?></p>
            </div>
            <span class="ph-count"><?= htmlspecialchars($countText) ?></span>
        </div>
    </div>
    <?php
}

function renderPrintFoot(int $jumlahBaris, string $tglCetak, string $jamCetak, string $namaPetugas, string $namaAplikasi = ''): void
{
    global $appNama;
    if (empty($namaAplikasi)) {
        $namaAplikasi = $appNama ?? 'Pinjam.In';
    }
    ?>
    <div class="print-foot">
        <div class="pf-note">
            <strong>Keterangan Dokumen</strong>
            Dicetak secara otomatis melalui sistem Perpustakaan Digital <?= htmlspecialchars($namaAplikasi) ?>.<br>
            Menampilkan <?= $jumlahBaris ?> baris data pada <?= htmlspecialchars($tglCetak) ?>, <?= htmlspecialchars($jamCetak) ?> WIB.
        </div>
        <div class="pf-sign">
            <span>Panyingkiran, <?= htmlspecialchars($tglCetak) ?></span>
            <span>Mengetahui / Petugas Perpustakaan,</span>
            <i></i>
            <b><?= htmlspecialchars($namaPetugas) ?></b>
            <small>Administrator Perpustakaan</small>
        </div>
    </div>
    <?php
}
