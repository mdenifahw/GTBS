<?php
/**
 * ==============================================================================
 * API EKSPOR DATA ANALITIK SCADA KE SPREADSHEET (CSV) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengunduh rekapan data produksi dan telemetri SCADA ke dalam file CSV siap olah di Excel.
 * - Mendukung filter dinamis:
 *   1. Per Jam (Hourly) untuk 1 hari tertentu.
 *   2. Per Hari (Daily) untuk 1 bulan penuh.
 *   3. Per Bulan (Monthly) untuk 1 tahun penuh.
 * - Dilengkapi filter departemen dan shift kerja.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Format Nama File Unduhan:
 *    Cari parameter nama file di pemanggilan `ExportHelper::toCSV()`.
 * 2. Menambah Kolom Baru pada File CSV:
 *    Tambahkan elemen pada array `` dan `` di dalam loop data.
 * ==============================================================================
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/export.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$selectedPeriod = $_GET['period'] ?? 'hourly';
$selectedDate = $_GET['filter_date'] ?? date('Y-m-d');
$selectedMonth = $_GET['filter_month'] ?? date('Y-m');
$selectedYear = (int)($_GET['filter_year'] ?? date('Y'));
$selectedShift = $_GET['shift_filter'] ?? 'all';
$selectedDept = $_GET['dept'] ?? 'ALL';

$user = Auth::user();
$userDeptCode = $user['dept_code'] ?? '';
$userSectionCode = $user['section_code'] ?? '';
$userNik = $user['nik'] ?? '';

// Scoping hak akses
if (Auth::isDeptHeadLevel() || Auth::isAdminDept() || Auth::isSectionLevel() || Auth::isOperator()) {
    $selectedDept = $userDeptCode ?: 'ALL';
}

$whereClauses = ["1=1"];
$params = [];

if ($selectedDept !== 'ALL') {
    $whereClauses[] = "dept_id = ?";
    $params[] = $selectedDept;
}

if (Auth::isSectionLevel() && !empty($userSectionCode)) {
    $whereClauses[] = "(section_id = ? OR section_id LIKE ?)";
    $params[] = $userSectionCode;
    $params[] = "%" . $userSectionCode . "%";
}

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

if ($selectedShift !== 'all') {
    $whereClauses[] = "shift = ?";
    $params[] = 'Shift ' . $selectedShift;
}

if ($selectedPeriod === 'hourly') {
    $whereClauses[] = "log_date = ?";
    $params[] = $selectedDate;
} elseif ($selectedPeriod === 'daily') {
    $whereClauses[] = "TO_CHAR(log_date, 'YYYY-MM') = ?";
    $params[] = $selectedMonth;
} else {
    $whereClauses[] = "EXTRACT(YEAR FROM log_date) = ?";
    $params[] = $selectedYear;
}

$whereSql = implode(" AND ", $whereClauses);

$stmt = $pdo->prepare("
    SELECT 
        log_date,
        created_at,
        shift,
        dept_id,
        section_id,
        machine_id,
        operator_nik,
        barcode,
        item_code,
        qty,
        quality_status
    FROM production_scada_logs
    WHERE {$whereSql}
    ORDER BY created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    'Tanggal', 'Waktu', 'Shift', 'Dept', 'Bagian', 'Mesin', 'NIK Operator', 'Barcode', 'Item Code', 'Qty', 'Status Mutu'
];

ExportHelper::exportCSV('SCADA_Log_Report_' . date('Ymd_His'), $headers, $rows);
