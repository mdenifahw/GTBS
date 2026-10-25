<?php
/**
 * ==============================================================================
 * DASHBOARD UTAMA - PT GAJAH TUNGGAL TBK BARCODE & PRODUCTION TRACKING SYSTEM
 * ==============================================================================
 * Fungsi File:
 * - Menampilkan pusat ringkasan operasional pabrik ban secara real-time.
 * - Memantau 4 KPI utama: Total Produksi Hari Ini, Mesin Downtime Aktif, 
 *   Kapasitas Rak Gudang (Warehouse Rack Occupancy), dan Panggilan Darurat K3 (SOS).
 * - Memberikan rincian kemajuan per departemen (Material, Building, Curing, Finishing, Warehouse).
 * - Menyediakan filter interaktif bersarang (Cascading: Dept -> Section) untuk melihat riwayat aktivitas.
 * - Menampilkan log transaksi barcode terbaru dan peringatan downtime aktif.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Interval Auto-Refresh:
 *    Cari fungsi JavaScript `setInterval` di bagian bawah file (default: 30000ms / 30 detik).
 * 2. Menambah Kartu KPI Baru:
 *    Tambahkan query SQL penghitung di blok "BAGIAN 2: HITUNG KPI UTAMA", lalu duplikasi
 *    elemen div dengan class `card kpi-card` pada bagian template HTML.
 * 3. Menyesuaikan Target Produksi:
 *    Ubah nilai persentase target di bagian perulangan departemen (`dept_summary`).
 * ==============================================================================
 */
$pageTitle = 'Dashboard Utama';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// ==============================================================================
// BAGIAN 1: PENGATURAN TINGKAT KEWENANGAN PANTAUAN (ROLE-BASED SCOPE)
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userNik  = $currentUser['nik'] ?? '';
$userId   = $currentUser['id'] ?? 0;
$userDept = $currentUser['dept_code'] ?? '';
$userSec  = $currentUser['section_code'] ?? '';

// Definisi tingkat kewenangan pantauan:
// 1. Pantauan Global (Akses Penuh): Super Admin, Plant Head, Asst Plant Head
$isGlobalMonitor = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head']);

// 2. Pantauan Tingkat Departemen: Dept Head, Asst Dept Head, Admin Dept
$isDeptMonitor = in_array($userRole, ['dept_head', 'asst_dept_head', 'admin_dept']);

// 3. Pantauan Tingkat Bagian: Section Head, Leader
$isSectionMonitor = in_array($userRole, ['section_head', 'leader']);

// 4. Operator: Khusus menampilkan data personal karyawan itu sendiri
$isOperatorOnly = ($userRole === 'operator');

// Parameter Filter Dept & Section
if ($isGlobalMonitor) {
    $filterDept = $_GET['filter_dept'] ?? '';
    $filterSection = $_GET['filter_section'] ?? '';
} elseif ($isDeptMonitor) {
    $filterDept = $userDept;
    $filterSection = $_GET['filter_section'] ?? '';
} else {
    $filterDept = $userDept;
    $filterSection = $userSec;
}

// Ambil data departments & sections
$departments = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll();
$sections = $pdo->query("SELECT id, dept_id, code, name FROM sections ORDER BY dept_id, id")->fetchAll();

$deptIdToCode = [];
foreach ($departments as $d) {
    $deptIdToCode[$d['id']] = $d['code'];
}

$deptUrlMap = [
    'WH'  => 'dept_warehouse.php',
    'MAT' => 'dept_material.php',
    'BLD' => 'dept_building.php',
    'CUR' => 'dept_curing.php',
    'FI'  => 'dept_final_inspection.php'
];
$operatorDeptUrl = $deptUrlMap[$userDept] ?? 'dashboard.php';

// ==============================================================================
// BAGIAN 2: HITUNG KPI UTAMA OPERASIONAL SESUAI HAK AKSES PENGGUNA
// ==============================================================================
$shiftNum = $shiftInfo['shift'];
$today = $shiftInfo['date'];

if ($isOperatorOnly) {
    // KHUSUS OPERATOR: Hanya hitung output personal yang dikerjakan oleh operator ini hari ini
    $stmtMyScans = $pdo->prepare("
        SELECT COUNT(*) FROM production_activities 
        WHERE (operator_nik = ? OR operator_id = ?) 
          AND DATE(created_at) = CURRENT_DATE
    ");
    $stmtMyScans->execute([$userNik, $userId]);
    $totalScans = (int)$stmtMyScans->fetchColumn();
    $kpi1Title = "Total Output Saya Hari Ini";
    $kpi1Meta  = "Shift {$shiftNum} &bull; " . htmlspecialchars($currentUser['section_name'] ?? 'Bagian Lapangan');
} elseif ($isDeptMonitor) {
    // TINGKAT DEPT: Hitung output seluruh bagian di departemennya
    $stmtDeptScans = $pdo->prepare("
        SELECT COUNT(*) FROM production_activities pa
        JOIN departments d ON pa.dept_id = d.id
        WHERE d.code = ? AND DATE(pa.created_at) = CURRENT_DATE
    ");
    $stmtDeptScans->execute([$userDept]);
    $totalScans = (int)$stmtDeptScans->fetchColumn();
    $kpi1Title = "Total Output Dept {$userDept} Hari Ini";
    $kpi1Meta  = "Departemen " . htmlspecialchars($currentUser['dept_name'] ?? $userDept);
} else {
    // GLOBAL MONITOR: Super Admin, Plant Head, Asst Plant Head (Semua Dept)
    $totalScans = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT id FROM production_activities WHERE DATE(created_at) = CURRENT_DATE
            UNION ALL
            SELECT id FROM production_scada_logs WHERE log_date = CURRENT_DATE
        ) as t
    ")->fetchColumn();
    $kpi1Title = "Total Output Hari Ini";
    $kpi1Meta  = "Semua departemen manufaktur";
}

$activeDowntime = $pdo->query("SELECT COUNT(*) FROM downtime_events WHERE status = 'ACTIVE'")->fetchColumn();
$occupiedRacks  = $pdo->query("SELECT COUNT(*) FROM warehouse_racks WHERE is_occupied = TRUE")->fetchColumn();
$totalUsers      = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE")->fetchColumn();

// ==============================================================================
// BAGIAN 3: QUERY REKAMAN AKTIVITAS TERAKHIR (DISORTIR SESUAI HAK AKSES)
// ==============================================================================
$query = "
    SELECT 
        pa.id,
        pa.created_at,
        pa.shift,
        pa.operator_nik,
        u.full_name as operator_name,
        d.code as dept_code,
        d.name as dept_name,
        s.code as section_code,
        s.name as section_name,
        pa.action_type,
        pa.details
    FROM production_activities pa
    LEFT JOIN users u ON (pa.operator_nik = u.nik OR pa.operator_id = u.id)
    LEFT JOIN departments d ON pa.dept_id = d.id
    LEFT JOIN sections s ON pa.section_id = s.id
    WHERE 1=1
";
$params = [];

if ($isOperatorOnly) {
    // KHUSUS OPERATOR: HANYA menampilkan data karyawan itu sendiri!
    $query .= " AND (pa.operator_nik = ? OR pa.operator_id = ?)";
    $params[] = $userNik;
    $params[] = $userId;
} elseif ($isDeptMonitor) {
    // TINGKAT DEPARTEMEN: HANYA menampilkan data departemen terkait
    $query .= " AND d.code = ?";
    $params[] = $userDept;
    if (!empty($filterSection)) {
        $query .= " AND s.code = ?";
        $params[] = $filterSection;
    }
} elseif ($isSectionMonitor) {
    // TINGKAT SEKSI: HANYA menampilkan data seksi terkait
    if (!empty($userSec)) {
        $query .= " AND s.code = ?";
        $params[] = $userSec;
    } else {
        $query .= " AND d.code = ?";
        $params[] = $userDept;
    }
} else {
    // HAK AKSES PANTAUAN GLOBAL: Super Admin, Plant Head, Asst Plant Head
    if (!empty($filterDept)) {
        $query .= " AND d.code = ?";
        $params[] = $filterDept;
    }
    if (!empty($filterSection)) {
        $query .= " AND s.code = ?";
        $params[] = $filterSection;
    }
}

$query .= " ORDER BY pa.created_at DESC LIMIT 50";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();
?>

<!-- KPI Highlights (Disesuaikan dengan Hak Akses Pantauan Pengguna) -->
<div class="kpi-grid">
    <div class="kpi-card" <?= $isGlobalMonitor ? 'onclick="window.location.href=\'scada.php\'"' : ($isOperatorOnly ? 'onclick="window.location.href=\'operator_lite.php\'"' : '') ?>>
        <div class="kpi-title"><?= htmlspecialchars($kpi1Title) ?></div>
        <div class="kpi-value" style="color: var(--color-accent);"><?= number_format($totalScans) ?> <span style="font-size: 14px; font-weight: 500;">Item</span></div>
        <div class="kpi-meta"><?= $kpi1Meta ?></div>
    </div>

    <div class="kpi-card" onclick="window.location.href='downtime_monitor.php'">
        <div class="kpi-title">Hambatan Mesin Aktif (4M)</div>
        <div class="kpi-value" style="color: <?= $activeDowntime > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
            <?= $activeDowntime ?> <span style="font-size: 14px; font-weight: 500;">Insiden</span>
        </div>
        <div class="kpi-meta"><?= $activeDowntime > 0 ? 'Membutuhkan penanganan segera' : 'Kondisi operasional lancar' ?></div>
    </div>

    <?php if ($isOperatorOnly): ?>
        <div class="kpi-card" onclick="window.location.href='<?= $operatorDeptUrl ?>'">
            <div class="kpi-title">Bagian Kerja Aktif</div>
            <div class="kpi-value" style="color: var(--color-warning); font-size: 20px;"><?= htmlspecialchars($userSec ?: 'Operator') ?></div>
            <div class="kpi-meta"><?= htmlspecialchars($currentUser['section_name'] ?? $currentUser['dept_name']) ?></div>
        </div>
        <div class="kpi-card" onclick="window.location.href='operator_lite.php'">
            <div class="kpi-title">Status Shift Kerja</div>
            <div class="kpi-value" style="color: var(--color-success); font-size: 20px;">AKTIF (SHIFT <?= $shiftNum ?>)</div>
            <div class="kpi-meta"><?= htmlspecialchars($shiftInfo['label']) ?> &bull; Klik Buka Lite</div>
        </div>
    <?php else: ?>
        <?php $canWH = Auth::canAccessDeptPage('WH'); ?>
        <div class="kpi-card" <?= $canWH ? 'onclick="window.location.href=\'dept_warehouse.php\'"' : 'style="cursor: default;"' ?>>
            <div class="kpi-title">Kapasitas Rak Gudang Bahan</div>
            <div class="kpi-value" style="color: var(--color-warning);"><?= $occupiedRacks ?> / 300</div>
            <div class="kpi-meta"><?= round(($occupiedRacks / 300) * 100, 1) ?>% Terisi Pallet Bahan Baku</div>
        </div>
        <?php $canUsers = Auth::canManageUsers(); ?>
        <div class="kpi-card" <?= $canUsers ? 'onclick="window.location.href=\'user_management.php\'"' : 'style="cursor: default;"' ?>>
            <div class="kpi-title"><?= $isGlobalMonitor ? 'Total Personel Aktif' : 'Personel Dept ' . htmlspecialchars($userDept) ?></div>
            <div class="kpi-value" style="color: var(--color-info);"><?= $totalUsers ?> <span style="font-size: 14px; font-weight: 500;">Orang</span></div>
            <div class="kpi-meta"><?= $isGlobalMonitor ? '5 Departemen Pabrik' : 'Departemen Terkait' ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Aktivitas Terakhir di Shift Ini (Disortir Berdasarkan Hak Akses) -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php if ($isOperatorOnly): ?>
                    Aktivitas Terakhir Saya di Shift Ini
                <?php elseif ($isDeptMonitor): ?>
                    Aktivitas Terakhir Dept <?= htmlspecialchars($userDept) ?> (Shift Ini)
                <?php else: ?>
                    Aktivitas Terakhir di Shift Ini (Seluruh Pabrik)
                <?php endif; ?>
            </div>
            <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 4px;">
                <?php if ($isOperatorOnly): ?>
                    Menampilkan riwayat pelaporan transaksi personal yang Anda inputkan (NIK: <?= htmlspecialchars($userNik) ?>)
                <?php elseif ($isDeptMonitor): ?>
                    Pelaporan transaksi realtime khusus Departemen <?= htmlspecialchars($currentUser['dept_name'] ?? $userDept) ?>
                <?php else: ?>
                    Pelaporan realtime pantauan global seluruh Departemen dan Bagian kerja pabrik
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isOperatorOnly): ?>
            <!-- Controls Tampilan Khusus Operator: Info Stasiun & Tombol Cepat Mode Lite -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #93c5fd;">
                    Stasiun: <?= htmlspecialchars($currentUser['section_name'] ?? $userSec) ?> (<?= htmlspecialchars($userSec) ?>)
                </div>
                <a href="operator_lite.php" class="btn btn-primary btn-sm" style="background: linear-gradient(135deg, #10b981, #059669); border: none; font-size: 12px; font-weight: 700;">
                    ⚡ Mode Lite (Input Cepat)
                </a>
            </div>
        <?php elseif ($isDeptMonitor): ?>
            <!-- Filter Controls Khusus Tingkat Departemen -->
            <form method="GET" action="dashboard.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="filter_dept" value="<?= htmlspecialchars($userDept) ?>">
                <span style="font-size: 12px; font-weight: 700; color: #60a5fa; background: rgba(59,130,246,0.1); padding: 6px 12px; border-radius: 6px;">
                    Dept: <?= htmlspecialchars($userDept) ?>
                </span>
                <select name="filter_section" class="form-select" style="width: auto; min-width: 180px;">
                    <option value="">-- Semua Bagian (<?= htmlspecialchars($userDept) ?>) --</option>
                    <?php foreach ($sections as $s): ?>
                        <?php if (($deptIdToCode[$s['dept_id']] ?? '') !== $userDept) continue; ?>
                        <option value="<?= $s['code'] ?>" <?= $filterSection === $s['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= $s['code'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($filterSection)): ?>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <!-- Filter Controls Penuh Pantauan Global (Super Admin, Plant Head, Asst Plant Head) -->
            <form method="GET" action="dashboard.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <select name="filter_dept" id="deptSelect" class="form-select" style="width: auto; min-width: 170px;" onchange="updateSectionFilter()">
                    <option value="">-- Semua Departemen --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['code'] ?>" <?= $filterDept === $d['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_section" id="sectionSelect" class="form-select" style="width: auto; min-width: 180px;">
                    <option value="">-- Semua Bagian --</option>
                    <?php foreach ($sections as $s): ?>
                        <?php 
                        $secDeptCode = $deptIdToCode[$s['dept_id']] ?? ''; 
                        if (!empty($filterDept) && $secDeptCode !== $filterDept) continue;
                        ?>
                        <option value="<?= $s['code'] ?>" data-dept="<?= $secDeptCode ?>" <?= $filterSection === $s['code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= $s['code'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Terapkan Filter</button>
                <?php if (!empty($filterDept) || !empty($filterSection)): ?>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu & Shift</th>
                    <th>Departemen</th>
                    <th>Bagian (Section)</th>
                    <th>NIK / Operator</th>
                    <th>Jenis Aktivitas</th>
                    <th>Detail Transaksi / Barcode</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            <?= $isOperatorOnly ? "Belum ada rekaman aktivitas yang Anda input pada shift ini. Silakan lakukan penginputan melalui Mode Lite atau menu Departemen Anda." : "Belum ada rekaman aktivitas transaksi pada filter departemen / bagian yang dipilih." ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= date('H:i:s', strtotime($act['created_at'])) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= date('d M Y', strtotime($act['created_at'])) ?> (<?= htmlspecialchars($act['shift'] ?? 'Shift 1') ?>)</div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($act['dept_name'] ?? $act['dept_code'] ?? 'Pabrik') ?></span>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?= htmlspecialchars($act['section_name'] ?? $act['section_code'] ?? '-') ?></span>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($act['operator_name'] ?? 'Operator') ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);">NIK: <?= htmlspecialchars($act['operator_nik'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-success"><?= htmlspecialchars($act['action_type'] ?? 'SCAN') ?></span>
                            </td>
                            <td>
                                <code style="background: rgba(0,0,0,0.25); padding: 3px 6px; border-radius: 4px; font-size: 12px; color: #60a5fa;">
                                    <?= htmlspecialchars($act['details'] ?? '-') ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$allowedDepts = Auth::getAllowedDeptPages();
if (!empty($allowedDepts)):
?>
<!-- Department Quick Access Grid (Sesuai Hak Akses: Super Admin & Admin Dept Terkait) -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            Akses Cepat Modul Departemen Pabrik
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
        <?php if (in_array('WH', $allowedDepts)): ?>
        <a href="dept_warehouse.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #3b82f6;">1. Dept Gudang Bahan</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Penerima (Empty Racks), Pengirim (Occupied), Peta 300 Rak</div>
        </a>
        <?php endif; ?>

        <?php if (in_array('MAT', $allowedDepts)): ?>
        <a href="dept_material.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #10b981;">2. Dept Material</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Mixing Kompon, Calendering Ply/Sheet, Delivery Antar Dept</div>
        </a>
        <?php endif; ?>

        <?php if (in_array('BLD', $allowedDepts)): ?>
        <a href="dept_building.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #f59e0b;">3. Dept Building</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Green Tire Assembly (PCR, SUV, LTR, TBR)</div>
        </a>
        <?php endif; ?>

        <?php if (in_array('CUR', $allowedDepts)): ?>
        <a href="dept_curing.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #8b5cf6;">4. Dept Curing</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Curing Tire, Supply GT Lory/Delivery, Bladder Installer & Assembly, Compound</div>
        </a>
        <?php endif; ?>

        <?php if (in_array('FI', $allowedDepts)): ?>
        <a href="dept_final_inspection.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #ec4899;">5. Final Inspection</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Visual & Dynamic Inspection, Cutting Hair Tire, Finish Delivery</div>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<!-- Quick Operational Links untuk Jabatan Tanpa Akses Menu Departemen Pabrik -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            Akses Cepat Operasional (<?= htmlspecialchars(Auth::getRoleLabel()) ?>)
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
        <a href="scada.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #3b82f6;">📊 SCADA Monitoring</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Monitoring output mesin & tren per jam/hari/bulan</div>
        </a>
        <a href="scan_terminal.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #10b981;">🔍 Scan Terminal</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Pelacak jejak barcode & riwayat perjalanan ban</div>
        </a>
        <a href="downtime_monitor.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #f59e0b;">⚠️ Hambatan Mesin (4M)</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Pelaporan & penyelesaian kendala mesin aktif</div>
        </a>
        <a href="communication.php" style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 6px;">
            <div style="font-weight: 700; color: #8b5cf6;">💬 Pesan & Broadcast</div>
            <div style="font-size: 12px; color: var(--text-secondary);">Komunikasi dan instruksi shift pabrik</div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- BAGIAN 6: JAVASCRIPT LOGIKA FILTER BERTINGKAT & REAL-TIME AUTO REFRESH -->
<!-- ===================================================================== -->
<script>
// Data master seluruh sections per departemen
const allSections = <?= json_encode(array_values(array_map(function($s) use ($deptIdToCode) {
    return [
        'code' => $s['code'],
        'name' => $s['name'],
        'dept' => $deptIdToCode[$s['dept_id']] ?? ''
    ];
}, $sections))) ?>;

const initialSectionValue = <?= json_encode($filterSection) ?>;

// Dynamic Section dropdown linked to Department
function updateSectionFilter(isInit = false) {
    const deptSelect = document.getElementById('deptSelect');
    const sectSelect = document.getElementById('sectionSelect');
    if (!deptSelect || !sectSelect) return;

    const selectedDept = deptSelect.value;
    const targetVal = isInit ? initialSectionValue : sectSelect.value;

    // Bersihkan opsi lama dan pasang default
    sectSelect.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = selectedDept ? '-- Semua Bagian (' + selectedDept + ') --' : '-- Semua Bagian --';
    sectSelect.appendChild(defaultOpt);

    let isTargetValFound = false;

    allSections.forEach(function(s) {
        if (!selectedDept || s.dept === selectedDept) {
            const opt = document.createElement('option');
            opt.value = s.code;
            opt.textContent = s.name + ' (' + s.code + ')';
            opt.setAttribute('data-dept', s.dept);
            if (s.code === targetVal) {
                opt.selected = true;
                isTargetValFound = true;
            }
            sectSelect.appendChild(opt);
        }
    });

    if (!isTargetValFound) {
        sectSelect.value = '';
    }
}

// Inisialisasi saat DOM siap
document.addEventListener('DOMContentLoaded', function() {
    updateSectionFilter(true);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
