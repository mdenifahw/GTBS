<?php
/**
 * ==============================================================================
 * SCADA & INDUSTRIAL AUTOMATION MONITORING - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Panel visualisasi data telemetri mesin pabrik & output produksi langsung dari PLC/SCADA.
 * - Menampilkan status konektivitas mesin, suhu, tekanan hidrolik curing, getaran, & cycle time.
 * - Menyediakan fitur analitik produksi multi-periode fleksibel:
 *   1. Per Jam (Hourly) dalam 1 Hari penuh (00:00 - 23:00) dengan pemilihan tanggal spesifik.
 *   2. Per Hari (Daily) dalam rentang 1 Bulan penuh (Tgl 1 - 28/30/31) dengan pemilihan bulan & tahun.
 *   3. Per Bulan (Monthly) dalam 1 Tahun penuh (Jan - Des) dengan pemilihan tahun spesifik.
 * - Visualisasi data berupa grafik batang interaktif (CSS responsive bar chart) dengan skala otomatis.
 * - Ekspor langsung hasil filter analitik ke format spreadsheet (CSV).
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Satuan / Nilai Ambang Sensor:
 *    Cari blok kartu telemetri mesin (`telemetry-grid`) untuk mengubah batas normal suhu atau tekanan.
 * 2. Mengubah Warna Grafik Batang:
 *    Cari kelas CSS `.bar-fill` atau style inline background color pada perulangan data grafik.
 * 3. Menambahkan Departemen Baru ke Filter:
 *    Daftar departemen diambil otomatis dari tabel database `departments`. Cukup tambahkan record di tabel tsb.
 * ==============================================================================
 */
$pageTitle = 'SCADA Monitoring';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// ==============================================================================
// BAGIAN 1: PENGAMBILAN & VALIDASI PARAMETER PERIODE & FILTER ANALITIK
// ==============================================================================
// Parameters
$selectedPeriod = $_GET['period'] ?? 'hourly'; // hourly, daily, monthly
$selectedDate = $_GET['filter_date'] ?? date('Y-m-d'); // For hourly
$selectedMonth = $_GET['filter_month'] ?? date('Y-m'); // For daily
$selectedYear = (int)($_GET['filter_year'] ?? date('Y')); // For monthly
$selectedShift = $_GET['shift_filter'] ?? 'all'; // all, 1, 2, 3
$selectedDept = $_GET['dept'] ?? 'ALL';
$selectedMetric = $_GET['metric'] ?? 'total'; // total, pass, shift1, shift2, shift3

// Departments for filter
$departments = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll();

// ==============================================================================
// PENEGAKAN HAK AKSES TINGKAT JABATAN (ROLE-BASED SCADA SCOPING)
// ==============================================================================
$userDeptCode = $currentUser['dept_code'] ?? '';
$userSectionCode = $currentUser['section_code'] ?? '';
$userNik = $currentUser['nik'] ?? '';

// 1. Dept Head, Asst Dept Head, & Admin Dept: Terkunci pada departemennya
if (Auth::isDeptHeadLevel() || Auth::isAdminDept()) {
    $selectedDept = $userDeptCode ?: 'ALL';
}

// 2. Section Head & Leader: Terkunci pada departemen dan bagiannya
if (Auth::isSectionLevel()) {
    $selectedDept = $userDeptCode ?: 'ALL';
}

// 3. Operator: Terkunci pada departemen, bagian, dan NIK pribadinya
if (Auth::isOperator()) {
    $selectedDept = $userDeptCode ?: 'ALL';
}

// Build WHERE clause based on dept, shift, section, dan operator
$whereClauses = ["1=1"];
$params = [];

if ($selectedDept !== 'ALL') {
    $whereClauses[] = "dept_id = ?";
    $params[] = $selectedDept;
}

if ($selectedShift !== 'all') {
    $whereClauses[] = "shift = ?";
    $params[] = 'Shift ' . $selectedShift;
}

// Scoping tambahan untuk Section Head & Leader (Hanya bagiannya)
if (Auth::isSectionLevel() && !empty($userSectionCode)) {
    $whereClauses[] = "(section_id = ? OR section_id LIKE ?)";
    $params[] = $userSectionCode;
    $params[] = "%" . $userSectionCode . "%";
}

// Scoping tambahan untuk Operator (Hanya tugas/data pribadinya)
if (Auth::isOperator()) {
    if (!empty($userSectionCode)) {
        $whereClauses[] = "(section_id = ? OR section_id LIKE ?)";
        $params[] = $userSectionCode;
        $params[] = "%" . $userSectionCode . "%";
    }
    if (!empty($userNik)) {
        $whereClauses[] = "operator_nik = ?";
        $params[] = $userNik;
    }
}

// Timeframe WHERE filter for KPI and Chart
$kpiWhere = $whereClauses;
$kpiParams = $params;

if ($selectedPeriod === 'hourly') {
    $kpiWhere[] = "log_date = ?";
    $kpiParams[] = $selectedDate;
    $periodTitle = "Per Jam (Tanggal: " . date('d M Y', strtotime($selectedDate)) . ")";
} elseif ($selectedPeriod === 'daily') {
    $kpiWhere[] = "TO_CHAR(log_date, 'YYYY-MM') = ?";
    $kpiParams[] = $selectedMonth;
    $periodTitle = "Per Hari (Bulan: " . date('F Y', strtotime($selectedMonth . '-01')) . ")";
} else { // monthly
    $kpiWhere[] = "EXTRACT(YEAR FROM log_date) = ?";
    $kpiParams[] = $selectedYear;
    $periodTitle = "Per Bulan (Tahun: " . $selectedYear . ")";
}

$kpiWhereSql = implode(" AND ", $kpiWhere);

// KPI counts for the selected timeframe
$kpiStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(qty), 0) as total_output,
        COALESCE(SUM(CASE WHEN quality_status = 'PASS' THEN qty ELSE 0 END), 0) as pass_count,
        COALESCE(SUM(CASE WHEN quality_status = 'REJECT' THEN qty ELSE 0 END), 0) as reject_count,
        COALESCE(SUM(CASE WHEN quality_status = 'REPAIR' THEN qty ELSE 0 END), 0) as repair_count,
        COALESCE(SUM(CASE WHEN shift = 'Shift 1' THEN qty ELSE 0 END), 0) as shift1_count,
        COALESCE(SUM(CASE WHEN shift = 'Shift 2' THEN qty ELSE 0 END), 0) as shift2_count,
        COALESCE(SUM(CASE WHEN shift = 'Shift 3' THEN qty ELSE 0 END), 0) as shift3_count
    FROM production_scada_logs
    WHERE {$kpiWhereSql}
");
$kpiStmt->execute($kpiParams);
$kpi = $kpiStmt->fetch();

$total = (int)($kpi['total_output'] ?? 0);
$pass = (int)($kpi['pass_count'] ?? 0);
$reject = (int)($kpi['reject_count'] ?? 0);
$repair = (int)($kpi['repair_count'] ?? 0);
$shift1 = (int)($kpi['shift1_count'] ?? 0);
$shift2 = (int)($kpi['shift2_count'] ?? 0);
$shift3 = (int)($kpi['shift3_count'] ?? 0);
$passRate = $total > 0 ? round(($pass / $total) * 100, 1) : 100.0;

// Query and build chart items based on user's selected period
$chartItems = [];

if ($selectedPeriod === 'hourly') {
    // 1 Hari: Data per jam (standar pabrik 24 jam shift)
    if ($selectedShift === '1') {
        $hoursDisplay = [7, 8, 9, 10, 11, 12, 13, 14];
    } elseif ($selectedShift === '2') {
        $hoursDisplay = [15, 16, 17, 18, 19, 20, 21, 22];
    } elseif ($selectedShift === '3') {
        $hoursDisplay = [23, 0, 1, 2, 3, 4, 5, 6];
    } else {
        $hoursDisplay = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6];
    }

    $trendStmt = $pdo->prepare("
        SELECT 
            log_hour,
            COALESCE(SUM(qty), 0) as total_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'PASS' THEN qty ELSE 0 END), 0) as pass_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'REJECT' THEN qty ELSE 0 END), 0) as reject_qty
        FROM production_scada_logs
        WHERE {$kpiWhereSql}
        GROUP BY log_hour
    ");
    $trendStmt->execute($kpiParams);
    $trendMap = [];
    foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $trendMap[(int)$row['log_hour']] = $row;
    }

    foreach ($hoursDisplay as $hr) {
        $row = $trendMap[$hr] ?? ['total_qty' => 0, 'pass_qty' => 0, 'reject_qty' => 0];
        $chartItems[] = [
            'label' => sprintf("%02d:00", $hr),
            'full_label' => "Pukul " . sprintf("%02d:00", $hr) . " (" . date('d M Y', strtotime($selectedDate)) . ")",
            'total_qty' => (int)$row['total_qty'],
            'pass_qty' => (int)$row['pass_qty'],
            'reject_qty' => (int)$row['reject_qty'],
        ];
    }

} elseif ($selectedPeriod === 'daily') {
    // 1 Bulan: Data per hari (rentang hari 1 s/d akhir bulan)
    $numDays = (int)date('t', strtotime($selectedMonth . '-01'));
    $trendStmt = $pdo->prepare("
        SELECT 
            EXTRACT(DAY FROM log_date)::int as day_num,
            COALESCE(SUM(qty), 0) as total_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'PASS' THEN qty ELSE 0 END), 0) as pass_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'REJECT' THEN qty ELSE 0 END), 0) as reject_qty
        FROM production_scada_logs
        WHERE {$kpiWhereSql}
        GROUP BY EXTRACT(DAY FROM log_date)
    ");
    $trendStmt->execute($kpiParams);
    $trendMap = [];
    foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $trendMap[(int)$row['day_num']] = $row;
    }

    for ($d = 1; $d <= $numDays; $d++) {
        $row = $trendMap[$d] ?? ['total_qty' => 0, 'pass_qty' => 0, 'reject_qty' => 0];
        $chartItems[] = [
            'label' => sprintf("%02d", $d),
            'full_label' => sprintf("Tanggal %02d ", $d) . date('F Y', strtotime($selectedMonth . '-01')),
            'total_qty' => (int)$row['total_qty'],
            'pass_qty' => (int)$row['pass_qty'],
            'reject_qty' => (int)$row['reject_qty'],
        ];
    }

} else { // monthly
    // 1 Tahun: Data per bulan (12 bulan: Jan s/d Des)
    $monthNames = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $monthFullNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $trendStmt = $pdo->prepare("
        SELECT 
            EXTRACT(MONTH FROM log_date)::int as month_num,
            COALESCE(SUM(qty), 0) as total_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'PASS' THEN qty ELSE 0 END), 0) as pass_qty,
            COALESCE(SUM(CASE WHEN quality_status = 'REJECT' THEN qty ELSE 0 END), 0) as reject_qty
        FROM production_scada_logs
        WHERE {$kpiWhereSql}
        GROUP BY EXTRACT(MONTH FROM log_date)
    ");
    $trendStmt->execute($kpiParams);
    $trendMap = [];
    foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $trendMap[(int)$row['month_num']] = $row;
    }

    for ($m = 1; $m <= 12; $m++) {
        $row = $trendMap[$m] ?? ['total_qty' => 0, 'pass_qty' => 0, 'reject_qty' => 0];
        $chartItems[] = [
            'label' => $monthNames[$m],
            'full_label' => "Bulan " . $monthFullNames[$m] . " " . $selectedYear,
            'total_qty' => (int)$row['total_qty'],
            'pass_qty' => (int)$row['pass_qty'],
            'reject_qty' => (int)$row['reject_qty'],
        ];
    }
}

// Max value scaling for the chart
$maxQty = 1;
foreach ($chartItems as $ci) {
    if ($ci['total_qty'] > $maxQty) {
        $maxQty = $ci['total_qty'];
    }
}

// Recent SCADA logs for table
$recentLogsStmt = $pdo->prepare("
    SELECT psl.*, d.name as dept_name 
    FROM production_scada_logs psl
    LEFT JOIN departments d ON psl.dept_id = d.code
    WHERE {$kpiWhereSql}
    ORDER BY psl.created_at DESC, psl.id DESC
    LIMIT 25
");
$recentLogsStmt->execute($kpiParams);
$recentLogs = $recentLogsStmt->fetchAll();
?>

<!-- SCADA Filter Controls Bar -->
<div class="card" style="padding: 14px 20px; margin-bottom: 20px;">
    <form method="GET" action="scada.php" id="scadaFilterForm" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <input type="hidden" name="metric" id="metricInput" value="<?= htmlspecialchars($selectedMetric) ?>">
        <input type="hidden" name="shift_filter" id="shiftInput" value="<?= htmlspecialchars($selectedShift) ?>">

        <!-- Period Toggle: Jam / Hari / Bulan -->
        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin-right: 4px;">Pilihan Periode:</span>
            <button type="submit" name="period" value="hourly" class="btn <?= $selectedPeriod === 'hourly' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Per Jam (1 Hari)
            </button>
            <button type="submit" name="period" value="daily" class="btn <?= $selectedPeriod === 'daily' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Per Hari (1 Bulan)
            </button>
            <button type="submit" name="period" value="monthly" class="btn <?= $selectedPeriod === 'monthly' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Per Bulan (1 Tahun)
            </button>
        </div>

        <!-- Dynamic Date / Month / Year Picker based on active Period -->
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <?php if ($selectedPeriod === 'hourly'): ?>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <label class="form-label" style="margin: 0; font-size: 12px;">Pilih Hari:</label>
                    <input type="date" name="filter_date" class="form-input" style="padding: 4px 8px; width: auto; font-size: 12px;" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                </div>
            <?php elseif ($selectedPeriod === 'daily'): ?>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <label class="form-label" style="margin: 0; font-size: 12px;">Pilih Bulan:</label>
                    <input type="month" name="filter_month" class="form-input" style="padding: 4px 8px; width: auto; font-size: 12px;" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()">
                </div>
            <?php else: ?>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <label class="form-label" style="margin: 0; font-size: 12px;">Pilih Tahun:</label>
                    <select name="filter_year" class="form-select" style="padding: 4px 8px; width: auto; font-size: 12px;" onchange="this.form.submit()">
                        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
                            <option value="<?= $y ?>" <?= (int)$selectedYear === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Dept Selection Filter (Sesuai Hak Akses Jabatan) -->
            <?php if (Auth::isPlantLevel()): ?>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <label class="form-label" style="margin: 0; font-size: 12px;">Departemen:</label>
                    <select name="dept" class="form-select" style="padding: 4px 8px; width: auto; min-width: 150px; font-size: 12px;" onchange="this.form.submit()">
                        <option value="ALL" <?= $selectedDept === 'ALL' ? 'selected' : '' ?>>-- Semua Dept --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['code'] ?>" <?= $selectedDept === $d['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="dept" value="<?= htmlspecialchars($selectedDept) ?>">
                <div style="padding: 4px 10px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 6px; font-size: 12px; color: #60a5fa; font-weight: 600;">
                    Scope: <?= htmlspecialchars($currentUser['dept_name'] ?? $selectedDept) ?>
                    <?php if (Auth::isSectionLevel() || Auth::isOperator()): ?>
                        &bull; <?= htmlspecialchars($currentUser['section_name'] ?? $userSectionCode) ?>
                    <?php endif; ?>
                    <?php if (Auth::isOperator()): ?>
                        &bull; Personal (NIK: <?= htmlspecialchars($userNik) ?>)
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($selectedShift !== 'all' || $selectedDept !== 'ALL' || ($selectedPeriod === 'hourly' && $selectedDate !== date('Y-m-d')) || ($selectedPeriod === 'daily' && $selectedMonth !== date('Y-m')) || ($selectedPeriod === 'monthly' && $selectedYear != date('Y'))): ?>
                <a href="scada.php?period=<?= urlencode($selectedPeriod) ?>" class="btn btn-secondary btn-sm" style="font-size: 12px; padding: 4px 10px;">Reset Filter</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Interactive SCADA KPI Summary Cards (Clickable to filter shift & metric) -->
<div class="kpi-grid">
    <div class="kpi-card <?= $selectedMetric === 'total' && $selectedShift === 'all' ? 'active' : '' ?>" onclick="selectMetric('total')">
        <div class="kpi-title">Total Output Produksi</div>
        <div class="kpi-value" style="color: var(--color-accent);"><?= number_format($total) ?></div>
        <div class="kpi-meta"><?= $periodTitle ?></div>
    </div>
    <div class="kpi-card <?= $selectedMetric === 'pass' ? 'active' : '' ?>" onclick="selectMetric('pass')">
        <div class="kpi-title">Pass Rate (Kualitas OK)</div>
        <div class="kpi-value" style="color: var(--color-success);"><?= $passRate ?>%</div>
        <div class="kpi-meta"><?= number_format($pass) ?> Pass | <?= number_format($reject) ?> Reject</div>
    </div>
    <div class="kpi-card <?= $selectedShift === '1' ? 'active' : '' ?>" onclick="selectShift('1')">
        <div class="kpi-title">Shift 1 (07:00 - 15:00)</div>
        <div class="kpi-value" style="color: #60a5fa;"><?= number_format($shift1) ?></div>
        <div class="kpi-meta">Klik untuk filter grafik Shift 1</div>
    </div>
    <div class="kpi-card <?= $selectedShift === '2' ? 'active' : '' ?>" onclick="selectShift('2')">
        <div class="kpi-title">Shift 2 (15:00 - 23:00)</div>
        <div class="kpi-value" style="color: #a78bfa;"><?= number_format($shift2) ?></div>
        <div class="kpi-meta">Klik untuk filter grafik Shift 2</div>
    </div>
    <div class="kpi-card <?= $selectedShift === '3' ? 'active' : '' ?>" onclick="selectShift('3')">
        <div class="kpi-title">Shift 3 (23:00 - 07:00)</div>
        <div class="kpi-value" style="color: #f472b6;"><?= number_format($shift3) ?></div>
        <div class="kpi-meta">Klik untuk filter grafik Shift 3</div>
    </div>
</div>

<!-- Trend Graph Container (Pure SCADA Realtime Visualisation per Jam, Hari, atau Bulan) -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
                Tren Output Produksi & Mutu &mdash; <?= $periodTitle ?>
            </div>
            <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 4px;">
                Filter Dept: <strong><?= htmlspecialchars($selectedDept === 'ALL' ? 'Semua Departemen' : $selectedDept) ?></strong> | 
                Shift: <strong><?= htmlspecialchars($selectedShift === 'all' ? 'Semua Shift' : 'Shift ' . $selectedShift) ?></strong> | 
                Skala Maks: <strong><?= number_format($maxQty) ?> pcs</strong>
            </div>
        </div>
        <div style="display: flex; gap: 14px; align-items: center; font-size: 12px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; background: #3b82f6; border-radius: 3px; display: inline-block;"></span>
                <span>Output Pass (OK)</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 3px; display: inline-block;"></span>
                <span>Reject / Cacat</span>
            </div>
        </div>
    </div>

    <!-- Clean CSS Bar Chart Visualisation -->
    <div style="height: 250px; display: flex; align-items: flex-end; gap: 8px; padding: 24px 14px 10px 14px; background: rgba(0,0,0,0.18); border-radius: 8px; overflow-x: auto; border: 1px solid var(--border-color);">
        <?php foreach ($chartItems as $item): ?>
            <?php
            $totalVal = $item['total_qty'];
            $passVal = $item['pass_qty'];
            $rejVal = $item['reject_qty'];

            // Height relative to maxQty (between 6% minimum if >0, up to 100%)
            $colHeightPercent = $totalVal > 0 ? max(6, round(($totalVal / $maxQty) * 100)) : 0;
            
            // Pass vs Reject split inside column
            $passPercentOfCol = $totalVal > 0 ? round(($passVal / $totalVal) * 100) : 100;
            $rejPercentOfCol = 100 - $passPercentOfCol;

            $tooltipText = "{$item['full_label']}\nTotal Output: " . number_format($totalVal) . " pcs\nPass (OK): " . number_format($passVal) . " pcs\nReject: " . number_format($rejVal) . " pcs";
            ?>
            <div style="flex: 1; min-width: <?= $selectedPeriod === 'daily' ? '24px' : ($selectedPeriod === 'monthly' ? '45px' : '32px') ?>; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: 4px;" title="<?= htmlspecialchars($tooltipText) ?>">
                <!-- Total Value text on top of bar -->
                <div style="font-size: 10px; font-weight: 600; color: <?= $totalVal > 0 ? 'var(--text-primary)' : 'var(--text-muted)' ?>; line-height: 1;">
                    <?= $totalVal > 0 ? ($totalVal >= 1000 ? round($totalVal / 1000, 1) . 'k' : number_format($totalVal)) : '0' ?>
                </div>

                <!-- Bar Column -->
                <div style="width: 100%; max-width: 28px; height: 100%; display: flex; flex-direction: column; justify-content: flex-end;">
                    <div style="width: 100%; height: <?= $colHeightPercent ?>%; min-height: <?= $totalVal > 0 ? '4px' : '2px' ?>; background: <?= $totalVal > 0 ? 'transparent' : 'rgba(255,255,255,0.06)' ?>; border-radius: 3px 3px 0 0; display: flex; flex-direction: column; overflow: hidden; transition: height 0.3s ease;">
                        <!-- Reject Bar (Red) -->
                        <?php if ($rejVal > 0): ?>
                            <div style="width: 100%; height: <?= $rejPercentOfCol ?>%; background: #ef4444;" title="Reject: <?= number_format($rejVal) ?>"></div>
                        <?php endif; ?>
                        <!-- Pass Bar (Blue) -->
                        <?php if ($passVal > 0): ?>
                            <div style="width: 100%; height: <?= $passPercentOfCol ?>%; background: #3b82f6;" title="Pass: <?= number_format($passVal) ?>"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Label underneath -->
                <div style="font-size: 10px; color: var(--text-secondary); margin-top: 6px; white-space: nowrap; font-weight: 500;">
                    <?= htmlspecialchars($item['label']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- SCADA Live Log Feeds -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            Data Pelaporan SCADA Terkini (<?= $periodTitle ?>)
        </div>
        <a href="api/export_scada.php?period=<?= urlencode($selectedPeriod) ?>&filter_date=<?= urlencode($selectedDate) ?>&filter_month=<?= urlencode($selectedMonth) ?>&filter_year=<?= urlencode($selectedYear) ?>&dept=<?= urlencode($selectedDept) ?>&shift_filter=<?= urlencode($selectedShift) ?>" class="btn btn-secondary btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Export CSV / Excel
        </a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Scan</th>
                    <th>Barcode</th>
                    <th>Dept & Section</th>
                    <th>Mesin</th>
                    <th>Operator NIK</th>
                    <th>Qty</th>
                    <th>Status Mutu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 28px; color: var(--text-muted);">
                            Belum ada rekaman log pelaporan SCADA terkini pada filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> (<?= htmlspecialchars($log['shift']) ?>)</td>
                            <td><code><?= htmlspecialchars($log['barcode']) ?></code></td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($log['dept_id']) ?></span>
                                <span class="badge badge-warning"><?= htmlspecialchars($log['section_id']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($log['machine_id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($log['operator_nik']) ?></td>
                            <td><?= number_format($log['qty']) ?> pcs</td>
                            <td>
                                <?php if ($log['quality_status'] === 'PASS'): ?>
                                    <span class="badge badge-success">PASS</span>
                                <?php elseif ($log['quality_status'] === 'REJECT'): ?>
                                    <span class="badge badge-danger">REJECT</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><?= htmlspecialchars($log['quality_status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT DINAMIS TAMPILAN FILTER & FORM AUTO-SUBMIT -->
<!-- ===================================================================== -->
<script>
function selectMetric(m) {
    document.getElementById('metricInput').value = m;
    if (m === 'total' || m === 'pass') {
        document.getElementById('shiftInput').value = 'all';
    }
    document.getElementById('scadaFilterForm').submit();
}

function selectShift(s) {
    const shiftInput = document.getElementById('shiftInput');
    // Toggle: klik shift yang sama akan mereset ke 'all'
    if (shiftInput.value === s) {
        shiftInput.value = 'all';
        document.getElementById('metricInput').value = 'total';
    } else {
        shiftInput.value = s;
        document.getElementById('metricInput').value = 'shift' + s;
    }
    document.getElementById('scadaFilterForm').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
