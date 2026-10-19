<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Pemutusan Sesi Pengguna (Logout Action)
 * File : logout.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Menghapus seluruh data sesi login pengguna (cookies dan session server)
 * lalu mengembalikan pengguna ke halaman formulir login secara aman.
 */

require_once __DIR__ . '/helpers/auth.php';

// Panggil metode logout dari kelas Auth untuk membersihkan sesi
Auth::logout();

// Alihkan pengguna ke halaman login
header('Location: login.php');
exit;