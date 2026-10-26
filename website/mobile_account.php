<?php
/**
 * ==============================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Halaman Akun & Profil Pengguna (Mobile App)
 * File : mobile_account.php
 * ==============================================================================
 */

$pageTitle = 'Akun Pengguna';
require_once __DIR__ . '/includes/header.php';

$user = $currentUser ?? Auth::user();
$role = $user['role'] ?? '';
$roleLabel = Auth::getRoleLabel($role);
$userPhoto = $user['photo'] ?? '';
?>

<!-- ==================== HEADER KARTU PROFIL ==================== -->
<div class="m-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);">
    <div class="m-profile">
        <div class="m-avatar-xl">
            <?php if (!empty($userPhoto) && file_exists(__DIR__ . '/' . $userPhoto)): ?>
                <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Avatar">
            <?php else: ?>
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <h2><?= htmlspecialchars($user['full_name'] ?? 'Karyawan GT') ?></h2>
        <div style="font-size: 13px; opacity: 0.85;">NIK: <strong><?= htmlspecialchars($user['nik'] ?? '-') ?></strong> &bull; @<?= htmlspecialchars($user['username'] ?? '') ?></div>
        
        <div class="m-role-pill">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #34d399;"></span>
            <span><?= htmlspecialchars($roleLabel) ?></span>
        </div>
    </div>
</div>

<!-- ==================== KARTU IDENTITAS RESMI ==================== -->
<div class="m-section-title">
    <span>Identitas Karyawan</span>
    <span style="font-size: 11px; color: var(--m-text-2);">Data Terdaftar</span>
</div>

<div class="m-kv" style="margin-bottom: 14px;">
    <div>
        <small>Departemen</small>
        <b><?= htmlspecialchars($user['dept_name'] ?? 'Pusat Pabrik') ?> (<?= htmlspecialchars($user['dept_code'] ?? 'ALL') ?>)</b>
    </div>
    <div>
        <small>Bagian / Seksi</small>
        <b><?= htmlspecialchars($user['section_name'] ?? 'Semua Seksi') ?></b>
    </div>
    <div>
        <small>Shift Kerja Aktif</small>
        <b style="color: #60a5fa;">Shift <?= (int)($shiftInfo['shift'] ?? 1) ?> (<?= htmlspecialchars(substr($shiftInfo['label'], strpos($shiftInfo['label'], '(') + 1, -1)) ?>)</b>
    </div>
    <div>
        <small>Status Akun</small>
        <b style="color: #34d399;">Aktif (Terverifikasi)</b>
    </div>
</div>

<!-- ==================== PENGATURAN & AKSES ==================== -->
<div class="m-section-title">
    <span>Pengaturan & Alat</span>
</div>

<div class="m-list" style="margin-bottom: 18px;">
    <?php if (Auth::canManageUsers()): ?>
    <a href="user_management.php" class="m-list-item">
        <div class="m-li-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
            <?= gtbs_m_icon('users', 18) ?>
        </div>
        <div class="m-li-text">
            <b>Kelola Karyawan (Admin)</b>
            <small>Atur data akun, role, departemen & foto profil</small>
        </div>
        <span class="m-li-chev"><?= gtbs_m_icon('chevron', 16) ?></span>
    </a>
    <?php endif; ?>

    <button type="button" class="m-list-item" onclick="document.getElementById('themeToggleBtn')?.click()">
        <div class="m-li-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
            <?= gtbs_m_icon('moon', 18) ?>
        </div>
        <div class="m-li-text">
            <b>Ganti Tema Tampilan</b>
            <small>Beralih antara Mode Gelap (Dark) dan Terang (Light)</small>
        </div>
        <span class="m-li-chev"><?= gtbs_m_icon('chevron', 16) ?></span>
    </button>

    <a href="k3_emergency.php" class="m-list-item">
        <div class="m-li-icon" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
            <?= gtbs_m_icon('shield', 18) ?>
        </div>
        <div class="m-li-text">
            <b>Pusat Tanggap K3 & Darurat</b>
            <small>Prosedur keselamatan, ambulans & kontak evakuasi</small>
        </div>
        <span class="m-li-chev"><?= gtbs_m_icon('chevron', 16) ?></span>
    </a>

    <a href="logout.php" class="m-list-item danger">
        <div class="m-li-icon">
            <?= gtbs_m_icon('logout', 18) ?>
        </div>
        <div class="m-li-text">
            <b>Keluar Akun (Logout)</b>
            <small>Akhiri sesi kerja dan amankan terminal PDT</small>
        </div>
        <span class="m-li-chev"><?= gtbs_m_icon('chevron', 16) ?></span>
    </a>
</div>

<div style="text-align: center; font-size: 11.5px; color: var(--m-muted); padding-bottom: 12px;">
    GT Barcode Mobile Edition &bull; PT Gajah Tunggal Tbk &bull; v2.0
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
