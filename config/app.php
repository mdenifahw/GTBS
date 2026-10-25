<?php
/**
 * ==============================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Konfigurasi Global Aplikasi & Pengaturan Error Reporting Lingkungan Produksi
 * File : config/app.php
 * ==============================================================================
 */

// 1. Zona Waktu Seragam (WIB)
date_default_timezone_set('Asia/Jakarta');

// 2. Pengaturan Error Reporting Lingkungan Web / Produksi
//    Mencegah Deprecated Warning & Notice teknis tampil langsung di layar pengguna,
//    serta melindungi informasi internal struktur database dan server.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$logFile = dirname(__DIR__) . '/storage/logs/php_error.log';
ini_set('error_log', $logFile);

// 3. Helper Keamanan HTML Escaping Bebas Deprecated Warning (Mendukung PHP 8.1 - 8.5+)
if (!function_exists('e')) {
    /**
     * Escape output HTML secara aman terhadap nilai null / boolean / integer.
     * Tidak akan memicu Deprecated Warning di PHP 8.1 - 8.5+.
     */
    function e($val, string $default = ''): string {
        if ($val === null || $val === '') {
            return $default;
        }
        return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('h')) {
    /**
     * Alias pendek untuk fungsi e()
     */
    function h($val, string $default = ''): string {
        return e($val, $default);
    }
}

// 4. Format Tanggal Aman terhadap NULL
if (!function_exists('formatDateSafe')) {
    function formatDateSafe($dateStr, string $format = 'd M Y H:i:s', string $default = '-'): string {
        if (empty($dateStr)) return $default;
        $ts = strtotime((string)$dateStr);
        return $ts ? date($format, $ts) : $default;
    }
}

// 5. Konfigurasi Sesi Aman & Kompatibel Serverless Cloud (Vercel)
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || !empty($_ENV['VERCEL'])) {
        // Lingkungan Serverless Vercel menggunakan /tmp untuk session storage sementara
        if (is_dir('/tmp') && is_writable('/tmp')) {
            ini_set('session.save_path', '/tmp');
        }
    }
    
    // Cookie params yang aman untuk HTTPS & Browser Modern
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 hari
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}