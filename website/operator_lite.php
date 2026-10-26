<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Mode Lite Operator Lapangan (Fast Input & Touch-Optimized Station)
 * File : operator_lite.php
 * ==============================================================================================
 *
 * [FUNGSI & KEGUNAAN]:
 * Halaman antarmuka khusus (Mode Lite) yang didesain ringkas, cepat, dan fokus 100% pada 
 * proses penginputan transaksi barcode di lantai pabrik (shop floor).
 * Menghilangkan navigasi rumit ERP dan grafik berat agar operator dapat bekerja efisien 
 * menggunakan perangkat PDT scanner, tablet, maupun layar sentuh workstation.
 *
 * Fitur Utama:
 * 1. Otomatis mendeteksi Departemen & Bagian kerja operator yang sedang login.
 * 2. Input barcode dengan autofokus langsung siap scan.
 * 3. Indikator audio chime & visual banner saat scan sukses / gagal.
 * 4. Rekap instan jumlah input per shift & riwayat 10 scan terakhir operator.
 * 5. Tombol cepat beralih ke Mode Pro (ERP Lengkap).
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/shift.php';
require_once __DIR__ . '/helpers/mobile.php';

// Wajib Login
Auth::requireLogin();
$user = Auth::user();

// Proteksi Otorisasi Mode Lite:
// Manajemen Pabrik & Departemen (Plant Head, Asst Plant Head, Dept Head, Asst Dept Head)
// bertugas untuk MONITORING & PENGAWASAN, bukan penginputan transaksi lapangan.
// Akses Mode Lite HANYA untuk Super Admin dan Operator/Leader/Section Head.
if (in_array($user['role'] ?? '', ['plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head'])) {
    header('Location: dashboard.php?error=' . urlencode('Akses Mode Lite Ditolak: Anda berwenang sebagai Manajemen untuk monitoring & pengawasan, bukan penginputan transaksi lapangan.'));
    exit;
}

$shiftInfo = ShiftHelper::getShiftInfo();
$pdo = Database::getConnection();

// Ambil info foto pengguna
$userPhoto = $user['photo'] ?? '';
if (empty($userPhoto) && !empty($user['id'])) {
    try {
        $stmtP = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
        $stmtP->execute([$user['id']]);
        $userPhoto = $stmtP->fetchColumn() ?: '';
        if ($userPhoto) $_SESSION['user_data']['photo'] = $userPhoto;
    } catch (Exception $e) {}
}

// Ambil semua daftar seksi dari database
$sectionsQuery = $pdo->query("
    SELECT s.id, s.code, s.name, s.dept_id, d.code as dept_code, d.name as dept_name
    FROM sections s 
    JOIN departments d ON s.dept_id = d.id 
    ORDER BY d.id, s.id
")->fetchAll(PDO::FETCH_ASSOC);

// Tentukan bagian aktif
$userSectionCode = $user['section_code'] ?? '';
$activeSectionCode = $_GET['section'] ?? ($userSectionCode ?: 'CUR-PRE');

// HANYA super_admin yang berhak berpindah stasiun penginputan secara bebas!
if (($user['role'] ?? '') !== 'super_admin') {
    if (!empty($userSectionCode)) {
        $activeSectionCode = $userSectionCode;
    }
}

// Cari metadata seksi aktif
$activeSection = null;
foreach ($sectionsQuery as $sec) {
    if ($sec['code'] === $activeSectionCode) {
        $activeSection = $sec;
        break;
    }
}
if (!$activeSection && !empty($sectionsQuery)) {
    $activeSection = $sectionsQuery[0];
    $activeSectionCode = $activeSection['code'];
}

$flashMessage = '';
$flashType = '';
$soundTrigger = '';

// ==============================================================================
// PROSES FORM SUBMISSION DARI OPERATOR (POST)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $operatorNik = $user['nik'] ?? 'OPERATOR';
    $operatorId = $user['id'] ?? 1;
    $shiftLabel = 'Shift ' . $shiftInfo['shift'];
    $todayDate = date('Y-m-d');

    try {
        // 1. INPUT CURING PRESS
        if ($action === 'lite_save_curing_press') {
            $pressMachine = trim($_POST['press_machine'] ?? 'CUR-PRS-01');
            $moldCavity = trim($_POST['mold_cavity'] ?? 'L');
            $gtBarcode = strtoupper(trim($_POST['gt_barcode'] ?? ''));
            $curingTime = (int)($_POST['curing_time_min'] ?? 14);
            $curingTemp = (float)($_POST['curing_temp_c'] ?? 168.5);

            if (empty($gtBarcode)) throw new Exception("Barcode Green Tire wajib diisi atau discan!");

            // Validasi barcode di master barcodes
            $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
            $bcCheck->execute([$gtBarcode]);
            $bcData = $bcCheck->fetch(PDO::FETCH_ASSOC);

            if (!$bcData) {
                throw new Exception("Barcode '{$gtBarcode}' tidak ditemukan dalam master barcode sistem!");
            }

            $pdo->beginTransaction();

            // Insert ke production_activities
            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, machine_code, item_code, size_info, prod_date, shift, status, qty, unit, notes, operator_id, operator_nik, action_type, details)
                VALUES (?, 4, 10, ?, ?, ?, ?, ?, 'NORMAL', 1, 'Pcs', ?, ?, ?, 'CURING_CURE_COMPLETE', ?)
            ");
            $detailStr = "Curing Vulkanisasi di Press {$pressMachine} [Cavity {$moldCavity}] | Barcode: {$gtBarcode} | Waktu: {$curingTime}m | Suhu: {$curingTemp}°C";
            $stmtAct->execute([
                $gtBarcode, $pressMachine, 'CURED_TIRE', $bcData['size_name'] ?? 'Tire',
                $todayDate, $shiftInfo['shift'], "Press {$pressMachine} Cavity {$moldCavity}",
                $operatorId, $operatorNik, $detailStr
            ]);

            // Insert ke production_scada_logs
            $stmtScada = $pdo->prepare("
                INSERT INTO production_scada_logs (barcode, dept_id, section_id, machine_id, operator_nik, item_code, qty, quality_status, shift)
                VALUES (?, 'CUR', 'curing_tire', ?, ?, 'CURED_TIRE', 1, 'PASS', ?)
            ");
            $stmtScada->execute([$gtBarcode, $pressMachine, $operatorNik, $shiftLabel]);

            // Update status barcode
            $stmtUp = $pdo->prepare("UPDATE barcodes SET status = 'FI', current_dept = 'CUR' WHERE barcode = ?");
            $stmtUp->execute([$gtBarcode]);

            $pdo->commit();

            $flashMessage = "SUKSES: Ban {$gtBarcode} berhasil dimasak di Press {$pressMachine} ({$moldCavity})!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 2. INPUT REPAIR TIRE
        elseif ($action === 'lite_save_repair_tire') {
            $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
            $defectType = trim($_POST['defect_type'] ?? 'Under cure');
            $actionTaken = trim($_POST['action_taken'] ?? 'Trimming & buffing');
            $repairResult = trim($_POST['repair_result'] ?? 'REPAIRED_OK');

            if (empty($tireBarcode)) throw new Exception("Barcode Ban Cacat wajib diisi!");

            $pdo->beginTransaction();

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, prod_date, shift, status, qty, unit, notes, operator_id, operator_nik, action_type, details)
                VALUES (?, 4, 15, ?, ?, ?, 1, 'Pcs', ?, ?, ?, 'TIRE_REPAIR_PROCESSED', ?)
            ");
            $detailStr = "Repair Ban {$tireBarcode} | Defect: {$defectType} | Tindakan: {$actionTaken} | Hasil: {$repairResult}";
            $stmtAct->execute([
                $tireBarcode, $todayDate, $shiftInfo['shift'], 
                ($repairResult === 'REPAIRED_OK' ? 'NORMAL' : 'SCRAP'),
                "Defect: {$defectType} - {$actionTaken}",
                $operatorId, $operatorNik, $detailStr
            ]);

            // Update barcode status
            $newBcStatus = ($repairResult === 'REPAIRED_OK') ? 'FI' : 'AFKIR';
            $stmtUp = $pdo->prepare("UPDATE barcodes SET status = ? WHERE barcode = ?");
            $stmtUp->execute([$newBcStatus, $tireBarcode]);

            $pdo->commit();

            $flashMessage = "SUKSES: Repair ban {$tireBarcode} berhasil dicatat (Hasil: {$repairResult})!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 3. INPUT BLADDER SETUP & ASSEMBLY
        elseif ($action === 'lite_save_bladder_install') {
            $pressMachine = trim($_POST['press_machine'] ?? 'CUR-PRS-01');
            $bladderBarcode = strtoupper(trim($_POST['bladder_barcode'] ?? ''));
            $clampStatus = trim($_POST['clamp_status'] ?? 'OK');

            if (empty($bladderBarcode)) throw new Exception("Barcode Bladder wajib discan!");

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, machine_code, prod_date, shift, status, qty, unit, operator_id, operator_nik, action_type, details)
                VALUES (?, 4, 13, ?, ?, ?, 'NORMAL', 1, 'Pcs', ?, ?, 'BLADDER_INSTALLED', ?)
            ");
            $detailStr = "Pemasangan Bladder {$bladderBarcode} pada Press {$pressMachine} | Clamp: {$clampStatus}";
            $stmtAct->execute([
                $bladderBarcode, $pressMachine, $todayDate, $shiftInfo['shift'],
                $operatorId, $operatorNik, $detailStr
            ]);

            $flashMessage = "SUKSES: Bladder {$bladderBarcode} terpasang di Press {$pressMachine}!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 4. INPUT SUPPLY LORY & DELIVERY
        elseif ($action === 'lite_save_supply_lory') {
            $lorryBarcode = strtoupper(trim($_POST['lorry_barcode'] ?? ''));
            $curingLine = trim($_POST['curing_line_target'] ?? 'LINE-A');
            $qtyTire = (int)($_POST['qty_tire'] ?? 20);

            if (empty($lorryBarcode)) throw new Exception("Barcode Lori wajib discan!");

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, prod_date, shift, status, qty, unit, operator_id, operator_nik, action_type, details)
                VALUES (?, 4, 11, ?, ?, 'NORMAL', ?, 'Pcs', ?, ?, 'SUPPLY_LORY_ARRIVED', ?)
            ");
            $detailStr = "Supply Lori {$lorryBarcode} ke {$curingLine} ({$qtyTire} pcs)";
            $stmtAct->execute([
                $lorryBarcode, $todayDate, $shiftInfo['shift'], $qtyTire,
                $operatorId, $operatorNik, $detailStr
            ]);

            $flashMessage = "SUKSES: Pasokan Lori {$lorryBarcode} ({$qtyTire} ban) menuju {$curingLine} dicatat!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 5. INPUT BUILDING GREEN TIRE
        elseif ($action === 'lite_save_building') {
            $machineCode = trim($_POST['machine_code'] ?? 'BLD-M01');
            $specCode = trim($_POST['spec_code'] ?? '185/65 R15');
            $gtBarcode = strtoupper(trim($_POST['gt_barcode'] ?? ''));
            $weightKg = (float)($_POST['weight_kg'] ?? 8.2);

            if (empty($gtBarcode)) throw new Exception("Barcode Green Tire wajib diisi!");

            $pdo->beginTransaction();

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, machine_code, item_code, size_info, prod_date, shift, status, qty, unit, operator_id, operator_nik, action_type, details)
                VALUES (?, 3, ?, ?, 'GREEN_TIRE', ?, ?, ?, 'NORMAL', 1, 'Pcs', ?, ?, 'BUILDING_COMPLETE', ?)
            ");
            $detailStr = "Green Tire {$gtBarcode} selesai dirakit di Mesin {$machineCode} ({$specCode}, {$weightKg} Kg)";
            $stmtAct->execute([
                $gtBarcode, $activeSection['id'], $machineCode, $specCode,
                $todayDate, $shiftInfo['shift'], $operatorId, $operatorNik, $detailStr
            ]);

            // Insert SCADA log
            $stmtScada = $pdo->prepare("
                INSERT INTO production_scada_logs (barcode, dept_id, section_id, machine_id, operator_nik, item_code, qty, quality_status, shift)
                VALUES (?, 'BLD', ?, ?, ?, 'GREEN_TIRE', 1, 'PASS', ?)
            ");
            $stmtScada->execute([$gtBarcode, $activeSectionCode, $machineCode, $operatorNik, $shiftLabel]);

            // Update atau daftarkan barcode
            $stmtUp = $pdo->prepare("
                INSERT INTO barcodes (barcode, product_code, product_name, size_name, status, current_dept, created_at)
                VALUES (?, 'GT-BLD', 'Green Tire Assembly', ?, 'BUILDING', 'BLD', NOW())
                ON CONFLICT (barcode) DO UPDATE SET status = 'BUILDING', current_dept = 'BLD'
            ");
            $stmtUp->execute([$gtBarcode, $specCode]);

            $pdo->commit();

            $flashMessage = "SUKSES: Green Tire {$gtBarcode} ({$specCode}) berhasil disimpan!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 6. INPUT MATERIAL (MIXING / CALENDERING / DELIVERY)
        elseif ($action === 'lite_save_material') {
            $batchBarcode = strtoupper(trim($_POST['batch_barcode'] ?? ''));
            $compoundCode = trim($_POST['compound_code'] ?? 'CMP-TREAD-01');
            $weightKg = (float)($_POST['weight_kg'] ?? 50.0);
            $machineCode = trim($_POST['machine_code'] ?? 'MIX-01');

            if (empty($batchBarcode)) throw new Exception("Barcode Batch / Lot Material wajib diisi!");

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, machine_code, item_code, prod_date, shift, status, qty, unit, operator_id, operator_nik, action_type, details)
                VALUES (?, 2, ?, ?, ?, ?, ?, 'NORMAL', ?, 'Kg', ?, ?, 'MATERIAL_PROCESSED', ?)
            ");
            $detailStr = "Proses Material {$compoundCode} [Batch: {$batchBarcode}] di Mesin {$machineCode} ({$weightKg} Kg)";
            $stmtAct->execute([
                $batchBarcode, $activeSection['id'], $machineCode, $compoundCode,
                $todayDate, $shiftInfo['shift'], $weightKg, $operatorId, $operatorNik, $detailStr
            ]);

            $flashMessage = "SUKSES: Material {$compoundCode} ({$weightKg} Kg) batch {$batchBarcode} berhasil dicatat!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 7. INPUT GUDANG BAHAN (PENERIMAAN / PENGELUARAN)
        elseif ($action === 'lite_save_warehouse') {
            $itemBarcode = strtoupper(trim($_POST['item_barcode'] ?? ''));
            $materialName = trim($_POST['material_name'] ?? 'Karet Alam');
            $rackLocation = trim($_POST['rack_location'] ?? 'RAK-A01');
            $qty = (float)($_POST['qty'] ?? 100);
            $txType = trim($_POST['tx_type'] ?? 'MASUK');

            if (empty($itemBarcode)) throw new Exception("Barcode Bahan / Pallet wajib diisi!");

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, material_info, prod_date, shift, status, qty, unit, notes, operator_id, operator_nik, action_type, details)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'Kg', ?, ?, ?, 'WH_TRANSACTION', ?)
            ");
            $detailStr = "Transaksi Gudang {$txType} [{$itemBarcode}] - {$materialName} ({$qty} Kg) di Rak {$rackLocation}";
            $stmtAct->execute([
                $itemBarcode, $activeSection['id'], $materialName,
                $todayDate, $shiftInfo['shift'], $txType, $qty, "Lokasi: {$rackLocation}",
                $operatorId, $operatorNik, $detailStr
            ]);

            $flashMessage = "SUKSES: Transaksi {$txType} bahan {$materialName} ({$qty} Kg) disimpan!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }

        // 8. INPUT FINAL INSPECTION (QC / CUTTING / FINISH DELIVERY)
        elseif ($action === 'lite_save_fi') {
            $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
            $qcStatus = trim($_POST['qc_status'] ?? 'PASS');
            $defectNote = trim($_POST['defect_note'] ?? '');

            if (empty($tireBarcode)) throw new Exception("Barcode Ban Jadi wajib discan!");

            $pdo->beginTransaction();

            $newStatus = ($qcStatus === 'PASS') ? 'SELESAI' : (($qcStatus === 'REPAIR') ? 'REPAIR' : 'AFKIR');

            $stmtAct = $pdo->prepare("
                INSERT INTO production_activities 
                (barcode, dept_id, section_id, prod_date, shift, status, qty, unit, notes, operator_id, operator_nik, action_type, details)
                VALUES (?, 5, ?, ?, ?, ?, 1, 'Pcs', ?, ?, ?, 'FINAL_INSPECTION_COMPLETE', ?)
            ");
            $detailStr = "Final Inspection Ban {$tireBarcode} | Hasil: {$qcStatus}" . ($defectNote ? " | Catatan: {$defectNote}" : "");
            $stmtAct->execute([
                $tireBarcode, $activeSection['id'], $todayDate, $shiftInfo['shift'],
                $newStatus, $defectNote, $operatorId, $operatorNik, $detailStr
            ]);

            // Update barcode
            $stmtUp = $pdo->prepare("UPDATE barcodes SET status = ?, current_dept = 'FI' WHERE barcode = ?");
            $stmtUp->execute([$newStatus, $tireBarcode]);

            $pdo->commit();

            $flashMessage = "SUKSES: Inspeksi ban {$tireBarcode} dicatat dengan status: [{$qcStatus}]!";
            $flashType = 'success';
            $soundTrigger = 'success';
        }
        else {
            throw new Exception("Jenis aksi penginputan tidak dikenali.");
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $flashMessage = "GAGAL: " . $e->getMessage();
        $flashType = 'danger';
        $soundTrigger = 'error';
    }
}

// ==============================================================================
// AMBIL STATISTIK & 10 INPUT TERAKHIR OPERATOR PADA SHIFT INI
// ==============================================================================
$myScansToday = [];
$totalShiftScans = 0;
$todayDate = date('Y-m-d');
$currentShiftNum = $shiftInfo['shift'];

try {
    // Total scans shift ini oleh operator
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM production_activities 
        WHERE (operator_nik = ? OR operator_id = ?) 
          AND prod_date = ? 
          AND shift = ?
    ");
    $stmtCount->execute([$user['nik'] ?? '', $user['id'] ?? 0, $todayDate, $currentShiftNum]);
    $totalShiftScans = (int)$stmtCount->fetchColumn();

    // 10 riwayat transaksi scan terakhir
    $stmtHistory = $pdo->prepare("
        SELECT pa.*, s.name as section_name, d.code as dept_code 
        FROM production_activities pa
        LEFT JOIN sections s ON pa.section_id = s.id
        LEFT JOIN departments d ON pa.dept_id = d.id
        WHERE (pa.operator_nik = ? OR pa.operator_id = ?)
        ORDER BY pa.id DESC 
        LIMIT 10
    ");
    $stmtHistory->execute([$user['nik'] ?? '', $user['id'] ?? 0]);
    $myScansToday = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

// Tentukan URL halaman Pro yang sesuai
$proUrlMap = [
    'WH' => 'dept_warehouse.php',
    'MAT' => 'dept_material.php',
    'BLD' => 'dept_building.php',
    'CUR' => 'dept_curing.php',
    'FI' => 'dept_final_inspection.php'
];
$proTargetUrl = $proUrlMap[$activeSection['dept_code'] ?? ''] ?? 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mode Lite Operator - <?= htmlspecialchars($activeSection['name'] ?? 'Input') ?> | GT Barcode</title>
    <!-- Favicon / Tab Logo -->
    <link rel="icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Logo_Aplikasi.png">
    <link rel="apple-touch-icon" href="assets/img/Logo_Aplikasi.png">

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-body: #0a0e17;
            --bg-panel: #111827;
            --bg-card: rgba(17, 24, 39, 0.95);
            --bg-input: #1f2937;
            --border-panel: #374151;
            --border-highlight: #3b82f6;
            --color-accent: #3b82f6;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --text-main: #f9fafb;
            --text-sub: #9ca3af;
            --radius-md: 12px;
            --radius-lg: 18px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-bottom: 30px;
        }

        /* Topbar Lite Mode */
        .lite-header {
            background: #111827;
            border-bottom: 2px solid #1f2937;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }

        .lite-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .lite-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
        }

        .brand-box h1 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-lite {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .brand-sub {
            font-size: 11px;
            color: var(--text-sub);
            font-weight: 500;
        }

        .lite-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-pro-switch {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid #334155;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-pro-switch:hover {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-logout:hover {
            background: #ef4444;
            color: #fff;
        }

        /* Container */
        .lite-container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Operator Status Card */
        .operator-card {
            background: var(--bg-card);
            border: 1px solid var(--border-panel);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .operator-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .op-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255,255,255,0.15);
            object-fit: cover;
        }

        .op-details h2 {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .op-nik {
            font-family: 'JetBrains Mono', monospace;
            background: rgba(255,255,255,0.1);
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            color: #93c5fd;
        }

        .op-station {
            font-size: 13px;
            color: #60a5fa;
            margin-top: 3px;
            font-weight: 600;
        }

        .operator-badges {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pill-shift {
            background: #1e3a8a;
            color: #bfdbfe;
            border: 1px solid #3b82f6;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pill-counter {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Section Switcher (for Admins / Multi-Station) */
        .station-switcher-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: var(--radius-md);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .station-switcher-box select {
            background: #0f172a;
            color: #f1f5f9;
            border: 1px solid #475569;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            flex: 1;
            outline: none;
            cursor: pointer;
        }

        /* Banner Notifikasi Suara & Visual */
        .alert-banner {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .alert-banner.success {
            background: rgba(16, 185, 129, 0.2);
            border: 2px solid #10b981;
            color: #6ee7b7;
        }

        .alert-banner.danger {
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid #ef4444;
            color: #fca5a5;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card Form Penginputan Utama */
        .input-panel {
            background: var(--bg-panel);
            border: 2px solid #1f2937;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #1f2937;
            padding-bottom: 14px;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-sub);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input-lg {
            background: var(--bg-input);
            border: 2px solid #374151;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            padding: 14px 16px;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input-lg:focus {
            border-color: #3b82f6;
            background: #172033;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25);
        }

        .form-input-scanner {
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 2px;
            font-size: 22px;
            color: #60a5fa;
            border-color: #3b82f6;
            background: #0f172a;
        }

        /* Quick selector buttons */
        .btn-group-touch {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-touch-opt {
            background: #1f2937;
            border: 1px solid #374151;
            color: #d1d5db;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-touch-opt:hover {
            background: #374151;
            color: #fff;
        }

        .btn-touch-opt.active {
            background: #2563eb;
            color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(37,99,235,0.5);
        }

        /* Submit Button */
        .btn-submit-scan {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            padding: 16px 24px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-submit-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.6);
        }

        .btn-submit-scan:active {
            transform: translateY(1px);
        }

        /* Shift History Table */
        .history-panel {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: var(--radius-lg);
            padding: 20px;
        }

        .history-title {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .history-table th {
            text-align: left;
            padding: 10px 12px;
            background: #1f2937;
            color: var(--text-sub);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            border-bottom: 1px solid #374151;
        }

        .history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #1f2937;
            color: #d1d5db;
        }

        .history-table tr:hover {
            background: rgba(255,255,255,0.02);
        }

        .history-barcode {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #60a5fa;
        }

        @media (max-width: 640px) {
            .lite-header {
                padding: 10px 14px;
            }
            .brand-box h1 {
                font-size: 15px;
            }
            .operator-card {
                padding: 14px;
            }
            .input-panel {
                padding: 16px;
            }
            .form-input-lg {
                font-size: 16px;
                padding: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?= @filemtime(__DIR__ . '/assets/css/mobile.css') ?>">
</head>
<?php 
$isMobileApp = gtbs_is_mobile_app(); 
$mobileNav = gtbs_mobile_nav($currentUser ?? Auth::user()); 
$activeTabKey = "barcode"; 
?>
<body class="<?= $isMobileApp ? 'm-app m-lite' : '' ?>">

    <!-- Header Lite Mode -->
    <header class="lite-header">
        <div class="lite-header-left">
            <img src="assets/img/Logo_Aplikasi.png" alt="GT Logo" class="lite-logo">
            <div class="brand-box">
                <h1>GT BARCODE <span class="badge-lite">LITE</span></h1>
                <div class="brand-sub">Workstation Input Lapangan - PT Gajah Tunggal Tbk</div>
            </div>
        </div>

        <div class="lite-header-right">
            <!-- Tombol Beralih ke Mode Pro (ERP Lengkap) -->
            <a href="<?= htmlspecialchars($proTargetUrl) ?>" class="btn-pro-switch" title="Kembali ke halaman ERP Lengkap">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span>Mode Pro</span>
            </a>

            <!-- Tombol Logout -->
            <a href="logout.php" class="btn-logout" title="Keluar Akun">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
            </a>
        </div>
    </header>

    <div class="lite-container">

        <!-- SYNC DESK WIDGET (LOCAL-FIRST OFFLINE STORAGE) -->
        <div class="m-syncbar" style="margin: 0 0 14px 0;">
            <div class="m-syncbar-info">
                <div class="m-syncbar-title">
                    <span><?= gtbs_m_icon('sync', 16) ?></span>
                    <span>Sync Desk &bull; Local-First Storage</span>
                    <span class="m-pending-badge" id="mSyncPendingBadge">0</span>
                </div>
                <div class="m-syncbar-sub">Hasil scan tersimpan di PDT jika offline, kirim ke database server.</div>
            </div>
            <button type="button" class="m-sync-btn" id="mBtnSyncDesk" onclick="triggerMobileSync()">
                <?= gtbs_m_icon('sync', 14) ?>
                <span>Sync Desk</span>
            </button>
        </div>


        <!-- Banner Feedback Notifikasi -->
        <?php if (!empty($flashMessage)): ?>
            <div class="alert-banner <?= $flashType === 'success' ? 'success' : 'danger' ?>">
                <?php if ($flashType === 'success'): ?>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php else: ?>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                <?php endif; ?>
                <div><?= htmlspecialchars($flashMessage) ?></div>
            </div>
        <?php endif; ?>

        <!-- Kartu Identitas Operator & Shift -->
        <div class="operator-card">
            <div class="operator-info">
                <?php if (!empty($userPhoto) && file_exists(__DIR__ . '/' . $userPhoto)): ?>
                    <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Avatar" class="op-avatar">
                <?php else: ?>
                    <div class="op-avatar"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
                <?php endif; ?>

                <div class="op-details">
                    <h2>
                        <?= htmlspecialchars($user['full_name'] ?? 'Operator') ?>
                        <span class="op-nik"><?= htmlspecialchars($user['nik'] ?? '-') ?></span>
                    </h2>
                    <div class="op-station">
                        Stasiun: <?= htmlspecialchars($activeSection['name'] ?? 'Produksi') ?> 
                        (<?= htmlspecialchars($activeSection['code'] ?? '') ?>) &bull; <?= htmlspecialchars($activeSection['dept_name'] ?? '') ?>
                    </div>
                </div>
            </div>

            <div class="operator-badges">
                <div class="pill-shift">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?= htmlspecialchars($shiftInfo['label']) ?>
                </div>

                <div class="pill-counter">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Shift Ini: <strong><?= $totalShiftScans ?></strong>
                </div>
            </div>
        </div>

        <!-- Pemilih Bagian / Stasiun Kerja (KHUSUS Super Admin) -->
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <div class="station-switcher-box" style="background: rgba(30, 41, 59, 0.95); border: 2px solid #3b82f6; border-radius: var(--radius-md); padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; box-shadow: 0 4px 20px rgba(59, 130, 246, 0.2);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">🏢</span>
                    <label style="font-size: 13px; font-weight: 800; color: #93c5fd; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.5px;">
                        Pilih Stasiun Kerja (Super Admin):
                    </label>
                </div>
                <select id="stationSelectDropdown" style="background: #0f172a; color: #f1f5f9; border: 1.5px solid #3b82f6; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 700; flex: 1; min-width: 280px; outline: none; cursor: pointer;" onchange="window.location.href='operator_lite.php?section=' + this.value">
                    <?php 
                    $currentDeptGroup = '';
                    foreach ($sectionsQuery as $sec): 
                        if ($sec['dept_name'] !== $currentDeptGroup) {
                            if ($currentDeptGroup !== '') echo '</optgroup>';
                            $currentDeptGroup = $sec['dept_name'];
                            echo '<optgroup label="DEPT: ' . htmlspecialchars($currentDeptGroup) . '">';
                        }
                    ?>
                        <option value="<?= htmlspecialchars($sec['code']) ?>" <?= $sec['code'] === $activeSectionCode ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($sec['dept_code']) ?>] <?= htmlspecialchars($sec['name']) ?> (<?= htmlspecialchars($sec['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if ($currentDeptGroup !== '') echo '</optgroup>'; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- FORM PENGINPUTAN LITE SESUAI SEKSI AKTIF -->
        <!-- ========================================================================= -->
        <div class="input-panel">
            <div class="panel-heading">
                <div class="panel-title">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #3b82f6;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    <span><?= htmlspecialchars($activeSection['name'] ?? 'Form Penginputan') ?></span>
                </div>
                <span style="font-size: 11px; background: rgba(59,130,246,0.15); color: #93c5fd; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                    KODE: <?= htmlspecialchars($activeSectionCode) ?>
                </span>
            </div>

            <!-- FORM 1: CURING TIRE (CUR-PRE) -->
            <?php if ($activeSectionCode === 'CUR-PRE'): ?>
                <form method="POST" action="operator_lite.php?section=CUR-PRE" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_curing_press">

                    <!-- Scan Mesin Press & Cavity (Input by Text via Barcode Scanner) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">1. Barcode Mesin Press *</label>
                            <input type="text" name="press_machine" id="pressMachineInput" list="dl_press_machines" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Mesin (Contoh: CUR-PRS-01)..." value="CUR-PRS-01" required autocomplete="off">
                            <datalist id="dl_press_machines">
                                <?php for ($i = 1; $i <= 8; $i++): $p = sprintf("CUR-PRS-%02d", $i); ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endfor; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">2. Barcode Mold Cavity *</label>
                            <input type="text" name="mold_cavity" id="moldCavityInput" list="dl_mold_cavities" class="form-input-lg form-input-scanner" placeholder="Scan Cavity (L / R)..." value="L" required autocomplete="off">
                            <datalist id="dl_mold_cavities">
                                <option value="L">Mold L (Kiri)</option>
                                <option value="R">Mold R (Kanan)</option>
                            </datalist>
                        </div>
                    </div>

                    <!-- Scan Barcode Green Tire -->
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label" style="color: #60a5fa;">3. Scan Barcode Green Tire *</label>
                        <input type="text" name="gt_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Green Tire (Contoh: GT-PCR-10001)..." required autofocus autocomplete="off">
                    </div>

                    <!-- Parameter Curing (Input Text Numeric) -->
                    <div class="form-row" style="margin-top: 10px;">
                        <div class="form-group">
                            <label class="form-label">4. Waktu Curing (Menit)</label>
                            <input type="text" inputmode="numeric" name="curing_time_min" class="form-input-lg" placeholder="14" value="14" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label">5. Suhu Curing (°C)</label>
                            <input type="text" inputmode="decimal" name="curing_temp_c" class="form-input-lg" placeholder="168.5" value="168.5" required autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>SIMPAN & PROSES VULKANISASI</span>
                    </button>
                </form>

            <!-- FORM 2: REPAIR TIRE (CUR-REP) -->
            <?php elseif ($activeSectionCode === 'CUR-REP'): ?>
                <form method="POST" action="operator_lite.php?section=CUR-REP" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_repair_tire">
                    
                    <div class="form-group">
                        <label class="form-label" style="color: #60a5fa;">1. Scan Barcode Ban Rusak / Cacat *</label>
                        <input type="text" name="tire_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Ban..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-row" style="margin-top: 10px;">
                        <div class="form-group">
                            <label class="form-label">2. Jenis Defect (Scan / Pilih)</label>
                            <input type="text" name="defect_type" list="dl_defect_type" class="form-input-lg" placeholder="Scan / Pilih Jenis Defect..." value="Under cure" required autocomplete="off">
                            <datalist id="dl_defect_type">
                                <option value="Under cure">Under cure (Kurang Matang)</option>
                                <option value="Blister">Blister (Gelembung Udara)</option>
                                <option value="Tread crack">Tread crack (Retak Telapak)</option>
                                <option value="Sidewall scratch">Sidewall scratch (Gores Dinding)</option>
                                <option value="Bead bend">Bead bend (Bead Bengkok)</option>
                                <option value="Foreign material">Foreign material (Material Asing)</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">3. Hasil Perbaikan</label>
                            <input type="text" name="repair_result" list="dl_repair_result" class="form-input-lg" placeholder="Scan / Pilih Hasil (REPAIRED_OK / SCRAP)..." value="REPAIRED_OK" required autocomplete="off">
                            <datalist id="dl_repair_result">
                                <option value="REPAIRED_OK">REPAIRED_OK (Lolos Inspeksi Ulang)</option>
                                <option value="SCRAP">SCRAP (Tidak Bisa Diperbaiki)</option>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label">4. Tindakan Perbaikan</label>
                        <input type="text" name="action_taken" class="form-input-lg" placeholder="Scan / Ketik tindakan: Trimming, buffing, hot patch..." value="Trimming & buffing" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan" style="background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4);">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span>SIMPAN & PROSES REPAIR</span>
                    </button>
                </form>

            <!-- FORM 3: BLADDER SETUP (CUR-BSU) -->
            <?php elseif ($activeSectionCode === 'CUR-BSU'): ?>
                <form method="POST" action="operator_lite.php?section=CUR-BSU" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_bladder_install">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">1. Barcode Mesin Press *</label>
                            <input type="text" name="press_machine" list="dl_bsu_press" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Mesin (CUR-PRS-01)..." value="CUR-PRS-01" required autocomplete="off">
                            <datalist id="dl_bsu_press">
                                <?php for ($i = 1; $i <= 8; $i++): $p = sprintf("CUR-PRS-%02d", $i); ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endfor; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">2. Status Clamp</label>
                            <input type="text" name="clamp_status" list="dl_bsu_clamp" class="form-input-lg" placeholder="Scan / Pilih Status Clamp (OK / ADJUSTED)..." value="OK" required autocomplete="off">
                            <datalist id="dl_bsu_clamp">
                                <option value="OK">OK - Kencang & Presisi</option>
                                <option value="ADJUSTED">ADJUSTED - Disetel Ulang</option>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label" style="color: #60a5fa;">3. Scan Barcode Bladder *</label>
                        <input type="text" name="bladder_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Bladder..." required autofocus autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES PEMASANGAN BLADDER</span>
                    </button>
                </form>

            <!-- FORM 4: SUPPLY LORY & DELIVERY (CUR-SUP-LGT / CUR-SUP-DEL) -->
            <?php elseif (in_array($activeSectionCode, ['CUR-SUP-LGT', 'CUR-SUP-DEL'])): ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_supply_lory">
                    
                    <div class="form-group">
                        <label class="form-label" style="color: #60a5fa;">1. Scan Barcode Lori / Kereta GT *</label>
                        <input type="text" name="lorry_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Lori..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-row" style="margin-top: 10px;">
                        <div class="form-group">
                            <label class="form-label">2. Target Line Curing</label>
                            <input type="text" name="curing_line_target" list="dl_cur_lines" class="form-input-lg" placeholder="Scan / Target Line (LINE-A / LINE-B / LINE-C)..." value="LINE-A" required autocomplete="off">
                            <datalist id="dl_cur_lines">
                                <option value="LINE-A">LINE-A (PCR Ring 14-15)</option>
                                <option value="LINE-B">LINE-B (PCR Ring 16-17)</option>
                                <option value="LINE-C">LINE-C (SUV / LTR)</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">3. Jumlah Ban (Pcs)</label>
                            <input type="text" inputmode="numeric" name="qty_tire" class="form-input-lg" placeholder="20" value="20" required autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES PASOKAN LORI</span>
                    </button>
                </form>

            <!-- FORM 5: DEPT BUILDING (BLD-PCR / BLD-SUV / BLD-LTR / BLD-TBR) -->
            <?php elseif (strpos($activeSectionCode, 'BLD-') === 0): ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_building">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">1. Barcode Mesin TBM *</label>
                            <input type="text" name="machine_code" list="dl_bld_machines" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Mesin (Contoh: BLD-M01)..." value="BLD-M01" required autocomplete="off">
                            <datalist id="dl_bld_machines">
                                <option value="BLD-M01">TBM Machine #01 (PCR Line 1)</option>
                                <option value="BLD-M02">TBM Machine #02 (PCR Line 2)</option>
                                <option value="BLD-M03">TBM Machine #03 (SUV / LTR)</option>
                                <option value="BLD-M04">TBM Machine #04 (TBR Line)</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">2. Spesifikasi / Ukuran Ban</label>
                            <input type="text" name="spec_code" list="dl_bld_specs" class="form-input-lg" placeholder="Scan Barcode Ukuran..." value="<?= $activeSectionCode === 'BLD-PCR' ? '185/65 R15 ECO' : '225/65 R17 HP' ?>" required autocomplete="off">
                            <datalist id="dl_bld_specs">
                                <option value="185/65 R15 ECO">185/65 R15 Champiro Eco</option>
                                <option value="195/65 R15 GTX">195/65 R15 Champiro GTX Pro</option>
                                <option value="205/55 R16 HP">205/55 R16 Champiro HPY</option>
                                <option value="225/65 R17 HP">225/65 R17 Savero SUV</option>
                                <option value="11R22.5 TBR">11R22.5 Giti TBR Radial</option>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label" style="color: #60a5fa;">3. Scan Barcode Green Tire *</label>
                        <input type="text" name="gt_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode GT (Contoh: GT-PCR-10001)..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label">4. Berat Green Tire (Kg)</label>
                        <input type="text" inputmode="decimal" name="weight_kg" class="form-input-lg" placeholder="8.5" value="8.5" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES GREEN TIRE</span>
                    </button>
                </form>

            <!-- FORM 6: DEPT MATERIAL (MAT-MIX / MAT-CAL / MAT-DEL) -->
            <?php elseif (strpos($activeSectionCode, 'MAT-') === 0): ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_material">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">1. Barcode Mesin Material *</label>
                            <input type="text" name="machine_code" list="dl_mat_machines" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Mesin (Contoh: MIX-01)..." value="MIX-01" required autocomplete="off">
                            <datalist id="dl_mat_machines">
                                <option value="MIX-01">Banbury Mixer #01</option>
                                <option value="MIX-02">Banbury Mixer #02</option>
                                <option value="CAL-01">Calender Line #01 (Steel Cord)</option>
                                <option value="EXT-01">Extruder Tread #01</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">2. Kode Compound / Material</label>
                            <input type="text" name="compound_code" list="dl_mat_compounds" class="form-input-lg" placeholder="Scan Kode Compound..." value="CMP-TREAD-01" required autocomplete="off">
                            <datalist id="dl_mat_compounds">
                                <option value="CMP-TREAD-01">Compound Tread Passenger (PCR)</option>
                                <option value="CMP-SIDE-02">Compound Sidewall Flex</option>
                                <option value="CMP-BASE-01">Compound Under Tread / Base</option>
                                <option value="CMP-TBR-01">Compound High Grip Truck & Bus</option>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label" style="color: #60a5fa;">3. Scan Barcode Batch / Lot *</label>
                        <input type="text" name="batch_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Batch Material (Contoh: BATCH-CMP-001)..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label">4. Berat (Kg)</label>
                        <input type="text" inputmode="decimal" name="weight_kg" class="form-input-lg" placeholder="50.0" value="50.0" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES BATCH MATERIAL</span>
                    </button>
                </form>

            <!-- FORM 7: GUDANG BAHAN (WH-RCV / WH-SND) -->
            <?php elseif (strpos($activeSectionCode, 'WH-') === 0): ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_warehouse">
                    <input type="hidden" name="tx_type" value="<?= $activeSectionCode === 'WH-RCV' ? 'MASUK' : 'KELUAR' ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">1. Nama Material</label>
                            <input type="text" name="material_name" class="form-input-lg" placeholder="Scan / Nama Material..." value="Karet Alam (Natural Rubber)" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color: #60a5fa;">2. Barcode Lokasi Rak *</label>
                            <input type="text" name="rack_location" list="dl_wh_racks" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Rak (Contoh: RAK-A01)..." value="RAK-A01" required autocomplete="off">
                            <datalist id="dl_wh_racks">
                                <option value="RAK-A01">Rak Natural Rubber (Zona A)</option>
                                <option value="RAK-A02">Rak Synthetic Polymer (Zona A)</option>
                                <option value="RAK-B01">Rak Carbon Black & Silica (Zona B)</option>
                                <option value="RAK-C01">Rak Steel Cord & Wire Bead (Zona C)</option>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label" style="color: #60a5fa;">3. Scan Barcode Pallet / Kemasan *</label>
                        <input type="text" name="item_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Pallet..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label class="form-label">4. Qty (Kg / Pcs)</label>
                        <input type="text" inputmode="decimal" name="qty" class="form-input-lg" placeholder="100.0" value="100.0" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES TRANSAKSI GUDANG</span>
                    </button>
                </form>

            <!-- FORM 8: FINAL INSPECTION (FI-INS / FI-CUT / FI-DEL) -->
            <?php elseif (strpos($activeSectionCode, 'FI-') === 0): ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_fi">
                    
                    <div class="form-group">
                        <label class="form-label" style="color: #60a5fa;">1. Scan Barcode Ban Selesai Masak *</label>
                        <input type="text" name="tire_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode Ban..." required autofocus autocomplete="off">
                    </div>

                    <div class="form-row" style="margin-top: 10px;">
                        <div class="form-group">
                            <label class="form-label">2. Status Kelayakan QC</label>
                            <input type="text" name="qc_status" list="dl_fi_qc_status" class="form-input-lg" placeholder="Scan / Pilih Status (PASS / REPAIR / REJECT)..." value="PASS" required autocomplete="off">
                            <datalist id="dl_fi_qc_status">
                                <option value="PASS">PASS (Lolos Sempurna - Ban Jadi)</option>
                                <option value="REPAIR">REPAIR (Kirim ke Stasiun Perbaikan)</option>
                                <option value="REJECT">REJECT (Afkir / Scrap)</option>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label class="form-label">3. Catatan Defect (Opsional)</label>
                            <input type="text" name="defect_note" class="form-input-lg" placeholder="Scan / Ketik Catatan Defect..." autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES HASIL INSPEKSI</span>
                    </button>
                </form>

            <!-- DEFAULT FALLBACK FORM -->
            <?php else: ?>
                <form method="POST" action="operator_lite.php?section=<?= htmlspecialchars($activeSectionCode) ?>" id="liteScanForm" class="scanner-workflow-form">
                    <input type="hidden" name="action" value="lite_save_curing_press">
                    <div class="form-group">
                        <label class="form-label" style="color: #60a5fa;">Scan Barcode Transaksi *</label>
                        <input type="text" name="gt_barcode" id="barcodeScannerInput" class="form-input-lg form-input-scanner" placeholder="Scan Barcode..." required autofocus autocomplete="off">
                    </div>
                    <button type="submit" class="btn-submit-scan" id="btnSubmitScan">
                        <span>SIMPAN & PROSES TRANSAKSI</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ========================================================================= -->
        <!-- TABEL 10 TRANSAKSI TERAKHIR SHIFT INI -->
        <!-- ========================================================================= -->
        <div class="history-panel">
            <div class="history-title">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #60a5fa;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>10 Transaksi Input Terakhir Saya (Shift Ini)</span>
                </div>
                <span style="font-size: 11px; color: var(--text-sub);">Diperbarui Realtime</span>
            </div>

            <?php if (empty($myScansToday)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-sub); font-size: 13px;">
                    Belum ada riwayat transaksi pada shift ini. Silakan scan barcode di atas.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Barcode</th>
                                <th>Aksi / Stasiun</th>
                                <th>Keterangan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myScansToday as $scan): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-family: 'JetBrains Mono', monospace;">
                                        <?= date('H:i:s', strtotime($scan['created_at'])) ?>
                                    </td>
                                    <td class="history-barcode">
                                        <?= htmlspecialchars($scan['barcode'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: #fff;">
                                            <?= htmlspecialchars($scan['action_type'] ?: $scan['section_name'] ?: 'PRODUKSI') ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($scan['details'] ?: $scan['notes'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3);">
                                            <?= htmlspecialchars($scan['status'] ?: 'OK') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

        <!-- Web Audio API Sound Generator & Keyboard Scanner Navigation -->
    <script>
        // Audio Chime Synthesizer (Bekerja 100% offline tanpa file mp3 eksternal)
        function playChime(type) {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);

                if (type === 'success') {
                    // Nada tinggi gembira dua ketukan (D5 -> A5)
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                    osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12);
                    gain.gain.setValueAtTime(0.25, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.35);
                } else {
                    // Nada peringatan rendah (sawtooth)
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(140.00, ctx.currentTime);
                    gain.gain.setValueAtTime(0.35, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.45);
                }
            } catch(e) {}
        }

        function focusScanner() {
            const scannerInput = document.getElementById('barcodeScannerInput') || document.querySelector('form.scanner-workflow-form input[type="text"]:not([readonly])');
            if (scannerInput) {
                scannerInput.focus();
                if (scannerInput.select) scannerInput.select();
            }
        }

        // Inisialisasi Alur Scanner (Enter Auto-Next & Submit on Last)
        document.addEventListener('DOMContentLoaded', function() {
            focusScanner();

            // Putar audio jika ada trigger dari POST
            <?php if ($soundTrigger): ?>
                playChime('<?= $soundTrigger ?>');
            <?php endif; ?>

            // Setup navigasi scanner antar text input dalam form
            document.querySelectorAll('form.scanner-workflow-form').forEach(form => {
                const textInputs = Array.from(form.querySelectorAll('input[type="text"]:not([type="hidden"]):not([readonly])'));
                
                textInputs.forEach((input, idx) => {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const nextInput = textInputs[idx + 1];
                            if (nextInput) {
                                nextInput.focus();
                                if (nextInput.select) nextInput.select();
                            } else {
                                // Input terakhir: Eksekusi submit tombol Simpan Proses
                                const submitBtn = form.querySelector('button[type="submit"]');
                                if (submitBtn) {
                                    submitBtn.focus();
                                    submitBtn.click();
                                } else {
                                    form.submit();
                                }
                            }
                        }
                    });
                });
            });

            // Kembalikan fokus ke scanner saat klik di luar input
            // PENTING: Dikecualikan untuk SELECT, OPTION, OPTGROUP, MODAL, dll agar dropdown stasiun tidak tertutup otomatis!
            window.addEventListener('click', function(e) {
                // Abaikan jika target klik adalah atau berada di dalam elemen interaktif non-scanner
                if (
                    e.target.closest('.station-switcher-box') ||
                    e.target.closest('select') ||
                    ['INPUT', 'SELECT', 'OPTION', 'OPTGROUP', 'BUTTON', 'A', 'TEXTAREA', 'LABEL'].includes(e.target.tagName)
                ) {
                    return;
                }
                focusScanner();
            });
        });
    </script>

<?php if ($isMobileApp): 
    $roleBarcodeDest = $mobileNav['barcode']['url'] ?? 'operator_lite.php';
?>
<!-- ==================== SCANNER HUD MODAL ==================== -->
<div class="m-scan-hud" id="mScanHud">
    <div class="m-scan-hud-icon">
        <?= gtbs_m_icon('scan', 24) ?>
    </div>
    <div class="m-scan-hud-text">
        <b>Scanner Laser PDT Aktif</b>
        <small>Arahkan sensor ke barcode ban atau masukkan kode manual.</small>
    </div>
    <button type="button" onclick="document.getElementById('mScanHud').classList.remove('show')">Tutup</button>
</div>

<!-- ==================== BOTTOM NAVIGATION BAR (5 TAB) ==================== -->
<nav class="m-bottom-nav">
    <a href="<?= htmlspecialchars($mobileNav['home']['url']) ?>" class="m-nav-item">
        <?= gtbs_m_icon('home', 21) ?>
        <span>Home</span>
    </a>

    <a href="<?= htmlspecialchars($mobileNav['barcode']['url']) ?>" class="m-nav-item active">
        <?= gtbs_m_icon('barcode', 21) ?>
        <span class="m-nav-badge" id="mBarcodeNavBadge">0</span>
        <span>Barcode</span>
    </a>

    <button type="button" class="m-nav-item m-scan-action armed" onclick="handleCenterScanClick('<?= htmlspecialchars($roleBarcodeDest) ?>')">
        <div class="m-scan-btn">
            <?= gtbs_m_icon('scan', 26) ?>
        </div>
        <span>Scan</span>
    </button>

    <a href="<?= htmlspecialchars($mobileNav['message']['url']) ?>" class="m-nav-item">
        <?= gtbs_m_icon('message', 21) ?>
        <span>Message</span>
    </a>

    <a href="<?= htmlspecialchars($mobileNav['akun']['url']) ?>" class="m-nav-item">
        <?= gtbs_m_icon('user', 21) ?>
        <span>Akun</span>
    </a>
</nav>

<script src="assets/js/mobile.js?v=<?= @filemtime(__DIR__ . '/assets/js/mobile.js') ?>"></script>
<?php endif; ?>

</body>
</html>