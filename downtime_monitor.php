<?php
/**
 * ==============================================================================
 * MONITORING DOWNTIME MESIN & HAMBATAN 4M - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Memantau penghentian lini produksi yang tidak direncanakan (Unplanned Downtime)
 *   berdasarkan kategori 4M (Man, Machine, Material, Method).
 * - Fitur Utama:
 *   1. Papan Status Lini: Memantau mesin yang sedang rusak, jam mulai berhenti, dan durasi berjalan (menit).
 *   2. Alur Penanganan Insiden: Operator/Maintenance dapat mengubah status menjadi 'IN_PROGRESS' 
 *      saat perbaikan dimulai, dan 'RESOLVED' beserta catatan tindakan perbaikan yang dilakukan.
 *   3. Rekap Durasi Otomatis: Menghitung total durasi downtime dalam menit secara otomatis saat insiden diselesaikan.
 *   4. Analisis Pareto Hambatan: Membantu tim Continuous Improvement (Kaizen) melihat sumber hambatan terbesar.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Kategori Penyebab Downtime:
 *    Tambahkan opsi di modal pelaporan cepat (misal: 'POWER_OUTAGE', 'MOLD_CHANGE', 'TOOL_BROKEN').
 * 2. Mengirim Panggilan Teknisi Otomatis (Andon Call):
 *    Tambahkan trigger notifikasi ke teknisi departemen terkait saat downtime berstatus OPEN melebihi 15 menit.
 * 3. Menghubungkan ke Perhitungan OEE (Overall Equipment Effectiveness):
 *    Total menit downtime dapat dijadikan pengurang waktu operasi bersih (Operating Time).
 * ==============================================================================
 */
$pageTitle = 'Monitoring Mesin Rusak & Hambatan 4M';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';

// ==============================================================================
// BAGIAN 1: PEMROSESAN STATUS PENANGANAN DOWNTIME (IN_PROGRESS / RESOLVED)
// ==============================================================================
// Handle Status Updates (Mark as IN_PROGRESS or RESOLVED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? 'IN_PROGRESS';
    $actionTaken = trim($_POST['action_taken'] ?? '');

    try {
        if ($newStatus === 'RESOLVED') {
            $stmt = $pdo->prepare("
                UPDATE downtime_events 
                SET status = 'RESOLVED', end_time = CURRENT_TIMESTAMP, 
                    duration_minutes = ROUND(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - start_time))/60),
                    action_taken = ?
                WHERE id = ?
            ");
            $stmt->execute([$actionTaken, $eventId]);
            $message = "Hambatan telah berhasil diselesaikan (Status: RESOLVED)! Mesin kembali beroperasi.";
        } else {
            $stmt = $pdo->prepare("UPDATE downtime_events SET status = 'IN_PROGRESS' WHERE id = ?");
            $stmt->execute([$eventId]);
            $message = "Status hambatan diperbarui menjadi SEDANG DITANGANI (IN_PROGRESS).";
        }
    } catch (Exception $e) {
        $message = "Gagal memperbarui status: " . $e->getMessage();
    }
}

// Counts
$activeCount = $pdo->query("SELECT COUNT(*) FROM downtime_events WHERE status = 'ACTIVE'")->fetchColumn();
$inProgressCount = $pdo->query("SELECT COUNT(*) FROM downtime_events WHERE status = 'IN_PROGRESS'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM downtime_events WHERE status = 'RESOLVED' AND DATE(start_time) = CURRENT_DATE")->fetchColumn();

// Fetch Active & In-Progress Downtimes
$activeEvents = $pdo->query("
    SELECT de.*, d.name as dept_name 
    FROM downtime_events de
    LEFT JOIN departments d ON de.dept_id = d.code
    WHERE de.status IN ('ACTIVE', 'IN_PROGRESS')
    ORDER BY de.start_time DESC
")->fetchAll();

// Fetch Resolved Historical Downtimes
$resolvedEvents = $pdo->query("
    SELECT de.*, d.name as dept_name 
    FROM downtime_events de
    LEFT JOIN departments d ON de.dept_id = d.code
    WHERE de.status = 'RESOLVED'
    ORDER BY de.end_time DESC
    LIMIT 20
")->fetchAll();
?>

<!-- KPI Overview -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-title">Hambatan Menunggu (Active)</div>
        <div class="kpi-value" style="color: <?= $activeCount > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;">
            <?= $activeCount ?>
        </div>
        <div class="kpi-meta"><?= $activeCount > 0 ? 'Perlu tindakan teknisi segera' : 'Kondisi mesin aman' ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Sedang Ditangani (In Progress)</div>
        <div class="kpi-value" style="color: var(--color-warning);"><?= $inProgressCount ?></div>
        <div class="kpi-meta">Dalam penanganan maintenance</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Terselesaikan Hari Ini</div>
        <div class="kpi-value" style="color: var(--color-success);"><?= $resolvedCount ?></div>
        <div class="kpi-meta">Mesin kembali aktif berproduksi</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Daftar Hambatan & Mesin Rusak Aktif -->
<div class="card">
    <div class="card-header">
        <div>
            <?php if (!Auth::isPlantLevel()): ?>
    <div style="margin-bottom: 14px; padding: 8px 14px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; font-size: 12.5px; color: #60a5fa; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
        <span>🔒 Lingkup Pantauan:</span>
        <strong>
            <?= htmlspecialchars($currentUser['dept_name'] ?? $currentUser['dept_code']) ?>
            <?php if (Auth::isSectionLevel()): ?>
                &bull; Bagian <?= htmlspecialchars($currentUser['section_name'] ?? $currentUser['section_code']) ?>
            <?php elseif (Auth::isOperator()): ?>
                &bull; Laporan Pribadi (NIK: <?= htmlspecialchars($currentUser['nik']) ?>)
            <?php endif; ?>
        </strong>
    </div>
<?php endif; ?>
<div class="card-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Papan Pemantauan Mesin Rusak & Hambatan 4M Aktif
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                Semua pelaporan kendala dari operator di departemen pabrik terkumpul di sini secara realtime.
            </div>
        </div>
        <button class="btn btn-danger btn-sm" onclick="openModal('downtimeModal')">
            + Laporkan Kendala 4M Baru
        </button>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Mulai</th>
                    <th>Departemen</th>
                    <th>Mesin</th>
                    <th>Kategori 4M</th>
                    <th>Uraian Masalah</th>
                    <th>Pelapor</th>
                    <th>Status</th>
                    <th>Aksi Penyelesaian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeEvents)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 36px; color: var(--color-success); font-weight: 600;">
                            ✅ Tidak ada hambatan atau kerusakan mesin aktif saat ini! Seluruh lini berjalan normal.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeEvents as $ev): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #f87171;"><?= date('H:i:s', strtotime($ev['start_time'])) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= date('d/m/Y', strtotime($ev['start_time'])) ?> (<?= htmlspecialchars($ev['shift']) ?>)</div>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($ev['dept_id']) ?></span></td>
                            <td><strong><?= htmlspecialchars($ev['machine_id']) ?></strong></td>
                            <td>
                                <span class="badge badge-warning"><?= htmlspecialchars($ev['category_4m']) ?></span>
                            </td>
                            <td style="max-width: 260px;"><?= htmlspecialchars($ev['description']) ?></td>
                            <td>NIK: <?= htmlspecialchars($ev['operator_nik']) ?></td>
                            <td>
                                <span class="badge <?= $ev['status'] === 'ACTIVE' ? 'badge-danger' : 'badge-warning' ?>">
                                    <?= $ev['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <?php if ($ev['status'] === 'ACTIVE'): ?>
                                        <form method="POST" action="downtime_monitor.php">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                            <input type="hidden" name="new_status" value="IN_PROGRESS">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Tangani">Mulai Tangani</button>
                                        </form>
                                    <?php endif; ?>

                                    <button class="btn btn-success btn-sm" onclick="resolveModal(<?= $ev['id'] ?>, '<?= htmlspecialchars($ev['machine_id']) ?>')">
                                        Selesai (OK)
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dialog: Selesaikan Hambatan -->
<div class="modal-overlay" id="resolveDowntimeModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="resolveModalTitle">Penyelesaian Hambatan Mesin</h3>
            <button class="modal-close" onclick="closeModal('resolveDowntimeModal')">&times;</button>
        </div>
        <form method="POST" action="downtime_monitor.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="RESOLVED">
            <input type="hidden" name="event_id" id="resolveEventId" value="">

            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label required">Tindakan Perbaikan yang Dilakukan</label>
                    <textarea name="action_taken" class="form-textarea" rows="3" placeholder="Ganti bearing / reset sensor / kalibrasi ulang..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resolveDowntimeModal')">Batal</button>
                <button type="submit" class="btn btn-success">Konfirmasi Selesai & Hidupkan Mesin</button>
            </div>
        </form>
    </div>
</div>

<script>
function resolveModal(id, machine) {
    document.getElementById('resolveEventId').value = id;
    document.getElementById('resolveModalTitle').textContent = 'Penyelesaian Hambatan: ' + machine;
    openModal('resolveDowntimeModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
