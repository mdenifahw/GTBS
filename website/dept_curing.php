<?php
/**
 * ==============================================================================
 * DEPARTEMEN CURING (VULKANISASI & BLADDER) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengelola proses vulkanisasi (pemasakan) ban mentah dengan suhu dan tekanan tinggi di dalam cetakan (mold).
 * - Mendukung 8 sub-seksi operasional lengkap di lini Curing:
 *   1. Curing Tire: Proses pencetakan ban pada mesin press vulkanisasi (Mold Cavity Kiri/Kanan, waktu curing, suhu steam).
 *   2. Supply Lory: Pengangkutan ban mentah dari departemen building menggunakan lori khusus.
 *   3. Supply Delivery: Penyerahan ban matang yang telah dimasak menuju area inspeksi akhir.
 *   4. Bladder Installer: Pemasangan dan penyesuaian kantong pemanas karet (bladder) ke dalam mesin press.
 *   5. Bladder Assembly: Perakitan komponen perlengkapan bladder sebelum dipasang.
 *   6. Bladder Injection: Proses pencetakan/injeksi kantong bladder baru.
 *   7. Compound Reg: Pendaftaran dan alokasi kompon karet khusus bladder.
 *   8. Repair Tire: Penanganan dan perbaikan minor ban yang mengalami cacat curing (under-cure, blip, dsb).
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Standar Suhu & Waktu Curing:
 *    Cari input default `curing_time_min` (default: 14 menit) dan `curing_temp_c` (default: 168.5 °C).
 * 2. Menambah Nomor Mesin Press Baru:
 *    Cari dropdown `<select name="press_machine">` untuk menambah unit mesin press vulkanisasi (misal: PRESS-41 s/d PRESS-60).
 * 3. Menghubungkan Sensor IoT Suhu & Tekanan:
 *    Data pembacaan suhu riil dapat diambil langsung dari tabel `production_scada_logs`.
 * ==============================================================================
 */
$pageTitle = 'Dept Curing (Vulkanisasi & Bladder)';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
// (Dijalankan SEBELUM layout dicetak agar respon 403 valid & tanpa warning 'headers already sent')
Auth::requireDeptAccess('CUR');

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

// ==============================================================================
// PEMBATASAN AKSES TAB BAGIAN: OPERATOR HANYA BOLEH MENGAKSES BAGIANNYA SENDIRI
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userSect = $currentUser['section_code'] ?? '';
$hasFullCuringAccess = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head', 'admin_dept']);

$curingSectionTabs = [
    'CUR-PRE'     => ['curing_tire'],
    'CUR-SUP-LGT' => ['supply_lory'],
    'CUR-SUP-DEL' => ['supply_delivery'],
    'CUR-BSU'     => ['bladder_installer', 'bladder_assembly'],
    'CUR-BLD'     => ['bladder_injection', 'compound_reg'],
    'CUR-REP'     => ['repair_tire'],
];

if ($hasFullCuringAccess || empty($userSect) || !isset($curingSectionTabs[$userSect])) {
    $allowedTabs = ['curing_tire', 'supply_lory', 'supply_delivery', 'bladder_installer', 'bladder_assembly', 'bladder_injection', 'compound_reg', 'repair_tire'];
} else {
    // Operator/Leader: HANYA tab sesuai bagian tugasnya
    $allowedTabs = $curingSectionTabs[$userSect];
}

// Validasi activeTab: jika tab yang diminta tidak ada dalam daftar yang diizinkan, paksa ke tab pertama miliknya
$requestedTab = $_GET['tab'] ?? $allowedTabs[0];
if (!in_array($requestedTab, $allowedTabs)) {
    $activeTab = $allowedTabs[0];
} else {
    $activeTab = $requestedTab;
}

// Proteksi Keamanan POST: Pastikan pengguna hanya bisa memproses aksi pada tab yang diizinkan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actionToTabMap = [
        'save_curing_press'          => 'curing_tire',
        'save_supply_lory'           => 'supply_lory',
        'save_gt_delivery_transfer'  => 'supply_delivery',
        'save_bladder_install'       => 'bladder_installer',
        'save_bladder_assembly'      => 'bladder_assembly',
        'save_bladder_injection'     => 'bladder_injection',
        'save_compound_reg'          => 'compound_reg',
        'save_repair_tire'           => 'repair_tire'
    ];
    $action = $_POST['action'];
    if (isset($actionToTabMap[$action]) && !in_array($actionToTabMap[$action], $allowedTabs)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang untuk melakukan penginputan pada bagian ini!";
        $messageType = 'danger';
        // Hapus action agar handler tidak memproses query
        unset($_POST['action']);
    }
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN TRANSAKSI MESIN PRESS VULKANISASI (CURING TIRE)
// ==============================================================================
// 1. Curing Tire (Press Vulkanisasi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_curing_press') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $pressMachine = trim($_POST['press_machine'] ?? '');
    $moldCavity = trim($_POST['mold_cavity'] ?? 'L');
    $gtBarcode = strtoupper(trim($_POST['gt_barcode'] ?? ''));
    $curingTimeMin = (int)($_POST['curing_time_min'] ?? 14);
    $curingTempC = (float)($_POST['curing_temp_c'] ?? 168.5);

    // Validate barcode registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$gtBarcode]);
    if (!$bcCheck->fetch()) {
        $message = "Gagal: Barcode Green Tire '{$gtBarcode}' tidak terdaftar!";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

                        $opId = $currentUserId;
            if (!$opId && !empty($nik)) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
                $uStmt->execute([$nik]);
                $opId = $uStmt->fetchColumn() ?: 1;
            }

            $act = $pdo->prepare("
                INSERT INTO production_activities (
                    barcode, dept_id, section_id, prod_date, shift, status, 
                    qty, unit, operator_id, operator_nik, action_type, details
                ) VALUES (
                    ?, 4, 10, CURRENT_DATE, ?, 'NORMAL', 
                    1, 'Pcs', ?, ?, 'CURING_CURE_COMPLETE', ?
                )
            ");
            $act->execute([
                $gtBarcode,
                $currentShiftInt,
                $opId,
                $nik, 
                "Curing Vulkanisasi di Press {$pressMachine} [Cavity {$moldCavity}] | Barcode: {$gtBarcode} | Waktu: {$curingTimeMin}m | Suhu: {$curingTempC}°C"
            ]);

            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'CUR', 'curing_tire', ?, ?, 
                    ?, 1, 'PASS', CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$gtBarcode, $pressMachine, $nik, 'CURED_TIRE', 'Shift ' . $currentShiftInt]);

            // Update barcode status
            $up = $pdo->prepare("UPDATE barcodes SET status = 'CURED' WHERE barcode = ?");
            $up->execute([$gtBarcode]);

            $pdo->commit();
            $message = "Sukses! Ban mentah {$gtBarcode} selesai dimasak pada Press {$pressMachine} ({$moldCavity}).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses curing press: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// 2. Supply GT Lory (Input scanned is Lori ID barcode)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_supply_lory') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $lorryBarcode = strtoupper(trim($_POST['lorry_barcode'] ?? ''));
    $curingLineTarget = trim($_POST['curing_line_target'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    try {
                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 11, CURRENT_DATE, ?, 'NORMAL', 
                1, 'LORY', ?, ?, 'SUPPLY_GT_LORY_ARRIVED', ?
            )
        ");
        $act->execute([
            $lorryBarcode,
            $currentShiftInt,
            $opId,
            $nik, 
            "Lori Barcode: {$lorryBarcode} tiba dan dialokasikan ke Curing Line: {$curingLineTarget}. {$notes}"
        ]);

        $message = "Sukses! Kedatangan Lori {$lorryBarcode} di area Curing Line {$curingLineTarget} berhasil dicatat.";
    } catch (Exception $e) {
        $message = "Gagal mencatat supply lori: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 3. Supply GT Delivery (Loading GT from Building into Lori)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gt_delivery_transfer') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $lorryId = trim($_POST['lorry_id'] ?? '');
    $qtyTire = (int)($_POST['qty_tire'] ?? 1);
    $tireSize = trim($_POST['tire_size'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO curing_gt_delivery_transfer (lorry_id, qty_tire, tire_size, operator_nik, notes, shift)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lorryId, $qtyTire, $tireSize, $nik, $notes, 'Shift ' . $shiftInfo['shift']]);

                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 12, CURRENT_DATE, ?, 'NORMAL', 
                ?, 'Pcs', ?, ?, 'GT_DELIVERY_LOADED', ?
            )
        ");
        $act->execute([
            $lorryId,
            $currentShiftInt,
            $qtyTire,
            $opId,
            $nik, 
            "Loading {$qtyTire} ban ({$tireSize}) dari Building ke Lori {$lorryId}. Catatan: {$notes}"
        ]);

        $pdo->commit();
        $message = "Sukses! Pemuatan {$qtyTire} pcs ban ({$tireSize}) ke Lori {$lorryId} berhasil disimpan.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal memproses loading delivery: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 4. Bladder Installer (Setup Bladder on Press)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bladder_install') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $pressMachine = trim($_POST['press_machine'] ?? '');
    $bladderBarcode = strtoupper(trim($_POST['bladder_barcode'] ?? ''));
    $clampStatus = trim($_POST['clamp_status'] ?? 'OK');

    try {
                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 13, CURRENT_DATE, ?, 'NORMAL', 
                1, 'BLADDER', ?, ?, 'BLADDER_INSTALLED', ?
            )
        ");
        $act->execute([
            $bladderBarcode,
            $currentShiftInt,
            $opId,
            $nik, 
            "Pemasangan Bladder {$bladderBarcode} pada Press {$pressMachine} | Clamp Status: {$clampStatus}"
        ]);

        $message = "Sukses! Bladder {$bladderBarcode} terpasang pada Press {$pressMachine}.";
    } catch (Exception $e) {
        $message = "Gagal memasang bladder: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 5. Bladder Assembly (Upper/Lower Rim Size + Bladder Barcode)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bladder_assembly') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $upperRim = trim($_POST['upper_rim_size'] ?? '');
    $lowerRim = trim($_POST['lower_rim_size'] ?? '');
    $bladderBarcode = strtoupper(trim($_POST['bladder_barcode'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO curing_bladder_assembly (upper_rim_size, lower_rim_size, bladder_barcode, operator_nik, notes, shift)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$upperRim, $lowerRim, $bladderBarcode, $nik, $notes, 'Shift ' . $shiftInfo['shift']]);

                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 13, CURRENT_DATE, ?, 'NORMAL', 
                1, 'SET', ?, ?, 'BLADDER_ASSEMBLED', ?
            )
        ");
        $act->execute([
            $bladderBarcode,
            $currentShiftInt,
            $opId,
            $nik, 
            "Perakitan Bladder Assembly | Upper Rim: {$upperRim} | Lower Rim: {$lowerRim} | Bladder Barcode: {$bladderBarcode}"
        ]);

        $pdo->commit();
        $message = "Sukses! Bladder Assembly ({$bladderBarcode}) dengan Upper {$upperRim} dan Lower {$lowerRim} berhasil dicatat.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal merakit bladder assembly: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 6. Bladder Injection (No auto barcode, scan registered bladder barcode)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bladder_injection') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $machineId = trim($_POST['machine_id'] ?? '');
    $bladderSize = trim($_POST['bladder_size'] ?? '');
    $bladderBarcode = strtoupper(trim($_POST['bladder_barcode'] ?? ''));

    // Check pre-registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$bladderBarcode]);
    if (!$bcCheck->fetch()) {
        $message = "Gagal: Barcode '{$bladderBarcode}' belum terdaftar di sistem!";
        $messageType = 'danger';
    } else {
        try {
                        $opId = $currentUserId;
            if (!$opId && !empty($nik)) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
                $uStmt->execute([$nik]);
                $opId = $uStmt->fetchColumn() ?: 1;
            }

            $act = $pdo->prepare("
                INSERT INTO production_activities (
                    barcode, dept_id, section_id, prod_date, shift, status, 
                    qty, unit, operator_id, operator_nik, action_type, details
                ) VALUES (
                    ?, 4, 14, CURRENT_DATE, ?, 'NORMAL', 
                    1, 'BLADDER', ?, ?, 'BLADDER_INJECTION_PRODUCED', ?
                )
            ");
            $act->execute([
                $bladderBarcode,
                $currentShiftInt,
                $opId,
                $nik, 
                "Injection Bladder Mesin {$machineId} | Ukuran: {$bladderSize} | Barcode: {$bladderBarcode}"
            ]);

            $message = "Sukses! Bladder injection {$bladderSize} ({$bladderBarcode}) berhasil dicatat.";
        } catch (Exception $e) {
            $message = "Gagal mencatat bladder injection: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// 7. Pendaftaran Compound (Mixer machine, Qty, Target bladder press DBL01-DBL04)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_compound_reg') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $mixerMachine = trim($_POST['mixer_machine'] ?? '');
    $compoundCode = trim($_POST['compound_code'] ?? '');
    $qtyKg = (float)($_POST['qty_kg'] ?? 0);
    $targetMachine = trim($_POST['target_machine'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO curing_compound_registry (mixer_machine, compound_code, qty_kg, target_machine, operator_nik, notes, shift)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$mixerMachine, $compoundCode, $qtyKg, $targetMachine, $nik, $notes, 'Shift ' . $shiftInfo['shift']]);

                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 14, CURRENT_DATE, ?, 'NORMAL', 
                ?, 'KG', ?, ?, 'COMPOUND_REGISTERED', ?
            )
        ");
        $act->execute([
            $compoundCode,
            $currentShiftInt,
            $qtyKg,
            $opId,
            $nik, 
            "Pendaftaran Compound {$compoundCode} ({$qtyKg} Kg) dari Mixer {$mixerMachine} untuk Target Press {$targetMachine}"
        ]);

        $pdo->commit();
        $message = "Sukses! Pendaftaran compound {$compoundCode} untuk press {$targetMachine} berhasil disimpan.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal mendaftarkan compound: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 8. Repair Tire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_repair_tire') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
    $defectType = trim($_POST['defect_type'] ?? '');
    $actionTaken = trim($_POST['action_taken'] ?? '');
    $repairResult = trim($_POST['repair_result'] ?? 'REPAIRED_OK');

    try {
                $opId = $currentUserId;
        if (!$opId && !empty($nik)) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
            $uStmt->execute([$nik]);
            $opId = $uStmt->fetchColumn() ?: 1;
        }

        $act = $pdo->prepare("
            INSERT INTO production_activities (
                barcode, dept_id, section_id, prod_date, shift, status, 
                qty, unit, operator_id, operator_nik, action_type, details
            ) VALUES (
                ?, 4, 15, CURRENT_DATE, ?, 'NORMAL', 
                1, 'Pcs', ?, ?, 'TIRE_REPAIR_PROCESSED', ?
            )
        ");
        $act->execute([
            $tireBarcode,
            $currentShiftInt,
            $opId,
            $nik, 
            "Repair Ban Barcode: {$tireBarcode} | Cacat: {$defectType} | Tindakan: {$actionTaken} | Hasil: {$repairResult}"
        ]);

        $message = "Sukses! Tindakan perbaikan ban {$tireBarcode} berhasil dicatat (Hasil: {$repairResult}).";
    } catch (Exception $e) {
        $message = "Gagal memproses repair: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!-- Info Bagian Kerja Operator jika memiliki akses terbatas -->
<?php if (!$hasFullCuringAccess && count($allowedTabs) < 8): ?>
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between;">
        <span>Stasiun Kerja Aktif: <strong><?= htmlspecialchars($currentUser['section_name'] ?? $userSect) ?> (<?= htmlspecialchars($userSect) ?>)</strong></span>
        <span style="font-size: 11px; background: rgba(59, 130, 246, 0.2); padding: 3px 8px; border-radius: 4px; color: #bfdbfe;">Akses Khusus Bagian Anda</span>
    </div>
<?php endif; ?>

<!-- Tab Navigation across all Curing Sub-sections (Dibatasi per Hak Akses Bagian) -->
<div class="tabs">
    <?php if (in_array('curing_tire', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'curing_tire' ? 'active' : '' ?>" onclick="switchTab('curing_tire')">
        🔥 1. Curing Tire (Press)
    </button>
    <?php endif; ?>

    <?php if (in_array('supply_lory', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'supply_lory' ? 'active' : '' ?>" onclick="switchTab('supply_lory')">
        🛒 2. Supply GT Lory (Lori ID)
    </button>
    <?php endif; ?>

    <?php if (in_array('supply_delivery', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'supply_delivery' ? 'active' : '' ?>" onclick="switchTab('supply_delivery')">
        📦 3. Supply GT Delivery
    </button>
    <?php endif; ?>

    <?php if (in_array('bladder_installer', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'bladder_installer' ? 'active' : '' ?>" onclick="switchTab('bladder_installer')">
        🔧 4. Bladder Installer
    </button>
    <?php endif; ?>

    <?php if (in_array('bladder_assembly', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'bladder_assembly' ? 'active' : '' ?>" onclick="switchTab('bladder_assembly')">
        ⚙️ 5. Bladder Assembly
    </button>
    <?php endif; ?>

    <?php if (in_array('bladder_injection', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'bladder_injection' ? 'active' : '' ?>" onclick="switchTab('bladder_injection')">
        🧪 6. Bladder Injection
    </button>
    <?php endif; ?>

    <?php if (in_array('compound_reg', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'compound_reg' ? 'active' : '' ?>" onclick="switchTab('compound_reg')">
        📝 7. Pendaftaran Compound
    </button>
    <?php endif; ?>

    <?php if (in_array('repair_tire', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'repair_tire' ? 'active' : '' ?>" onclick="switchTab('repair_tire')">
        🔨 8. Repair Tire
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tab 1: Curing Tire (Press Vulkanisasi) -->
<?php if ($activeTab === 'curing_tire'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Pelaporan Curing Tire (Vulkanisasi Press)</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=curing_tire">
            <input type="hidden" name="action" value="save_curing_press">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Curing</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Nomor Mesin Press</label>
                    <input type="text" name="press_machine" list="dl_cur_press_machine_6c94" class="form-input" placeholder="Scan / Input Press Machine..." value="CUR-P01" required autocomplete="off">
                    <datalist id="dl_cur_press_machine_6c94">
                        <option value="CUR-P01">Curing Press Line A-01</option>
                        <option value="CUR-P02">Curing Press Line A-02</option>
                        <option value="CUR-P03">Curing Press Line B-01</option>
                        <option value="CUR-P04">Curing Press Line B-02</option>
                        <option value="CUR-P05">Curing Press Line C-01</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Cavity Mold (Sisi)</label>
                    <input type="text" name="mold_cavity" list="dl_cur_mold_cavity_09c5" class="form-input" placeholder="Scan / Input Mold Cavity..." value="L" required autocomplete="off">
                    <datalist id="dl_cur_mold_cavity_09c5">
                        <option value="L">Sisi Kiri (Left Mold Cavity)</option>
                        <option value="R">Sisi Kanan (Right Mold Cavity)</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">4. Scan Barcode Green Tire</label>
                <input type="text" name="gt_barcode" class="form-input" placeholder="Scan barcode green tire sebelum masuk mold..." required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">5. Waktu Masak / Cure Time (Menit)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" name="curing_time_min" class="form-input" value="14" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">6. Temperatur Steam (°C)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" step="0.1" name="curing_temp_c" class="form-input" value="168.5" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Simpan & Proses Vulkanisasi ke SCADA
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 2: Supply GT Lory (Input scanned is Lori ID barcode) -->
<?php if ($activeTab === 'supply_lory'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <div class="card-title">Supply Green Tire Lory</div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Input yang dipindai adalah <strong>Barcode ID Lori</strong> pembawa green tire.
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=supply_lory">
            <input type="hidden" name="action" value="save_supply_lory">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Petugas Lori</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Scan Barcode ID Lori</label>
                    <input type="text" name="lorry_barcode" class="form-input" placeholder="Scan stiker barcode Lori (cth: LORY-BLD-01)..." required>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Curing Line Target Alokasi</label>
                    <input type="text" name="curing_line_target" list="dl_cur_curing_line_target_db80" class="form-input" placeholder="Scan / Input Curing Line Target..." value="Line A (Press P01 - P10)" required autocomplete="off">
                    <datalist id="dl_cur_curing_line_target_db80">
                        <option value="Line A (Press P01 - P10)">Line A (Press P01 - P10)</option>
                        <option value="Line B (Press P11 - P20)">Line B (Press P11 - P20)</option>
                        <option value="Line C (Press P21 - P30)">Line C (Press P21 - P30)</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">4. Catatan / Kondisi Lori</label>
                <input type="text" name="notes" class="form-input" placeholder="Kondisi cover, pelindung debu, dll...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Pasokan Lori
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 3: Supply GT Delivery (Loading GT from Building into Lori) -->
<?php if ($activeTab === 'supply_delivery'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <div class="card-title">Supply GT Delivery (Pemuatan Ban ke Lori)</div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Mencatat proses pemuatan ban mentah (Green Tire) dari Building ke dalam Lori.
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=supply_delivery">
            <input type="hidden" name="action" value="save_gt_delivery_transfer">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Petugas Delivery</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Nomor / ID Lori</label>
                    <input type="text" name="lorry_id" class="form-input" placeholder="Contoh: LORY-05" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Jumlah Green Tire (Qty Ban)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" name="qty_tire" class="form-input" min="1" value="8" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Ukuran Ban (Tire Size)</label>
                    <input type="text" name="tire_size" class="form-input" placeholder="Contoh: 185/65 R15 Champiro" required>
                </div>
                <div class="form-group">
                    <label class="form-label">5. Catatan / Posisi Rak Lori</label>
                    <input type="text" name="notes" class="form-input" placeholder="Tingkat 1 & 2 penuh...">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Simpan & Proses Pemuatan Lori
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 4: Bladder Installer (Setup) -->
<?php if ($activeTab === 'bladder_installer'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Bladder Installer (Pemasangan Bladder pada Press)</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=bladder_installer">
            <input type="hidden" name="action" value="save_bladder_install">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Teknisi Installer</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Nomor Mesin Press</label>
                    <input type="text" name="press_machine" list="dl_cur_press_machine_3961" class="form-input" placeholder="Scan / Input Press Machine..." value="CUR-P01" required autocomplete="off">
                    <datalist id="dl_cur_press_machine_3961">
                        <option value="CUR-P01">Curing Press Line A-01</option>
                        <option value="CUR-P02">Curing Press Line A-02</option>
                        <option value="CUR-P03">Curing Press Line B-01</option>
                        <option value="CUR-P04">Curing Press Line B-02</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Scan Barcode Bladder</label>
                    <input type="text" name="bladder_barcode" class="form-input" placeholder="PRD-BLD-..." required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">4. Status Klem & Seal (Leak Test)</label>
                <input type="text" name="clamp_status" list="dl_cur_clamp_status_3f03" class="form-input" placeholder="Scan / Input Clamp Status..." value="OK" required autocomplete="off">
                    <datalist id="dl_cur_clamp_status_3f03">
                        <option value="OK">OK - Kencang &amp; Bebas Bocor</option>
                        <option value="RE-TORQUE">Perlu Pengetatan Ulang (Re-Torque)</option>
                    </datalist>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Pemasangan Bladder
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 5: Bladder Assembly (New Tab Requirement) -->
<?php if ($activeTab === 'bladder_assembly'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <div class="card-title">Bladder Assembly</div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Perakitan Bladder dengan Upper Rim dan Lower Rim.
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=bladder_assembly">
            <input type="hidden" name="action" value="save_bladder_assembly">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Assembly</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Upper Rim Size (Ukuran Rim Atas)</label>
                    <input type="text" name="upper_rim_size" list="dl_cur_upper_rim_size_0940" class="form-input" placeholder="Scan / Input Upper Rim Size..." value="15 Inch Standard" required autocomplete="off">
                    <datalist id="dl_cur_upper_rim_size_0940">
                        <option value="15 Inch Standard">15 Inch Standard</option>
                        <option value="16 Inch Standard">16 Inch Standard</option>
                        <option value="17 Inch Wide">17 Inch Wide</option>
                        <option value="18 Inch High Performance">18 Inch High Performance</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Lower Rim Size (Ukuran Rim Bawah)</label>
                    <input type="text" name="lower_rim_size" list="dl_cur_lower_rim_size_2ed3" class="form-input" placeholder="Scan / Input Lower Rim Size..." value="15 Inch Standard" required autocomplete="off">
                    <datalist id="dl_cur_lower_rim_size_2ed3">
                        <option value="15 Inch Standard">15 Inch Standard</option>
                        <option value="16 Inch Standard">16 Inch Standard</option>
                        <option value="17 Inch Wide">17 Inch Wide</option>
                        <option value="18 Inch High Performance">18 Inch High Performance</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">4. Scan Barcode Bladder Hasil Injection</label>
                <input type="text" name="bladder_barcode" class="form-input" placeholder="Scan barcode stiker bladder dari injection..." required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">5. Catatan Perakitan</label>
                <input type="text" name="notes" class="form-input" placeholder="Hasil inspeksi O-Ring, lubrikasi silikon...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Perakitan Bladder
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 6: Bladder Injection (Auto barcode removed) -->
<?php if ($activeTab === 'bladder_injection'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <div class="card-title">Bladder Injection</div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Pencetakan bladder baru. Auto barcode telah dihilangkan (gunakan barcode yang telah diregistrasi).
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=bladder_injection">
            <input type="hidden" name="action" value="save_bladder_injection">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Injection</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Mesin Injection Bladder</label>
                    <input type="text" name="machine_id" list="dl_cur_machine_id_c29b" class="form-input" placeholder="Scan / Input Machine Id..." value="INJ-BLD-01" required autocomplete="off">
                    <datalist id="dl_cur_machine_id_c29b">
                        <option value="INJ-BLD-01">Injection Press INJ-01</option>
                        <option value="INJ-BLD-02">Injection Press INJ-02</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Ukuran Bladder</label>
                    <input type="text" name="bladder_size" list="dl_cur_bladder_size_1465" class="form-input" placeholder="Scan / Input Bladder Size..." value="Bladder 15 Inch" required autocomplete="off">
                    <datalist id="dl_cur_bladder_size_1465">
                        <option value="Bladder 15 Inch">Bladder 15 Inch</option>
                        <option value="Bladder 16 Inch">Bladder 16 Inch</option>
                        <option value="Bladder 17 Inch">Bladder 17 Inch</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">4. Scan Barcode Bladder Terdaftar</label>
                <input type="text" name="bladder_barcode" class="form-input" placeholder="PRD-BLD-..." required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Injeksi Bladder
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 7: Pendaftaran Compound (New Section Requirement) -->
<?php if ($activeTab === 'compound_reg'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <div class="card-title">Pendaftaran Compound (Alokasi ke Press Bladder DBL01 - DBL04)</div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Mencatat kompon khusus untuk cetak bladder dengan target mesin DBL01 s/d DBL04.
                </div>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=compound_reg">
            <input type="hidden" name="action" value="save_compound_reg">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Karyawan Pelapor</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Mesin Mixer Asal</label>
                    <input type="text" name="mixer_machine" list="dl_cur_mixer_machine_e81d" class="form-input" placeholder="Scan / Input Mixer Machine..." value="MIX-BB01" required autocomplete="off">
                    <datalist id="dl_cur_mixer_machine_e81d">
                        <option value="MIX-BB01">Mixer Banbury BB-01</option>
                        <option value="MIX-BB02">Mixer Banbury BB-02</option>
                        <option value="MIX-LAB">Mixer Khusus Bladder Compound</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Kode Kompon Bladder</label>
                    <input type="text" name="compound_code" class="form-input" value="CMP-BLD-BUTYL-80" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Kuantitas Berat (Kg)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" step="0.1" name="qty_kg" class="form-input" value="150.0" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Target Mesin Bladder Press (DBL01 - DBL04)</label>
                    <input type="text" name="target_machine" list="dl_cur_target_machine_f77f" class="form-input" placeholder="Scan / Input Target Machine..." value="DBL01" required autocomplete="off">
                    <datalist id="dl_cur_target_machine_f77f">
                        <option value="DBL01">DBL01 - Bladder Press 01</option>
                        <option value="DBL02">DBL02 - Bladder Press 02</option>
                        <option value="DBL03">DBL03 - Bladder Press 03</option>
                        <option value="DBL04">DBL04 - Bladder Press 04</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">6. Catatan Tambahan</label>
                <input type="text" name="notes" class="form-input" placeholder="Viskositas mooney, batch date...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Alokasi Compound
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 8: Repair Tire -->
<?php if ($activeTab === 'repair_tire'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Repair Tire (Perbaikan Cacat Pasca Curing)</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_curing.php?tab=repair_tire">
            <input type="hidden" name="action" value="save_repair_tire">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Teknisi Repair</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Scan Barcode Ban Cacat</label>
                    <input type="text" name="tire_barcode" class="form-input" placeholder="Scan barcode ban..." required>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Jenis Cacat (Defect Type)</label>
                    <input type="text" name="defect_type" list="dl_cur_defect_type_dfbb" class="form-input" placeholder="Scan / Input Defect Type..." value="Flash / Sirip Karet Berlebih" required autocomplete="off">
                    <datalist id="dl_cur_defect_type_dfbb">
                        <option value="Flash / Sirip Karet Berlebih">Flash / Sirip Karet Berlebih</option>
                        <option value="Air Trap / Bintik Udara">Air Trap / Bintik Udara</option>
                        <option value="Under Cure Permukaan">Under Cure Permukaan</option>
                        <option value="Bladder Mark Goresan">Bladder Mark Goresan</option>
                    </datalist>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Tindakan Perbaikan</label>
                    <input type="text" name="action_taken" class="form-input" placeholder="Trimming, buffing, hot patch..." required>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Hasil Akhir</label>
                    <input type="text" name="repair_result" list="dl_cur_repair_result_eefa" class="form-input" placeholder="Scan / Input Repair Result..." value="REPAIRED_OK" required autocomplete="off">
                    <datalist id="dl_cur_repair_result_eefa">
                        <option value="REPAIRED_OK">Lolos Perbaikan (Siap ke Final Inspection)</option>
                        <option value="SCRAP_REJECT">Tidak Bisa Diperbaiki (Afkir / Scrap)</option>
                    </datalist>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Simpan & Proses Hasil Perbaikan Ban
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT NAVIGASI TAB CURING & VALIDASI TEMPERATUR -->
<!-- ===================================================================== -->
<script>
function switchTab(t) {
    window.location.href = 'dept_curing.php?tab=' + t;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
