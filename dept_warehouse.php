<?php
/**
 * ==============================================================================
 * DEPARTEMEN GUDANG BAHAN (WAREHOUSE) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengelola operasional penerimaan bahan mentah, penyimpanan pada rak, dan pengeluaran bahan ke lantai produksi.
 * - Fitur Utama:
 *   1. Tab Penerima: Input barang masuk, validasi NIK operator, pengecekan barcode, dan alokasi ke rak gudang.
 *   2. Tab Pengirim: Pengeluaran stok bahan dari rak gudang menuju lini produksi (Mixing, Calender, dll).
 *   3. Tab Peta Rak (Visual Grid Layout): Visualisasi denah rak A01-F10 dengan kode warna (Hijau: Kosong, Merah: Terisi).
 *   4. Tab Riwayat Transaksi: Log keluar-masuk lengkap yang dapat difilter berdasarkan tanggal & operator.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Kapasitas atau Dimensi Peta Rak:
 *    Cari blok pembuatan denah rak di Tab "peta". Anda bisa menambahkan baris/kolom baru di tabel `warehouse_racks`.
 * 2. Menyesuaikan Validasi Stok:
 *    Cari blok `receive_material` atau `dispatch_material` untuk mengubah batas minimal/maksimal kuantitas.
 * 3. Menghubungkan ke Scanner Rak Otomatis:
 *    Tambahkan auto-submit saat barcode rak berhasil dipindai oleh operator fork-lift.
 * ==============================================================================
 */
$pageTitle = 'Dept Gudang Bahan (Warehouse)';
require_once __DIR__ . '/includes/header.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
Auth::requireDeptAccess('WH');

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

// ==============================================================================
// PEMBATASAN AKSES TAB BAGIAN: OPERATOR HANYA BOLEH MENGAKSES BAGIANNYA SENDIRI
// ==============================================================================
$userRole = $currentUser['role'] ?? '';
$userSect = $currentUser['section_code'] ?? '';
$hasFullWhAccess = in_array($userRole, ['super_admin', 'plant_head', 'asst_plant_head', 'dept_head', 'asst_dept_head', 'admin_dept']);

$whSectionTabs = [
    'WH-RCV' => ['penerima', 'peta'],
    'WH-SND' => ['pengirim', 'peta'],
];

if ($hasFullWhAccess || empty($userSect) || !isset($whSectionTabs[$userSect])) {
    $allowedTabs = ['penerima', 'pengirim', 'peta'];
} else {
    $allowedTabs = $whSectionTabs[$userSect];
}

$requestedTab = $_GET['tab'] ?? $allowedTabs[0];
if (!in_array($requestedTab, $allowedTabs)) {
    $activeTab = $allowedTabs[0];
} else {
    $activeTab = $requestedTab;
}

// Proteksi Keamanan POST Gudang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actToTab = [
        'receive_material'  => 'penerima',
        'dispatch_material' => 'pengirim'
    ];
    $action = $_POST['action'];
    if (isset($actToTab[$action]) && !in_array($actToTab[$action], $allowedTabs)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang untuk melakukan penginputan pada bagian ini!";
        $messageType = 'danger';
        unset($_POST['action']);
    }
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN FORM PENERIMAAN BAHAN MASUK (RECEIVING)
// ==============================================================================
// 1. Handle Transaksi Penerima Bahan Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_material') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $barcode = strtoupper(trim($_POST['barcode'] ?? ''));
    $rackCode = trim($_POST['rack_code'] ?? '');
    $itemName = trim($_POST['item_name'] ?? '');
    $qty = (float)($_POST['qty'] ?? 1);
    $unit = trim($_POST['unit'] ?? 'KG');

    // Validation: Check if barcode is registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$barcode]);
    $validBc = $bcCheck->fetch();

    if (!$validBc) {
        $message = "Gagal: Barcode '{$barcode}' TIDAK TERDAFTAR dalam sistem! Barcode harus didaftarkan di Generate Barcode terlebih dahulu.";
        $messageType = 'danger';
    } elseif (empty($rackCode)) {
        $message = "Gagal: Anda harus memilih lokasi rak yang KOSONG dari dropdown.";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Mark rack as occupied
            $upRack = $pdo->prepare("
                UPDATE warehouse_racks 
                SET is_occupied = TRUE, current_item_name = ?, current_barcode = ?, current_qty = ?, current_unit = ?, last_updated = CURRENT_TIMESTAMP
                WHERE rack_code = ? AND is_occupied = FALSE
            ");
            $upRack->execute([$itemName, $barcode, $qty, $unit, $rackCode]);

            if ($upRack->rowCount() === 0) {
                throw new Exception("Rak {$rackCode} sudah terisi atau tidak ditemukan!");
            }

            // Record to production activities
            $act = $pdo->prepare("
                INSERT INTO production_activities (dept_id, section_id, operator_nik, action_type, details, shift)
                VALUES (1, 1, ?, 'RECEIVE_WAREHOUSE', ?, ?)
            ");
            $act->execute([
                $nik, 
                "Terima {$itemName} ({$qty} {$unit}) barcode {$barcode} disimpan di Rak {$rackCode}",
                'Shift ' . $shiftInfo['shift']
            ]);

            // Record to SCADA logs
            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (barcode, dept_id, section_id, machine_id, operator_nik, item_code, qty, quality_status, shift)
                VALUES (?, 'WH', 'penerima', ?, ?, ?, ?, 'PASS', ?)
            ");
            $scada->execute([$barcode, $rackCode, $nik, $itemName, $qty, 'Shift ' . $shiftInfo['shift']]);

            $pdo->commit();
            $message = "Sukses! Material '{$itemName}' berhasil disimpan di Rak {$rackCode} dengan barcode {$barcode}.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses penerimaan: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// 2. Handle Transaksi Pengirim Bahan Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispatch_material') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $rackCode = trim($_POST['rack_code'] ?? '');
    $destinationDept = trim($_POST['destination_dept'] ?? '');
    $destinationSection = trim($_POST['destination_section'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($rackCode)) {
        $message = "Gagal: Anda harus memilih rak TERISI yang akan dikeluarkan materialnya.";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Get rack details before freeing it
            $rStmt = $pdo->prepare("SELECT * FROM warehouse_racks WHERE rack_code = ? AND is_occupied = TRUE");
            $rStmt->execute([$rackCode]);
            $rackData = $rStmt->fetch();

            if (!$rackData) {
                throw new Exception("Rak {$rackCode} dalam kondisi kosong atau tidak valid!");
            }

            // Free the rack
            $freeRack = $pdo->prepare("
                UPDATE warehouse_racks 
                SET is_occupied = FALSE, current_item_name = NULL, current_barcode = NULL, current_qty = 0, last_updated = CURRENT_TIMESTAMP
                WHERE rack_code = ?
            ");
            $freeRack->execute([$rackCode]);

            // Log activity
            $act = $pdo->prepare("
                INSERT INTO production_activities (dept_id, section_id, operator_nik, action_type, details, shift)
                VALUES (1, 2, ?, 'DISPATCH_WAREHOUSE', ?, ?)
            ");
            $act->execute([
                $nik, 
                "Kirim {$rackData['current_item_name']} (Barcode: {$rackData['current_barcode']}) dari Rak {$rackCode} ke {$destinationDept} - {$destinationSection}. {$notes}",
                'Shift ' . $shiftInfo['shift']
            ]);

            // Record to SCADA logs
            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (barcode, dept_id, section_id, machine_id, operator_nik, item_code, qty, quality_status, shift)
                VALUES (?, 'WH', 'pengirim', ?, ?, ?, ?, 'PASS', ?)
            ");
            $scada->execute([$rackData['current_barcode'], $destinationDept, $nik, $rackData['current_item_name'], $rackData['current_qty'], 'Shift ' . $shiftInfo['shift']]);

            $pdo->commit();
            $message = "Sukses! Material dari Rak {$rackCode} berhasil dikirim ke {$destinationDept} - {$destinationSection}. Rak kini berstatus KOSONG.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses pengiriman: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch EMPTY racks for Penerima dropdown
$emptyRacks = $pdo->query("SELECT rack_code, zone FROM warehouse_racks WHERE is_occupied = FALSE ORDER BY zone, rack_code")->fetchAll();

// Fetch OCCUPIED racks for Pengirim dropdown
$occupiedRacks = $pdo->query("SELECT rack_code, zone, current_item_name, current_barcode, current_qty, current_unit FROM warehouse_racks WHERE is_occupied = TRUE ORDER BY zone, rack_code")->fetchAll();

// Fetch all 300 racks for Peta Rak
$rackSearch = trim($_GET['rack_search'] ?? '');
$rQuery = "SELECT * FROM warehouse_racks WHERE 1=1";
$rParams = [];
if (!empty($rackSearch)) {
    $rQuery .= " AND (rack_code ILIKE ? OR current_item_name ILIKE ? OR current_barcode ILIKE ?)";
    $rParams[] = "%{$rackSearch}%";
    $rParams[] = "%{$rackSearch}%";
    $rParams[] = "%{$rackSearch}%";
}
$rQuery .= " ORDER BY zone, row_num, col_num, tier_num";
$allRacksStmt = $pdo->prepare($rQuery);
$allRacksStmt->execute($rParams);
$allRacks = $allRacksStmt->fetchAll();

// All departments and sections for Destination dropdown
$destDepts = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll();
$destSections = $pdo->query("SELECT id, dept_id, code, name FROM sections ORDER BY dept_id, id")->fetchAll();
?>

<?php if (!$hasFullWhAccess && count($allowedTabs) < 3): ?>
    <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 13px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between;">
        <span>Stasiun Kerja Aktif: <strong><?= htmlspecialchars($currentUser['section_name'] ?? $userSect) ?> (<?= htmlspecialchars($userSect) ?>)</strong></span>
        <span style="font-size: 11px; background: rgba(59, 130, 246, 0.2); padding: 3px 8px; border-radius: 4px; color: #bfdbfe;">Akses Khusus Bagian Anda</span>
    </div>
<?php endif; ?>

<!-- Tab Navigation (Dibatasi per Hak Akses Bagian) -->
<div class="tabs">
    <?php if (in_array('penerima', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'penerima' ? 'active' : '' ?>" onclick="switchTab('penerima')">
        📥 1. Bagian Penerima (Bahan Masuk)
    </button>
    <?php endif; ?>

    <?php if (in_array('pengirim', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'pengirim' ? 'active' : '' ?>" onclick="switchTab('pengirim')">
        📤 2. Bagian Pengirim (Bahan Keluar)
    </button>
    <?php endif; ?>

    <?php if (in_array('peta', $allowedTabs)): ?>
    <button class="tab-btn <?= $activeTab === 'peta' ? 'active' : '' ?>" onclick="switchTab('peta')">
        🗺️ 3. Peta Digital 300 Rak Gudang
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tab 1: Penerima (Bahan Masuk) -->
<?php if ($activeTab === 'penerima'): ?>
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                Penerimaan Bahan Baku (Penerima Gudang)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_warehouse.php?tab=penerima">
            <input type="hidden" name="action" value="receive_material">

            <!-- 1. NIK Karyawan (Universal First Field) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Karyawan Pelapor</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">2. Scan Barcode Bahan</label>
                    <input type="text" name="barcode" class="form-input" placeholder="Contoh: PRD-RM-NR-001-..." required>
                    <small style="color: var(--text-muted); font-size: 11px;">Barcode wajib sudah terdaftar di sistem.</small>
                </div>
                <div class="form-group">
                    <label class="form-label required">3. Lokasi Rak Penyimpanan (Hanya Rak Kosong)</label>
                    <input type="text" name="rack_code" list="dl_wh_rack_code_dcf6" class="form-input" placeholder="Scan / Input Rack Code..." value="&lt;?= $er[" required autocomplete="off">
                    <datalist id="dl_wh_rack_code_dcf6">
                        <option value="">-- Pilih Rak Kosong ( Tersedia) --</option>
                        <option value="&lt;?= $er[">&quot;&gt;
                                [Zona ]  (Status: KOSONG)</option>
                    </datalist>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Nama Bahan / Item</label>
                    <input type="text" name="item_name" class="form-input" placeholder="Contoh: Natural Rubber RSS-1" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Jumlah (Qty) & Satuan</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" inputmode="decimal" autocomplete="off" step="0.01" name="qty" class="form-input" placeholder="Qty" value="100" required>
                        <input type="text" name="unit" list="dl_wh_unit_e773" class="form-input" placeholder="Scan / Input Unit..." value="KG" required autocomplete="off">
                    <datalist id="dl_wh_unit_e773">
                        <option value="KG">KG</option>
                        <option value="ROLL">ROLL</option>
                        <option value="PALLET">PALLET</option>
                        <option value="BAG">BAG</option>
                    </datalist>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Simpan & Proses Penerimaan Material
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 2: Pengirim (Bahan Keluar) -->
<?php if ($activeTab === 'pengirim'): ?>
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                Pengiriman Bahan ke Departemen Lain (Pengirim Gudang)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">⚠️ Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_warehouse.php?tab=pengirim">
            <input type="hidden" name="action" value="dispatch_material">

            <!-- 1. NIK Karyawan (Universal First Field) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Karyawan Pelapor</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <!-- Diambil dari rak: Hanya rak terisi! -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">2. Diambil Dari Rak (Hanya Rak TERISI)</label>
                <input type="text" name="rack_code" list="dl_wh_rack_code_38e2" class="form-input" placeholder="Scan / Input Rack Code..." value="&lt;?= $oc[" required autocomplete="off">
                    <datalist id="dl_wh_rack_code_38e2">
                        <option value="">-- Pilih Rak Terisi ( Rak Berisi Material) --</option>
                        <option value="&lt;?= $oc[">&quot;&gt;
                             :  ( ) - Barcode:</option>
                    </datalist>
                <small style="color: var(--text-muted); font-size: 11px;">Memilih rak ini akan mengeluarkan material dan mengosongkan status rak.</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">3. Departemen Tujuan</label>
                    <input type="text" name="destination_dept" list="dl_wh_destination_dept_f85a" class="form-input" placeholder="Scan / Input Destination Dept..." value="MAT" required autocomplete="off">
                    <datalist id="dl_wh_destination_dept_f85a">
                        <option value="MAT">Dept 2 - Material (Mixing / Calender)</option>
                        <option value="BLD">Dept 3 - Building (Green Tire)</option>
                        <option value="CUR">Dept 4 - Curing (Vulkanisasi)</option>
                        <option value="FI">Dept 5 - Final Inspection</option>
                        <option value="WH">Internal Gudang Bahan</option>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label required">4. Bagian (Sub-Section) Tujuan</label>
                    <input type="text" name="destination_section" list="dl_wh_destination_section_d6b4" class="form-input" placeholder="Scan / Input Destination Section..." value="&lt;?= $ds[" required autocomplete="off">
                    <datalist id="dl_wh_destination_section_d6b4">
                        <option value="&lt;?= $ds[">&quot;&gt; -</option>
                    </datalist>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">5. Catatan / No Surat Jalan</label>
                <input type="text" name="notes" class="form-input" placeholder="Keterangan pengiriman / nomor lory / pallet...">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                Simpan & Proses Pengeluaran & Transfer
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 3: Peta Digital 300 Rak -->
<?php if ($activeTab === 'peta'): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Peta Digital 300 Rak Gudang Bahan (Visual Interaktif)
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                    Klik pada kotak rak untuk melihat rincian isi pallet, barcode, dan keterangan bahan.
                </div>
            </div>

            <!-- Product search filter on top -->
            <form class="scanner-workflow-form" method="GET" action="dept_warehouse.php" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="tab" value="peta">
                <input type="text" name="rack_search" class="form-input" style="padding: 6px 12px; font-size: 13px;" placeholder="Cari nama produk / kode rak..." value="<?= htmlspecialchars($rackSearch) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                <?php if (!empty($rackSearch)): ?>
                    <a href="dept_warehouse.php?tab=peta" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="display: flex; gap: 16px; margin-bottom: 12px; font-size: 12px;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <div class="rack-cell rack-empty" style="width: 18px; height: 18px; aspect-ratio: 1;"></div>
                <span>Rak Kosong (<?= count($emptyRacks) ?>)</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <div class="rack-cell rack-occupied" style="width: 18px; height: 18px; aspect-ratio: 1;"></div>
                <span>Rak Terisi (<?= count($occupiedRacks) ?>)</span>
            </div>
        </div>

        <!-- 300 Racks Grid Container -->
        <div class="rack-grid-container">
            <?php foreach ($allRacks as $rk): ?>
                <div class="rack-cell <?= $rk['is_occupied'] ? 'rack-occupied' : 'rack-empty' ?>"
                     onclick="showRackDetail(<?= htmlspecialchars(json_encode($rk)) ?>)"
                     title="<?= $rk['rack_code'] ?> (<?= $rk['is_occupied'] ? 'TERISI: ' . htmlspecialchars($rk['current_item_name']) : 'KOSONG' ?>)">
                    <?= substr($rk['rack_code'], -5) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Detail Rak Terpilih (Popup) -->
<div class="modal-overlay" id="rackDetailModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalRackTitle">Detail Rak</h3>
            <button class="modal-close" onclick="closeModal('rackDetailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 14px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Status Rak</div>
                <div id="modalRackStatus" style="font-size: 16px; font-weight: 700;"></div>
            </div>
            <div style="margin-bottom: 14px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Nama Material / Produk</div>
                <div id="modalRackItem" style="font-size: 15px; font-weight: 600; color: var(--text-primary);">-</div>
            </div>
            <div style="margin-bottom: 14px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Barcode Terdaftar</div>
                <code id="modalRackBarcode" style="font-size: 14px; color: #60a5fa;">-</code>
            </div>
            <div style="margin-bottom: 14px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Kuantitas / Jumlah</div>
                <div id="modalRackQty" style="font-size: 15px; font-weight: 600;">-</div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Terakhir Diperbarui</div>
                <div id="modalRackDate" style="font-size: 12px; color: var(--text-secondary);">-</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('rackDetailModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT INTERAKTIF PETA RAK GUDANG & AUTO-FILL FORM -->
<!-- ===================================================================== -->
<script>
function switchTab(t) {
    window.location.href = 'dept_warehouse.php?tab=' + t;
}

function showRackDetail(data) {
    document.getElementById('modalRackTitle').textContent = 'Detail Rak: ' + data.rack_code;
    const statusEl = document.getElementById('modalRackStatus');
    if (data.is_occupied) {
        statusEl.innerHTML = '<span class="badge badge-danger">TERISI PALLET</span>';
        document.getElementById('modalRackItem').textContent = data.current_item_name || '-';
        document.getElementById('modalRackBarcode').textContent = data.current_barcode || '-';
        document.getElementById('modalRackQty').textContent = (data.current_qty || 0) + ' ' + (data.current_unit || 'PALLET');
    } else {
        statusEl.innerHTML = '<span class="badge badge-success">KOSONG (SIAP PAKAI)</span>';
        document.getElementById('modalRackItem').textContent = 'Tidak ada material';
        document.getElementById('modalRackBarcode').textContent = '-';
        document.getElementById('modalRackQty').textContent = '0';
    }
    document.getElementById('modalRackDate').textContent = data.last_updated || '-';
    openModal('rackDetailModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
