<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Navigasi Bilah Samping (Sidebar Navigation Layout)
 * File : includes/sidebar.php
 * ==============================================================================================
 *
 * [FUNGSI & KEGUNAAN]:
 * Menampilkan logo perusahaan, daftar menu sistem yang disesuaikan secara dinamis
 * berdasarkan Hierarki Jabatan dan Hak Akses Pengguna (Role-Based Access Control):
 * - Super Admin: Akses penuh (Menu Utama, Semua Dept, Seluruh Operasional, Kelola Karyawan).
 * - Plant Head & Asst Plant Head: Akses luas (Menu Utama, Operasional global).
 * - Dept Head & Asst Dept Head: Akses tingkat departemen spesifik.
 * - Admin Dept: Akses departemennya sendiri, Generate Barcode khusus departemennya, Operasional.
 * - Section Head & Leader: Akses tingkat bagian kerja.
 * - Operator: Akses tingkat personal / tugas barcode di lapangannya.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$user = $currentUser ?? Auth::user();
$allowedDepts = Auth::getAllowedDeptPages();
$roleLabel = Auth::getRoleLabel($user['role'] ?? '');

// Mapping seksi kerja untuk menampilkan sub-menu penginputan langsung bagi operator
$sectionSubmenuMap = [
    'WH' => [
        'WH-RCV' => ['name' => 'Input Penerima Bahan', 'tab' => 'penerima', 'url' => 'dept_warehouse.php?tab=penerima'],
        'WH-SND' => ['name' => 'Input Pengirim Bahan', 'tab' => 'pengirim', 'url' => 'dept_warehouse.php?tab=pengirim'],
    ],
    'MAT' => [
        'MAT-MIX' => ['name' => 'Input Mixing Ban', 'tab' => 'mixing', 'url' => 'dept_material.php?tab=mixing'],
        'MAT-CAL' => ['name' => 'Input Calendering Ban', 'tab' => 'calendery', 'url' => 'dept_material.php?tab=calendery'],
        'MAT-DEL' => ['name' => 'Input Delivery Material', 'tab' => 'delivery', 'url' => 'dept_material.php?tab=delivery'],
    ],
    'BLD' => [
        'BLD-PCR' => ['name' => 'Input Building PCR', 'tab' => 'pcr', 'url' => 'dept_building.php?size=pcr'],
        'BLD-SUV' => ['name' => 'Input Building SUV', 'tab' => 'suv', 'url' => 'dept_building.php?size=suv'],
        'BLD-LTR' => ['name' => 'Input Building LTR', 'tab' => 'ltr', 'url' => 'dept_building.php?size=ltr'],
        'BLD-TBR' => ['name' => 'Input Building TBR', 'tab' => 'tbr', 'url' => 'dept_building.php?size=tbr'],
    ],
    'CUR' => [
        'CUR-PRE'     => ['name' => 'Input Curing Press', 'tab' => 'curing_tire', 'url' => 'dept_curing.php?tab=curing_tire'],
        'CUR-SUP-LGT' => ['name' => 'Input Supply Lory', 'tab' => 'supply_lory', 'url' => 'dept_curing.php?tab=supply_lory'],
        'CUR-SUP-DEL' => ['name' => 'Input Supply Delivery', 'tab' => 'supply_delivery', 'url' => 'dept_curing.php?tab=supply_delivery'],
        'CUR-BSU'     => ['name' => 'Input Bladder Setup', 'tab' => 'bladder_installer', 'url' => 'dept_curing.php?tab=bladder_installer'],
        'CUR-BLD'     => ['name' => 'Input Bladder Injection', 'tab' => 'bladder_injection', 'url' => 'dept_curing.php?tab=bladder_injection'],
        'CUR-REP'     => ['name' => 'Input Repair Tire', 'tab' => 'repair_tire', 'url' => 'dept_curing.php?tab=repair_tire'],
    ],
    'FI' => [
        'FI-INS' => ['name' => 'Input Visual Inspection', 'tab' => 'inspection', 'url' => 'dept_final_inspection.php?tab=inspection'],
        'FI-CUT' => ['name' => 'Input Cutting Hair', 'tab' => 'cutting_hair', 'url' => 'dept_final_inspection.php?tab=cutting_hair'],
        'FI-DEL' => ['name' => 'Input Finish Delivery', 'tab' => 'delivery', 'url' => 'dept_final_inspection.php?tab=delivery'],
    ]
];
$userSecCode = $user['section_code'] ?? '';
$isOperatorUser = in_array($user['role'] ?? '', ['operator', 'leader', 'section_head']);
?>
<aside class="sidebar">
    <!-- Header Sidebar: Logo PT Gajah Tunggal Tbk & Nama Sistem -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="assets/img/Gajah_Tunggal.png" width="40px" height="40px" alt="GT Logo">
        </div>
        <div>
            <div class="sidebar-title">GT BARCODE</div>
            <div class="sidebar-subtitle">ERP & SCADA SYSTEM</div>
        </div>
    </div>

    <!-- Daftar Navigasi Menu Sidebar -->
    <nav class="sidebar-nav">
        <!-- SEKSI 1: MENU UTAMA -->
        <!-- PILIHAN MODE TAMPILAN: LITE (LAPANGAN) / PRO (ERP LENGKAP) -->
        <div class="nav-section-title" style="display: flex; align-items: center; justify-content: space-between;">
            <span>Mode Input</span>
            <span style="font-size: 10px; background: rgba(16, 185, 129, 0.2); padding: 2px 6px; border-radius: 4px; color: #34d399; font-weight: 800;">FAST</span>
        </div>
        <a href="operator_lite.php" class="nav-item <?= $currentPage === 'operator_lite.php' ? 'active' : '' ?>" style="background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.35); border-radius: 8px; margin-bottom: 12px; color: #34d399;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #34d399;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <div style="display: flex; flex-direction: column;">
                <span style="font-weight: 800; color: #34d399;">⚡ Mode Lite (Operator)</span>
                <span style="font-size: 10px; opacity: 0.8; color: #9ca3af;">Khusus Penginputan Lapangan</span>
            </div>
        </a>

        <!-- SEKSI 1: MENU UTAMA -->
        <div class="nav-section-title">Menu Utama</div>
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Dashboard
        </a>
        <a href="scada.php" class="nav-item <?= $currentPage === 'scada.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            SCADA Monitoring
        </a>
        <a href="scan_terminal.php" class="nav-item <?= $currentPage === 'scan_terminal.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
            </svg>
            Scan Terminal
        </a>

        <?php if (Auth::canGenerateBarcode()): ?>
        <!-- Menu Generate Barcode HANYA untuk Super Admin & Admin Dept -->
        <a href="barcode_management.php" class="nav-item <?= $currentPage === 'barcode_management.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            </svg>
            Generate Barcode
        </a>
        <?php endif; ?>

        <?php if (!empty($allowedDepts)): ?>
        <!-- SEKSI 2: LIMA DEPARTEMEN PABRIK BAN -->
        <!-- Sesuai Diagram: HANYA Super Admin (Semua) dan Admin Dept (Khusus Departemennya) -->
        <div class="nav-section-title">Departemen Pabrik</div>
        
        <?php if (in_array('WH', $allowedDepts)): ?>
        <a href="dept_warehouse.php" class="nav-item <?= $currentPage === 'dept_warehouse.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            1. Gudang Bahan
        </a>
        <?php if ($isOperatorUser && isset($sectionSubmenuMap['WH'][$userSecCode])): ?>
            <?php $secWh = $sectionSubmenuMap['WH'][$userSecCode]; ?>
            <a href="<?= $secWh['url'] ?>" class="nav-sub-item <?= ($currentPage === 'dept_warehouse.php' && ($_GET['tab'] ?? '') === $secWh['tab']) ? 'active' : '' ?>" style="margin-left: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #60a5fa; border-left: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.08); border-radius: 0 6px 6px 0; display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;">↳</span>
                <span><?= htmlspecialchars($secWh['name']) ?></span>
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('MAT', $allowedDepts)): ?>
        <a href="dept_material.php" class="nav-item <?= $currentPage === 'dept_material.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
            </svg>
            2. Dept Material
        </a>
        <?php if ($isOperatorUser && isset($sectionSubmenuMap['MAT'][$userSecCode])): ?>
            <?php $secMat = $sectionSubmenuMap['MAT'][$userSecCode]; ?>
            <a href="<?= $secMat['url'] ?>" class="nav-sub-item <?= ($currentPage === 'dept_material.php' && ($_GET['tab'] ?? '') === $secMat['tab']) ? 'active' : '' ?>" style="margin-left: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #60a5fa; border-left: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.08); border-radius: 0 6px 6px 0; display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;">↳</span>
                <span><?= htmlspecialchars($secMat['name']) ?></span>
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('BLD', $allowedDepts)): ?>
        <a href="dept_building.php" class="nav-item <?= $currentPage === 'dept_building.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            3. Dept Building
        </a>
        <?php if ($isOperatorUser && isset($sectionSubmenuMap['BLD'][$userSecCode])): ?>
            <?php $secBld = $sectionSubmenuMap['BLD'][$userSecCode]; ?>
            <a href="<?= $secBld['url'] ?>" class="nav-sub-item <?= ($currentPage === 'dept_building.php' && ($_GET['size'] ?? '') === $secBld['tab']) ? 'active' : '' ?>" style="margin-left: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #60a5fa; border-left: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.08); border-radius: 0 6px 6px 0; display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;">↳</span>
                <span><?= htmlspecialchars($secBld['name']) ?></span>
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('CUR', $allowedDepts)): ?>
        <a href="dept_curing.php" class="nav-item <?= $currentPage === 'dept_curing.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
            </svg>
            4. Dept Curing
        </a>
        <?php if ($isOperatorUser && isset($sectionSubmenuMap['CUR'][$userSecCode])): ?>
            <?php $secCur = $sectionSubmenuMap['CUR'][$userSecCode]; ?>
            <a href="<?= $secCur['url'] ?>" class="nav-sub-item <?= ($currentPage === 'dept_curing.php' && ($_GET['tab'] ?? '') === $secCur['tab']) ? 'active' : '' ?>" style="margin-left: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #60a5fa; border-left: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.08); border-radius: 0 6px 6px 0; display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;">↳</span>
                <span><?= htmlspecialchars($secCur['name']) ?></span>
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('FI', $allowedDepts)): ?>
        <a href="dept_final_inspection.php" class="nav-item <?= $currentPage === 'dept_final_inspection.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            5. Final Inspection
        </a>
        <?php if ($isOperatorUser && isset($sectionSubmenuMap['FI'][$userSecCode])): ?>
            <?php $secFi = $sectionSubmenuMap['FI'][$userSecCode]; ?>
            <a href="<?= $secFi['url'] ?>" class="nav-sub-item <?= ($currentPage === 'dept_final_inspection.php' && ($_GET['tab'] ?? '') === $secFi['tab']) ? 'active' : '' ?>" style="margin-left: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #60a5fa; border-left: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.08); border-radius: 0 6px 6px 0; display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;">↳</span>
                <span><?= htmlspecialchars($secFi['name']) ?></span>
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php endif; ?>

        <!-- SEKSI 3: OPERASIONAL & KENDALI -->
        <div class="nav-section-title">Operasional & Kendali</div>
        <a href="downtime_monitor.php" class="nav-item <?= $currentPage === 'downtime_monitor.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Hambatan Mesin (4M)
        </a>
        <a href="communication.php" class="nav-item <?= $currentPage === 'communication.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
            </svg>
            Pesan & Broadcast
        </a>

        <?php if (Auth::canManageUsers()): ?>
        <!-- Menu Kelola Karyawan HANYA tampil untuk Super Admin sesuai Diagram -->
        <a href="user_management.php" class="nav-item <?= $currentPage === 'user_management.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            Kelola Karyawan
        </a>
        <?php endif; ?>

        <a href="k3_emergency.php" class="nav-item <?= $currentPage === 'k3_emergency.php' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            K3 & Darurat
        </a>
    </nav>

    <!-- FOOTER SIDEBAR: PROFIL & FOTO KARYAWAN AKTIF -->
    <div class="sidebar-footer">
        <div class="user-badge">
            <?php
            // Ambil foto karyawan dari sesi, jika belum ada ambil dari database
            $userPhoto = $user['photo'] ?? '';
            if (empty($userPhoto) && !empty($user['id'])) {
                try {
                    $pdoSidebar = Database::getConnection();
                    $stmtPhoto = $pdoSidebar->prepare("SELECT photo FROM users WHERE id = ?");
                    $stmtPhoto->execute([$user['id']]);
                    $userPhoto = $stmtPhoto->fetchColumn();
                    if ($userPhoto) {
                        $_SESSION['user_data']['photo'] = $userPhoto;
                    }
                } catch (Exception $e) {}
            }
            ?>
            <!-- Tampilkan foto profil avatar bulat jika file foto tersedia -->
            <?php if (!empty($userPhoto) && file_exists(__DIR__ . '/../' . $userPhoto)): ?>
                <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Avatar" class="user-avatar" style="object-fit: cover; border-radius: 50%; padding: 0; border: 2px solid rgba(255,255,255,0.2);">
            <?php else: ?>
                <!-- Fallback: Tampilkan inisial huruf nama jika belum ada foto -->
                <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
            <?php endif; ?>

            <!-- Informasi Nama, Role Resmi dan NIK Karyawan yang sedang login -->
            <div class="user-meta">
                <div class="user-name" title="<?= htmlspecialchars($user['full_name'] ?? '') ?>"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></div>
                <div class="user-role" title="Jabatan: <?= htmlspecialchars($roleLabel) ?>">
                    <?= htmlspecialchars($roleLabel) ?>
                    <?php if (!empty($user['dept_code'])): ?>
                        <span style="opacity: 0.75;">(<?= htmlspecialchars($user['dept_code']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</aside>