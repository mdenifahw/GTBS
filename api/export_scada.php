<?php
/**
 * ==============================================================================
 * API EKSPOR DATA ANALITIK SCADA & KPI DASHBOARD - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/shift.php';

$pdo = Database::getConnection();

// Format JSON untuk Mobile App Android Retrofit
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    try {
        $today = date('Y-m-d');
        $stmtProd = $pdo->prepare("SELECT COUNT(*) FROM production_activities WHERE prod_date = ?");
        $stmtProd->execute([$today]);
        $totalProduced = (int)$stmtProd->fetchColumn();

        $stmtK3 = $pdo->query("SELECT COUNT(*) FROM k3_ambulance_calls WHERE status IN ('REQUESTED', 'DISPATCHED', 'ON_SITE')");
        $activeK3 = (int)$stmtK3->fetchColumn();

        $stmtDowntime = $pdo->query("SELECT COUNT(*) FROM downtime_events WHERE status IN ('ACTIVE', 'IN_PROGRESS')");
        $activeDowntime = (int)$stmtDowntime->fetchColumn();

        $s1 = (int)$pdo->query("SELECT COUNT(*) FROM production_activities WHERE prod_date = CURRENT_DATE AND shift = 1")->fetchColumn();
        $s2 = (int)$pdo->query("SELECT COUNT(*) FROM production_activities WHERE prod_date = CURRENT_DATE AND shift = 2")->fetchColumn();
        $s3 = (int)$pdo->query("SELECT COUNT(*) FROM production_activities WHERE prod_date = CURRENT_DATE AND shift = 3")->fetchColumn();

        $kpiTarget = 5000;
        $kpiPct = $kpiTarget > 0 ? round(($totalProduced / $kpiTarget) * 100, 1) : 0.0;

        echo json_encode([
            'status' => 'success',
            'total_produced_today' => $totalProduced,
            'kpi_target_percentage' => $kpiPct,
            'active_k3_incidents' => $activeK3,
            'active_downtime_machines' => $activeDowntime,
            'shift1_count' => $s1,
            'shift2_count' => $s2,
            'shift3_count' => $s3
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Format CSV untuk Export Web Spreadsheet
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/export.php';

Auth::requireLogin();
// Existing CSV export logic...
$stmt = $pdo->query("SELECT * FROM production_scada_logs ORDER BY id DESC LIMIT 500");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
ExportHelper::exportCSV('SCADA_Log_' . date('Ymd_His'), array_keys($rows[0] ?? ['id']), $rows);
