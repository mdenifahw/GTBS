<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Vercel Serverless Front Controller / Gateway
 * File : api/index.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Gateway utama Vercel Serverless yang menerima seluruh request dan mengarahkannya
 * ke modul PHP di dalam direktori website/ dengan mempertahankan working directory,
 * sehingga seluruh require_once, asset include, dan session PHP berjalan normal.
 */

// Tentukan direktori website utama
$websiteDir = realpath(__DIR__ . '/../website') ?: (__DIR__ . '/../website');

if (is_dir($websiteDir)) {
    chdir($websiteDir);
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = trim($parsedPath, '/');

// Bersihkan subfolder prefix jika ada (misal: /GT_Barcode_System/ atau /website/)
if (strpos($path, 'GT_Barcode_System/') === 0) {
    $path = substr($path, 18);
}
if (strpos($path, 'website/') === 0) {
    $path = substr($path, 8);
}

// 1. Root / Beranda / Index -> Arahkan ke login.php
if ($path === '' || $path === 'index.php' || $path === 'website') {
    require $websiteDir . '/login.php';
    exit;
}

// 2. Permintaan API (misal: api/barcode_management.php, api/user_management.php)
if (strpos($path, 'api/') === 0) {
    $subApiPath = substr($path, 4);
    $targetApi = $websiteDir . '/api/' . $subApiPath;
    if (is_file($targetApi)) {
        require $targetApi;
        exit;
    }
    if (is_file($targetApi . '.php')) {
        require $targetApi . '.php';
        exit;
    }
}

// 3. Akses langsung file PHP di website/ (misal: login.php, dashboard.php, operator_lite.php, scada.php)
$targetFile = $websiteDir . '/' . $path;
if (is_file($targetFile)) {
    require $targetFile;
    exit;
}

// 4. Akses Clean URL (misal: /login -> login.php, /dashboard -> dashboard.php, /scada -> scada.php)
if (is_file($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 5. Cek apakah file berada di subfolder api/
$apiDirect = $websiteDir . '/api/' . $path;
if (is_file($apiDirect)) {
    require $apiDirect;
    exit;
}
if (is_file($apiDirect . '.php')) {
    require $apiDirect . '.php';
    exit;
}

// 6. Cek root api/
$rootApi = __DIR__ . '/' . $path;
if (is_file($rootApi)) {
    require $rootApi;
    exit;
}

// 7. Fallback 404 Responsif Modern
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Halaman Tidak Ditemukan - GT Barcode System</title>
    <link rel="icon" type="image/png" href="/assets/img/Logo_Aplikasi.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b0f19; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; text-align: center; padding: 20px; }
        .card { background: rgba(17, 24, 39, 0.95); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 36px; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="color: #ef4444; font-size: 36px; margin: 0 0 12px 0; font-weight: 800;">404</h1>
        <h2 style="font-size: 18px; margin: 0 0 12px 0; color: #e5e7eb;">Halaman Tidak Ditemukan</h2>
        <p style="color: #9ca3af; font-size: 14px; margin-bottom: 24px;">Halaman <code><?= htmlspecialchars($parsedPath) ?></code> tidak ditemukan pada sistem.</p>
        <a href="/" style="display: inline-block; padding: 10px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">Kembali ke Beranda</a>
    </div>
</body>
</html>
