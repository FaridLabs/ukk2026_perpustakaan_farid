<?php
/**
 * ============================================================================
 * HEADER PORTAL SISWA - PINJAM.IN
 * ============================================================================
 * Berfungsi memuat deklarasi HTML, Google Fonts, stylesheet global,
 * stylesheet tema hijau emerald, serta stylesheet khusus siswa (siswa.css).
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Portal Siswa - Pinjam.In';
}
if (!isset($currentPage)) {
    $currentPage = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Pinjam.In</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono untuk tampilan modern -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Stylesheet Utama & Tema Hijau Emerald -->
    <link rel="stylesheet" href="../assets/css/style.css?v=4.0">
    <link rel="stylesheet" href="../assets/css/theme-green.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/transition.css?v=1.0">
    <link rel="stylesheet" href="../assets/css/siswa.css?v=1.1">
    
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>

<body>
    <!-- Overlay latar belakang untuk menutup sidebar pada layar mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="document.querySelector('.sidebar').classList.remove('open'); this.classList.remove('active');"></div>

    <div class="app-layout">
        <!-- Memuat navigasi samping (sidebar) -->
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="main-wrapper">
            <!-- Memuat bilah navigasi atas (topbar) -->
            <?php require __DIR__ . '/topbar.php'; ?>
            
            <!-- Konten Utama Halaman Siswa -->
            <main class="content">
