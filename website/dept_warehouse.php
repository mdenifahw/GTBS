<?php
/**
 * ==============================================================================
 * DEPARTEMEN GUDANG BAHAN (WAREHOUSE) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fitur Utama:
 * 1. Tab Penerima:
 *    - Input barang masuk, validasi NIK operator, pengecekan barcode, dan alokasi ke rak gudang.
 *    - Validasi Real-Time Status Rak:
 *      * Peringatan notifikasi interaktif jika nomor rak yang dimasukkan SUDAH TERISI.
 *      * Indikator hijau jika rak KOSONG dan siap digunakan.
 *      * Auto-fill nama material jika barcode terdaftar di sistem.
 * 2. Tab Pengirim:
 *    - Auto-Direction / Pencarian Otomatis Rak Berdasarkan Barcode:
 *      * Operator cukup scan barcode barang yang dicari, sistem langsung mengarahkan dan mengisi nomor rak penyimpanan.
 *      * Peringatan jika memilih rak yang KOSONG.
 *      * Pengeluaran stok bahan dari rak gudang menuju lini produksi (Mixing, Calender, dll).
 * 3. Tab Peta Digital 300 Rak:
 *    - Visualisasi denah rak A01-F10 dengan kode warna (Hijau: Kosong, Merah: Terisi).
 *    - Pencarian material / nomor rak dan link cepat aksi (Simpan / Ambil).
 * ==============================================================================
 */
$pageTitle = 'Dept Gudang Bahan (Warehouse)';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses Hierarki: Hanya Super Admin & Admin Dept terkait
Auth::requireDeptAccess('WH');

require_once __DIR__ . '/includes/header.php';

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

$currentShiftInt = (int)($shiftInfo['shift'] ?? 1);

// ==============================================================================
// BAGIAN 1: PEMROSESAN FORM PENERIMAAN BAHAN MASUK (RECEIVING)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_material') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $barcode = strtoupper(trim($_POST['barcode'] ?? ''));
    $rackCode = strtoupper(trim($_POST['rack_code'] ?? ''));
    $itemName = trim($_POST['item_name'] ?? '');
    $qty = (float)($_POST['qty'] ?? 1);
    $unit = trim($_POST['unit'] ?? 'KG');

    // Validation: Check if barcode is registered
    $bcCheck = $pdo->prepare("SELECT * FROM barcodes WHERE barcode = ?");
    $bcCheck->execute([$barcode]);
    $validBc = $bcCheck->fetch();

    if (!$validBc) {
        $message = "Gagal: Barcode '{$barcode}' TIDAK TERDAFTAR dalam sistem! Barcode harus didaftarkan di menu Barcode Management terlebih dahulu.";
        $messageType = 'danger';
    } elseif (empty($rackCode)) {
        $message = "Gagal: Anda harus memilih lokasi rak yang KOSONG.";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Pengecekan status rak saat ini
            $rCheck = $pdo->prepare("SELECT * FROM warehouse_racks WHERE rack_code = ?");
            $rCheck->execute([$rackCode]);
            $targetRack = $rCheck->fetch();

            if (!$targetRack) {
                throw new Exception("Nomor rak '{$rackCode}' tidak terdaftar dalam denah gudang!");
            }

            if ($targetRack['is_occupied']) {
                $occItem = $targetRack['current_item_name'] ?: 'Material lain';
                $occBc = $targetRack['current_barcode'] ?: '-';
                throw new Exception("PERINGATAN: Rak '{$rackCode}' SUDAH TERISI oleh {$occItem} (Barcode: {$occBc})! Silakan pilih rak lain yang berstatus KOSONG.");
            }

            // Mark rack as occupied
            $upRack = $pdo->prepare("
                UPDATE warehouse_racks 
                SET is_occupied = TRUE, status = 'OCCUPIED', current_item_name = ?, current_barcode = ?, current_qty = ?, current_unit = ?, last_updated = CURRENT_TIMESTAMP
                WHERE rack_code = ? AND is_occupied = FALSE
            ");
            $upRack->execute([$itemName, $barcode, $qty, $unit, $rackCode]);

            if ($upRack->rowCount() === 0) {
                throw new Exception("Rak {$rackCode} sudah terisi atau baru saja diisi operator lain!");
            }

            // Determine operator ID
            $opId = $currentUser['id'] ?? null;
            if (!$opId && !empty($nik)) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
                $uStmt->execute([$nik]);
                $opId = $uStmt->fetchColumn() ?: 1;
            }
            if (!$opId) $opId = 1;

            // Record to production activities
            $act = $pdo->prepare("
                INSERT INTO production_activities (
                    barcode, dept_id, section_id, prod_date, shift, status, 
                    qty, unit, operator_id, operator_nik, action_type, details
                ) VALUES (
                    ?, 1, 1, CURRENT_DATE, ?, 'NORMAL', 
                    ?, ?, ?, ?, 'RECEIVE_WAREHOUSE', ?
                )
            ");
            $act->execute([
                $barcode,
                $currentShiftInt,
                $qty,
                $unit,
                $opId,
                $nik,
                "Terima {$itemName} ({$qty} {$unit}) barcode {$barcode} disimpan di Rak {$rackCode}"
            ]);

            // Record to SCADA logs
            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'WH', 'penerima', ?, ?, 
                    ?, ?, 'PASS', CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$barcode, $rackCode, $nik, $itemName, $qty, 'Shift ' . $currentShiftInt]);

            $pdo->commit();
            $message = "Sukses! Material '{$itemName}' berhasil disimpan di Rak {$rackCode} dengan barcode {$barcode}.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses penerimaan: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ==============================================================================
// BAGIAN 2: PEMROSESAN FORM PENGIRIMAN BAHAN KELUAR (DISPATCH)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispatch_material') {
    $nik = trim($_POST['operator_nik'] ?? '');
    $rackCode = strtoupper(trim($_POST['rack_code'] ?? ''));
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
            $rStmt = $pdo->prepare("SELECT * FROM warehouse_racks WHERE rack_code = ?");
            $rStmt->execute([$rackCode]);
            $rackData = $rStmt->fetch();

            if (!$rackData) {
                throw new Exception("Nomor rak {$rackCode} tidak ditemukan dalam sistem gudang!");
            }

            if (!$rackData['is_occupied']) {
                throw new Exception("PERINGATAN: Rak {$rackCode} berstatus KOSONG! Tidak ada material yang dapat dikeluarkan.");
            }

            // Free the rack
            $freeRack = $pdo->prepare("
                UPDATE warehouse_racks 
                SET is_occupied = FALSE, status = 'EMPTY', current_item_name = NULL, current_barcode = NULL, current_qty = 0, last_updated = CURRENT_TIMESTAMP
                WHERE rack_code = ?
            ");
            $freeRack->execute([$rackCode]);

            // Determine operator ID
            $opId = $currentUser['id'] ?? null;
            if (!$opId && !empty($nik)) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
                $uStmt->execute([$nik]);
                $opId = $uStmt->fetchColumn() ?: 1;
            }
            if (!$opId) $opId = 1;

            // Log activity
            $act = $pdo->prepare("
                INSERT INTO production_activities (
                    barcode, dept_id, section_id, prod_date, shift, status, 
                    qty, unit, operator_id, operator_nik, action_type, details
                ) VALUES (
                    ?, 1, 2, CURRENT_DATE, ?, 'NORMAL', 
                    ?, ?, ?, ?, 'DISPATCH_WAREHOUSE', ?
                )
            ");
            $act->execute([
                $rackData['current_barcode'],
                $currentShiftInt,
                $rackData['current_qty'] ?: 1,
                $rackData['current_unit'] ?: 'KG',
                $opId,
                $nik,
                "Kirim {$rackData['current_item_name']} (Barcode: {$rackData['current_barcode']}) dari Rak {$rackCode} ke {$destinationDept} - {$destinationSection}. {$notes}"
            ]);

            // Record to SCADA logs
            $scada = $pdo->prepare("
                INSERT INTO production_scada_logs (
                    barcode, dept_id, section_id, machine_id, operator_nik, 
                    item_code, qty, quality_status, log_date, shift, log_hour
                ) VALUES (
                    ?, 'WH', 'pengirim', ?, ?, 
                    ?, ?, 'PASS', CURRENT_DATE, ?, EXTRACT(HOUR FROM CURRENT_TIME)
                )
            ");
            $scada->execute([$rackData['current_barcode'], $destinationDept, $nik, $rackData['current_item_name'], $rackData['current_qty'], 'Shift ' . $currentShiftInt]);

            $pdo->commit();
            $message = "Sukses! Material '{$rackData['current_item_name']}' dari Rak {$rackCode} berhasil dikirim ke {$destinationDept} - {$destinationSection}. Rak kini berstatus KOSONG.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal memproses pengiriman: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ==============================================================================
// BAGIAN 3: DATA RETRIEVAL & LOOKUP MAPS
// ==============================================================================
// Fetch EMPTY racks for Penerima dropdown
$emptyRacks = $pdo->query("SELECT rack_code, zone, row_num, tier_num FROM warehouse_racks WHERE is_occupied = FALSE ORDER BY zone, rack_code")->fetchAll(PDO::FETCH_ASSOC);

// Fetch OCCUPIED racks for Pengirim dropdown
$occupiedRacks = $pdo->query("SELECT rack_code, zone, row_num, tier_num, current_item_name, current_barcode, current_qty, current_unit, last_updated FROM warehouse_racks WHERE is_occupied = TRUE ORDER BY zone, rack_code")->fetchAll(PDO::FETCH_ASSOC);

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
$allRacks = $allRacksStmt->fetchAll(PDO::FETCH_ASSOC);

// Build fast lookup dictionary for JavaScript
$allRacksMap = [];
$occupiedBarcodesMap = [];

$allRawRacks = $pdo->query("SELECT rack_code, zone, row_num, tier_num, is_occupied, status, current_item_name, current_barcode, current_qty, current_unit, last_updated FROM warehouse_racks ORDER BY rack_code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allRawRacks as $rk) {
    $cCode = strtoupper(trim($rk['rack_code']));
    $rkData = [
        'rack_code'         => $rk['rack_code'],
        'zone'              => $rk['zone'],
        'row_num'           => $rk['row_num'],
        'tier_num'          => $rk['tier_num'],
        'is_occupied'       => (bool)$rk['is_occupied'],
        'status'            => $rk['is_occupied'] ? 'OCCUPIED' : 'EMPTY',
        'current_item_name' => $rk['current_item_name'] ?? '',
        'current_barcode'   => $rk['current_barcode'] ?? '',
        'current_qty'       => (float)($rk['current_qty'] ?? 0),
        'current_unit'      => $rk['current_unit'] ?? 'KG',
        'last_updated'      => $rk['last_updated'] ? date('d-m-Y H:i', strtotime($rk['last_updated'])) : '-'
    ];
    $allRacksMap[$cCode] = $rkData;
    if ($rk['is_occupied'] && !empty($rk['current_barcode'])) {
        $occupiedBarcodesMap[strtoupper(trim($rk['current_barcode']))] = $rkData;
    }
}

// Fetch registered barcodes for instant auto-complete in Penerima (barcode -> product_name)
$registeredBarcodes = $pdo->query("SELECT barcode, product_name FROM barcodes ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
$registeredBarcodesMap = [];
foreach ($registeredBarcodes as $rb) {
    $registeredBarcodesMap[strtoupper(trim($rb['barcode']))] = $rb['product_name'];
}

// All departments and sections for Destination dropdown
$destDepts = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$destSections = $pdo->query("SELECT s.id, s.dept_id, s.code, s.name, d.code as dept_code FROM sections s JOIN departments d ON s.dept_id = d.id ORDER BY d.id, s.sequence, s.id")->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                Penerimaan Bahan Baku (Penerima Gudang)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">🚨 Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_warehouse.php?tab=penerima" id="form_receive">
            <input type="hidden" name="action" value="receive_material">

            <!-- 1. NIK Karyawan (Universal First Field) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Karyawan Pelapor</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-grid">
                <!-- 2. Scan Barcode Bahan -->
                <div class="form-group">
                    <label class="form-label required">2. Scan / Input Barcode Bahan</label>
                    <input type="text" id="receive_barcode" name="barcode" class="form-input" placeholder="Scan barcode produk / bahan..." required autocomplete="off" oninput="checkReceiveBarcode(this.value)">
                    <small id="receive_barcode_hint" style="color: var(--text-muted); font-size: 11px;">Barcode wajib sudah terdaftar di sistem.</small>
                    <div id="receive_barcode_feedback" style="margin-top: 6px; font-size: 12px; display: none;"></div>
                </div>

                <!-- 3. Lokasi Rak Penyimpanan (Hanya Rak Kosong) -->
                <div class="form-group">
                    <label class="form-label required">3. Lokasi Rak Penyimpanan (Hanya Rak Kosong)</label>
                    <input type="text" id="receive_rack_code" name="rack_code" list="dl_wh_empty_racks" class="form-input" placeholder="Pilih / ketik nomor rak (contoh: A1, B12)..." required autocomplete="off" oninput="checkReceiveRack(this.value)">
                    <datalist id="dl_wh_empty_racks">
                        <option value="">-- Pilih Dari <?= count($emptyRacks) ?> Rak Kosong --</option>
                        <?php foreach ($emptyRacks as $er): ?>
                            <option value="<?= htmlspecialchars($er['rack_code']) ?>">
                                [Zona <?= htmlspecialchars($er['zone']) ?>] Rak <?= htmlspecialchars($er['rack_code']) ?> (Status: KOSONG)
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <small style="color: var(--text-muted); font-size: 11px;">Hanya rak kosong yang dapat digunakan untuk menyimpan.</small>
                </div>
            </div>

            <!-- Dynamic Interactive Notification for Occupied/Empty Rack -->
            <div id="receive_rack_feedback" style="margin-bottom: 16px; display: none;"></div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Nama Bahan / Item</label>
                    <input type="text" id="receive_item_name" name="item_name" class="form-input" placeholder="Contoh: Natural Rubber RSS-1" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Jumlah (Qty) &amp; Satuan</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="number" step="0.01" name="qty" class="form-input" placeholder="Qty" value="100" required>
                        <select name="unit" class="form-input" style="width: 110px;">
                            <option value="KG">KG</option>
                            <option value="ROLL">ROLL</option>
                            <option value="PALLET">PALLET</option>
                            <option value="BAG">BAG</option>
                            <option value="PCS">PCS</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" id="btn_submit_receive" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px; font-weight: 600;">
                Simpan &amp; Proses Penerimaan Material ke Rak
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Tab 2: Pengirim (Bahan Keluar) -->
<?php if ($activeTab === 'pengirim'): ?>
    <div class="card" style="max-width: 760px; margin: 0 auto;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                Pengiriman Bahan ke Departemen Lain (Pengirim Gudang)
            </div>
            <button class="btn btn-secondary btn-sm" onclick="openModal('downtimeModal')">🚨 Lapor Kendala 4M</button>
        </div>

        <form class="scanner-workflow-form" method="POST" action="dept_warehouse.php?tab=pengirim" id="form_dispatch">
            <input type="hidden" name="action" value="dispatch_material">

            <!-- 1. NIK Karyawan (Universal First Field) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">1. NIK Karyawan Pelapor</label>
                <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required autofocus>
            </div>

            <!-- FITUR UTAMA 1: Auto-Direction / Pencarian Cepat Berdasarkan Barcode -->
            <div class="form-group" style="margin-bottom: 16px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 8px; padding: 14px;">
                <label class="form-label" style="color: #60a5fa; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    2. Scan / Input Barcode Barang yang Dicari (Auto-Arahkan ke Rak)
                </label>
                <input type="text" id="dispatch_search_barcode" class="form-input" placeholder="Scan barcode barang yang akan diambil... (Contoh: DBB01261000125)" autocomplete="off" oninput="autoLocateRackByBarcode(this.value)">
                <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 4px;">
                    ⚡ Sistem otomatis mendeteksi dan mengarahkan operator ke rak tempat barang tersebut tersimpan.
                </small>
                <!-- Visual Auto-Direction Result Banner -->
                <div id="dispatch_barcode_feedback" style="margin-top: 10px; display: none;"></div>
            </div>

            <!-- FITUR UTAMA 2: Diambil Dari Rak (Hanya Rak TERISI) -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label required">3. Diambil Dari Rak (Hanya Rak TERISI)</label>
                <input type="text" id="dispatch_rack_code" name="rack_code" list="dl_wh_occupied_racks" class="form-input" placeholder="Pilih rak terisi atau scan kode rak..." required autocomplete="off" oninput="checkDispatchRack(this.value)">
                <datalist id="dl_wh_occupied_racks">
                    <option value="">-- Pilih Dari <?= count($occupiedRacks) ?> Rak Terisi --</option>
                    <?php foreach ($occupiedRacks as $oc): ?>
                        <option value="<?= htmlspecialchars($oc['rack_code']) ?>">
                            [Zona <?= htmlspecialchars($oc['zone']) ?>] Rak <?= htmlspecialchars($oc['rack_code']) ?>: <?= htmlspecialchars($oc['current_item_name']) ?> (<?= htmlspecialchars($oc['current_qty']) ?> <?= htmlspecialchars($oc['current_unit']) ?>) - Barcode: <?= htmlspecialchars($oc['current_barcode']) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
                <small style="color: var(--text-muted); font-size: 11px;">Memilih rak ini akan mengeluarkan material dan mengosongkan status rak.</small>
                <!-- Dynamic Feedback on Rack Selection in Dispatch -->
                <div id="dispatch_rack_feedback" style="margin-top: 8px; display: none;"></div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">4. Departemen Tujuan</label>
                    <select id="dispatch_destination_dept" name="destination_dept" class="form-input" required onchange="filterDestinationSections(this.value)">
                        <?php foreach ($destDepts as $dd): ?>
                            <option value="<?= htmlspecialchars($dd['code']) ?>" <?= $dd['code'] === 'MAT' ? 'selected' : '' ?>>
                                Dept <?= htmlspecialchars($dd['id']) ?> - <?= htmlspecialchars($dd['name']) ?> (<?= htmlspecialchars($dd['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">5. Bagian (Sub-Section) Tujuan</label>
                    <select id="dispatch_destination_section" name="destination_section" class="form-input" required>
                        <?php foreach ($destSections as $ds): ?>
                            <option value="<?= htmlspecialchars($ds['code']) ?>" data-dept="<?= htmlspecialchars($ds['dept_code']) ?>" <?= $ds['code'] === 'MAT-MIX' ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($ds['dept_code']) ?>] <?= htmlspecialchars($ds['code']) ?> - <?= htmlspecialchars($ds['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">6. Catatan / No Surat Jalan</label>
                <input type="text" name="notes" class="form-input" placeholder="Keterangan pengiriman / nomor lory / pallet...">
            </div>

            <button type="submit" id="btn_submit_dispatch" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 600;">
                Simpan &amp; Proses Pengeluaran &amp; Transfer
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
                <input type="text" name="rack_search" class="form-input" style="padding: 6px 12px; font-size: 13px;" placeholder="Cari material / barcode / rak..." value="<?= htmlspecialchars($rackSearch) ?>">
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
            <div style="margin-bottom: 14px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Terakhir Diperbarui</div>
                <div id="modalRackDate" style="font-size: 12px; color: var(--text-secondary);">-</div>
            </div>
            <div id="modalRackActionBox" style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                <!-- Dynamic action button based on empty / occupied -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('rackDetailModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT INTERAKTIF PETA RAK GUDANG & REAL-TIME VALIDASI -->
<!-- ===================================================================== -->
<script>
// Data Lookup Maps injected from backend PHP
const allRacksData = <?= json_encode($allRacksMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const occupiedBarcodesMap = <?= json_encode($occupiedBarcodesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const registeredBarcodesMap = <?= json_encode($registeredBarcodesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function switchTab(t) {
    window.location.href = 'dept_warehouse.php?tab=' + t;
}

// -----------------------------------------------------------------------------
// TAB 1: PENERIMAAN (INBOUND) VALIDATION & ALERTING
// -----------------------------------------------------------------------------
function checkReceiveBarcode(barcode) {
    barcode = barcode.trim().toUpperCase();
    const fb = document.getElementById('receive_barcode_feedback');
    const nameInput = document.getElementById('receive_item_name');
    if (!barcode) {
        if (fb) fb.style.display = 'none';
        return;
    }

    if (registeredBarcodesMap[barcode]) {
        const prodName = registeredBarcodesMap[barcode];
        if (nameInput && (!nameInput.value || nameInput.value === '')) {
            nameInput.value = prodName;
        }
        if (fb) {
            fb.style.display = 'block';
            fb.innerHTML = `<span style="color: #34d399; font-weight: 600;">✅ Barcode Terdaftar: ${escapeHtml(prodName)}</span>`;
        }
    } else {
        if (fb) {
            fb.style.display = 'block';
            fb.innerHTML = `<span style="color: #fca5a5; font-size: 11px;">⚠️ Barcode belum terdaftar di sistem. Pastikan sudah di-generate terlebih dahulu.</span>`;
        }
    }
}

function checkReceiveRack(code) {
    code = code.trim().toUpperCase();
    const fb = document.getElementById('receive_rack_feedback');
    const input = document.getElementById('receive_rack_code');
    const submitBtn = document.getElementById('btn_submit_receive');

    if (!code) {
        fb.style.display = 'none';
        input.style.borderColor = '';
        input.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    const rack = allRacksData[code];
    if (!rack) {
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fbbf24; padding: 12px 16px; border-radius: 8px;">
                ⚠️ <strong>Perhatian:</strong> Kode rak <code>${escapeHtml(code)}</code> tidak ditemukan dalam denah gudang. Pastikan format rak benar (contoh: A1, B12).
            </div>`;
        input.style.borderColor = '#f59e0b';
        input.setCustomValidity('Kode rak tidak terdaftar');
        if (submitBtn) submitBtn.disabled = true;
    } else if (rack.is_occupied) {
        // FITUR UTAMA: Memberikan pesan notifikasi bahwa rak tersebut sudah terisi!
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(239, 68, 68, 0.18); border: 1px solid #ef4444; color: #fca5a5; padding: 14px 18px; border-radius: 8px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <div style="font-size: 22px; line-height: 1;">⚠️</div>
                    <div style="flex: 1;">
                        <div style="font-size: 14px; font-weight: 800; color: #ef4444; margin-bottom: 4px;">
                            PERINGATAN: RAK ${escapeHtml(rack.rack_code)} SUDAH TERISI!
                        </div>
                        <div style="font-size: 13px; color: var(--text-primary); margin-bottom: 6px;">
                            Rak <strong>${escapeHtml(rack.rack_code)}</strong> (Zona ${escapeHtml(rack.zone)}) saat ini sudah digunakan untuk menyimpan:
                        </div>
                        <div style="background: rgba(0,0,0,0.3); border-left: 3px solid #ef4444; padding: 8px 12px; border-radius: 4px; font-size: 12px;">
                            📦 <strong>Material:</strong> ${escapeHtml(rack.current_item_name)}<br>
                            🏷️ <strong>Barcode:</strong> <code>${escapeHtml(rack.current_barcode)}</code><br>
                            ⚖️ <strong>Jumlah:</strong> <strong>${rack.current_qty} ${escapeHtml(rack.current_unit)}</strong>
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; color: #f87171; font-weight: 600;">
                            🚫 Anda tidak dapat menyimpan di rak ini. Silakan pilih nomor rak lain yang berstatus KOSONG!
                        </div>
                    </div>
                </div>
            </div>`;
        input.style.borderColor = '#ef4444';
        input.setCustomValidity('Rak ini sudah terisi! Silakan pilih rak kosong.');
        if (submitBtn) submitBtn.disabled = true;
    } else {
        // Rak KOSONG & Valid
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #a7f3d0; padding: 10px 14px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>✅ Rak <strong>${escapeHtml(rack.rack_code)}</strong> (Zona ${escapeHtml(rack.zone)}) <strong>KOSONG &amp; TERSEDIA</strong> untuk penyimpanan.</span>
            </div>`;
        input.style.borderColor = '#10b981';
        input.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;
    }
}

// -----------------------------------------------------------------------------
// TAB 2: PENGIRIMAN (OUTBOUND) AUTO-DIRECTION & VALIDATION
// -----------------------------------------------------------------------------
// FITUR UTAMA: Operator memasukkan barcode, sistem otomatis mengarahkan ke rak yang ada barang tersebut
function autoLocateRackByBarcode(barcode) {
    barcode = barcode.trim().toUpperCase();
    const fb = document.getElementById('dispatch_barcode_feedback');
    const rackInput = document.getElementById('dispatch_rack_code');
    const rackFeedback = document.getElementById('dispatch_rack_feedback');
    const submitBtn = document.getElementById('btn_submit_dispatch');

    if (!barcode) {
        fb.style.display = 'none';
        return;
    }

    const rack = occupiedBarcodesMap[barcode];
    if (rack) {
        // Otomatis arahkan dan masukkan ke input nomor rak!
        rackInput.value = rack.rack_code;
        rackInput.style.borderColor = '#10b981';
        rackInput.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;

        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(16, 185, 129, 0.18); border: 1px solid #10b981; color: #a7f3d0; padding: 14px 18px; border-radius: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <div style="font-size: 24px; line-height: 1;">📍</div>
                    <div style="flex: 1;">
                        <div style="font-size: 15px; font-weight: 800; color: #34d399; margin-bottom: 4px;">
                            BARANG DITEMUKAN! DIARAHKAN KE RAK: <span style="background: #065f46; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 16px;">${escapeHtml(rack.rack_code)}</span>
                        </div>
                        <div style="font-size: 13px; color: var(--text-primary); margin-top: 6px;">
                            <strong>Nama Material:</strong> ${escapeHtml(rack.current_item_name)}<br>
                            <strong>Lokasi Denah:</strong> Zona ${escapeHtml(rack.zone)} (Baris ${rack.row_num || '-'}, Tingkat ${rack.tier_num || '-'})<br>
                            <strong>Stok Tersedia:</strong> <span style="color: #60a5fa; font-weight: 700;">${rack.current_qty} ${escapeHtml(rack.current_unit)}</span>
                        </div>
                        <div style="margin-top: 8px; font-size: 11px; color: #6ee7b7; font-weight: 600;">
                            ✅ Nomor rak telah otomatis dimasukkan ke kolom formulir di bawah.
                        </div>
                    </div>
                </div>
            </div>`;

        if (rackFeedback) rackFeedback.style.display = 'none';
    } else {
        // Barcode tidak ada di rak gudang manapun
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: 8px;">
                ⚠️ <strong>Material Tidak Ditemukan di Rak Gudang:</strong><br>
                Barcode <code>${escapeHtml(barcode)}</code> tidak tercatat berada di rak gudang manapun saat ini. Pastikan barcode benar atau barang belum dikeluarkan sebelumnya.
            </div>`;
    }
}

function checkDispatchRack(code) {
    code = code.trim().toUpperCase();
    const fb = document.getElementById('dispatch_rack_feedback');
    const input = document.getElementById('dispatch_rack_code');
    const searchBc = document.getElementById('dispatch_search_barcode');
    const submitBtn = document.getElementById('btn_submit_dispatch');

    if (!code) {
        fb.style.display = 'none';
        input.style.borderColor = '';
        input.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    const rack = allRacksData[code];
    if (!rack) {
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fbbf24; padding: 10px 14px; border-radius: 8px;">
                ⚠️ Kode rak <code>${escapeHtml(code)}</code> tidak ditemukan dalam sistem gudang.
            </div>`;
        input.style.borderColor = '#f59e0b';
        input.setCustomValidity('Kode rak tidak valid');
        if (submitBtn) submitBtn.disabled = true;
    } else if (!rack.is_occupied) {
        // Rak KOSONG - Tidak bisa dikeluarkan!
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(239, 68, 68, 0.18); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: 8px;">
                ⚠️ <strong>PERINGATAN: RAK INI KOSONG!</strong><br>
                Rak <strong>${escapeHtml(rack.rack_code)}</strong> saat ini tidak menyimpan material apapun. Pengiriman hanya dapat diproses dari rak yang berstatus <strong>TERISI</strong>.
            </div>`;
        input.style.borderColor = '#ef4444';
        input.setCustomValidity('Rak ini kosong! Tidak ada barang untuk dikirim.');
        if (submitBtn) submitBtn.disabled = true;
    } else {
        // Rak TERISI - Valid untuk dispatch
        fb.style.display = 'block';
        fb.innerHTML = `
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 10px 14px; border-radius: 8px;">
                📦 <strong>Siap Kirim:</strong> ${escapeHtml(rack.current_item_name)} | Barcode: <code>${escapeHtml(rack.current_barcode)}</code> | Stok: <strong>${rack.current_qty} ${escapeHtml(rack.current_unit)}</strong>
            </div>`;
        input.style.borderColor = '#10b981';
        input.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;

        // Sinkronisasi otomatis ke kolom scan barcode jika kosong
        if (searchBc && !searchBc.value) {
            searchBc.value = rack.current_barcode;
        }
    }
}

function filterDestinationSections(deptCode) {
    const sectSelect = document.getElementById('dispatch_destination_section');
    if (!sectSelect) return;
    let hasMatch = false;
    Array.from(sectSelect.options).forEach(opt => {
        const optDept = opt.getAttribute('data-dept');
        if (!deptCode || optDept === deptCode) {
            opt.style.display = '';
            if (!hasMatch) {
                sectSelect.value = opt.value;
                hasMatch = true;
            }
        } else {
            opt.style.display = 'none';
        }
    });
}

// -----------------------------------------------------------------------------
// TAB 3: PETA RAK GUDANG & QUICK ACTION
// -----------------------------------------------------------------------------
function showRackDetail(data) {
    document.getElementById('modalRackTitle').textContent = 'Detail Rak: ' + data.rack_code;
    const statusEl = document.getElementById('modalRackStatus');
    const actBox = document.getElementById('modalRackActionBox');

    if (data.is_occupied) {
        statusEl.innerHTML = '<span class="badge badge-danger">TERISI PALLET</span>';
        document.getElementById('modalRackItem').textContent = data.current_item_name || '-';
        document.getElementById('modalRackBarcode').textContent = data.current_barcode || '-';
        document.getElementById('modalRackQty').textContent = (data.current_qty || 0) + ' ' + (data.current_unit || 'KG');

        actBox.innerHTML = `
            <a href="dept_warehouse.php?tab=pengirim&rack_code=${encodeURIComponent(data.rack_code)}&barcode=${encodeURIComponent(data.current_barcode || '')}" class="btn btn-warning" style="width: 100%; text-align: center; text-decoration: none; display: block; padding: 10px;">
                📤 Ambil / Kirim Material dari Rak Ini
            </a>`;
    } else {
        statusEl.innerHTML = '<span class="badge badge-success">KOSONG (SIAP PAKAI)</span>';
        document.getElementById('modalRackItem').textContent = 'Tidak ada material';
        document.getElementById('modalRackBarcode').textContent = '-';
        document.getElementById('modalRackQty').textContent = '0';

        actBox.innerHTML = `
            <a href="dept_warehouse.php?tab=penerima&rack_code=${encodeURIComponent(data.rack_code)}" class="btn btn-primary" style="width: 100%; text-align: center; text-decoration: none; display: block; padding: 10px;">
                📥 Simpan Barang Baru ke Rak Ini
            </a>`;
    }
    document.getElementById('modalRackDate').textContent = data.last_updated || '-';
    openModal('rackDetailModal');
}

// Inisialisasi awal saat halaman selesai dimuat (menangani link query parameter)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const prefillRack = urlParams.get('rack_code');
    const prefillBarcode = urlParams.get('barcode');

    // Inisialisasi Tab Penerima
    const recRack = document.getElementById('receive_rack_code');
    if (recRack && prefillRack) {
        recRack.value = prefillRack;
        checkReceiveRack(prefillRack);
    }
    const recBc = document.getElementById('receive_barcode');
    if (recBc && prefillBarcode) {
        recBc.value = prefillBarcode;
        checkReceiveBarcode(prefillBarcode);
    }

    // Inisialisasi Tab Pengirim
    const dispBc = document.getElementById('dispatch_search_barcode');
    if (dispBc && prefillBarcode) {
        dispBc.value = prefillBarcode;
        autoLocateRackByBarcode(prefillBarcode);
    }
    const dispRack = document.getElementById('dispatch_rack_code');
    if (dispRack && prefillRack) {
        dispRack.value = prefillRack;
        checkDispatchRack(prefillRack);
    }

    // Filter awal seksi tujuan
    const destDept = document.getElementById('dispatch_destination_dept');
    if (destDept) {
        filterDestinationSections(destDept.value);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>