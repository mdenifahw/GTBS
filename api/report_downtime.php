<?php
/**
 * ==============================================================================
 * API ENDPOINT - PELAPORAN CEPAT DOWNTIME MESIN & HAMBATAN 4M - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Endpoint RESTful JSON untuk pelaporan mesin berhenti atau kendala operasional dari client/terminal.
 * - Menerima input via HTTP POST: dept_id, machine_id, reason_category (4M), reason_description.
 * - Mengembalikan respon berformat JSON: `{"status": "success", "event_id": 123}`.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menghubungkan ke Sistem Andon Display:
 *    Respon JSON ini dapat dikonsumsi oleh layar display Andon di atas lini perakitan.
 * 2. Menambah Field Baru:
 *    Tambahkan parameter seperti `target_technician` atau `estimated_repair_time` jika diperlukan.
 * ==============================================================================
 */
// GTBS - Quick Downtime Report API
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/shift.php';

if (!Auth::check()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$deptId = trim($_POST['dept_id'] ?? '');
$machineId = trim($_POST['machine_id'] ?? '');
$category4m = trim($_POST['category_4m'] ?? 'Machine');
$description = trim($_POST['description'] ?? '');
$operatorNik = trim($_POST['operator_nik'] ?? '');

if (empty($deptId) || empty($machineId) || empty($description) || empty($operatorNik)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua bidang wajib diisi']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $shiftInfo = ShiftHelper::getShiftInfo();

    $stmt = $pdo->prepare("
        INSERT INTO downtime_events (dept_id, machine_id, category_4m, description, status, operator_nik, start_time, shift)
        VALUES (?, ?, ?, ?, 'ACTIVE', ?, CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$deptId, $machineId, $category4m, $description, $operatorNik, 'Shift ' . $shiftInfo['shift']]);

    echo json_encode(['status' => 'success', 'message' => 'Laporan hambatan berhasil dikirim']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
