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
// Handle Status Update for Ambulance Call
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ambulance_status') {
    $callId = (int)($_POST['call_id'] ?? 0);
    $status = $_POST['status'] ?? 'ARRIVED';

    try {
        $stmt = $pdo->prepare("UPDATE k3_ambulance_calls SET status = ? WHERE id = ?");
        $stmt->execute([$status, $callId]);
        $message = "Status panggilan darurat diperbarui menjadi: " . htmlspecialchars($status);
    } catch (Exception $e) {
        $message = "Gagal memperbarui status: " . $e->getMessage();
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
    <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 700; font-size: 14px;">
        <?= htmlspecialchars($message) ?>
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
                        <tr>
                            <td><?= date('d M Y H:i:s', strtotime($sc['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($sc['caller_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted);">NIK: <?= htmlspecialchars($sc['caller_nik']) ?></div>
                            </td>
                            <td><strong style="color: #60a5fa;"><?= htmlspecialchars($sc['location']) ?></strong></td>
                            <td style="max-width: 250px;"><?= htmlspecialchars($sc['notes'] ?? $sc['incident_type']) ?></td>
                            <td>
                                <span class="badge <?= $sc['status'] === 'DISPATCHED' ? 'badge-danger' : ($sc['status'] === 'ARRIVED' ? 'badge-warning' : 'badge-success') ?>">
                                    <?= htmlspecialchars($sc['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sc['status'] === 'DISPATCHED'): ?>
                                    <form method="POST" action="k3_emergency.php" style="display: inline;">
                                        <input type="hidden" name="action" value="update_ambulance_status">
                                        <input type="hidden" name="call_id" value="<?= $sc['id'] ?>">
                                        <input type="hidden" name="status" value="ARRIVED">
                                        <button type="submit" class="btn btn-warning btn-sm">Tiba di TKP</button>
                                    </form>
                                <?php elseif ($sc['status'] === 'ARRIVED'): ?>
                                    <form method="POST" action="k3_emergency.php" style="display: inline;">
                                        <input type="hidden" name="action" value="update_ambulance_status">
                                        <input type="hidden" name="call_id" value="<?= $sc['id'] ?>">
                                        <input type="hidden" name="status" value="RESOLVED">
                                        <button type="submit" class="btn btn-success btn-sm">Insiden Selesai</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: var(--color-success); font-weight: 600;">Selesai Ditangani</span>
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
