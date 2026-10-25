<?php
/**
 * ==============================================================================
 * SCAN TERMINAL (PELACAK JEJAK BARCODE) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Terminal universal untuk memindai barcode ban menggunakan barcode scanner (USB / Handheld).
 * - Berfungsi ganda:
 *   1. Pengecekan spesifikasi barang (Nama produk, tipe kompon/ukuran, batch lot, status saat ini).
 *   2. Penelusuran riwayat lengkap perjalanan ban (Factory Audit Trail & Stepper):
 *      Mencatat mulai dari Pendaftaran Barcode -> Building (Green Tire) -> Curing (Vulkanisasi) 
 *      -> Final Inspection (QC) -> Penempatan Rak Gudang (Warehouse Storage).
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambahkan Validasi Alur Baru:
 *    Cari blok query `` yang menyatukan histori dari tabel `barcodes`, `production_activities`,
 *    dan `warehouse_racks`. Anda dapat menambahkan tahapan pengujian laboratorium atau sortir ulang.
 * 2. Mengintegrasikan Suara Beep / Notifikasi Audio:
 *    Pada bagian penanganan jika barcode ditemukan atau tidak ditemukan, Anda dapat menyisipkan
 *    kode audio HTML5 `<audio autoplay src="beep.mp3"></audio>`.
 * 3. Menghubungkan ke Mesin Cetak Label:
 *    Dapat ditambahkan tombol cetak ulang label barcode langsung dari kartu informasi produk.
 * ==============================================================================
 */
$pageTitle = 'Scan Terminal (Pelacak Jejak Barcode)';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$scannedBarcode = trim($_GET['barcode'] ?? '');

$barcodeInfo = null;
$pipelineEvents = [];
$errorMsg = '';

// ==============================================================================
// BAGIAN 1: PENCARIAN DATA BARCODE & REKONSTRUKSI RIWAYAT ALUR PRODUKSI
// ==============================================================================
if (!empty($scannedBarcode)) {
    // 1. Look up barcode registry
    $stmt = $pdo->prepare("
        SELECT b.*, bm.name as item_name, bm.category, bm.unit, bm.description as item_desc
        FROM barcodes b
        LEFT JOIN barcode_master bm ON b.product_code = bm.code
        WHERE b.barcode = ?
    ");
    $stmt->execute([$scannedBarcode]);
    $barcodeInfo = $stmt->fetch();

    if ($barcodeInfo) {
        // 2. Fetch history/events across factory pipeline
        $evStmt = $pdo->prepare("
            SELECT * FROM (
                SELECT 
                    created_at, 
                    'REGISTRATION' as stage, 
                    COALESCE(dept_id, 'ADMIN') as dept_id, 
                    COALESCE(section_id, 'GENERATE') as section_id, 
                    'Barcode Diterbitkan & Terdaftar' as description, 
                    created_by as operator_nik 
                FROM barcodes WHERE barcode = ?
                
                UNION ALL
                
                SELECT 
                    created_at, 
                    'SCADA_OUTPUT' as stage, 
                    dept_id, 
                    section_id, 
                    CONCAT('Mesin: ', machine_id, ' | Status Mutu: ', quality_status, ' | Qty: ', qty) as description, 
                    operator_nik 
                FROM production_scada_logs WHERE barcode = ?

                UNION ALL

                SELECT 
                    created_at, 
                    'WAREHOUSE_RACK' as stage, 
                    'WH' as dept_id, 
                    'penerima' as section_id, 
                    CONCAT('Disimpan di Rak ', rack_code) as description, 
                    'SYSTEM' as operator_nik
                FROM warehouse_racks WHERE current_barcode = ?
            ) as combined
            ORDER BY created_at ASC
        ");
        $evStmt->execute([$scannedBarcode, $scannedBarcode, $scannedBarcode]);
        $pipelineEvents = $evStmt->fetchAll();
    } else {
        $errorMsg = "Barcode '{$scannedBarcode}' TIDAK TERDAFTAR dalam sistem pabrik! Pastikan barcode sudah didaftarkan terlebih dahulu di menu Generate Barcode.";
    }
}
?>

<!-- Scan Terminal Header & Scanner Input -->
<div class="card" style="text-align: center; max-width: 760px; margin: 0 auto 24px auto;">
    <div style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
        🔍 Terminal Pelacakan Barcode Pabrik
    </div>
    <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px;">
        Fitur penelusuran read-only (Traceability). Masukkan atau scan barcode untuk memverifikasi posisi, status, dan riwayat proses dari hulu ke hilir.
    </p>

    <form method="GET" action="scan_terminal.php" style="display: flex; gap: 10px; max-width: 540px; margin: 0 auto;">
        <input type="text" name="barcode" class="form-input" style="font-size: 16px; padding: 12px 16px; text-transform: uppercase;" placeholder="Scan barcode produk / aktivitas di sini..." value="<?= htmlspecialchars($scannedBarcode) ?>" autofocus required>
        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 14px;">
            Lacak
        </button>
    </form>
</div>

<?php if (!empty($errorMsg)): ?>
    <div style="max-width: 760px; margin: 0 auto 24px auto; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 16px 20px; border-radius: 10px; font-weight: 600;">
        ⚠️ <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- BAGIAN 3: TAMPILAN SPESIFIKASI PRODUK & TIMELINE JEJAK PRODUKSI -->
<!-- ===================================================================== -->
<?php if ($barcodeInfo): ?>
    <div style="max-width: 760px; margin: 0 auto;" class="card">
        <div class="card-header">
            <div class="card-title">
                Informasi Identitas Barcode: <span style="color: var(--color-accent); font-family: monospace;"><?= htmlspecialchars($barcodeInfo['barcode']) ?></span>
            </div>
            <span class="badge badge-success"><?= htmlspecialchars($barcodeInfo['status'] ?? 'REGISTERED') ?></span>
        </div>

        <div class="form-grid" style="margin-bottom: 20px;">
            <div>
                <div style="font-size: 11.5px; color: var(--text-muted); text-transform: uppercase;">Jenis Barcode</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">
                    <?= $barcodeInfo['barcode_type'] === 'ACTIVITY' ? '🏷️ IDENTITAS AKTIVITAS' : '📦 IDENTITAS PRODUK' ?>
                </div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: var(--text-muted); text-transform: uppercase;">Kode & Nama Item</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">
                    <?= htmlspecialchars($barcodeInfo['item_name'] ?? $barcodeInfo['product_code']) ?>
                </div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: var(--text-muted); text-transform: uppercase;">Departemen Terdaftar</div>
                <div style="font-size: 14px; font-weight: 600; color: #60a5fa;">
                    <?= htmlspecialchars($barcodeInfo['dept_id'] ?? 'ALL') ?> - <?= htmlspecialchars($barcodeInfo['section_id'] ?? 'GENERAL') ?>
                </div>
            </div>
            <div>
                <div style="font-size: 11.5px; color: var(--text-muted); text-transform: uppercase;">No Urut / Bulan</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-secondary);">
                    Seq #<?= htmlspecialchars($barcodeInfo['seq_number'] ?? '1') ?> (<?= htmlspecialchars($barcodeInfo['month_year'] ?? date('Y-m')) ?>)
                </div>
            </div>
        </div>

        <!-- Pipeline History Trail -->
        <div style="font-size: 14px; font-weight: 700; margin-bottom: 14px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
            Riwayat Jejak Pipeline (Alur Pabrik)
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($pipelineEvents as $idx => $ev): ?>
                <div style="display: flex; gap: 14px; align-items: flex-start; background: var(--bg-input); padding: 12px 16px; border-radius: 8px; border-left: 4px solid var(--color-accent);">
                    <div style="font-size: 16px;">⏱️</div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span style="font-weight: 700; font-size: 13px; color: var(--text-primary);">
                                [<?= htmlspecialchars($ev['dept_id']) ?> / <?= htmlspecialchars($ev['section_id']) ?>] <?= htmlspecialchars($ev['stage']) ?>
                            </span>
                            <span style="font-size: 11.5px; color: var(--text-muted);">
                                <?= date('d M Y H:i:s', strtotime($ev['created_at'])) ?>
                            </span>
                        </div>
                        <div style="font-size: 12.5px; color: var(--text-secondary);">
                            <?= htmlspecialchars($ev['description']) ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                            Operator NIK: <?= htmlspecialchars($ev['operator_nik'] ?? '-') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
