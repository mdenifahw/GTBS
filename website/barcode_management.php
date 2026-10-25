<?php

/**
 * ==============================================================================
 * MANAJEMEN MASTER PRODUK & REGISTRASI BARCODE - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Pusat pengelolaan master data produk/aktivitas dan pembuatan nomor barcode resmi pabrik.
 * - Mengatur siklus hidup barcode (AVAILABLE -> IN_USE -> FINISHED -> SCRAPPED).
 * - Fitur Utama:
 *   1. Tambah Master Produk/Aktivitas baru beserta kategori, departemen, dan satuannya.
 *   2. Generator Barcode Otomatis: Membuat satu atau banyak barcode (bulk) sekaligus dengan prefix standar.
 *   3. Pencarian & Filter Cepat: Berdasarkan status penggunaan, departemen, tipe, dan kata kunci.
 *   4. Ekspor Data: Mengunduh katalog master dan daftar barcode terdaftar ke format file Excel/CSV.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Format Penomoran Barcode:
 *    Cari blok `action === 'generate_barcode'`, sesuaikan pola prefix (default: GT-[DEPT]-[RANDOM]).
 * 2. Menambah Status Barcode Baru:
 *    Ubah opsi select `status` di modal edit dan query filter (misal: 'HOLD_QC', 'TRANSIT').
 * 3. Menambah Kolom Spesifikasi Ban:
 *    Tambahkan input field pada form Master Produk dan simpan ke kolom `spec` atau `description`.
 * ==============================================================================
 */
$pageTitle = 'Generate & Registrasi Barcode';
require_once __DIR__ . '/helpers/auth.php';

// Proteksi Hak Akses: HANYA Super Admin & Admin Dept
if (!Auth::canGenerateBarcode()) {
    http_response_code(403);
    $roleName = Auth::getRoleLabel();
    die("
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <title>403 Akses Ditolak - GT Barcode System</title>
            <link rel='icon' type='image/png' href='assets/img/Logo_Aplikasi.png'>
            <link rel='stylesheet' href='assets/css/style.css'>
            <style>
                body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0b0f19; color: #fff; font-family: sans-serif; text-align: center; padding: 20px; }
                .error-card { background: rgba(17, 24, 39, 0.95); border: 1px solid #ef4444; border-radius: 12px; padding: 32px; max-width: 520px; }
            </style>
        </head>
        <body>
            <div class='error-card'>
                <h1 style='color: #ef4444; font-size: 26px; margin-bottom: 12px;'>⚠️ 403 Akses Ditolak</h1>
                <p style='color: #d1d5db; line-height: 1.6;'>
                    Akun Anda dengan jabatan <strong>{$roleName}</strong> tidak memiliki wewenang untuk Generate & Registrasi Barcode.
                </p>
                <p style='color: #9ca3af; font-size: 13px; margin-top: 8px;'>
                    Sesuai Hierarki Hak Akses Aplikasi, modul ini diperuntukkan khusus bagi Super Admin dan Admin Departemen.
                </p>
                <a href='dashboard.php' class='btn btn-primary' style='display: inline-block; margin-top: 20px; text-decoration: none;'>Kembali ke Dashboard</a>
            </div>
        </body>
        </html>
    ");
}

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

$currentMonthYear = date('Y-m');

// ==============================================================================
// BAGIAN 1: PEMROSESAN FORM - TAMBAH MASTER PRODUK / AKTIVITAS BARU
// ==============================================================================
// Handle Form 1: Tambah Master Produk / Aktivitas / Mesin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_master') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $category = strtoupper(trim($_POST['category'] ?? 'PRODUCT')); // PRODUCT, ACTIVITY, MACHINE
    if (!in_array($category, ['PRODUCT', 'ACTIVITY', 'MACHINE'])) {
        $category = 'PRODUCT';
    }

    $deptId = $_POST['dept_id'] ?? '';
    $sectionId = $_POST['section_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $unit = strtoupper(trim($_POST['unit'] ?? ($category === 'MACHINE' ? 'UNIT' : 'PCS')));
    $machineTypeConfig = $_POST['machine_type_config'] ?? 'SINGLE'; // SINGLE, DUAL_LR, CUSTOM_SUB
    $customSub = strtoupper(trim($_POST['custom_sub'] ?? ''));

    if (empty($code) || empty($name)) {
        $message = 'Kode dan nama item master wajib diisi.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $insertMasterStmt = $pdo->prepare("
                INSERT INTO barcode_master (product_code, product_name, product_type, code, name, category, dept_id, section_id, description, unit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (product_code) DO UPDATE 
                SET product_name = EXCLUDED.product_name, product_type = EXCLUDED.product_type,
                    name = EXCLUDED.name, category = EXCLUDED.category, dept_id = EXCLUDED.dept_id,
                    section_id = EXCLUDED.section_id, description = EXCLUDED.description, unit = EXCLUDED.unit
            ");

            // Otomatis daftarkan nomor identitas barcode awal untuk mesin agar siap discan
            $insertBarcodeStmt = $pdo->prepare("
                INSERT INTO barcodes (barcode, product_code, product_name, barcode_type, dept_id, section_id, seq_number, month_year, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, 'GENERATED', ?)
                ON CONFLICT (barcode) DO NOTHING
            ");

            if ($category === 'MACHINE' && $machineTypeConfig === 'DUAL_LR') {
                // Mesin dengan 2 cavity/sisi simultan (Left & Right), misal Curing Press
                $codeL = "{$code}-L";
                $nameL = "{$name} (Mold Left / Kiri)";
                $descL = ($description ? $description . " | " : "") . "Identitas Mesin Sisi Left (Cavity Kiri)";
                $insertMasterStmt->execute([$codeL, $nameL, 'MACHINE', $codeL, $nameL, 'MACHINE', $deptId, $sectionId, $descL, 'UNIT']);
                $insertBarcodeStmt->execute([$codeL, $codeL, $nameL, 'MACHINE', $deptId, $sectionId, $currentMonthYear, $currentUser['nik'] ?? 'ADMIN']);

                $codeR = "{$code}-R";
                $nameR = "{$name} (Mold Right / Kanan)";
                $descR = ($description ? $description . " | " : "") . "Identitas Mesin Sisi Right (Cavity Kanan)";
                $insertMasterStmt->execute([$codeR, $nameR, 'MACHINE', $codeR, $nameR, 'MACHINE', $deptId, $sectionId, $descR, 'UNIT']);
                $insertBarcodeStmt->execute([$codeR, $codeR, $nameR, 'MACHINE', $deptId, $sectionId, $currentMonthYear, $currentUser['nik'] ?? 'ADMIN']);

                $pdo->commit();
                $message = "Sukses! Master & Barcode Mesin Dual Cavity berhasil didaftarkan: '{$codeL}' (Left) dan '{$codeR}' (Right).";
            } elseif ($category === 'MACHINE' && $machineTypeConfig === 'CUSTOM_SUB' && !empty($customSub)) {
                $codeSub = "{$code}-{$customSub}";
                $nameSub = "{$name} (Sub: {$customSub})";
                $descSub = ($description ? $description . " | " : "") . "Sub-Identitas Stasiun {$customSub}";
                $insertMasterStmt->execute([$codeSub, $nameSub, 'MACHINE', $codeSub, $nameSub, 'MACHINE', $deptId, $sectionId, $descSub, 'UNIT']);
                $insertBarcodeStmt->execute([$codeSub, $codeSub, $nameSub, 'MACHINE', $deptId, $sectionId, $currentMonthYear, $currentUser['nik'] ?? 'ADMIN']);

                $pdo->commit();
                $message = "Sukses! Master & Barcode Mesin '{$codeSub}' ({$nameSub}) berhasil didaftarkan.";
            } else {
                // Unit Tunggal / Standar
                $insertMasterStmt->execute([$code, $name, $category, $code, $name, $category, $deptId, $sectionId, $description, $unit]);

                // Mesin dan Aktivitas memiliki sifat barcode identitas tetap yang dipergunakan sekali
                // Sistem langsung mendaftarkan 1 barcode resmi tetap ke database agar siap discan di lapangan
                if ($category === 'MACHINE' || $category === 'ACTIVITY') {
                    $insertBarcodeStmt->execute([$code, $code, $name, $category, $deptId, $sectionId, $currentMonthYear, $currentUser['nik'] ?? 'ADMIN']);
                }
                $pdo->commit();
                $labelCat = $category === 'MACHINE' ? 'Mesin/Alat' : ($category === 'ACTIVITY' ? 'Aktivitas' : 'Produk');
                $extraNote = ($category !== 'PRODUCT') ? " Barcode identitas tetap '<strong>{$code}</strong>' telah otomatis diterbitkan dan siap dipindai di lapangan." : "";
                $message = "Master {$labelCat} '{$name}' ({$code}) berhasil didaftarkan!{$extraNote}";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal menyimpan master: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Form 2: Registrasi Batch Barcode Berbasis Item Code Barcode
$curYY = date('y'); // Contoh: "26" (tahun 2026)
$curMM = date('m'); // Contoh: "10" (bulan Oktober)
$curMonthYearKey = date('ym'); // Format tahun-bulan: "2610"
$currentMonthYear = date('Y-m'); // Format standar: "2026-10"

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_batch') {
    $masterCode = strtoupper(trim($_POST['master_code'] ?? ''));
    $qtyToGen = (int)($_POST['qty_to_gen'] ?? 1);

    if ($qtyToGen < 1) $qtyToGen = 1;
    if ($qtyToGen > 500) $qtyToGen = 500;

    // Cari data master item berdasarkan Item Code
    $mStmt = $pdo->prepare("SELECT * FROM barcode_master WHERE UPPER(code) = ? OR UPPER(product_code) = ? LIMIT 1");
    $mStmt->execute([$masterCode, $masterCode]);
    $master = $mStmt->fetch(PDO::FETCH_ASSOC);

    if (!$master) {
        $message = "Item Code '{$masterCode}' belum terdaftar sebagai Master! Silakan daftarkan terlebih dahulu pada form Tambah Master di sebelah kanan.";
        $messageType = 'danger';
    } elseif (($master['category'] ?? 'PRODUCT') !== 'PRODUCT') {
        $catLabel = ($master['category'] === 'MACHINE') ? 'MESIN / ALAT' : 'AKTIVITAS';
        $message = "Pemberitahuan: Item Code <strong>{$masterCode}</strong> adalah Kategori <strong>{$catLabel}</strong>.<br>" .
            "Barcode untuk Mesin dan Aktivitas bersifat <strong>identitas tetap</strong> yang dipergunakan sekali dan telah otomatis terdaftar saat pembuatan master.<br>" .
            "Kotak penerbitan serial batch dengan nomor urut <code>[YY][MM][00001]</code> hanya dikhususkan untuk <strong>Kategori PRODUK</strong> yang diproduksi terus-menerus.";
        $messageType = 'warning';
    } else {
        try {
            $pdo->beginTransaction();

            $itemCodeActual = strtoupper($master['code'] ?: $master['product_code']);
            $itemNameActual = $master['name'] ?: $master['product_name'];
            $itemCategory = $master['category'] ?: 'PRODUCT';

            // Ambil nomor urut tertinggi pada bulan berjalan untuk Item Code ini
            // [ATURAN SISTEM]: Nomor urut akan otomatis restart dari 00001 lagi jika dimulai bulan baru (misal pergantian dari 2610 ke 2611)
            $seqStmt = $pdo->prepare("
                SELECT COALESCE(MAX(seq_number), 0) FROM barcodes 
                WHERE (month_year = ? OR month_year = ?) AND UPPER(product_code) = ?
            ");
            $seqStmt->execute([$curMonthYearKey, $currentMonthYear, $itemCodeActual]);
            $lastSeq = (int)$seqStmt->fetchColumn();

            $insertStmt = $pdo->prepare("
                INSERT INTO barcodes (barcode, product_code, product_name, barcode_type, dept_id, section_id, seq_number, month_year, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'GENERATED', ?)
            ");

            $generatedList = [];
            $startSeqNum = $lastSeq + 1;
            $endSeqNum = $lastSeq + $qtyToGen;

            for ($i = 1; $i <= $qtyToGen; $i++) {
                $newSeq = $lastSeq + $i;
                $seq5Digit = sprintf('%05d', $newSeq);

                // [STRUKTUR BARCODE PABRIK GT]:
                // [ItemCode][YY][MM][00001]
                // Contoh: DBB01 + 26 + 10 + 00001 = DBB01261000001
                $barcodeStr = sprintf("%s%s%s%s", $itemCodeActual, $curYY, $curMM, $seq5Digit);

                $insertStmt->execute([
                    $barcodeStr,
                    $itemCodeActual,
                    $itemNameActual,
                    $itemCategory,
                    $master['dept_id'],
                    $master['section_id'],
                    $newSeq,
                    $curMonthYearKey,
                    $currentUser['nik'] ?? 'ADMIN'
                ]);
                $generatedList[] = $barcodeStr;
            }

            $pdo->commit();
            $startSeqFormatted = sprintf('%05d', $startSeqNum);
            $endSeqFormatted = sprintf('%05d', $endSeqNum);
            $message = "Sukses meregistrasi <strong>{$qtyToGen} pcs</strong> barcode baru untuk Item Code <strong>{$itemCodeActual}</strong> ({$itemNameActual})!<br>" .
                "Nomor Urut Bulan Ini (Periode {$curYY}{$curMM}): <strong>{$startSeqFormatted}</strong> s/d <strong>{$endSeqFormatted}</strong><br>" .
                "Rentang Barcode: <code>{$generatedList[0]}</code> s/d <code>" . end($generatedList) . "</code>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Gagal meregistrasi barcode: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Ambil daftar master untuk dropdown / datalist
if (Auth::isAdminDept() && !empty($currentUser['dept_code'])) {
    $mQ = $pdo->prepare("SELECT * FROM barcode_master WHERE dept_id = ? ORDER BY category, name");
    $mQ->execute([$currentUser['dept_code']]);
    $masterList = $mQ->fetchAll(PDO::FETCH_ASSOC);
    if (empty($filterDept)) {
        $filterDept = $currentUser['dept_code'];
    }
} else {
    $masterList = $pdo->query("SELECT * FROM barcode_master ORDER BY category, dept_id, name")->fetchAll(PDO::FETCH_ASSOC);
}

// Query nomor urut berjalan bulan ini per Item Code untuk preview realtime di frontend
$monthSeqQuery = $pdo->prepare("
    SELECT UPPER(product_code) as pcode, COALESCE(MAX(seq_number), 0) as max_seq
    FROM barcodes
    WHERE month_year = ? OR month_year = ?
    GROUP BY UPPER(product_code)
");
$monthSeqQuery->execute([$curMonthYearKey, $currentMonthYear]);
$itemSeqs = [];
while ($row = $monthSeqQuery->fetch(PDO::FETCH_ASSOC)) {
    $itemSeqs[$row['pcode']] = (int)$row['max_seq'];
}

// Buat dictionary master item untuk lookup cepat JavaScript
$masterDict = [];
foreach ($masterList as $m) {
    $codeKey = strtoupper($m['code'] ?: $m['product_code']);
    $masterDict[$codeKey] = [
        'code'       => $codeKey,
        'name'       => $m['name'] ?: $m['product_name'],
        'category'   => $m['category'] ?? 'PRODUCT',
        'dept_id'    => $m['dept_id'] ?? '-',
        'section_id' => $m['section_id'] ?? '-',
        'last_seq'   => $itemSeqs[$codeKey] ?? 0
    ];
}

// Fetch departments for master form
$departments = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sections = $pdo->query("SELECT id, dept_id, code, name FROM sections ORDER BY dept_id, id")->fetchAll(PDO::FETCH_ASSOC);
$deptIdToCode = [];
foreach ($departments as $d) {
    $deptIdToCode[$d['id']] = $d['code'];
}

// Filter for Daftar Barcode Diterbitkan
$filterType = $_GET['filter_type'] ?? '';
$filterDept = $_GET['filter_dept'] ?? '';

$bQuery = "
    SELECT b.*, bm.name as master_name, bm.unit
    FROM barcodes b
    LEFT JOIN barcode_master bm ON UPPER(b.product_code) = UPPER(bm.code)
    WHERE 1=1
";
$bParams = [];
if (!empty($filterType)) {
    $bQuery .= " AND b.barcode_type = ?";
    $bParams[] = $filterType;
}
if (!empty($filterDept)) {
    $bQuery .= " AND b.dept_id = ?";
    $bParams[] = $filterDept;
}
$bQuery .= " ORDER BY b.created_at DESC LIMIT 50";
$bStmt = $pdo->prepare($bQuery);
$bStmt->execute($bParams);
$recentBarcodes = $bStmt->fetchAll(PDO::FETCH_ASSOC);

// Sequence overview for this month
$seqSummary = $pdo->query("
    SELECT product_code, barcode_type, MAX(seq_number) as max_seq, COUNT(*) as total_registered
    FROM barcodes 
    WHERE month_year = '{$curMonthYearKey}' OR month_year = '{$currentMonthYear}'
    GROUP BY product_code, barcode_type
    ORDER BY total_registered DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; line-height: 1.6;">
        <?= $message ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Box 1: Form Penerbitan Barcode Serial Produk (Batch Production) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Terbitkan Barcode Serial Produk (Batch Production)
            </div>
        </div>
        <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 16px; line-height: 1.5;">
            Khusus penerbitan nomor seri bertahap untuk <strong>Kategori PRODUK</strong> (ban, bladder, material, compound) dengan format: <code>[ItemCode][YY][MM][00001]</code>.<br>
            <span style="color: #60a5fa; font-size: 11.5px;">ℹ️ Barcode untuk <strong>Mesin</strong> dan <strong>Aktivitas</strong> bersifat identitas tetap sekali pakai dan otomatis diterbitkan melalui form Master sebelah kanan.</span>
        </p>

        <form method="POST" action="barcode_management.php">
            <input type="hidden" name="action" value="generate_batch">

            <!-- 1. Input / Scan Media Item Code dari Barcode -->
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label required">Scan / Input Item Code Master</label>
                <div style="position: relative;">
                    <input type="text" name="master_code" id="box1MasterCode" list="dl_box1_master_codes"
                        class="form-input form-input-scanner" placeholder="Scan Barcode / Ketik Item Code (Contoh: DBB01, RDL, CUR-PRS-01-L)..."
                        value="<?= htmlspecialchars($_POST['master_code'] ?? 'DBB01') ?>" required autocomplete="off" oninput="onItemCodeChanged(this.value)">
                    <datalist id="dl_box1_master_codes">
                        <?php foreach ($masterList as $m): ?>
                            <?php
                            $iCode = strtoupper($m['code'] ?: $m['product_code']);
                            $iName = $m['name'] ?: $m['product_name'];
                            $iCat = $m['category'] ?? 'PRODUCT';
                            if ($iCat === 'PRODUCT'):
                            ?>
                                <option value="<?= htmlspecialchars($iCode) ?>">
                                    <?= htmlspecialchars($iName) ?> (Item Produk)
                                </option>
                        <?php endif;
                        endforeach; ?>
                    </datalist>
                </div>
            </div>

            <!-- Panel Otomatis Deteksi Item Code (Produk / Aktivitas / Mesin) -->
            <div id="box1ItemInfo" style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 13.5px; font-weight: 700; color: #fff;" id="box1ItemName">Memuat Item Code...</span>
                    <span class="badge badge-info" id="box1ItemCatBadge" style="font-size: 10.5px; font-weight: 700;">PRODUK</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary); display: flex; justify-content: space-between;" id="box1ItemMeta">
                    <span id="box1ItemDeptSection">Departemen: -</span>
                    <span id="box1ItemLastSeq">Urutan Terakhir: #0</span>
                </div>
            </div>

            <!-- 2. Input Jumlah Barcode yang Diinginkan -->
            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label required">Jumlah Barcode Diterbitkan (Pcs)</label>
                <input type="number" name="qty_to_gen" id="box1QtyInput" class="form-input" min="1" max="500" value="100" required oninput="onQtyChanged()">
                <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 4px;">Ketik jumlah barcode (contoh: 100 pcs untuk mencetak 100 nomor seri).</small>
            </div>

            <!-- 3. Dua Kotak Divisi Nomor Urut (Awal & Akhir) Sesuai Permintaan Pengguna -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 10px 14px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                        Nomor Urut Awal
                    </div>
                    <div id="dispSeqStart" style="font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 800; color: #38bdf8; line-height: 1.2;">
                        00001
                    </div>
                </div>

                <div style="background: rgba(52, 211, 153, 0.08); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 8px; padding: 10px 14px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: #34d399; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                        Nomor Urut Akhir
                    </div>
                    <div id="dispSeqEnd" style="font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 800; color: #34d399; line-height: 1.2;">
                        00100
                    </div>
                </div>
            </div>

            <!-- 4. Simulasi Kode Barcode Lengkap -->
            <div style="background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; margin-bottom: 20px;">
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-bottom: 4px;">
                    Kode Barcode yang Dihasilkan:
                </div>
                <div id="dispBarcodeRange" style="font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 700; color: #f59e0b; word-break: break-all;">
                    DBB01261000001 s/d DBB01261000100
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px; line-height: 1.4;">
                    Struktur: <code>[ItemCode][YY: <?= $curYY ?>][MM: <?= $curMM ?>][NomorUrut: 5 Digit]</code> &bull; Restart ke 00001 setiap bulan baru.
                </div>
            </div>

            <button type="submit" id="btnSubmitBatchBarcode" class="btn btn-primary" style="width: 100%; padding: 12px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Terbitkan & Simpan ke Database
            </button>
        </form>
    </div>
    <!-- Box 2: Tambah Master Produk, Aktivitas & Alat/Mesin Baru -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Tambah Master Produk, Aktivitas & Alat/Mesin Baru
            </div>
        </div>

        <form method="POST" action="barcode_management.php">
            <input type="hidden" name="action" value="add_master">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Kategori Identitas Master</label>
                    <select name="category" id="masterCategorySelect" class="form-select" onchange="toggleMachineOptions()" required>
                        <option value="PRODUCT">1. Barcode Produk (Material / Green Tire / Ban Jadi)</option>
                        <option value="ACTIVITY">2. Barcode Aktivitas (Setup / Mold / QC / Repair)</option>
                        <option value="MACHINE">3. Barcode Alat atau Mesin (Mesin Produksi / Cavity / Tool)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required" id="lblMasterCode">Kode Master Item / Mesin</label>
                    <input type="text" name="code" id="masterCodeInput" class="form-input" placeholder="Contoh: GT-215-60-16 / CUR-PRS-01" required>
                </div>
            </div>

            <!-- Panel Khusus Konfigurasi Mesin / Alat (Hanya Tampil jika Kategori = MACHINE) -->
            <div id="machineConfigBox" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px dashed rgba(59, 130, 246, 0.35); border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-weight: 700; font-size: 13px; color: #60a5fa; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Identifikasi Barcode Alat / Mesin Produksi
                </div>
                <div class="form-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label required">Tipe / Konstruksi Mesin</label>
                        <select name="machine_type_config" id="machineTypeConfig" class="form-select" onchange="updateMachinePlaceholder()">
                            <option value="SINGLE">Unit Tunggal / Standar (1 Mesin = 1 Barcode Identitas)</option>
                            <option value="DUAL_LR">Dual Cavity / Sisi Ganda (Otomatis Buat 2 Barcode: Left & Right)</option>
                            <option value="CUSTOM_SUB">Sub-Identitas Kustom (Stasiun / Spindle / Jalur)</option>
                        </select>
                    </div>
                    <div class="form-group" id="customSubGroup" style="display: none; margin-bottom: 0;">
                        <label class="form-label required">Kode Suffix / Posisi</label>
                        <input type="text" name="custom_sub" id="customSubInput" class="form-input" placeholder="Contoh: ST-01 / LINE-A">
                    </div>
                </div>
                <div id="machineDualInfo" style="font-size: 11.5px; color: #93c5fd; margin-top: 10px; line-height: 1.5; display: none;">
                    💡 <strong>Otomatisasi Mesin Dual Cavity:</strong> Seperti pada mesin <strong>Curing Press</strong> yang memproduksi 2 ban secara simultan, sistem akan otomatis menerbitkan 2 identitas barcode terpisah: <code>[KODE]-L</code> (Sisi Left / Kiri) dan <code>[KODE]-R</code> (Sisi Right / Kanan) agar operator di lapangan cukup memindai barcode stasiun kerjanya.
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label required" id="lblMasterName">Nama Material / Jenis Aktivitas / Nama Mesin</label>
                <input type="text" name="name" id="masterNameInput" class="form-input" placeholder="Contoh: Green Tire 215/60 R16 SUV / Curing Press Line A-01" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required">Departemen</label>
                    <select name="dept_id" id="masterDeptSelect" class="form-select" onchange="updateMasterSectionFilter()" required>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['code'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Bagian (Section)</label>
                    <select name="section_id" id="masterSectionSelect" class="form-select" required>
                        <?php foreach ($sections as $s): ?>
                            <?php $sDept = $deptIdToCode[$s['dept_id']] ?? ''; ?>
                            <option value="<?= $s['code'] ?>" data-dept="<?= $sDept ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= $s['code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Keterangan / Spesifikasi</label>
                <input type="text" name="description" class="form-input" placeholder="Keterangan tambahan material atau panduan teknis...">
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%;">
                Daftarkan Master Baru
            </button>
        </form>
    </div>
</div>

<!-- Running Sequence Status (Bulan Berjalan) -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
            Status Running Sequence Barcode (Bulan Berjalan: <?= date('F Y') ?>)
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
        <?php foreach ($seqSummary as $seq): ?>
            <div style="background: var(--bg-input); padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-weight: 700; font-size: 13px; color: #60a5fa;"><?= htmlspecialchars($seq['product_code']) ?></span>
                    <span class="badge <?= $seq['barcode_type'] === 'ACTIVITY' ? 'badge-warning' : ($seq['barcode_type'] === 'MACHINE' ? 'badge-success' : 'badge-info') ?>">
                        <?= $seq['barcode_type'] === 'MACHINE' ? 'MESIN / ALAT' : ($seq['barcode_type'] === 'ACTIVITY' ? 'AKTIVITAS' : 'PRODUK') ?>
                    </span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary);">
                    No Urut Terakhir: <strong style="color: var(--text-primary); font-size: 14px;">#<?= $seq['max_seq'] ?></strong>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                    Total Bulan Ini: <?= $seq['total_registered'] ?> barcode
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Daftar Barcode Diterbitkan (No Print Button, Clean Structured Layout) -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Daftar Barcode Resmi Terdaftar (Siap Di-scan di Pabrik)
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                Cetak label via browser telah dinonaktifkan sesuai SOP barcode industri. Gunakan ekspor data rapi di bawah.
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Filter by Type -->
            <form method="GET" action="barcode_management.php" style="display: flex; gap: 8px;">
                <select name="filter_type" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori --</option>
                    <option value="PRODUCT" <?= $filterType === 'PRODUCT' ? 'selected' : '' ?>>1. Identitas Produk</option>
                    <option value="ACTIVITY" <?= $filterType === 'ACTIVITY' ? 'selected' : '' ?>>2. Identitas Aktivitas</option>
                    <option value="MACHINE" <?= $filterType === 'MACHINE' ? 'selected' : '' ?>>3. Identitas Alat atau Mesin</option>
                </select>
                <select name="filter_dept" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">-- Semua Dept --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['code'] ?>" <?= $filterDept === $d['code'] ? 'selected' : '' ?>><?= $d['code'] ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Export to Clean Excel/CSV with Categorisation -->
            <a href="api/export_barcodes.php?filter_type=<?= urlencode($filterType) ?>&filter_dept=<?= urlencode($filterDept) ?>" class="btn btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Export Excel / CSV Terkategori
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Daftar</th>
                    <th>Nomor Barcode</th>
                    <th>Jenis Identitas</th>
                    <th>Item Master</th>
                    <th>Departemen & Bagian</th>
                    <th>No Urut (Seq)</th>
                    <th>Didaftarkan Oleh</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBarcodes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            Belum ada rekaman barcode resmi terdaftar pada filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentBarcodes as $bc): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($bc['created_at'])) ?></td>
                            <td><strong style="color: var(--color-accent); font-family: monospace; font-size: 13.5px;"><?= htmlspecialchars($bc['barcode']) ?></strong></td>
                            <td>
                                <?php if ($bc['barcode_type'] === 'ACTIVITY'): ?>
                                    <span class="badge badge-warning">AKTIVITAS</span>
                                <?php elseif ($bc['barcode_type'] === 'MACHINE'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">MESIN / ALAT</span>
                                <?php else: ?>
                                    <span class="badge badge-info">PRODUK</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($bc['master_name'] ?? $bc['product_code']) ?></td>
                            <td>
                                <span class="badge badge-secondary"><?= htmlspecialchars($bc['dept_id'] ?? '-') ?></span>
                                <span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($bc['section_id'] ?? '-') ?></span>
                            </td>
                            <td>#<?= $bc['seq_number'] ?></td>
                            <td><?= htmlspecialchars($bc['created_by'] ?? 'Admin') ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($bc['status'] ?? 'REGISTERED') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 4: JAVASCRIPT AJAX DROPDOWN SEKSI & CETAK LABEL BARCODE -->
<!-- ===================================================================== -->
<script>
    // Data master seluruh sections per departemen
    const allMasterSections = <?= json_encode(array_values(array_map(function ($s) use ($deptIdToCode) {
                                    return [
                                        'code' => $s['code'],
                                        'name' => $s['name'],
                                        'dept' => $deptIdToCode[$s['dept_id']] ?? ''
                                    ];
                                }, $sections))) ?>;

    function updateMasterSectionFilter() {
        const deptSelect = document.getElementById('masterDeptSelect');
        const sectSelect = document.getElementById('masterSectionSelect');
        if (!deptSelect || !sectSelect) return;

        const selectedDept = deptSelect.value;
        const currentVal = sectSelect.value;

        sectSelect.innerHTML = '';
        let firstVal = null;
        let currentFound = false;

        allMasterSections.forEach(function(s) {
            if (!selectedDept || s.dept === selectedDept) {
                const opt = document.createElement('option');
                opt.value = s.code;
                opt.textContent = s.name + ' (' + s.code + ')';
                opt.setAttribute('data-dept', s.dept);
                if (!firstVal) firstVal = s.code;
                if (s.code === currentVal) {
                    opt.selected = true;
                    currentFound = true;
                }
                sectSelect.appendChild(opt);
            }
        });

        if (!currentFound && firstVal) {
            sectSelect.value = firstVal;
        }
    }
    document.addEventListener('DOMContentLoaded', updateMasterSectionFilter);

    function toggleMachineOptions() {
        const catSelect = document.getElementById('masterCategorySelect');
        const machineBox = document.getElementById('machineConfigBox');
        const codeInput = document.getElementById('masterCodeInput');
        const nameInput = document.getElementById('masterNameInput');
        const lblCode = document.getElementById('lblMasterCode');
        const lblName = document.getElementById('lblMasterName');

        if (!catSelect || !machineBox) return;

        if (catSelect.value === 'MACHINE') {
            machineBox.style.display = 'block';
            lblCode.textContent = 'Kode Mesin / Alat (Base Code) *';
            lblName.textContent = 'Nama Mesin / Stasiun Kerja *';
            codeInput.placeholder = 'Contoh: CUR-PRS-01 / TBM-01 / MIX-01';
            nameInput.placeholder = 'Contoh: Curing Press Line A #01 / Banbury Mixer 01';
            updateMachinePlaceholder();
        } else if (catSelect.value === 'ACTIVITY') {
            machineBox.style.display = 'none';
            lblCode.textContent = 'Kode Master Aktivitas *';
            lblName.textContent = 'Jenis Aktivitas / Tindakan *';
            codeInput.placeholder = 'Contoh: ACT-CLN-01 / ACT-REP-01';
            nameInput.placeholder = 'Contoh: Pembersihan Mold Curing / Buffing Tire';
        } else {
            machineBox.style.display = 'none';
            lblCode.textContent = 'Kode Master Produk / Material *';
            lblName.textContent = 'Nama Produk / Material *';
            codeInput.placeholder = 'Contoh: GT-215-60-16 / CMP-TRD-01';
            nameInput.placeholder = 'Contoh: Green Tire 215/60 R16 / Compound Tread';
        }
    }

    function updateMachinePlaceholder() {
        const typeConfig = document.getElementById('machineTypeConfig');
        const customSubGroup = document.getElementById('customSubGroup');
        const dualInfo = document.getElementById('machineDualInfo');
        const codeInput = document.getElementById('masterCodeInput');

        if (!typeConfig) return;

        if (typeConfig.value === 'DUAL_LR') {
            if (customSubGroup) customSubGroup.style.display = 'none';
            if (dualInfo) dualInfo.style.display = 'block';
            codeInput.placeholder = 'Contoh: CUR-PRS-01 (Otomatis jadi CUR-PRS-01-L & CUR-PRS-01-R)';
        } else if (typeConfig.value === 'CUSTOM_SUB') {
            if (customSubGroup) customSubGroup.style.display = 'block';
            if (dualInfo) dualInfo.style.display = 'none';
            codeInput.placeholder = 'Contoh: ST-QC-01 / TBM-LINE1';
        } else {
            if (customSubGroup) customSubGroup.style.display = 'none';
            if (dualInfo) dualInfo.style.display = 'none';
            codeInput.placeholder = 'Contoh: TBM-01 / MIX-01 / CAL-01';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleMachineOptions();
    });

    // Dictionary master items dari database untuk lookup instan Box 1
    const masterItemDictionary = <?= json_encode($masterDict) ?>;
    const currentYY = '<?= $curYY ?>';
    const currentMM = '<?= $curMM ?>';

    function onItemCodeChanged(rawVal) {
        const itemCode = (rawVal || '').trim().toUpperCase();
        const infoBox = document.getElementById('box1ItemInfo');
        const nameEl = document.getElementById('box1ItemName');
        const badgeEl = document.getElementById('box1ItemCatBadge');
        const deptSecEl = document.getElementById('box1ItemDeptSection');
        const lastSeqEl = document.getElementById('box1ItemLastSeq');

        const item = masterItemDictionary[itemCode];
        const submitBtn = document.getElementById('btnSubmitBatchBarcode');
        const previewBoxes = document.querySelectorAll('#dispSeqStart, #dispSeqEnd');

        if (item) {
            infoBox.style.display = 'block';
            nameEl.textContent = item.name + ' (' + item.code + ')';

            if (item.category === 'ACTIVITY') {
                badgeEl.textContent = '2. AKTIVITAS (Identitas Tetap Sekali)';
                badgeEl.className = 'badge badge-warning';
                deptSecEl.innerHTML = '<span style="color: #fbbf24;">⚠️ Identitas Aktivitas dipergunakan sekali dan telah terdaftar tetap. Kotak terbit serial batch ini khusus untuk PRODUK.</span>';
                lastSeqEl.textContent = 'Barcode Tetap: ' + item.code;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.title = 'Penerbitan serial nomor urut hanya berlaku untuk Kategori PRODUK';
                }
            } else if (item.category === 'MACHINE') {
                badgeEl.textContent = '3. MESIN / ALAT (Identitas Tetap Sekali)';
                badgeEl.className = 'badge badge-success';
                deptSecEl.innerHTML = '<span style="color: #34d399;">ℹ️ Barcode Mesin/Alat adalah identitas fisik tetap yang diterbitkan sekali di form Master. Tidak membutuhkan batch serial produksi.</span>';
                lastSeqEl.textContent = 'Barcode Tetap: ' + item.code;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.title = 'Penerbitan serial nomor urut hanya berlaku untuk Kategori PRODUK';
                }
            } else {
                badgeEl.textContent = '1. PRODUK (Serial Batch)';
                badgeEl.className = 'badge badge-info';
                deptSecEl.textContent = 'Dept: ' + (item.dept_id || '-') + ' | Seksi: ' + (item.section_id || '-');
                lastSeqEl.textContent = 'Urutan Terakhir Bulan Ini: #' + item.last_seq;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.title = '';
                }
            }
        } else {
            if (itemCode.length > 0) {
                infoBox.style.display = 'block';
                nameEl.textContent = 'Item Code Belum Terdaftar';
                badgeEl.textContent = 'BARU';
                badgeEl.className = 'badge badge-secondary';
                deptSecEl.textContent = 'Silakan daftarkan master terlebih dahulu di form sebelah kanan.';
                lastSeqEl.textContent = 'Urutan: #0';
            } else {
                infoBox.style.display = 'none';
            }
        }

        updateSequencePreview();
    }

    function onQtyChanged() {
        updateSequencePreview();
    }

    function updateSequencePreview() {
        const itemCodeInput = document.getElementById('box1MasterCode');
        const qtyInput = document.getElementById('box1QtyInput');
        const dispStart = document.getElementById('dispSeqStart');
        const dispEnd = document.getElementById('dispSeqEnd');
        const dispRange = document.getElementById('dispBarcodeRange');

        if (!itemCodeInput || !qtyInput) return;

        const itemCode = (itemCodeInput.value || '').trim().toUpperCase();
        let qty = parseInt(qtyInput.value) || 1;
        if (qty < 1) qty = 1;

        const item = masterItemDictionary[itemCode];
        const lastSeq = item ? item.last_seq : 0;

        const startSeqNum = lastSeq + 1;
        const endSeqNum = lastSeq + qty;

        const startSeqFormatted = String(startSeqNum).padStart(5, '0');
        const endSeqFormatted = String(endSeqNum).padStart(5, '0');

        dispStart.textContent = startSeqFormatted;
        dispEnd.textContent = endSeqFormatted;

        const codePrefix = itemCode || 'DBB01';
        const startBarcode = codePrefix + currentYY + currentMM + startSeqFormatted;
        const endBarcode = codePrefix + currentYY + currentMM + endSeqFormatted;

        dispRange.textContent = startBarcode + ' s/d ' + endBarcode;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const initCode = document.getElementById('box1MasterCode');
        if (initCode) {
            onItemCodeChanged(initCode.value);
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>