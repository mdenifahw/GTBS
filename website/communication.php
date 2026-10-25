<?php
/**
 * ==============================================================================
 * PAPAN KOMUNIKASI & BROADCAST ANTAR-DEPARTEMEN - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Pusat pertukaran pesan, pengumuman darurat, dan serah terima tugas (shift handover)
 *   antar lini dan departemen produksi di seluruh pabrik.
 * - Saluran Komunikasi (Channel):
 *   1. ALL: Pengumuman umum untuk seluruh personel pabrik.
 *   2. WH: Saluran internal Departemen Gudang Bahan (Warehouse).
 *   3. MAT: Saluran internal Departemen Persiapan Material (Mixing & Calender).
 *   4. BLD: Saluran internal Departemen Perakitan Ban Mentah (Building).
 *   5. CUR: Saluran internal Departemen Pemasakan Ban (Curing & Bladder).
 *   6. FI: Saluran internal Departemen Quality Control (Final Inspection).
 * - Fitur Unggulan:
 *   Pemilihan tujuan pesan terikat secara otomatis dengan tab departemen yang aktif di atas,
 *   sehingga operator tidak perlu memilih ulang dropdown departemen saat mengetik pesan.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Saluran (Channel) Baru:
 *    Tambahkan kode baru di array `` (misal: 'ENG' => 'Engineering / Maintenance').
 * 2. Menambah Tipe Pesan Baru:
 *    Ubah dropdown `<select name="message_type">` untuk menambah kategori pesan (misal: 'K3_ALERT', 'MAINTENANCE').
 * 3. Mengintegrasikan Webhook Notifikasi (Telegram / WhatsApp / Slack):
 *    Pada blok pemrosesan `action === 'send_message'`, Anda dapat memanggil cURL HTTP POST
 *    ke gateway webhook bot perusahaan saat pesan bertipe 'URGENT' dikirim.
 * ==============================================================================
 */
$pageTitle = 'Pesan & Broadcast Komunikasi Pabrik';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';

$channelNames = [
    'ALL' => 'Seluruh Pabrik (ALL)',
    'WH'  => 'Dept Gudang Bahan (WH)',
    'MAT' => 'Dept Material (MAT)',
    'BLD' => 'Dept Building (BLD)',
    'CUR' => 'Dept Curing (CUR)',
    'FI'  => 'Dept Final Inspection (FI)'
];

// Saluran yang diizinkan untuk pengguna ini
$userDept = strtoupper($currentUser['dept_code'] ?? '');
$allowedChannels = ['ALL'];
if (Auth::isPlantLevel()) {
    $allowedChannels = array_keys($channelNames);
} elseif (!empty($userDept) && isset($channelNames[$userDept])) {
    $allowedChannels[] = $userDept;
}

$channelFilter = $_GET['channel'] ?? 'ALL';
if (!in_array($channelFilter, $allowedChannels)) {
    $channelFilter = !empty($userDept) && in_array($userDept, $allowedChannels) ? $userDept : 'ALL';
}

$activeChannelName = $channelNames[$channelFilter] ?? "Saluran {$channelFilter}";

// Handle Broadcast Send
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    // Otomatis menggunakan saluran dari tab yang sedang aktif di atas
    $targetChannel = $_POST['target_channel'] ?? $channelFilter;
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'NORMAL';

    if (empty($title) || empty($body)) {
        $message = "Judul dan pesan tidak boleh kosong.";
    } elseif (!in_array($targetChannel, $allowedChannels)) {
        $message = "Akses ditolak: Anda tidak memiliki wewenang mengirim siaran ke saluran {$targetChannel}.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO broadcast_messages (sender_nik, sender_name, target_channel, title, message, priority)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $currentUser['nik'],
                $currentUser['full_name'],
                $targetChannel,
                $title,
                $body,
                $priority
            ]);
            $channelLabel = $channelNames[$targetChannel] ?? $targetChannel;
            $message = "Pengumuman siaran berhasil dikirimkan ke {$channelLabel}!";
        } catch (Exception $e) {
            $message = "Gagal mengirim broadcast: " . $e->getMessage();
        }
    }
}

// Fetch Messages based on Channel Filter
$where = "1=1";
$params = [];
if ($channelFilter !== 'ALL') {
    $where = "(target_channel = ? OR target_channel = 'ALL')";
    $params[] = $channelFilter;
}

$stmt = $pdo->prepare("
    SELECT * FROM broadcast_messages 
    WHERE {$where}
    ORDER BY created_at DESC 
    LIMIT 30
");
$stmt->execute($params);
$broadcasts = $stmt->fetchAll();
?>

<!-- Channel Filter Pills (Hanya Menampilkan Saluran Sesuai Hak Akses Jabatan) -->
<div class="tabs">
    <a href="communication.php?channel=ALL" class="tab-btn <?= $channelFilter === 'ALL' ? 'active' : '' ?>">
        📢 Seluruh Pabrik (ALL)
    </a>
    <?php if (in_array('WH', $allowedChannels)): ?>
    <a href="communication.php?channel=WH" class="tab-btn <?= $channelFilter === 'WH' ? 'active' : '' ?>">
        📦 Dept Gudang Bahan (WH)
    </a>
    <?php endif; ?>
    <?php if (in_array('MAT', $allowedChannels)): ?>
    <a href="communication.php?channel=MAT" class="tab-btn <?= $channelFilter === 'MAT' ? 'active' : '' ?>">
        ⚗️ Dept Material (MAT)
    </a>
    <?php endif; ?>
    <?php if (in_array('BLD', $allowedChannels)): ?>
    <a href="communication.php?channel=BLD" class="tab-btn <?= $channelFilter === 'BLD' ? 'active' : '' ?>">
        🚗 Dept Building (BLD)
    </a>
    <?php endif; ?>
    <?php if (in_array('CUR', $allowedChannels)): ?>
    <a href="communication.php?channel=CUR" class="tab-btn <?= $channelFilter === 'CUR' ? 'active' : '' ?>">
        🔥 Dept Curing (CUR)
    </a>
    <?php endif; ?>
    <?php if (in_array('FI', $allowedChannels)): ?>
    <a href="communication.php?channel=FI" class="tab-btn <?= $channelFilter === 'FI' ? 'active' : '' ?>">
        🔍 Dept Final Inspection (FI)
    </a>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
    <!-- Box 1: Kirim Pengumuman Siaran -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <div class="card-title">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Buat Siaran Pengumuman Baru
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">
                    Target pengiriman pesan otomatis sesuai dengan pilihan tab departemen di atas
                </div>
            </div>
        </div>

        <form method="POST" action="communication.php?channel=<?= urlencode($channelFilter) ?>">
            <input type="hidden" name="action" value="send_broadcast">
            <!-- Saluran tujuan otomatis diambil dari tab aktif di atas -->
            <input type="hidden" name="target_channel" value="<?= htmlspecialchars($channelFilter) ?>">

            <div class="form-grid">
                <!-- Info Saluran Aktif (Tidak perlu input dropdown lagi) -->
                <div class="form-group">
                    <label class="form-label">Departemen Tujuan Pesan</label>
                    <div style="padding: 9px 14px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.35); border-radius: 6px; font-weight: 700; color: #60a5fa; display: flex; align-items: center; gap: 8px; font-size: 13.5px;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?= htmlspecialchars($activeChannelName) ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label required">Prioritas Siaran</label>
                    <select name="priority" class="form-select" required>
                        <option value="NORMAL">Normal (Informasi Rutin)</option>
                        <option value="URGENT">Penting / Urgent (Instruksi Shift)</option>
                        <option value="SOS">Kritis / Darurat Keselamatan K3</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label required">Judul Pengumuman</label>
                <input type="text" name="title" class="form-input" placeholder="Contoh: Perubahan Jadwal Ganti Mold Shift 2" required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label required">Isi Pesan Siaran</label>
                <textarea name="message" class="form-textarea" rows="4" placeholder="Tulis instruksi atau pengumuman resmi di sini..." required></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 600;">
                Kirim Siaran ke <?= htmlspecialchars($activeChannelName) ?>
            </button>
        </form>
    </div>

    <!-- Box 2: Feed Siaran Masuk -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                Saluran Pesan: [<?= htmlspecialchars($channelFilter) ?>] &mdash; <?= htmlspecialchars($activeChannelName) ?>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; max-height: 480px; overflow-y: auto; padding-right: 4px;">
            <?php if (empty($broadcasts)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Belum ada siaran pesan pada saluran <?= htmlspecialchars($activeChannelName) ?> ini.
                </div>
            <?php else: ?>
                <?php foreach ($broadcasts as $bc): ?>
                    <div style="background: var(--bg-input); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid <?= $bc['priority'] === 'SOS' ? '#ef4444' : ($bc['priority'] === 'URGENT' ? '#f59e0b' : '#3b82f6') ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div>
                                <span class="badge <?= $bc['priority'] === 'SOS' ? 'badge-danger' : ($bc['priority'] === 'URGENT' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= htmlspecialchars($bc['priority']) ?>
                                </span>
                                <span class="badge badge-secondary" style="margin-left: 6px;">
                                    Saluran: <?= htmlspecialchars($bc['target_channel']) ?>
                                </span>
                            </div>
                            <span style="font-size: 11px; color: var(--text-muted);">
                                <?= date('d M Y H:i', strtotime($bc['created_at'])) ?>
                            </span>
                        </div>

                        <div style="font-weight: 700; font-size: 14px; color: var(--text-primary); margin-bottom: 6px;">
                            <?= htmlspecialchars($bc['title']) ?>
                        </div>

                        <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px;">
                            <?= nl2br(htmlspecialchars($bc['message'])) ?>
                        </div>

                        <div style="font-size: 11.5px; color: var(--text-muted);">
                            Oleh: <strong><?= htmlspecialchars($bc['sender_name']) ?></strong> (NIK: <?= htmlspecialchars($bc['sender_nik']) ?>)
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
