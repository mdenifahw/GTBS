<?php
/**
 * ==============================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Pusat Menu Barcode & Sync Desk (Mobile App)
 * File : mobile_barcode.php
 * ==============================================================================
 */

$pageTitle = 'Pusat Barcode & Scanner';
require_once __DIR__ . '/includes/header.php';

$user = $currentUser ?? Auth::user();
$role = $user['role'] ?? '';
$allowedDepts = Auth::getAllowedDeptPages();
$deptMeta = gtbs_m_dept_meta();
$isField = gtbs_m_is_field_role($user);
?>

<div class="m-hero">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
        <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9;">
            Modul Barcode Pabrik
        </span>
        <span class="m-net online" id="mNetBadge">
            <i></i> <span>Online</span>
        </span>
    </div>
    <h2>Pusat Barcode & Scanner</h2>
    <p>Kelola pemindaian, verifikasi keaslian label, dan pemantauan pergerakan ban antar departemen.</p>

    <!-- Kotak Scan Cepat -->
    <form class="m-quickscan" method="GET" action="scan_terminal.php">
        <input type="text" name="barcode" id="barcodeScannerInput" placeholder="Scan / ketik nomor barcode..." required autocomplete="off" autofocus>
        <button type="submit">Lacak</button>
    </form>
</div>

<!-- ==================== WIDGET SYNC DESK ==================== -->
<div class="m-sync-card">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <div>
            <div style="font-size: 14px; font-weight: 800; color: var(--m-text); display: flex; align-items: center; gap: 6px;">
                <span><?= gtbs_m_icon('sync', 18) ?></span>
                <span>Sync Desk (Local-First Offline)</span>
            </div>
            <div style="font-size: 11.5px; color: var(--m-text-2); margin-top: 2px;">
                Sinkronisasi data antrean scanner lokal ke server PostgreSQL.
            </div>
        </div>
        <button type="button" class="m-sync-btn" id="mBtnSyncDesk" onclick="triggerMobileSync()">
            <?= gtbs_m_icon('sync', 15) ?>
            <span>Sync Desk</span>
        </button>
    </div>

    <div class="m-sync-stats">
        <div class="m-sync-stat">
            <b class="m-pending-count" id="mSyncPendingBadge" style="color: #fbbf24;">0</b>
            <small>Pending Offline</small>
        </div>
        <div class="m-sync-stat">
            <b style="color: #34d399;">Ready</b>
            <small>Database Server</small>
        </div>
        <div class="m-sync-stat">
            <b style="color: #60a5fa;">Shift <?= (int)($shiftInfo['shift'] ?? 1) ?></b>
            <small>Shift Kerja</small>
        </div>
    </div>
    <div style="font-size: 11px; color: var(--m-muted); text-align: center;">
        Data scan di area blank-spot otomatis tersimpan aman di memori PDT dan akan dikirim otomatis saat koneksi pulih.
    </div>
</div>

<!-- ==================== MENU UTAMA BARCODE ==================== -->
<div class="m-section-title">
    <span>Fitur Utama Barcode</span>
    <span style="font-size: 11px; color: var(--m-accent); font-weight: 600;">Traceability</span>
</div>

<div class="m-grid">
    <a href="scan_terminal.php" class="m-tile">
        <div class="m-tile-icon"><?= gtbs_m_icon('search', 20) ?></div>
        <div>
            <b>Scan Terminal</b>
            <small>Pelacakan riwayat proses ban dari hulu ke hilir</small>
        </div>
    </a>

    <?php if (Auth::canGenerateBarcode()): ?>
    <a href="barcode_management.php" class="m-tile">
        <div class="m-tile-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
            <?= gtbs_m_icon('tag', 20) ?>
        </div>
        <div>
            <b>Generate Barcode</b>
            <small>Buat barcode batch material, ban, lori & rak</small>
        </div>
    </a>
    <?php endif; ?>

    <?php if ($role === 'super_admin' || $isField): ?>
    <a href="operator_lite.php" class="m-tile">
        <div class="m-tile-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
            <?= gtbs_m_icon('bolt', 20) ?>
        </div>
        <div>
            <b>Mode Lite (Operator)</b>
            <small>Form scan cepat khusus stasiun kerja lapangan</small>
        </div>
    </a>
    <?php endif; ?>
</div>

<!-- ==================== MENU 5 DEPARTEMEN PABRIK ==================== -->
<?php if (!empty($allowedDepts)): ?>
<div class="m-section-title">
    <span>Departemen Pabrik</span>
    <span style="font-size: 11px; color: var(--m-text-2); font-weight: 600;">Input & Monitoring</span>
</div>

<div class="m-list">
    <?php foreach ($deptMeta as $code => $meta): ?>
        <?php if (in_array($code, $allowedDepts, true)): ?>
        <a href="<?= htmlspecialchars($meta[1]) ?>" class="m-list-item">
            <div class="m-li-icon"><?= gtbs_m_icon($meta[2], 18) ?></div>
            <div class="m-li-text">
                <b><?= htmlspecialchars($meta[0]) ?></b>
                <small>Proses input & monitoring stasiun kerja departemen <?= $code ?></small>
            </div>
            <span class="m-li-chev"><?= gtbs_m_icon('chevron', 16) ?></span>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
