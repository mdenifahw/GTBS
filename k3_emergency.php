<?php
/**
 * ==============================================================================
 * K3 KESELAMATAN KERJA & PANGGILAN DARURAT AMBULANS (SOS) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Konsol tanggap darurat Keselamatan & Kesehatan Kerja (K3 / EHS) pabrik.
 * - Fitur Utama:
 *   1. Tombol Merah SOS Cepat (1-Click Emergency Dispatch):
 *      Memungkinkan siapa pun di lantai produksi memanggil ambulans, tim medis klinik,
 *      dan regu pemadam kebakaran pabrik dalam hitungan detik.
 *   2. Pelacak Status Evakuasi:
 *      Memantau status respon ambulans: DISPATCHED (Menuju Lokasi) -> ARRIVED (Tiba di TKP) -> RESOLVED (Selesai).
 *   3. Log Insiden K3: Mencatat histori kecelakaan kerja, lokasi kejadian, NIK pelapor, dan waktu tanggap tim medis.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Nomor Telepon Hotline K3:
 *    Cari elemen kontak darurat di bagian bawah untuk memperbarui nomor ekstensi internal klinik / pos keamanan.
 * 2. Mengintegrasikan Sirine atau Lampu Strobo Pabrik:
 *    Saat status 'DISPATCHED' terpicu, sistem dapat mengirim sinyal HTTP/Relay ke sirine fisik di plant.
 * 3. Menambahkan Formulir Berita Acara Kecelakaan Kerja (BAP K3):
 *    Dapat ditambahkan tombol lampiran form investigasi sebab kecelakaan kerja pada baris insiden yang selesai.
 * ==============================================================================
 */
$pageTitle = 'K3 Keselamatan Kerja & Panggilan Darurat';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';

if (isset($_GET['sos_dispatched'])) {
    $message = "🚨 PANGGILAN DARURAT SOS TELAH DIKIRIM! Ambulans dan tim medis tanggap darurat pabrik segera meluncur ke lokasi!";
}

// ==============================================================================
// BAGIAN 1: PEMROSESAN PERUBAHAN STATUS PANGGILAN AMBULANS (DISPATCHED/ARRIVED/RESOLVED)
// ==============================================================================
$messageType = 'success';

// Handle Status Update for Ambulance Call ("Tiba di TKP" & "Selesai")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ambulance_status') {
    $callId = (int)($_POST['call_id'] ?? 0);
    $status = strtoupper(trim($_POST['status'] ?? 'ARRIVED'));

    try {
        if ($status === 'ON_SITE' || $status === 'ARRIVED') {
            // Konfirmasi Tiba di TKP: catat waktu kedatangan & update status ke ON_SITE (sesuai constraint DB)
            $stmt = $pdo->prepare("
                UPDATE k3_ambulance_calls 
                SET status = 'ON_SITE', arrival_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$callId]);
            $message = "Konfirmasi Berhasil: Tim Medis & Ambulans K3 telah TIBA DI TKP pada jam " . date('H:i:s') . " WIB!";
            $messageType = 'success';
        } elseif ($status === 'COMPLETED' || $status === 'RESOLVED' || $status === 'DONE') {
            // Konfirmasi Selesai Penanganan
            $stmt = $pdo->prepare("
                UPDATE k3_ambulance_calls 
                SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$callId]);
            $message = "Sukses: Penanganan darurat K3 pada panggilan ini telah dinyatakan SELESAI (COMPLETED)!";
            $messageType = 'success';
        } else {
            $stmt = $pdo->prepare("UPDATE k3_ambulance_calls SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $callId]);
            $message = "Status panggilan darurat diperbarui menjadi: " . e($status, "ON_SITE");
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = "Gagal memperbarui status: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Fetch SOS calls
$sosCalls = $pdo->query("
    SELECT * FROM k3_ambulance_calls 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.2)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 18px;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
        <span><?= e($message) ?></span>
    </div>
<?php endif; ?>

<!-- Emergency Call Action Card -->
<div class="card" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.4);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="font-size: 18px; font-weight: 800; color: #ef4444; margin-bottom: 4px;">
                🚨 Tombol Darurat Medis & Ambulans Pabrik (SOS)
            </div>
            <div style="font-size: 13px; color: var(--text-secondary);">
                Gunakan saat terjadi kecelakaan kerja berat, luka bakar steam curing, terjepit mesin, atau serangan jantung.
            </div>
        </div>
        <button class="btn btn-danger" style="padding: 12px 24px; font-size: 14px; font-weight: 800;" onclick="openModal('sosEmergencyModal')">
            PANGGIL AMBULANS K3
        </button>
    </div>
</div>

<!-- Riwayat Panggilan Ambulans & Darurat -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Log Panggilan Darurat Ambulans & Tanggap Darurat K3
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Panggilan</th>
                    <th>Pelapor (NIK)</th>
                    <th>Lokasi Kejadian</th>
                    <th>Keterangan Insiden</th>
                    <th>Status Ambulans</th>
                    <th>Tindakan Tim K3</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sosCalls)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            Tidak ada riwayat panggilan darurat K3.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sosCalls as $sc): ?>
    <?php
        $callerName = !empty($sc['caller_name']) ? $sc['caller_name'] : (!empty($sc['caller_id']) ? 'Personel Plant (ID: ' . $sc['caller_id'] . ')' : 'Personel Pabrik');
        $callerNik  = !empty($sc['caller_nik']) ? $sc['caller_nik'] : '-';
        $location   = !empty($sc['location']) ? $sc['location'] : (!empty($sc['location_detail']) ? $sc['location_detail'] : 'Area Produksi');
        $notes      = !empty($sc['notes']) ? $sc['notes'] : (!empty($sc['patient_condition']) ? $sc['patient_condition'] : (!empty($sc['incident_type']) ? $sc['incident_type'] : 'Kecelakaan Kerja / Medis'));
        $status     = !empty($sc['status']) ? strtoupper($sc['status']) : 'REQUESTED';
        $timeStr    = formatDateSafe($sc['created_at'] ?? null);
    ?>
    <tr>
        <td><?= e($timeStr) ?></td>
        <td>
            <strong><?= e($callerName) ?></strong>
            <div style="font-size: 11px; color: var(--text-muted);">NIK: <?= e($callerNik) ?></div>
        </td>
        <td><strong style="color: #60a5fa;"><?= e($location) ?></strong></td>
        <td style="max-width: 250px;"><?= e($notes) ?></td>
        <td>
            <?php if (in_array($status, ['DISPATCHED', 'HIGH_EMERGENCY', 'EMERGENCY', 'REQUESTED'])): ?>
                <span class="badge badge-danger">MENUJU TKP</span>
            <?php elseif (in_array($status, ['ON_SITE', 'ARRIVED'])): ?>
                <span class="badge badge-warning">TIBA DI TKP (ON SITE)</span>
                <?php if (!empty($sc['arrival_at'])): ?>
                    <div style="font-size: 11px; color: #fbbf24; margin-top: 4px; font-weight: 600;">
                        ⏱️ Tiba: <?= date('H:i:s', strtotime($sc['arrival_at'])) ?> WIB
                    </div>
                <?php endif; ?>
            <?php elseif (in_array($status, ['COMPLETED', 'RESOLVED', 'DONE'])): ?>
                <span class="badge badge-success">SELESAI (COMPLETED)</span>
            <?php else: ?>
                <span class="badge badge-warning"><?= e($status) ?></span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (in_array($status, ['DISPATCHED', 'REQUESTED', 'HIGH_EMERGENCY', 'EMERGENCY'])): ?>
                <form method="POST" action="k3_emergency.php" style="display: inline;" onsubmit="return confirm('Konfirmasi bahwa tim medis/ambulans K3 telah tiba di lokasi kejadian (TKP)?');">
                    <input type="hidden" name="action" value="update_ambulance_status">
                    <input type="hidden" name="call_id" value="<?= (int)$sc['id'] ?>">
                    <input type="hidden" name="status" value="ON_SITE">
                    <button type="submit" class="btn btn-warning btn-sm" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; padding: 6px 12px; cursor: pointer;">
                        <span>🚑 Tiba di TKP</span>
                    </button>
                </form>
            <?php elseif (in_array($status, ['ON_SITE', 'ARRIVED'])): ?>
                <form method="POST" action="k3_emergency.php" style="display: inline;" onsubmit="return confirm('Tandai bahwa penanganan darurat K3 ini telah selesai seluruhnya?');">
                    <input type="hidden" name="action" value="update_ambulance_status">
                    <input type="hidden" name="call_id" value="<?= (int)$sc['id'] ?>">
                    <input type="hidden" name="status" value="COMPLETED">
                    <button type="submit" class="btn btn-success btn-sm" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; padding: 6px 12px; cursor: pointer;">
                        <span>✅ Insiden Selesai</span>
                    </button>
                </form>
            <?php else: ?>
                <span style="font-size: 11.5px; color: var(--color-success); font-weight: 700;">✅ Selesai Ditangani</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
