<?php
/**
 * ==============================================================================
 * DEPARTEMEN MATERIAL PREPARATION (MIXING, CALENDER, DELIVERY) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengelola proses pengolahan bahan kompon karet mentah dan pelapisan kawat/benang penguat.
 * - Fitur Utama:
 *   1. Tab Mixing: Pencatatan formula kompon karet, nomor batch Banbury mixer, berat (Kg), dan mesin pencampur.
 *   2. Tab Calendery: Pelapisan lembaran kompon pada benang tekstil atau kawat baja (Steel Cord / Fabric Cord).
 *   3. Tab Delivery: Penyerahan material setengah jadi siap rakit menuju departemen Building.
 *   4. Tab Riwayat: Audit trail seluruh batch kompon yang telah diproses.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Tipe Kompon Baru:
 *    Cari elemen `<select name="compound_code">` untuk menambahkan jenis kompon karet (misal: Tread, Sidewall, Innerliner).
 * 2. Mengubah Batas Toleransi Berat:
 *    Pada validasi berat ``, Anda dapat menambahkan pengecekan batas atas dan batas bawah (misal: toleransi ±0.5 Kg).
 * 3. Menghubungkan ke Timbangan Digital (RS-232 / Modbus):
 *    Dapat diintegrasikan script Web Serial API di Tab Mixing agar berat terisi otomatis dari timbangan.
 * ==============================================================================
 */
$pageTitle = 'Dept Material (Mixing, Calender, Delivery)';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
// (Dijalankan SEBELUM layout dicetak agar respon 403 valid & tanpa warning 'headers already sent')
Auth::requireDeptAccess('MAT');

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

// ==============================================================================
// PEMBATASAN AKSES TAB BAGIAN: OPERATOR HANYA BOLEH MENGAKSES BAGIANNYA SENDIRI
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userSect = $currentUser['section_code'] ?? '';
$hasFullMatAccess = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head', 'admin_dept']);

$matSectionTabs = [
    'MAT-MIX' => ['mixing', 'riwayat'],
    'MAT-CAL' => ['calendery', 'riwayat'],
    'MAT-DEL' => ['delivery', 'riwayat'],
];

if ($hasFullMatAccess || empty($userSect) || !isset($matSectionTabs[$userSect])) {
    $allowedTabs = ['mixing', 'calendery', 'delivery', 'riwayat'];
} else {
    $allowedTabs = $matSectionTabs[$userSect];
}

$requestedTab = $_GET['tab'] ?? $allowedTabs[0];
if (!in_array($requestedTab, $allowedTabs)) {
    $activeTab = $allowedTabs[0];
} else {
    $activeTab = $requestedTab;
}

$currentShiftInt = (int)($shiftInfo['shift'] ?? 1);
$currentUserId = $currentUser['id'] ?? 1;

// Proteksi Keamanan POST Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actToTab = [
        'save_mixing'   => 'mixing',
        'save_calender' => 'calendery',
        'save_delivery' => 'delivery'
    ];
    $action = $_POST['action'];
    if (isset($actToTab[$action]) && !in_array($actToTab[$action], $allowedTabs)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang untuk melakukan penginputan pada bagian ini!";
        $messageType = 'danger';
        unset($_POST['action']);
    }
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN FORM TRANSAKSI MIXING BANBURY (FORMULA KOMPON)
// ==============================================================================
// 1. Handle Transaksi Mixing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mixing') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $machineId = trim($_POST['machine_id'] ?? '');
    $compoundCode = trim($_POST['compound_code'] ?? '');
    $weightKg = (float)($_POST['weight_kg'] ?? 0);
    $batchNo = trim($_POST['batch_no'] ?? '');
    $barcode = strtoupper(trim($_POST['barcode'] ?? ''));

    // Check barcode registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$barcode]);
    if (!$bcCheck->fetch()) {
        $message = "Gagal: Barcode '{$barcode}' belum terdaftar di sistem! Registrasi di menu Generate Barcode.";
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
                    ?, 2, 3, CURRENT_DATE, ?, 'NORMAL', 
                    ?, 'KG', ?, ?, 'MIXING_PRODUCED', ?
                )
            ");
            $act->execute([
                $barcode,
                $currentShiftInt,
                $weightKg,
                $opId,
                $nik,
                "Mixing di Mesin {$machineId} | Kompon: {$compoundCode} | Berat: {$weightKg} Kg | Batch: {$batchNo} | Barcode: {$barcode}"
            ]);

            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'MAT', 'mixing', ?, ?, 
                    ?, ?, 'PASS', CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$barcode, $machineId, $nik, $compoundCode, $weightKg, 'Shift ' . $currentShiftInt]);

            $pdo->commit();
            $message = "Sukses! Transaksi Mixing untuk kompon {$compoundCode} berhasil disimpan ke database dan SCADA.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal menyimpan mixing: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// 2. Handle Transaksi Calendering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_calender') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $machineId = trim($_POST['machine_id'] ?? '');
    $materialType = trim($_POST['material_type'] ?? '');
    $thicknessMm = (float)($_POST['thickness_mm'] ?? 0);
    $rollQty = (int)($_POST['roll_qty'] ?? 1);
    $barcode = strtoupper(trim($_POST['barcode'] ?? ''));

    // Check barcode registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$barcode]);
    if (!$bcCheck->fetch()) {
        $message = "Gagal: Barcode '{$barcode}' belum terdaftar di sistem!";
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
                    ?, 2, 4, CURRENT_DATE, ?, 'NORMAL', 
                    ?, 'ROLL', ?, ?, 'CALENDERING_PRODUCED', ?
                )
            ");
            $act->execute([
                $barcode,
                $currentShiftInt,
                $rollQty,
                $opId,
                $nik,
                "Calendering di Mesin {$machineId} | Tipe: {$materialType} | Tebal: {$thicknessMm} mm | Qty: {$rollQty} Roll | Barcode: {$barcode}"
            ]);

            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'MAT', 'calendery', ?, ?, 
                    ?, ?, 'PASS', CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$barcode, $machineId, $nik, $materialType, $rollQty, 'Shift ' . $currentShiftInt]);

            $pdo->commit();
            $message = "Sukses! Transaksi Calendering material {$materialType} berhasil disimpan.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal menyimpan calender: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ==============================================================================
// BAGIAN 3: PEMROSESAN FORM PENYERAHAN MATERIAL KE BUILDING (DELIVERY)
// ==============================================================================
// 3. Handle Transaksi Delivery Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_delivery') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $targetDept = trim($_POST['target_dept'] ?? 'BLD');
    $palletLorryId = trim($_POST['pallet_lorry_id'] ?? '');
    $barcode = strtoupper(trim($_POST['barcode'] ?? ''));
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
                ?, 2, 5, CURRENT_DATE, ?, 'NORMAL', 
                1, 'PALLET', ?, ?, 'MATERIAL_DELIVERY', ?
            )
        ");
        $act->execute([
            $barcode,
            $currentShiftInt,
            $opId,
            $nik,
            "Delivery Material ke {$targetDept} | Pallet/Lori: {$palletLorryId} | Barcode: {$barcode} | Catatan: {$notes}"
        ]);

        $message = "Sukses! Pengiriman material ke {$targetDept} berhasil dicatat.";
    } catch (Exception $e) {
        $message = "Gagal memproses delivery: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch recent material activities
$recentMat = $pdo->query("
    SELECT pa.*, u.full_name as operator_name 
    FROM production_activities pa
    LEFT JOIN users u ON pa.operator_nik = u.nik
    WHERE pa.dept_id = 2 
    ORDER BY pa.created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<?php if (!$hasFullMatAccess && count($allowedTabs) < 4): ?>
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between;">
        <span>Stasiun Kerja Aktif: <strong><?= htmlspecialchars($currentUser['section_name'] ?? $userSect) ?> (<?= htmlspecialchars($userSect) ?>)</strong></span>
        <span style="font-size: 11px; background: rgba(59, 130, 246, 0.2); padding: 3px 8px; border-radius: 4px; color: #bfdbfe;">Akses Khusus Bagian Anda</span>
    </div>
<?php endif; ?>

<!-- Tab Navigation (Dibatasi per Hak Akses Bagian) -->
<div class="tabs">
    <?php if (in_array('mixing', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'mixing' ? 'active' : '' ?>" onclick="switchTab('mixing')">
        ⚗️ 1. Bagian Mixing (Banbury)
    </button>
    <?php endif; ?>

    <?php if (in_array('calendery', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'calendery' ? 'active' : '' ?>" onclick="switchTab('calendery')">
        📜 2. Bagian Calendery (Sheet / Ply)
    </button>
    <?php endif; ?>

    <?php if (in_array('delivery', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'delivery' ? 'active' : '' ?>" onclick="switchTab('delivery')">
        🚚 3. Delivery Material Antar Dept
    </button>
    <?php endif; ?>

    <?php if (in_array('riwayat', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'riwayat' ? 'active' : '' ?>" onclick="switchTab('riwayat')">
        📋 4. Riwayat Transaksi & Export
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tab 1: Mixing -->
<?php if ($activeTab === 'mixing'): ?>
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                Pelaporan Produksi Mixing (Banbury Mixer)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_material.php?tab=mixing">
            <input type="hidden" name="action" value="save_mixing">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Mixing</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Mesin Mixer / Banbury</label>
                    <input type="text" name="machine_id" list="dl_mat_machine_id_45a9" class="form-input" placeholder="Scan / Input Machine Id..." value="MIX-BB01" required autocomplete="off">
                    <datalist id="dl_mat_machine_id_45a9">
                        <option value="MIX-BB01">Banbury Mixer BB-01 (Kapasitas Besar)</option>
                        <option value="MIX-BB02">Banbury Mixer BB-02</option>
                        <option value="MIX-BB03">Banbury Mixer BB-03</option>
                        <option value="MIX-BB04">Banbury Mixer BB-04</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Scan Barcode Kompon</label>
                    <input type="text" name="barcode" class="form-input" placeholder="PRD-CMP-TR-..." required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Kode Kompon (Compound Code)</label>
                    <input type="text" name="compound_code" class="form-input" placeholder="Contoh: CMP-TR-205" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Berat Kompon (Kg)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" step="0.1" name="weight_kg" class="form-input" placeholder="Berat dalam Kg" value="250.0" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">6. Nomor Batch Kompon</label>
                <input type="text" name="batch_no" class="form-input" placeholder="Contoh: BATCH-<?= date('ymd') ?>-01" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Laporan Mixing
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 2: Calendery -->
<?php if ($activeTab === 'calendery'): ?>
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                Pelaporan Produksi Calendering (Steel Cord / Textile Ply)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_material.php?tab=calendery">
            <input type="hidden" name="action" value="save_calender">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Calender</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Mesin Calender</label>
                    <input type="text" name="machine_id" list="dl_mat_machine_id_af65" class="form-input" placeholder="Scan / Input Machine Id..." value="CAL-01" required autocomplete="off">
                    <datalist id="dl_mat_machine_id_af65">
                        <option value="CAL-01">Calender Line 1 (Steel Cord 4-Roll)</option>
                        <option value="CAL-02">Calender Line 2 (Textile Fabric)</option>
                        <option value="CAL-03">Calender Line 3 (Inner Liner Sheet)</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Scan Barcode Roll</label>
                    <input type="text" name="barcode" class="form-input" placeholder="PRD-CAL-..." required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Jenis Material Calender</label>
                    <input type="text" name="material_type" list="dl_mat_material_type_0eed" class="form-input" placeholder="Scan / Input Material Type..." value="Steel Cord Belt Ply" required autocomplete="off">
                    <datalist id="dl_mat_material_type_0eed">
                        <option value="Steel Cord Belt Ply">Steel Cord Belt Ply</option>
                        <option value="Nylon Body Ply">Nylon Body Ply</option>
                        <option value="Polyester Carcass Ply">Polyester Carcass Ply</option>
                        <option value="Innerliner Rubber Sheet">Innerliner Rubber Sheet</option>
                        <option value="Sidewall Veneer Sheet">Sidewall Veneer Sheet</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Ketebalan (Thickness mm)</label>
                    <input type="text" inputmode="decimal" autocomplete="off" step="0.01" name="thickness_mm" class="form-input" placeholder="Tebal dalam mm" value="1.25" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">6. Jumlah Roll Dihasilkan</label>
                <input type="text" inputmode="decimal" autocomplete="off" name="roll_qty" class="form-input" value="1" min="1" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Calendering
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 3: Delivery -->
<?php if ($activeTab === 'delivery'): ?>
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                Pengiriman Material ke Building / Curing
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_material.php?tab=delivery">
            <input type="hidden" name="action" value="save_delivery">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Petugas Delivery</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Departemen Tujuan</label>
                    <input type="text" name="target_dept" list="dl_mat_target_dept_683b" class="form-input" placeholder="Scan / Input Target Dept..." value="BLD" required autocomplete="off">
                    <datalist id="dl_mat_target_dept_683b">
                        <option value="BLD">Dept 3 - Building (Green Tire)</option>
                        <option value="CUR">Dept 4 - Curing (Vulkanisasi)</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Nomor Pallet / Troli</label>
                    <input type="text" name="pallet_lorry_id" class="form-input" placeholder="Contoh: TRL-MAT-04" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label required">4. Scan Barcode Material</label>
                <input type="text" name="barcode" class="form-input" placeholder="Scan barcode roll / batch material..." required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">5. Catatan Pengiriman</label>
                <input type="text" name="notes" class="form-input" placeholder="Keterangan kondisi / lokasi penempatan...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Pengiriman Material
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 4: Riwayat & Export -->
<?php if ($activeTab === 'riwayat'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                Riwayat Transaksi Dept Material
            </div>
            <a href="api/export_material.php" class="btn btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV / Excel
            </a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Shift</th>
                        <th>Bagian</th>
                        <th>Operator</th>
                        <th>Jenis Aksi</th>
                        <th>Detail Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMat)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 24px;">Belum ada rekaman transaksi material.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentMat as $rm): ?>
                            <tr>
                                <td><?= date('d/m H:i:s', strtotime($rm['created_at'])) ?></td>
                                <td><?= htmlspecialchars($rm['shift']) ?></td>
                                <td><span class="badge badge-info"><?= $rm['section_id'] == 3 ? 'Mixing' : ($rm['section_id'] == 4 ? 'Calender' : 'Delivery') ?></span></td>
                                <td><?= htmlspecialchars($rm['operator_name'] ?? $rm['operator_nik']) ?></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars($rm['action_type']) ?></span></td>
                                <td><code><?= htmlspecialchars($rm['details']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT VALIDASI INPUT FORM & PENGALIHAN TAB -->
<!-- ===================================================================== -->
<script>
function switchTab(t) {
    window.location.href = 'dept_material.php?tab=' + t;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
