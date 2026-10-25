<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Vercel Serverless Front Controller / Gateway
 * File : api/index.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Bertindak sebagai gateway utama untuk menangani seluruh permintaan halaman PHP di Vercel Serverless.
 * Mengarahkan URL bersih (Clean URLs) dan file PHP di direktori root ke file target masing-masing
 * dengan mempertahankan working directory root proyek.
 */

// Pindahkan working directory ke root proyek agar seluruh include / require relatif berjalan normal
chdir(dirname(__DIR__));

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = trim($parsedPath, '/');

// 1. Root / Beranda
if ($path === '' || $path === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

// 2. Akses langsung file root (misal: /login.php, /dashboard.php, /operator_lite.php)
$targetFile = __DIR__ . '/../' . $path;
if (is_file($targetFile)) {
    require $targetFile;
    exit;
}

// 3. Akses Clean URL (misal: /login -> login.php, /dashboard -> dashboard.php)
if (is_file($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 4. Akses file di dalam subdirektori api/ jika terpanggil melalui gateway ini
$apiTarget = __DIR__ . '/' . $path;
if (is_file($apiTarget)) {
    require $apiTarget;
    exit;
}
if (is_file($apiTarget . '.php')) {
    require $apiTarget . '.php';
    exit;
}

// 5. Fallback 404 jika halaman tidak ditemukan
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>404 Halaman Tidak Ditemukan - GT Barcode System</title>
    <link rel="icon" type="image/png" href="/assets/img/Logo_Aplikasi.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b0f19; color: #fff; font-family: sans-serif; text-align: center; padding: 20px; }
        .card { background: rgba(17, 24, 39, 0.95); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 32px; max-width: 480px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="color: #ef4444; font-size: 28px; margin-bottom: 12px;">404</h1>
        <p style="color: #9ca3af; margin-bottom: 24px;">Halaman yang Anda tuju (<code><?= htmlspecialchars($parsedPath) ?></code>) tidak ditemukan pada sistem.</p>
        <a href="/" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 6px;">Kembali ke Beranda</a>
    </div>
</body>
</html>