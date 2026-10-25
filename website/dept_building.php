<?php
/**
 * ==============================================================================
 * DEPARTEMEN BUILDING (GREEN TIRE ASSEMBLY) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengelola stasiun perakitan ban mentah (Green Tire) dari gabungan ply, bead, belt, dan tread.
 * - Mengatur kategori ukuran ban: PCR (Passenger Car Radial), SUV, LTR (Light Truck), dan TBR (Truck & Bus Radial).
 * - Fitur Utama:
 *   1. Filter Ukuran Ban Cepat: Memilih kelompok ukuran ban dengan satu klik tombol kategori.
 *   2. Form Input Operator: Mencatat NIK operator, nomor mesin rakit (Tire Building Machine / TBM),
 *      spesifikasi pola telapak ban (Pattern), dan status kualitas hasil rakitan (PASS / REWORK).
 *   3. Validasi Ketat Barcode: Mengharuskan barcode ban mentah sudah terdaftar terlebih dahulu oleh Admin
 *      (Mencegah barcode liar tanpa identitas master).
 *   4. Tabel Log Perakitan: Menampilkan ban yang baru saja dirakit pada shift berjalan.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Ukuran Ban Baru:
 *    Cari array `` atau daftar dropdown `<select name="tire_size">` untuk menambah varian ukuran baru.
 * 2. Mengubah Kategori Ban:
 *    Sesuaikan tombol tab filter `` (misal menambah kategori EV Tire / Ban Listrik).
 * 3. Menghubungkan ke Lampu Indikator Mesin (Andon Light):
 *    Dapat ditambahkan pemanggilan trigger API downtime jika status kualitas yang diinput adalah REWORK.
 * ==============================================================================
 */
$pageTitle = 'Dept Building (Green Tire Assembly)';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
// (Dijalankan SEBELUM layout dicetak agar respon 403 valid & tanpa warning 'headers already sent')
Auth::requireDeptAccess('BLD');

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

// ==============================================================================
// PEMBATASAN AKSES UKURAN: OPERATOR HANYA BOLEH MENGAKSES BAGIANNYA SENDIRI
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userSect = $currentUser['section_code'] ?? '';
$hasFullBldAccess = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head', 'admin_dept']);

$bldSectionSizes = [
    'BLD-PCR' => ['PCR'],
    'BLD-SUV' => ['SUV'],
    'BLD-LTR' => ['LTR'],
    'BLD-TBR' => ['TBR'],
];

if ($hasFullBldAccess || empty($userSect) || !isset($bldSectionSizes[$userSect])) {
    $allowedSizes = ['PCR', 'SUV', 'LTR', 'TBR'];
} else {
    $allowedSizes = $bldSectionSizes[$userSect];
}

$requestedSize = strtoupper($_GET['size'] ?? $allowedSizes[0]);
if (!in_array($requestedSize, $allowedSizes)) {
    $activeSize = $allowedSizes[0];
} else {
    $activeSize = $requestedSize;
}

// Proteksi Keamanan POST Building
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_building') {
    if (!in_array($activeSize, $allowedSizes)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang untuk melakukan penginputan pada lini {$activeSize}!";
        $messageType = 'danger';
        unset($_POST['action']);
    }
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN FORM PERAKITAN BAN MENTAH (GREEN TIRE ASSEMBLY)
// ==============================================================================
// Handle Form Submission: Green Tire Assembly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_building') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $machineId = trim($_POST['machine_id'] ?? '');
    $tireSize = trim($_POST['tire_size'] ?? '');
    $tirePattern = trim($_POST['tire_pattern'] ?? '');
    $gtBarcode = strtoupper(trim($_POST['gt_barcode'] ?? ''));
    $qualityStatus = $_POST['quality_status'] ?? 'PASS';

    // Verification: Barcode MUST be pre-registered by Admin (No auto generate button here!)
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$gtBarcode]);
    $barcodeData = $bcCheck->fetch();

    if (!$barcodeData) {
        $message = "GAGAL: Barcode '{$gtBarcode}' BELUM TERDAFTAR dalam sistem! Barcode green tire harus diterbitkan terlebih dahulu oleh Admin di menu Generate Barcode.";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $sectionIdMap = [
                'PCR' => 6,
                'SUV' => 7,
                'LTR' => 8,
                'TBR' => 9
            ];
            $sectionId = $sectionIdMap[$activeSize] ?? 6;

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
                    ?, 3, ?, CURRENT_DATE, ?, 'NORMAL', 
                    1, 'Pcs', ?, ?, 'BUILDING_GT_PRODUCED', ?
                )
            ");
            $act->execute([
                $gtBarcode,
                $sectionId,
                $currentShiftInt,
                $opId,
                $nik, 
                "Building Green Tire [{$activeSize}] Mesin {$machineId} | Ukuran: {$tireSize} {$tirePattern} | Barcode: {$gtBarcode} | QC: {$qualityStatus}"
            ]);

            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'BLD', ?, ?, ?, 
                    ?, 1, ?, CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$gtBarcode, 'BLD-' . $activeSize, $machineId, $nik, $tireSize, $qualityStatus, 'Shift ' . $currentShiftInt]);

            // Mark barcode status as ASSEMBLED
            $upBc = $pdo->prepare("UPDATE barcodes SET status = 'BUILDING_ASSEMBLED' WHERE barcode = ?");
            $upBc->execute([$gtBarcode]);

            $pdo->commit();
            $message = "Sukses! Green Tire [{$activeSize}] {$tireSize} (Barcode: {$gtBarcode}) berhasil dicatat ke SCADA.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses perakitan ban mentah: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch recent building logs
$recentBld = $pdo->query("
    SELECT pa.*, u.full_name as operator_name 
    FROM production_activities pa
    LEFT JOIN users u ON pa.operator_nik = u.nik
    WHERE pa.dept_id = 3 
    ORDER BY pa.created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<?php if (!$hasFullBldAccess && count($allowedSizes) < 4): ?>
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between;">
        <span>Stasiun Kerja Aktif: <strong><?= htmlspecialchars($currentUser['section_name'] ?? $userSect) ?> (<?= htmlspecialchars($userSect) ?>)</strong></span>
        <span style="font-size: 11px; background: rgba(59, 130, 246, 0.2); padding: 3px 8px; border-radius: 4px; color: #bfdbfe;">Akses Khusus Bagian Anda</span>
    </div>
<?php endif; ?>

<!-- Tab Selector per Tire Size (Dibatasi per Hak Akses Bagian) -->
<div class="tabs">
    <?php if (in_array('PCR', $allowedSizes)): ?>
    <button class="tab-btn <?= $activeSize === 'PCR' ? 'active' : '' ?>" onclick="switchSize('PCR')">
        🚗 1. PCR (R13 - R16)
    </button>
    <?php endif; ?>

    <?php if (in_array('SUV', $allowedSizes)): ?>
    <button class="tab-btn <?= $activeSize === 'SUV' ? 'active' : '' ?>" onclick="switchSize('SUV')">
        🚙 2. SUV / PCR-HP (R17 - R22)
    </button>
    <?php endif; ?>

    <?php if (in_array('LTR', $allowedSizes)): ?>
    <button class="tab-btn <?= $activeSize === 'LTR' ? 'active' : '' ?>" onclick="switchSize('LTR')">
        🚐 3. LTR (Light Truck)
    </button>
    <?php endif; ?>

    <?php if (in_array('TBR', $allowedSizes)): ?>
    <button class="tab-btn <?= $activeSize === 'TBR' ? 'active' : '' ?>" onclick="switchSize('TBR')">
        🚛 4. TBR (Truck & Bus)
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Building Assembly Form -->
<div class="card" style="max-width: 760px; margin: 0 auto 24px auto;">
    <div class="card-header">
        <div>
            <div class="card-title">
                Pelaporan Green Tire Assembly - Kategori: <?= $activeSize ?>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                Tombol auto barcode telah dihilangkan. Operator wajib memindai stiker barcode resmi yang diterbitkan oleh Admin.
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
    </div>

    <form class="scanner-workflow-form" method="POST" action="dept_building.php?size=<?= $activeSize ?>">
        <input type="hidden" name="action" value="save_building">

        <!-- Universal First Field: NIK Karyawan -->
        <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label required">1. NIK Operator Building</label>
            <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label required">2. Nomor Mesin Building</label>
                <input type="text" name="machine_id" list="dl_bld_machine_id_d147" class="form-input" placeholder="Scan / Input Machine Id..." value="BLD-&lt;?= $activeSize ?&gt;-01" required autocomplete="off">
                    <datalist id="dl_bld_machine_id_d147">
                        <option value="BLD-&lt;?= $activeSize ?&gt;-01">Mesin Building  Line 01</option>
                        <option value="BLD-&lt;?= $activeSize ?&gt;-02">Mesin Building  Line 02</option>
                        <option value="BLD-&lt;?= $activeSize ?&gt;-03">Mesin Building  Line 03</option>
                        <option value="BLD-&lt;?= $activeSize ?&gt;-04">Mesin Building  Line 04</option>
                    </datalist>
            </div>

            <div class="form-group">
                <label class="form-label required">3. Scan Barcode Green Tire (Pre-Registered)</label>
                <input type="text" name="gt_barcode" class="form-input" placeholder="Scan barcode GT resmi..." required>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label required">4. Ukuran Ban (Tire Size)</label>
                <?php if ($activeSize === 'PCR'): ?>
                    <input type="text" name="tire_size" list="dl_bld_tire_size_4432" class="form-input" placeholder="Scan / Input Tire Size..." value="175/65 R14" required autocomplete="off">
                    <datalist id="dl_bld_tire_size_4432">
                        <option value="175/65 R14">175/65 R14</option>
                        <option value="185/65 R15">185/65 R15</option>
                        <option value="195/60 R15">195/60 R15</option>
                        <option value="205/55 R16">205/55 R16</option>
                    </datalist>
                <?php elseif ($activeSize === 'SUV'): ?>
                    <input type="text" name="tire_size" list="dl_bld_tire_size_9b49" class="form-input" placeholder="Scan / Input Tire Size..." value="215/60 R17" required autocomplete="off">
                    <datalist id="dl_bld_tire_size_9b49">
                        <option value="215/60 R17">215/60 R17 Savero SUV</option>
                        <option value="225/65 R17">225/65 R17 Champiro HP</option>
                        <option value="235/50 R18">235/50 R18 High Performance</option>
                        <option value="265/60 R18">265/60 R18 Savero A/T</option>
                    </datalist>
                <?php elseif ($activeSize === 'LTR'): ?>
                    <input type="text" name="tire_size" list="dl_bld_tire_size_b98f" class="form-input" placeholder="Scan / Input Tire Size..." value="7.00 R15 LT" required autocomplete="off">
                    <datalist id="dl_bld_tire_size_b98f">
                        <option value="7.00 R15 LT">7.00 R15 Light Truck</option>
                        <option value="7.50 R16 LT">7.50 R16 Light Truck</option>
                        <option value="195 R14C 8PR">195 R14C Commercial</option>
                    </datalist>
                <?php else: ?>
                    <input type="text" name="tire_size" list="dl_bld_tire_size_3ee1" class="form-input" placeholder="Scan / Input Tire Size..." value="11 R22.5 TBR" required autocomplete="off">
                    <datalist id="dl_bld_tire_size_3ee1">
                        <option value="11 R22.5 TBR">11 R22.5 Truck Bus Radial</option>
                        <option value="12 R22.5 TBR">12 R22.5 Heavy Load</option>
                        <option value="295/80 R22.5 TBR">295/80 R22.5 Highway</option>
                    </datalist>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label required">5. Pola Telapak (Pattern / Seri)</label>
                <input type="text" name="tire_pattern" class="form-input" value="CHAMPIRO ECOTEC" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 22px;">
            <label class="form-label required">6. Pemeriksaan Visual Awal (Scan / Input QC Status)</label>
            <input type="text" name="quality_status" list="dl_bld_quality" class="form-input" placeholder="Scan / Input Status QC (PASS / REPAIR / REJECT)..." value="PASS" required autocomplete="off">
            <datalist id="dl_bld_quality">
                <option value="PASS">PASS (Green Tire OK - Lolos)</option>
                <option value="REPAIR">REPAIR (Minor Adjustment)</option>
                <option value="REJECT">REJECT (Cacat Sambungan / Afkir)</option>
            </datalist>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
            Simpan & Proses Green Tire ke SCADA
        </button>
    </form>
</div>

<!-- Riwayat Perakitan Building -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            Riwayat Produksi Green Tire (Dept Building)
        </div>
        <a href="api/export_building.php" class="btn btn-secondary btn-sm">
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
                    <th>Operator NIK</th>
                    <th>Nama Operator</th>
                    <th>Rincian Perakitan Green Tire</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBld)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 24px;">Belum ada rekaman produksi green tire.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentBld as $rb): ?>
                        <tr>
                            <td><?= date('d/m H:i:s', strtotime($rb['created_at'])) ?></td>
                            <td><?= htmlspecialchars($rb['shift']) ?></td>
                            <td><?= htmlspecialchars($rb['operator_nik']) ?></td>
                            <td><?= htmlspecialchars($rb['operator_name'] ?? 'Operator') ?></td>
                            <td><code><?= htmlspecialchars($rb['details']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 3: JAVASCRIPT FORM FEEDBACK & FOKUS SCANNER -->
<!-- ===================================================================== -->
<script>
function switchSize(s) {
    window.location.href = 'dept_building.php?size=' + s;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
