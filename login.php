<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Formulir Masuk Pengguna (Login Page)
 * File : login.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Halaman otentikasi login karyawan pabrik ban. Mendukung penginputan NIK / Username dan Password,
 * serta dilengkapi tombol "Quick Login" akun percontohan (Super Admin, Head, Leader, Operator) 
 * untuk mempermudah demonstrasi dan pengujian sistem.
 */

require_once __DIR__ . '/helpers/auth.php';

$error = '';

// 1. Penanganan Aksi Quick Login (Masuk Cepat Satu Klik)
if (isset($_GET['quick_nik'])) {
    $nik = trim($_GET['quick_nik']);
    if (Auth::quickLoginByNik($nik)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Akun demo (' . htmlspecialchars($nik) . ') tidak ditemukan atau tidak aktif.';
    }
}

// 2. Penanganan Submit Form Login Standar
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Harap isi username/NIK dan password.';
    } else {
        // Validasi kredensial melalui helper Auth::login
        if (Auth::login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Kredensial tidak valid. Silakan periksa kembali NIK/username dan password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - GT Barcode System (ERP & SCADA)</title>
    <!-- Favicon / Tab Logo -->
    <link rel="icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="apple-touch-icon" href="assets/img/Logo_Aplikasi.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0b0f19 80%);
            padding: 20px;
        }
        .login-box {
            width: 100%;
            max-width: 460px;
            background: rgba(17, 24, 39, 0.95);
            border: 1px solid #374151;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
        .login-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .login-title {
            font-size: 20px;
            font-weight: 800;
            color: #f3f4f6;
            letter-spacing: -0.5px;
        }
        .login-subtitle {
            font-size: 12px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .quick-accounts {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #374151;
        }
        .quick-accounts-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .quick-btn-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .quick-btn {
            background: rgba(31, 41, 55, 0.8);
            border: 1px solid #4b5563;
            color: #e5e7eb;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            cursor: pointer;
            text-align: left;
            text-decoration: none;
            display: block;
            transition: all 0.2s;
        }
        .quick-btn:hover {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }
        .quick-btn-role {
            font-size: 10px;
            color: #9ca3af;
            display: block;
        }
    </style>
</head>
<body>

<div class="login-box">
    <!-- Identitas Perusahaan -->
    <div class="login-logo">
        <img src="assets/img/Gajah_Tunggal.png" width="48" height="48" alt="Logo GT">
        <div>
            <div class="login-title">GT BARCODE SYSTEM</div>
            <div class="login-subtitle">PT Gajah Tunggal Tbk &bull; Plant ERP & SCADA</div>
        </div>
    </div>

    <!-- Tampilan Pesan Kesalahan jika login gagal -->
    <?php if (!empty($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Formulir Input Username / NIK dan Password -->
    <form method="POST" action="login.php">
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label required">NIK atau Username Karyawan</label>
            <input type="text" name="username" class="form-input" placeholder="Masukkan NIK atau username" required autofocus>
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label required">Password</label>
            <input type="password" name="password" class="form-input" placeholder="Masukkan password akun" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 700;">
            Masuk ke Sistem
        </button>
    </form>

    <!-- Pilihan Akun Masuk Cepat untuk Pengujian / Demo -->
    <div class="quick-accounts">
        <div class="quick-accounts-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Akses Cepat Uji Hierarki Jabatan (1-Klik):</span>
            <span style="color: #60a5fa; font-size: 10px;">9 Tingkat Jabatan</span>
        </div>
        <div class="quick-btn-grid" style="grid-template-columns: repeat(2, 1fr); max-height: 230px; overflow-y: auto; padding-right: 4px;">
            <a href="login.php?quick_nik=SA-001" class="quick-btn">
                <strong style="color: #60a5fa;">1. Super Admin</strong>
                <span class="quick-btn-role">NIK: SA-001 &bull; Akses Penuh</span>
            </a>
            <a href="login.php?quick_nik=PH-001" class="quick-btn">
                <strong style="color: #34d399;">2. Plant Head</strong>
                <span class="quick-btn-role">NIK: PH-001 &bull; Akses Luas</span>
            </a>
            <a href="login.php?quick_nik=APH-001" class="quick-btn">
                <strong style="color: #a78bfa;">3. Asst Plant Head</strong>
                <span class="quick-btn-role">NIK: APH-001 &bull; Membantu PH</span>
            </a>
            <a href="login.php?quick_nik=HEAD-CUR-01" class="quick-btn">
                <strong style="color: #fbbf24;">4. Dept Head Curing</strong>
                <span class="quick-btn-role">NIK: HEAD-CUR-01 &bull; Dept Curing</span>
            </a>
            <a href="login.php?quick_nik=AHEAD-CUR-01" class="quick-btn">
                <strong style="color: #f59e0b;">5. Asst Dept Head</strong>
                <span class="quick-btn-role">NIK: AHEAD-CUR-01 &bull; Curing</span>
            </a>
            <a href="login.php?quick_nik=ADM-CUR-01" class="quick-btn">
                <strong style="color: #38bdf8;">6. Admin Dept Curing</strong>
                <span class="quick-btn-role">NIK: ADM-CUR-01 &bull; Generate Barcode</span>
            </a>
            <a href="login.php?quick_nik=ADM-WH-01" class="quick-btn">
                <strong style="color: #38bdf8;">6b. Admin Dept Gudang</strong>
                <span class="quick-btn-role">NIK: ADM-WH-01 &bull; Gudang Bahan</span>
            </a>
            <a href="login.php?quick_nik=SH-CUR-01" class="quick-btn">
                <strong style="color: #f97316;">7. Section Head Curing</strong>
                <span class="quick-btn-role">NIK: SH-CUR-01 &bull; Seksi Curing</span>
            </a>
            <a href="login.php?quick_nik=LDR-CUR-01" class="quick-btn">
                <strong style="color: #ec4899;">8. Leader Curing</strong>
                <span class="quick-btn-role">NIK: LDR-CUR-01 &bull; Leader Regu</span>
            </a>
            <a href="login.php?quick_nik=OP-CUR-01" class="quick-btn">
                <strong style="color: #94a3b8;">9. Operator Curing</strong>
                <span class="quick-btn-role">NIK: OP-CUR-01 &bull; Operator Press</span>
            </a>
            <a href="login.php?quick_nik=OP-CUR-04" class="quick-btn">
                <strong style="color: #94a3b8;">9b. Operator Repair</strong>
                <span class="quick-btn-role">NIK: OP-CUR-04 &bull; Operator Repair</span>
            </a>
        </div>
        <div style="font-size: 11px; color: #6b7280; margin-top: 12px; text-align: center;">
            Password default untuk semua akun: <code>password123</code>
        </div>
    </div>
</div>

</body>
</html>