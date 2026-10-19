<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Header & Navigasi Atas (Top Header Layout)
 * File : includes/header.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Menghasilkan struktur pembuka halaman HTML, import CSS/Fonts, inisialisasi sesi, 
 * menu burger navigasi mobile, judul halaman aktif, pill shift berjalan, jam digital live (WIB),
 * tombol dark/light theme, tombol pemicu darurat K3 (SOS), serta tombol Logout.
 * 
 * [PANDUAN MODIFIKASI]:
 * - Header ini otomatis memeriksa login (Auth::requireLogin()) sebelum halaman dirender.
 * - Tampilan didesain responsive dengan CSS flexbox agar rapi di layar desktop, tablet, maupun layar sempit PDT.
 */

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/shift.php';

// Pastikan pengguna sudah terotentikasi sebelum membuka modul
Auth::requireLogin();
$currentUser = Auth::user();
$shiftInfo = ShiftHelper::getShiftInfo();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?? 'GT Barcode System' ?> - PT Gajah Tunggal Tbk</title>
    <!-- Favicon / Tab Logo -->
    <link rel="icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="apple-touch-icon" href="assets/img/Logo_Aplikasi.png">

    
    <!-- Google Fonts: Inter untuk tipografi industri modern yang jelas -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Master CSS Design System (Tema Gelap, Glassmorphism & Responsif) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- Memuat Navigasi Menu Samping (Sidebar) -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Pembungkus Konten Utama Aplikasi -->
    <div class="main-wrapper">
        <header class="header">
            <!-- Bagian Kiri Header: Toggle Menu Mobile, Judul Halaman & Pill Shift -->
            <div class="header-left">
                <!-- Tombol Menu Burger (Tampil pada layar HP / Tablet) -->
                <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Menu">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Judul Halaman Aktif -->
                <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>

                <!-- Badge / Pill Indikator Shift Berjalan Realtime -->
                <div class="shift-pill">
                    <span id="shiftPill"><?= $shiftInfo['label'] ?></span>
                </div>
            </div>

            <!-- Bagian Kanan Header: Jam Digital, Ganti Tema, Tombol SOS K3 & Tombol Logout -->
            <div class="header-right">


                <!-- Jam Digital Realtime (Diperbarui setiap detik oleh main.js) -->
                <div id="liveClock" style="font-size: 13px; font-weight: 600; color: var(--text-secondary);"><?= $shiftInfo['time'] ?> WIB</div>

                <!-- Tombol Ganti Tema (Dark Mode / Light Mode) -->
                <button class="header-btn" id="themeToggleBtn" title="Ganti Tema">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                    </svg>
                </button>

                <!-- Tombol Darurat K3 (Membuka Modal Panggilan Ambulans / Evakuasi) -->
                <button class="header-btn btn-sos" onclick="openModal('sosEmergencyModal')" title="Tombol Darurat K3">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    SOS K3
                </button>

                <!-- Tombol Keluar (Logout) -->
                <a href="logout.php" class="header-btn" title="Keluar">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </a>
            </div>
        </header>

        <!-- Area Konten Utama Halaman (Dibuka di sini, ditutup di includes/footer.php) -->
        <div class="content-body">