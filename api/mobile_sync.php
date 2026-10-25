<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/shift.php';

$pdo = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. PULL MASTER DATA (Untuk cache lokal SQLite di perangkat PDT)
if ($action === 'pull_master') {
    try {
        $depts = $pdo->query("SELECT id, code, name, icon, color FROM departments WHERE is_active = true ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $sections = $pdo->query("SELECT id, dept_id, code, name FROM sections WHERE is_active = true ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $racks = $pdo->query("SELECT id, COALESCE(rack_code, code) as rack_code, zone, row_num, tier_num, status, is_occupied, current_item_name, current_barcode, current_qty FROM warehouse_racks ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $barcodes = $pdo->query("SELECT id, barcode, product_code, product_name, size_name, status, barcode_type, current_dept FROM barcodes ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'timestamp' => date('c'),
            'data' => [
                'departments' => $depts,
                'sections' => $sections,
                'warehouse_racks' => $racks,
                'recent_barcodes' => $barcodes
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. PUSH OFFLINE PRODUCTION ACTIVITIES (Batch upload dari SQLite PDT)
if ($action === 'push_activities') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $items = $input['items'] ?? [];

    if (!is_array($items) || empty($items)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Daftar item aktivitas kosong.']);
        exit;
    }

    $syncedIds = [];
    $errors = [];
    $shiftInfo = ShiftHelper::getShiftInfo();
    $currentShift = (int)($shiftInfo['shift'] ?? 1);

    foreach ($items as $item) {
        $localId = $item['local_id'] ?? null;
        $barcode = strtoupper(trim($item['barcode'] ?? ''));
        $deptId = (int)($item['dept_id'] ?? 1);
        $sectionId = (int)($item['section_id'] ?? 1);
        $qty = (float)($item['qty'] ?? 1.0);
        $unit = trim($item['unit'] ?? 'Pcs');
        $opId = (int)($item['operator_id'] ?? 1);
        $opNik = trim($item['operator_nik'] ?? 'OPERATOR');
        $actionType = trim($item['action_type'] ?? 'PDT_SCAN');
        $details = trim($item['details'] ?? 'Offline PDT Scan');
        $recordedDate = !empty($item['created_at']) ? date('Y-m-d', strtotime($item['created_at'])) : date('Y-m-d');
        $recordedShift = isset($item['shift']) ? (int)$item['shift'] : $currentShift;
        $status = trim($item['status'] ?? 'NORMAL');

        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO production_activities (
                        barcode, dept_id, section_id, prod_date, shift, status, 
                        qty, unit, operator_id, operator_nik, action_type, details, created_at
                    ) VALUES (
                        :barcode, :dept_id, :section_id, :prod_date, :shift, :status, 
                        :qty, :unit, :operator_id, :operator_nik, :action_type, :details, CURRENT_TIMESTAMP
                    )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':barcode' => $barcode,
                ':dept_id' => $deptId,
                ':section_id' => $sectionId,
                ':prod_date' => $recordedDate,
                ':shift' => $recordedShift,
                ':status' => $status,
                ':qty' => $qty,
                ':unit' => $unit,
                ':operator_id' => $opId,
                ':operator_nik' => $opNik,
                ':action_type' => $actionType,
                ':details' => $details . " (Sync from PDT)"
            ]);

            // SCADA logging
            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, operator_nik, item_code, 
                    qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, CURRENT_DATE, ?, ?
                )
            ");
            $deptCode = 'DEPT-' . $deptId;
            $scada->execute([
                $barcode, $deptCode, $sectionId, $opNik, $actionType,
                $qty, 'PASS', 'Shift ' . $recordedShift, (int)date('H')
            ]);

            $pdo->commit();
            if ($localId !== null) {
                $syncedIds[] = $localId;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = [
                'local_id' => $localId,
                'barcode' => $barcode,
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => count($syncedIds) . ' aktivitas berhasil disinkronisasi ke server.',
        'synced_local_ids' => $syncedIds,
        'failed_items' => $errors
    ]);
    exit;
}

// 3. PUSH OFFLINE K3 EMERGENCY (SOS BARU)
if ($action === 'push_k3') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $callNo = 'AMB-' . date('ymd') . rand(1000, 9999);
    $callerId = (int)($input['caller_id'] ?? 1);
    $deptId = (int)($input['dept_id'] ?? 1);
    $location = trim($input['location'] ?? 'Area Pabrik');
    $condition = trim($input['condition'] ?? 'Insiden Darurat');
    $priority = trim($input['priority'] ?? 'HIGH_EMERGENCY');
    $callerName = trim($input['caller_name'] ?? 'Operator PDT');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO k3_ambulance_calls (
                call_no, caller_id, dept_id, location_detail, patient_condition, 
                priority, status, caller_name, created_at
            ) VALUES (
                :call_no, :caller_id, :dept_id, :loc, :cond, 
                :prio, 'REQUESTED', :cname, CURRENT_TIMESTAMP
            ) RETURNING id
        ");
        $stmt->execute([
            ':call_no' => $callNo,
            ':caller_id' => $callerId,
            ':dept_id' => $deptId,
            ':loc' => $location,
            ':cond' => $condition,
            ':prio' => $priority,
            ':cname' => $callerName
        ]);
        $newId = $stmt->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'message' => 'Panggilan darurat K3 berhasil diteruskan ke sistem!',
            'call_id' => $newId,
            'call_no' => $callNo
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 4. UPDATE K3 STATUS (Tiba di TKP / Insiden Selesai)
if ($action === 'update_k3_status') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $callId = (int)($input['call_id'] ?? $input['id'] ?? 0);
    $status = trim($input['status'] ?? '');

    if ($callId <= 0 || empty($status)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Parameter call_id dan status diperlukan.']);
        exit;
    }

    try {
        if ($status === 'ON_SITE') {
            $stmt = $pdo->prepare("
                UPDATE k3_ambulance_calls 
                SET status = 'ON_SITE', arrival_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$callId]);
            $msg = 'Tim Medis K3 telah dicatat TIBA DI TKP (ON SITE).';
        } elseif ($status === 'COMPLETED' || $status === 'DONE') {
            $stmt = $pdo->prepare("
                UPDATE k3_ambulance_calls 
                SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$callId]);
            $msg = 'Insiden K3 berhasil diselesaikan (COMPLETED).';
        } else {
            $stmt = $pdo->prepare("
                UPDATE k3_ambulance_calls 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$status, $callId]);
            $msg = 'Status K3 diperbarui ke: ' . $status;
        }

        echo json_encode([
            'status' => 'success',
            'message' => $msg,
            'call_id' => $callId,
            'updated_status' => $status
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 5. GET ACTIVE K3 CALLS
if ($action === 'get_active_k3') {
    try {
        $stmt = $pdo->query("
            SELECT id, call_no, caller_name, location_detail, patient_condition, priority, status, arrival_at, created_at 
            FROM k3_ambulance_calls 
            ORDER BY created_at DESC 
            LIMIT 25
        ");
        $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $calls
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 6. DASHBOARD SUMMARY
if ($action === 'dashboard_summary') {
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

// 7. WAREHOUSE SMART RACK ALLOCATION & ROUTING
if ($action === 'assign_rack') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $rackCode = strtoupper(trim($input['rack_code'] ?? ''));
    $barcode = strtoupper(trim($input['barcode'] ?? ''));
    $itemName = trim($input['item_name'] ?? 'Ban Jadi');
    $qty = (float)($input['qty'] ?? 1.0);

    if (empty($rackCode) || empty($barcode)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Rack code dan barcode harus diisi.']);
        exit;
    }

    try {
        // Cek ketersediaan rak
        $checkStmt = $pdo->prepare("SELECT id, COALESCE(rack_code, code) as code, is_occupied, current_barcode FROM warehouse_racks WHERE COALESCE(rack_code, code) = ?");
        $checkStmt->execute([$rackCode]);
        $rack = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$rack) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => "Rak {$rackCode} tidak ditemukan di master warehouse."]);
            exit;
        }

        if ($rack['is_occupied'] && !empty($rack['current_barcode']) && $rack['current_barcode'] !== $barcode) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => "Peringatan: Rak {$rackCode} saat ini terisi oleh barcode {$rack['current_barcode']}!"]);
            exit;
        }

        // Simpan penempatan
        $upStmt = $pdo->prepare("
            UPDATE warehouse_racks 
            SET is_occupied = true, status = 'OCCUPIED', current_barcode = ?, current_item_name = ?, current_qty = ?
            WHERE id = ?
        ");
        $upStmt->execute([$barcode, $itemName, $qty, $rack['id']]);

        echo json_encode([
            'status' => 'success',
            'message' => "Produk barcode {$barcode} sukses disimpan di {$rackCode}.",
            'rack_code' => $rackCode
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak valid atau tidak didukung.']);
