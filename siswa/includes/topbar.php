<?php
/**
 * ============================================================================
 * TOPBAR PORTAL SISWA - PINJAM.IN
 * ============================================================================
 * Menampilkan tombol toggle sidebar (mobile), breadcrumb penunjuk halaman,
 * dan indikator tanggal sistem perpustakaan.
 */

// Menyiapkan nama hari dan bulan dalam bahasa Indonesia
$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$hariIni = $hariIndo[date('w')] . ', ' . date('j') . ' ' . $bulanIndo[date('n') - 1] . ' ' . date('Y');
?>
<header class="topbar">
    <div class="topbar-left">
        <!-- Tombol pembuka sidebar di layar HP/Tablet -->
        <button type="button" class="sidebar-toggle-btn" onclick="
            document.querySelector('.sidebar').classList.toggle('open');
            var bd = document.getElementById('sidebarBackdrop');
            if (bd) bd.classList.toggle('active');
        " aria-label="Buka Menu Navigasi">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Breadcrumb / penunjuk lokasi halaman saat ini -->
        <div class="breadcrumb">
            <span>Portal Siswa</span>
            <span class="sep">/</span>
            <span class="current"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Indikator kalender hari ini -->
        <div class="badge-status-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span><?= $hariIni ?></span>
        </div>
    </div>
</header>
