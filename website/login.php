<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Formulir Masuk Pengguna (Login Page) - Mobile & Desktop Modern UI
 * File : login.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Halaman otentikasi login karyawan pabrik ban. Mendukung penginputan NIK / Username dan Password,
 * serta dilengkapi tombol "Quick Login" akun percontohan (Super Admin, Head, Leader, Operator) 
 * dengan desain antarmuka mobile-first yang serasi dengan UI/UX website.
 */

require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/shift.php';
require_once __DIR__ . '/helpers/mobile.php';

$shiftInfo = ShiftHelper::getShiftInfo();
$isMobileApp = gtbs_is_mobile_app();

function getRoleDestination($user) {
    if (!$user) return 'dashboard.php';
    $role = $user['role'] ?? '';
    $deptCode = strtoupper($user['dept_code'] ?? '');
    
    // 1. Operator lapangan -> langsung menuju Mode Lite (Operator)
    if ($role === 'operator') {
        return 'operator_lite.php';
    }
    
    // 2. Pejabat departemen spesifik -> langsung menuju halaman departemen masing-masing
    if (in_array($role, ['dept_head', 'asst_dept_head', 'admin_dept', 'section_head', 'leader'])) {
        switch ($deptCode) {
            case 'WH':  return 'dept_warehouse.php';
            case 'MAT': return 'dept_material.php';
            case 'BLD': return 'dept_building.php';
            case 'CUR': return 'dept_curing.php';
            case 'FI':  return 'dept_final_inspection.php';
        }
    }
    
    // 3. Super Admin, Plant Head, Asst Plant Head -> Dashboard Utama
    return 'dashboard.php';
}

$error = '';

// 1. Penanganan Aksi Quick Login (Masuk Cepat Satu Klik)
if (isset($_GET['quick_nik'])) {
    $nik = trim($_GET['quick_nik']);
    if (Auth::quickLoginByNik($nik)) {
        $target = getRoleDestination(Auth::user());
        header('Location: ' . $target);
        exit;
    } else {
        $error = 'Akun demo (' . htmlspecialchars($nik) . ') tidak ditemukan atau tidak aktif.';
    }
}

// 2. Penanganan Submit Form Standar
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Harap isi username/NIK dan password.';
    } else {
        if (Auth::login($username, $password)) {
            $target = getRoleDestination(Auth::user());
            header('Location: ' . $target);
            exit;
        } else {
            $error = 'Kredensial tidak valid. Silakan periksa kembali NIK/username dan password.';
        }
    }
}

// Jika sudah login, redirect langsung ke halaman kerja yang sesuai
if (Auth::check()) {
    $target = getRoleDestination(Auth::user());
    header('Location: ' . $target);
    exit;
}

// Daftar Akun Demo Quick Login dengan Kategori
$quickAccounts = [
    [
        'category' => 'admin',
        'nik'      => 'SA-001',
        'role'     => 'Super Admin',
        'name'     => 'Super Administrator',
        'desc'     => 'Akses Penuh Seluruh Modul',
        'color'    => '#3b82f6',
        'badge'    => 'SA'
    ],
    [
        'category' => 'mgmt',
        'nik'      => 'PH-001',
        'role'     => 'Plant Head',
        'name'     => 'Ir. Bambang Santoso',
        'desc'     => 'Akses Luas Pemantauan Pabrik',
        'color'    => '#10b981',
        'badge'    => 'PH'
    ],
    [
        'category' => 'mgmt',
        'nik'      => 'APH-001',
        'role'     => 'Asst Plant Head',
        'name'     => 'Dedi Prasetyo, M.T.',
        'desc'     => 'Membantu Pengawasan Operasional',
        'color'    => '#a855f7',
        'badge'    => 'APH'
    ],
    [
        'category' => 'dept',
        'nik'      => 'HEAD-CUR-01',
        'role'     => 'Dept Head Curing',
        'name'     => 'Hendra Wijaya',
        'desc'     => 'Kepala Departemen Curing',
        'color'    => '#f59e0b',
        'badge'    => 'DH'
    ],
    [
        'category' => 'dept',
        'nik'      => 'AHEAD-CUR-01',
        'role'     => 'Asst Dept Head',
        'name'     => 'Agus Setiawan',
        'desc'     => 'Asisten Kepala Dept Curing',
        'color'    => '#f97316',
        'badge'    => 'ADH'
    ],
    [
        'category' => 'dept',
        'nik'      => 'ADM-CUR-01',
        'role'     => 'Admin Dept Curing',
        'name'     => 'Siti Rahmawati',
        'desc'     => 'Admin Khusus Dept Curing',
        'color'    => '#06b6d4',
        'badge'    => 'ADM'
    ],
    [
        'category' => 'dept',
        'nik'      => 'ADM-WH-01',
        'role'     => 'Admin Gudang',
        'name'     => 'Fajar Nugroho',
        'desc'     => 'Admin Gudang Bahan Baku',
        'color'    => '#06b6d4',
        'badge'    => 'WH'
    ],
    [
        'category' => 'operator',
        'nik'      => 'SH-CUR-01',
        'role'     => 'Section Head Curing',
        'name'     => 'Budi Hartono',
        'desc'     => 'Kepala Seksi Produksi Curing',
        'color'    => '#ec4899',
        'badge'    => 'SH'
    ],
    [
        'category' => 'operator',
        'nik'      => 'LDR-CUR-01',
        'role'     => 'Leader Curing',
        'name'     => 'Rian Firmansyah',
        'desc'     => 'Leader Regu Curing Press',
        'color'    => '#8b5cf6',
        'badge'    => 'LDR'
    ],
    [
        'category' => 'operator',
        'nik'      => 'OP-CUR-01',
        'role'     => 'Operator Curing',
        'name'     => 'Ahmad Junaidi',
        'desc'     => 'Operator Stasiun Curing Press',
        'color'    => '#64748b',
        'badge'    => 'OP'
    ],
    [
        'category' => 'operator',
        'nik'      => 'OP-CUR-04',
        'role'     => 'Operator Repair',
        'name'     => 'Joko Wahyudi',
        'desc'     => 'Operator Stasiun Repair Ban',
        'color'    => '#64748b',
        'badge'    => 'REP'
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - GT Barcode System (ERP & SCADA)</title>
    <!-- Favicon / Tab Logo -->
    <link rel="icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="apple-touch-icon" href="assets/img/Logo_Aplikasi.png">

    <!-- Typography Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?= @filemtime(__DIR__ . '/assets/css/mobile.css') ?>">

    <style>
        :root {
            --app-bg: #0b0f19;
            --app-card: #111827;
            --app-input: #1a2233;
            --app-border: rgba(148, 163, 184, 0.16);
            --app-text: #f9fafb;
            --app-text-muted: #9ca3af;
            --app-accent: #3b82f6;
            --app-accent-cyan: #06b6d4;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            min-height: 100vh;
            background: 
                radial-gradient(120% 60% at 50% 0%, rgba(59, 130, 246, 0.18) 0%, transparent 60%),
                radial-gradient(80% 50% at 50% 100%, rgba(6, 182, 212, 0.1) 0%, transparent 60%),
                var(--app-bg);
            background-attachment: fixed;
            color: var(--app-text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            padding-top: calc(18px + env(safe-area-inset-top, 0px));
            padding-bottom: calc(18px + env(safe-area-inset-bottom, 0px));
        }

        .m-login-wrapper {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Top Brand Card */
        .m-brand-card {
            background: linear-gradient(145deg, rgba(17, 24, 39, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid var(--app-border);
            border-radius: 22px;
            padding: 22px 20px 18px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(16px);
        }

        .m-brand-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #06b6d4, #10b981);
        }

        .m-brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .m-brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .m-logo-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(96, 165, 250, 0.4);
            display: grid;
            place-items: center;
            padding: 3px;
            box-shadow: 0 8px 16px -6px rgba(59, 130, 246, 0.5);
            overflow: hidden;
        }

        .m-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 11px;
        }

        .m-brand-info h1 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #ffffff;
            line-height: 1.2;
        }

        .m-brand-info p {
            font-size: 11px;
            font-weight: 600;
            color: var(--app-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }

        .m-shift-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.12);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.28);
            white-space: nowrap;
        }

        .m-shift-pill .m-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 8px #34d399;
        }

        /* Form Inputs & Fields */
        .m-input-group {
            margin-bottom: 14px;
        }

        .m-input-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 6px;
            letter-spacing: 0.2px;
        }

        .m-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .m-input-icon {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: #64748b;
            pointer-events: none;
            display: grid;
            place-items: center;
            transition: color 0.2s ease;
        }

        .m-input-field {
            width: 100%;
            height: 50px;
            background: var(--app-input);
            border: 1px solid var(--app-border);
            border-radius: 14px;
            padding: 0 44px 0 44px;
            font-size: 15px;
            color: #ffffff;
            font-weight: 500;
            outline: none;
            transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .m-input-field:focus {
            border-color: #3b82f6;
            background: #1e293b;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .m-input-field:focus + .m-input-icon,
        .m-input-box:focus-within .m-input-icon {
            color: #60a5fa;
        }

        .m-input-toggle-pwd {
            position: absolute;
            right: 12px;
            width: 32px;
            height: 32px;
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            display: grid;
            place-items: center;
            border-radius: 8px;
        }

        .m-input-toggle-pwd:hover,
        .m-input-toggle-pwd:active {
            color: #94a3b8;
        }

        /* Tombol Masuk Utama */
        .m-btn-login {
            width: 100%;
            height: 50px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #0284c7 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 10px 24px -6px rgba(37, 99, 235, 0.65);
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            margin-top: 18px;
        }

        .m-btn-login:active {
            transform: scale(0.97);
            box-shadow: 0 6px 14px -4px rgba(37, 99, 235, 0.7);
        }

        /* Banner Error */
        .m-alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        /* ==================== QUICK LOGIN ACCORDION / TABS ==================== */
        .m-quick-card {
            background: rgba(17, 24, 39, 0.9);
            border: 1px solid var(--app-border);
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .m-quick-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .m-quick-head-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .m-quick-filter {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: none;
        }
        .m-quick-filter::-webkit-scrollbar { display: none; }

        .m-filter-btn {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid var(--app-border);
            color: #94a3b8;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .m-filter-btn.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #3b82f6;
        }

        .m-quick-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 240px;
            overflow-y: auto;
            padding-right: 4px;
            scrollbar-width: thin;
        }

        .m-account-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 14px;
            text-decoration: none;
            color: var(--app-text);
            transition: all 0.18s ease;
        }

        .m-account-item:active {
            transform: scale(0.98);
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(96, 165, 250, 0.4);
        }

        .m-acc-badge {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 800;
            color: #ffffff;
            flex-shrink: 0;
        }

        .m-acc-text {
            flex: 1;
            min-width: 0;
        }

        .m-acc-name {
            font-size: 13px;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .m-acc-nik {
            font-size: 10.5px;
            font-weight: 700;
            color: #94a3b8;
            background: rgba(15, 23, 42, 0.6);
            padding: 1px 6px;
            border-radius: 4px;
        }

        .m-acc-desc {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .m-acc-arrow {
            color: #64748b;
            font-size: 12px;
        }

        .m-footer-note {
            text-align: center;
            font-size: 11px;
            color: #64748b;
            padding-top: 4px;
        }
    </style>
</head>
<body>

<div class="m-login-wrapper">

    <!-- Kartu Brand & Formulir Login -->
    <div class="m-brand-card">
        <div class="m-brand-header">
            <div class="m-brand-logo-wrap">
                <div class="m-logo-box">
                    <img src="assets/img/Logo_Aplikasi.png" alt="GT Logo">
                </div>
                <div class="m-brand-info">
                    <h1>GT BARCODE</h1>
                    <p>Plant ERP & SCADA Mobile</p>
                </div>
            </div>
            <div class="m-shift-pill" title="Shift Berjalan: <?= htmlspecialchars($shiftInfo['label']) ?>">
                <span class="m-dot"></span>
                <span>Shift <?= (int)$shiftInfo['shift'] ?></span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="m-alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <!-- Input NIK / Username -->
            <div class="m-input-group">
                <label class="m-input-label" for="usernameInput">NIK / Username Karyawan</label>
                <div class="m-input-box">
                    <div class="m-input-icon">
                        <?= gtbs_m_icon('user', 18) ?>
                    </div>
                    <input type="text" id="usernameInput" name="username" class="m-input-field" placeholder="Masukkan NIK atau username..." required autofocus autocomplete="username">
                </div>
            </div>

            <!-- Input Password -->
            <div class="m-input-group">
                <label class="m-input-label" for="passwordInput">Password Terminal</label>
                <div class="m-input-box">
                    <div class="m-input-icon">
                        <?= gtbs_m_icon('lock', 18) ?>
                    </div>
                    <input type="password" id="passwordInput" name="password" class="m-input-field" placeholder="Masukkan password..." required autocomplete="current-password">
                    <button type="button" class="m-input-toggle-pwd" id="togglePasswordBtn" aria-label="Lihat Password">
                        <?= gtbs_m_icon('eye', 17) ?>
                    </button>
                </div>
            </div>

            <!-- Tombol Masuk Utama -->
            <button type="submit" class="m-btn-login" id="submitLoginBtn">
                <span>Masuk ke Sistem</span>
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </button>
        </form>
    </div>

    <!-- Pilihan Akses Cepat (1-Klik) dengan Kategori -->
    <div class="m-quick-card">
        <div class="m-quick-head">
            <div class="m-quick-head-title">
                <?= gtbs_m_icon('bolt', 16) ?>
                <span>Akses Cepat Uji Jabatan</span>
            </div>
            <span style="font-size: 10.5px; color: #60a5fa; font-weight: 700;">1-Klik Masuk</span>
        </div>

        <!-- Filter Chips Kategori -->
        <div class="m-quick-filter">
            <button type="button" class="m-filter-btn active" data-filter="all">Semua (11)</button>
            <button type="button" class="m-filter-btn" data-filter="operator">Operator (4)</button>
            <button type="button" class="m-filter-btn" data-filter="dept">Dept (4)</button>
            <button type="button" class="m-filter-btn" data-filter="mgmt">Manajemen (2)</button>
            <button type="button" class="m-filter-btn" data-filter="admin">Admin (1)</button>
        </div>

        <!-- Daftar Akun Demo -->
        <div class="m-quick-list" id="quickAccountList">
            <?php foreach ($quickAccounts as $acc): ?>
                <a href="login.php?quick_nik=<?= urlencode($acc['nik']) ?>" class="m-account-item" data-category="<?= htmlspecialchars($acc['category']) ?>">
                    <div class="m-acc-badge" style="background: <?= htmlspecialchars($acc['color']) ?>;">
                        <?= htmlspecialchars($acc['badge']) ?>
                    </div>
                    <div class="m-acc-text">
                        <div class="m-acc-name">
                            <span><?= htmlspecialchars($acc['role']) ?></span>
                            <span class="m-acc-nik"><?= htmlspecialchars($acc['nik']) ?></span>
                        </div>
                        <div class="m-acc-desc"><?= htmlspecialchars($acc['name']) ?> &bull; <?= htmlspecialchars($acc['desc']) ?></div>
                    </div>
                    <div class="m-acc-arrow">&rarr;</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="m-footer-note">
        PT Gajah Tunggal Tbk &bull; Secure Industrial Terminal &bull; v2.0
    </div>

</div>

<script>
    // 1. Password Visibility Toggle
    const pwdInput = document.getElementById('passwordInput');
    const toggleBtn = document.getElementById('togglePasswordBtn');

    if (toggleBtn && pwdInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = pwdInput.type === 'password';
            pwdInput.type = isPassword ? 'text' : 'password';
            toggleBtn.style.color = isPassword ? '#60a5fa' : '#64748b';
        });
    }

    // 2. Kategori Filter untuk Akun Cepat
    const filterBtns = document.querySelectorAll('.m-filter-btn');
    const accItems = document.querySelectorAll('.m-account-item');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.getAttribute('data-filter');
            accItems.forEach(item => {
                if (filter === 'all' || item.getAttribute('data-category') === filter) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // 3. Haptic feedback saat menekan tombol masuk (jika di Android PDT)
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            if (window.AndroidPDT && typeof window.AndroidPDT.triggerVibration === 'function') {
                window.AndroidPDT.triggerVibration(100);
            }
        });
    }
</script>

</body>
</html>
