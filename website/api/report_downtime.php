<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/shift.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$deptId = trim($input['dept_id'] ?? '1');
$machineId = trim($input['machine_code'] ?? $input['machine_id'] ?? '');
$category4m = trim($input['downtime_type'] ?? $input['category_4m'] ?? 'Machine');
$description = trim($input['description'] ?? '');
$operatorNik = trim($input['reported_by'] ?? $input['operator_nik'] ?? 'OPERATOR');
$durationMin = (int)($input['duration_minutes'] ?? 0);

if (empty($machineId) || empty($description)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Kode mesin dan deskripsi hambatan wajib diisi.']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $shiftInfo = ShiftHelper::getShiftInfo();
    $currentShift = 'Shift ' . ($shiftInfo['shift'] ?? 1);

    $stmt = $pdo->prepare("
        INSERT INTO downtime_events (
            dept_id, machine_id, category_4m, description, 
            status, operator_nik, start_time, shift, duration_minutes
        ) VALUES (
            ?, ?, ?, ?, 
            'ACTIVE', ?, CURRENT_TIMESTAMP, ?, ?
        ) RETURNING id
    ");
    $stmt->execute([$deptId, $machineId, $category4m, $description, $operatorNik, $currentShift, $durationMin]);
    $newId = $stmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'message' => 'Laporan downtime mesin ' . $machineId . ' berhasil dikirim ke monitor SCADA.',
        'event_id' => $newId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
