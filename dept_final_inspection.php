<?php
/**
 * ==============================================================================
 * DEPARTEMEN FINAL INSPECTION (QUALITY CONTROL BAN JADI) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Pengujian dan verifikasi kualitas akhir ban matang sebelum diserahkan ke gudang ban jadi.
 * - Fitur Utama:
 *   1. Tab Inspeksi Visual & Balancing: Memeriksa cacat visual telapak/dinding ban dan uji keseimbangan putaran (Dynamic Balance).
 *   2. Tab Cutting Hair: Pencatatan pembersihan rambut karet (vent spews) sisa cetakan ban.
 *   3. Tab Delivery: Pengiriman ban yang telah berstatus PASS ke gudang ban jadi (Finished Goods).
 *   4. Tab Riwayat: Monitoring rasio kelulusan kualitas (First Pass Yield / FPY) dan catatan ban reject.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Kategori Cacat (Defect List):
 *    Cari input atau dropdown `<select name="defect_notes">` untuk menambah opsi cacat (misal: Crack, Blister, Low Spot).
 * 2. Mengubah Ambang Batas Uji Balancing:
 *    Cari bagian validasi `dynamic_balance` untuk menambah parameter nilai gram ketidakseimbangan (Static/Dynamic imbalance).
 * 3. Menghubungkan ke Mesin Sortir Otomatis (Conveyor Diverter):
 *    Jika status diset menjadi 'SCRAP', sistem dapat mengirim sinyal HTTP/MQTT ke PLC diverter agar memisahkan ban ke jalur sortir.
 * ==============================================================================
 */
$pageTitle = 'Dept Final Inspection (QC Ban Jadi)';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
// (Dijalankan SEBELUM layout dicetak agar respon 403 valid & tanpa warning 'headers already sent')
Auth::requireDeptAccess('FI');

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

// ==============================================================================
// PEMBATASAN AKSES TAB BAGIAN: OPERATOR HANYA BOLEH MENGAKSES BAGIANNYA SENDIRI
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userSect = $currentUser['section_code'] ?? '';
$hasFullFiAccess = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head', 'admin_dept']);

$fiSectionTabs = [
    'FI-INS' => ['inspection', 'riwayat'],
    'FI-CUT' => ['cutting_hair', 'riwayat'],
    'FI-DEL' => ['delivery', 'riwayat'],
];

if ($hasFullFiAccess || empty($userSect) || !isset($fiSectionTabs[$userSect])) {
    $allowedTabs = ['inspection', 'cutting_hair', 'delivery', 'riwayat'];
} else {
    $allowedTabs = $fiSectionTabs[$userSect];
}

$requestedTab = $_GET['tab'] ?? $allowedTabs[0];
if (!in_array($requestedTab, $allowedTabs)) {
    $activeTab = $allowedTabs[0];
} else {
    $activeTab = $requestedTab;
}

// Proteksi Keamanan POST Final Inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actToTab = [
        'save_inspection'      => 'inspection',
        'save_cutting_hair'    => 'cutting_hair',
        'save_finish_delivery' => 'delivery'
    ];
    $action = $_POST['action'];
    if (isset($actToTab[$action]) && !in_array($actToTab[$action], $allowedTabs)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang untuk melakukan penginputan pada bagian ini!";
        $messageType = 'danger';
        unset($_POST['action']);
    }
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN TRANSAKSI INSPEKSI KUALITAS VISUAL & DINAMIK BALANCING
// ==============================================================================
// 1. Handle Inspection (Visual & Balancing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_inspection') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $stationId = trim($_POST['station_id'] ?? '');
    $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
    $qualityStatus = $_POST['quality_status'] ?? 'PASS';
    $dynamicBalance = trim($_POST['dynamic_balance'] ?? 'OK');
    $defectNotes = trim($_POST['defect_notes'] ?? '');

    // Check barcode registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$tireBarcode]);
    if (!$bcCheck->fetch()) {
        $message = "Gagal: Barcode '{$tireBarcode}' tidak terdaftar di sistem!";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $act = $pdo->prepare("
                INSERT INTO production_activities (dept_id, section_id, operator_nik, action_type, details, shift)
                VALUES (5, 16, ?, 'FINAL_INSPECTION_RECORDED', ?, ?)
            ");
            $act->execute([
                $nik, 
                "Inspeksi Pos {$stationId} | Barcode: {$tireBarcode} | QC: {$qualityStatus} | Balance: {$dynamicBalance} | Catatan: {$defectNotes}",
                'Shift ' . $shiftInfo['shift']
            ]);

            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (barcode, dept_id, section_id, machine_id, operator_nik, item_code, qty, quality_status, shift)
                VALUES (?, 'FI', 'inspection', ?, ?, ?, 1, ?, ?)
            ");
            $scada->execute([$tireBarcode, $stationId, $nik, 'INSPECTED_TIRE', $qualityStatus, 'Shift ' . $shiftInfo['shift']]);

            // Update barcode status
            $up = $pdo->prepare("UPDATE barcodes SET status = ? WHERE barcode = ?");
            $up->execute(['FI_' . $qualityStatus, $tireBarcode]);

            $pdo->commit();
            $message = "Sukses! Hasil inspeksi ban {$tireBarcode} ({$qualityStatus}) berhasil disimpan.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal menyimpan inspeksi: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// 2. Handle Cutting Hair Tire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cutting_hair') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $machineTrimmer = trim($_POST['machine_trimmer'] ?? '');
    $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
    $spewCleanStatus = trim($_POST['spew_clean_status'] ?? 'CLEAN');

    try {
        $act = $pdo->prepare("
            INSERT INTO production_activities (dept_id, section_id, operator_nik, action_type, details, shift)
            VALUES (5, 17, ?, 'CUTTING_HAIR_PROCESSED', ?, ?)
        ");
        $act->execute([
            $nik, 
            "Pemotongan Rambut Ban (Vent Spews) di Mesin {$machineTrimmer} | Barcode: {$tireBarcode} | Status: {$spewCleanStatus}",
            'Shift ' . $shiftInfo['shift']
        ]);

        $message = "Sukses! Pembersihan rambut ban {$tireBarcode} berhasil dicatat.";
    } catch (Exception $e) {
        $message = "Gagal mencatat cutting hair: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 3. Handle Finish Delivery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_finish_delivery') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $destination = trim($_POST['destination'] ?? 'Gudang Finished Goods (FG)');
    $tireBarcode = strtoupper(trim($_POST['tire_barcode'] ?? ''));
    $palletNo = trim($_POST['pallet_no'] ?? '');

    try {
        $pdo->beginTransaction();

        $act = $pdo->prepare("
            INSERT INTO production_activities (dept_id, section_id, operator_nik, action_type, details, shift)
            VALUES (5, 18, ?, 'FINISH_GOODS_DELIVERY', ?, ?)
        ");
        $act->execute([
            $nik, 
            "Pengiriman Ban Jadi Barcode: {$tireBarcode} | Pallet: {$palletNo} | Tujuan: {$destination}",
            'Shift ' . $shiftInfo['shift']
        ]);

        $up = $pdo->prepare("UPDATE barcodes SET status = 'DELIVERED_FG' WHERE barcode = ?");
        $up->execute([$tireBarcode]);

        $pdo->commit();
        $message = "Sukses! Ban jadi {$tireBarcode} siap dikirim ke {$destination}.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal mencatat pengiriman ban: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch recent inspection logs
$recentFi = $pdo->query("
    SELECT pa.*, u.full_name as operator_name 
    FROM production_activities pa
    LEFT JOIN users u ON pa.operator_nik = u.nik
    WHERE pa.dept_id = 5 
    ORDER BY pa.created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<?php if (!$hasFullFiAccess && count($allowedTabs) < 4): ?>
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between;">
        <span>Stasiun Kerja Aktif: <strong><?= htmlspecialchars($currentUser['section_name'] ?? $userSect) ?> (<?= htmlspecialchars($userSect) ?>)</strong></span>
        <span style="font-size: 11px; background: rgba(59, 130, 246, 0.2); padding: 3px 8px; border-radius: 4px; color: #bfdbfe;">Akses Khusus Bagian Anda</span>
    </div>
<?php endif; ?>

<!-- Tab Selector (Dibatasi per Hak Akses Bagian) -->
<div class="tabs">
    <?php if (in_array('inspection', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'inspection' ? 'active' : '' ?>" onclick="switchTab('inspection')">
        🔍 1. Pos Final Inspection (QC)
    </button>
    <?php endif; ?>

    <?php if (in_array('cutting_hair', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'cutting_hair' ? 'active' : '' ?>" onclick="switchTab('cutting_hair')">
        ✂️ 2. Cutting Hair Tire (Trimming)
    </button>
    <?php endif; ?>

    <?php if (in_array('delivery', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'delivery' ? 'active' : '' ?>" onclick="switchTab('delivery')">
        🚚 3. Delivery Ban Jadi (Finished Goods)
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

<!-- Tab 1: Final Inspection -->
<?php if ($activeTab === 'inspection'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Inspeksi Visual & Dynamic Balancing</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_final_inspection.php?tab=inspection">
            <input type="hidden" name="action" value="save_inspection">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Inspector QC</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Pos Inspeksi / Mesin</label>
                    <input type="text" name="station_id" list="dl_fi_station_id_1f04" class="form-input" placeholder="Scan / Input Station Id..." value="QC-STATION-01" required autocomplete="off">
                    <datalist id="dl_fi_station_id_1f04">
                        <option value="QC-STATION-01">Pos QC Visual 01</option>
                        <option value="QC-STATION-02">Pos QC Visual 02</option>
                        <option value="BAL-DYNAMIC-01">Mesin Dynamic Balancing 01</option>
                        <option value="XRAY-CHECK-01">Mesin X-Ray Tire Inspection</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Scan Barcode Ban Jadi</label>
                    <input type="text" name="tire_barcode" class="form-input" placeholder="Scan barcode stiker ban jadi..." required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Status Mutu (Quality Decision)</label>
                    <input type="text" name="quality_status" list="dl_fi_quality_status_4b87" class="form-input" placeholder="Scan / Input Quality Status..." value="PASS" required autocomplete="off">
                    <datalist id="dl_fi_quality_status_4b87">
                        <option value="PASS">PASS (Lolos Standar Mutu A)</option>
                        <option value="REPAIR">REPAIR (Perlu Trimming / Polish Ulang)</option>
                        <option value="REJECT">REJECT (Cacat Kritis / Afkir)</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Hasil Uji Dynamic Balancing</label>
                    <input type="text" name="dynamic_balance" list="dl_fi_dynamic_balance_dea7" class="form-input" placeholder="Scan / Input Dynamic Balance..." value="OK" required autocomplete="off">
                    <datalist id="dl_fi_dynamic_balance_dea7">
                        <option value="OK">OK - Keseimbangan Dinamis Sesuai Toleransi</option>
                        <option value="OUT_OF_SPEC">Out of Spec (Unbalance Melebihi Batas)</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">6. Catatan Defect / Cacat (Jika Ada)</label>
                <input type="text" name="defect_notes" class="form-input" placeholder="Keterangan defect ban...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Hasil Inspeksi QC
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 2: Cutting Hair Tire -->
<?php if ($activeTab === 'cutting_hair'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Cutting Hair Tire (Pembersihan Vent Spews Ban)</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_final_inspection.php?tab=cutting_hair">
            <input type="hidden" name="action" value="save_cutting_hair">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Operator Trimmer</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Mesin Trimmer</label>
                    <input type="text" name="machine_trimmer" list="dl_fi_machine_trimmer_9c31" class="form-input" placeholder="Scan / Input Machine Trimmer..." value="TRM-01" required autocomplete="off">
                    <datalist id="dl_fi_machine_trimmer_9c31">
                        <option value="TRM-01">Mesin Trimmer Otomatis Line 1</option>
                        <option value="TRM-02">Mesin Trimmer Otomatis Line 2</option>
                        <option value="TRM-MANUAL">Meja Trimming Manual</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Scan Barcode Ban</label>
                    <input type="text" name="tire_barcode" class="form-input" placeholder="Scan barcode ban..." required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">4. Status Pembersihan Rambut Ban</label>
                <input type="text" name="spew_clean_status" list="dl_fi_spew_clean_status_b525" class="form-input" placeholder="Scan / Input Spew Clean Status..." value="CLEAN" required autocomplete="off">
                    <datalist id="dl_fi_spew_clean_status_b525">
                        <option value="CLEAN">Bersih Sempurna (100% Vent Spews Terpotong)</option>
                        <option value="RE-TRIM">Perlu Pemotongan Tambahan Sisi Bahu</option>
                    </datalist>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Trimming Rambut Ban
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 3: Delivery Ban Jadi -->
<?php if ($activeTab === 'delivery'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">Delivery Ban Jadi ke Gudang Finished Goods</div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>
        <form class="scanner-workflow-form" method="POST" action="dept_final_inspection.php?tab=delivery">
            <input type="hidden" name="action" value="save_finish_delivery">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Petugas Delivery Ban</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Scan Barcode Ban Jadi</label>
                    <input type="text" name="tire_barcode" class="form-input" placeholder="Scan barcode ban yang siap dikirim..." required>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Nomor Pallet / Racking FG</label>
                    <input type="text" name="pallet_no" class="form-input" placeholder="Contoh: PALLET-FG-102" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">4. Tujuan Pengiriman</label>
                <input type="text" name="destination" list="dl_fi_destination_c2b8" class="form-input" placeholder="Scan / Input Destination..." value="Gudang Finished Goods (FG Warehouse)" required autocomplete="off">
                    <datalist id="dl_fi_destination_c2b8">
                        <option value="Gudang Finished Goods (FG Warehouse)">Gudang Finished Goods (FG Warehouse)</option>
                        <option value="Area Ekspedisi Ekspor">Area Ekspedisi Ekspor</option>
                        <option value="OEM Warehouse Supply">OEM Warehouse Supply</option>
                    </datalist>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Kirim ke Finished Goods
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 4: Riwayat & Export -->
<?php if ($activeTab === 'riwayat'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Riwayat Transaksi Dept Final Inspection</div>
            <a href="api/export_inspection.php" class="btn btn-secondary btn-sm">
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
                        <th>NIK Operator</th>
                        <th>Nama Operator</th>
                        <th>Aksi</th>
                        <th>Rincian Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentFi)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 24px;">Belum ada rekaman inspeksi ban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentFi as $rf): ?>
                            <tr>
                                <td><?= date('d/m H:i:s', strtotime($rf['created_at'])) ?></td>
                                <td><?= htmlspecialchars($rf['shift']) ?></td>
                                <td><?= htmlspecialchars($rf['operator_nik']) ?></td>
                                <td><?= htmlspecialchars($rf['operator_name'] ?? 'Operator') ?></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars($rf['action_type']) ?></span></td>
                                <td><code><?= htmlspecialchars($rf['details']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT VALIDASI DEFECT & PENGALIHAN FORM QC -->
<!-- ===================================================================== -->
<script>
function switchTab(t) {
    window.location.href = 'dept_final_inspection.php?tab=' + t;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
