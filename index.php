<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Gerbang Masuk Aplikasi (Entry Point Router)
 * File : index.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Halaman utama yang pertama kali diakses oleh browser saat membuka root web.
 * Memeriksa status sesi login pengguna:
 * - Jika sudah login: Dialihkan langsung ke dashboard.php
 * - Jika belum login: Dialihkan ke login.php
 */

require_once __DIR__ . '/helpers/auth.php';

// Cek apakah pengguna sudah memiliki sesi login yang valid
if (Auth::check()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;