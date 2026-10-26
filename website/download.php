<?php
/**
 * ==============================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Halaman Unduh APK Mobile App untuk Handphone / PDT Android
 * File : download.php
 * ==============================================================================
 */

$hostIp = '192.168.1.100';
$port = '8000';
$apkUrl = "http://{$hostIp}:{$port}/GT_Barcode_System/website/gtbs-mobile.apk";
$serverTargetUrl = "http://{$hostIp}:{$port}/GT_Barcode_System/website/login.php";
$apkFile = __DIR__ . '/gtbs-mobile.apk';
$apkSizeMb = file_exists($apkFile) ? round(filesize($apkFile) / (1024 * 1024), 1) : 9.7;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unduh Aplikasi Mobile - GT Barcode System</title>
    <link rel="icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0b0f19;
            --bg-card: rgba(17, 24, 39, 0.95);
            --border-card: rgba(59, 130, 246, 0.22);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --accent-blue: #3b82f6;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        
        body {
            background-color: var(--bg-base);
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.12) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .m-card {
            width: 100%;
            max-width: 480px;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(16px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .m-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan), var(--accent-emerald));
        }

        .logo-box {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 20px;
            background: #ffffff;
            border: 2px solid rgba(96, 165, 250, 0.4);
            display: grid;
            place-items: center;
            padding: 6px;
            box-shadow: 0 12px 24px -6px rgba(59, 130, 246, 0.4);
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 14px;
        }

        h1 { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 4px; }
        p.sub { font-size: 13px; color: var(--text-sub); margin-bottom: 22px; }

        .qr-wrap {
            background: #ffffff;
            padding: 14px;
            border-radius: 18px;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
        }

        .qr-wrap img {
            display: block;
            width: 180px;
            height: 180px;
            border-radius: 8px;
        }

        .btn-download {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #0284c7 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 10px 24px -4px rgba(37, 99, 235, 0.6);
            transition: all 0.2s ease;
            margin-bottom: 18px;
        }

        .btn-download:active {
            transform: scale(0.98);
        }

        .info-box {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 14px;
            padding: 14px;
            text-align: left;
            font-size: 12.5px;
            line-height: 1.5;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .info-box b { color: #60a5fa; }
        .info-box code {
            display: block;
            margin-top: 6px;
            padding: 6px 10px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 8px;
            font-family: monospace;
            font-size: 11.5px;
            color: #38bdf8;
            word-break: break-all;
        }

        .steps {
            text-align: left;
            font-size: 12px;
            color: var(--text-sub);
            padding-left: 18px;
            line-height: 1.6;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="m-card">
    <div class="logo-box">
        <img src="assets/img/Logo_Aplikasi.png" alt="GT Logo">
    </div>

    <h1>GT Barcode System</h1>
    <p class="sub">Unduh Paket Aplikasi Android (APK) untuk Handphone / PDT</p>

    <div class="qr-wrap">
        <!-- QR Code langsung menuju URL unduhan file APK -->
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode($apkUrl) ?>" alt="Scan QR Code untuk Unduh">
    </div>

    <a href="gtbs-mobile.apk" download="GTBS_Mobile.apk" class="btn-download">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
        </svg>
        <span>Unduh APK Langsung (<?= $apkSizeMb ?> MB)</span>
    </a>

    <div class="info-box">
        <b>💡 Konfigurasi Server di Handphone:</b>
        Pastikan HP terhubung ke Wi-Fi yang sama dengan PC laptop. URL server pabrik yang digunakan di HP:
        <code><?= htmlspecialchars($serverTargetUrl) ?></code>
    </div>

    <ol class="steps">
        <li>Buka kamera HP atau browser Chrome di HP Anda, lalu arahkan ke QR Code di atas.</li>
        <li>Unduh file <b>GTBS_Mobile.apk</b>, lalu ketuk Pasang / Instal.</li>
        <li>Jika muncul peringatan <i>"Sumber Tidak Dikenal"</i>, izinkan penginstalan.</li>
        <li>Buka aplikasi, ketuk ikon obeng di kanan bawah untuk memastikan URL server telah sesuai.</li>
    </ol>

    <div class="footer-note">
        PT Gajah Tunggal Tbk &bull; Android Industrial Terminal Build v2.0
    </div>
</div>

</body>
</html>
