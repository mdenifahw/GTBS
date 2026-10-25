<?php
/**
 * ==============================================================================
 * MANAJEMEN KARYAWAN & PERSONEL PABRIK - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Pusat administrasi data personel karyawan, hak akses (RBAC), dan kredensial login.
 * - Akses dibatasi ketat hanya untuk peran Super Admin, Plant Head, dan Dept Head.
 * - Fitur Utama:
 *   1. Pendaftaran Karyawan Baru:
 *      Mencatat NIK, Nama Lengkap, Email, Departemen, Sub-seksi (Cascading Dropdown), Peran,
 *      dan Upload Foto Profil Karyawan (`uploads/avatars/`).
 *   2. Modal Detail Lengkap & Lihat Password:
 *      Menyediakan tombol "Lihat Detail" yang menampilkan seluruh biodata karyawan termasuk
 *      password teks asli (membantu jika operator/teknisi di lapangan lupa password) dilengkapi
 *      tombol "Salin Password" satu klik.
 *   3. Modal Edit Data & Ganti Foto Profil:
 *      Memungkinkan Admin mengubah biodata karyawan, memperbarui foto profil, atau memindahkan
 *      departemen dan seksi dengan dropdown otomatis tersinkronisasi.
 *   4. Sinkronisasi Avatar Profil:
 *      Foto profil karyawan langsung ditampilkan di tabel personel dan footer navigasi sidebar.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengubah Format / Batas Ukuran Upload Foto:
 *    Cari fungsi `handlePhotoUpload()` (default: format JPG/PNG/WEBP/GIF, batas maks: 2MB).
 * 2. Menambah Jabatan / Role Baru:
 *    Cari array peran di dropdown `<select name="role">` (misal menambah 'qa_lead' atau 'warehouse_supervisor').
 * 3. Menyesuaikan Hak Akses Halaman:
 *    Ubah parameter pada baris `Auth::requireRole([...])` di bagian atas file ini.
 * ==============================================================================
 */
$pageTitle = 'Kelola Karyawan & Personel';
require_once __DIR__ . '/helpers/auth.php';

// Only Admin / Super Admin / Head can manage users
// Sesuai Diagram Hierarki Jabatan: HANYA Super Admin yang memiliki hak akses Kelola Karyawan
Auth::requireRole(['super_admin']);

require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$message = '';
$messageType = 'success';

$uploadDir = __DIR__ . '/uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ==============================================================================
// BAGIAN 1: FUNGSI BANTUAN UPLOAD & VALIDASI FOTO PROFIL KARYAWAN
// ==============================================================================
// Helper function to handle photo upload
function handlePhotoUpload($fileInputName, $prefix = 'avatar') {
    global $uploadDir;
    if (!empty($_FILES[$fileInputName]['name']) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        if (in_array($ext, $allowed)) {
            $destName = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDir . $destName)) {
                return 'uploads/avatars/' . $destName;
            }
        }
    }
    return null;
}

// Handle Tambah Karyawan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $nik = strtoupper(trim($_POST['nik'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
    $sectionId = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $password = trim($_POST['password'] ?? 'password123');

    if (empty($nik) || empty($fullName)) {
        $message = "NIK dan Nama Lengkap karyawan wajib diisi.";
        $messageType = 'danger';
    } else {
        try {
            // Upload foto jika ada
            $photoPath = handlePhotoUpload('photo', 'avatar_new');

            // Generate SVG default avatar jika tidak upload
            if (!$photoPath) {
                $initials = strtoupper(substr($fullName, 0, 2));
                $svgFilename = 'avatar_auto_' . time() . '_' . rand(100, 999) . '.svg';
                $svgContent = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\" width=\"100\" height=\"100\">
                  <defs>
                    <linearGradient id=\"g\" x1=\"0%\" y1=\"0%\" x2=\"100%\" y2=\"100%\">
                      <stop offset=\"0%\" stop-color=\"#3b82f6\" />
                      <stop offset=\"100%\" stop-color=\"#1d4ed8\" />
                    </linearGradient>
                  </defs>
                  <circle cx=\"50\" cy=\"50\" r=\"48\" fill=\"url(#g)\" stroke=\"#ffffff\" stroke-width=\"2\"/>
                  <text x=\"50\" y=\"58\" font-family=\"Arial, sans-serif\" font-size=\"34\" font-weight=\"bold\" fill=\"#ffffff\" text-anchor=\"middle\" dominant-baseline=\"middle\">{$initials}</text>
                </svg>";
                file_put_contents($uploadDir . $svgFilename, $svgContent);
                $photoPath = 'uploads/avatars/' . $svgFilename;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (nik, username, password_hash, password_plain, full_name, role, dept_id, section_id, photo, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$nik, $username ?: $nik, $hash, $password, $fullName, $role, $deptId, $sectionId, $photoPath]);
            $message = "Karyawan baru '{$fullName}' (NIK: {$nik}) berhasil didaftarkan!";
        } catch (Exception $e) {
            $message = "Gagal menambah karyawan: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Edit Biodata Karyawan & Foto
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $nik = strtoupper(trim($_POST['nik'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
    $sectionId = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $password = trim($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

    if ($userId <= 0 || empty($nik) || empty($fullName)) {
        $message = "Data karyawan tidak valid atau tidak lengkap.";
        $messageType = 'danger';
    } else {
        try {
            // Cek upload foto baru
            $newPhotoPath = handlePhotoUpload('photo', 'avatar_u' . $userId);

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($newPhotoPath) {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, is_active = ?, password_hash = ?, password_plain = ?, photo = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$nik, $username ?: $nik, $fullName, $role, $deptId, $sectionId, $isActive, $hash, $password, $newPhotoPath, $userId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, is_active = ?, password_hash = ?, password_plain = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$nik, $username ?: $nik, $fullName, $role, $deptId, $sectionId, $isActive, $hash, $password, $userId]);
                }
            } else {
                if ($newPhotoPath) {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, is_active = ?, photo = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$nik, $username ?: $nik, $fullName, $role, $deptId, $sectionId, $isActive, $newPhotoPath, $userId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$nik, $username ?: $nik, $fullName, $role, $deptId, $sectionId, $isActive, $userId]);
                }
            }

            // Jika user yang diedit adalah akun yang sedang login, update session foto dan nama seketika
            if ($userId === ($currentUser['id'] ?? 0)) {
                if ($newPhotoPath) {
                    $_SESSION['user_data']['photo'] = $newPhotoPath;
                }
                $_SESSION['user_data']['full_name'] = $fullName;
                $_SESSION['user_data']['username'] = $username ?: $nik;
                $_SESSION['user_data']['nik'] = $nik;
                $_SESSION['user_data']['role'] = $role;
            }

            $message = "Biodata karyawan '{$fullName}' (NIK: {$nik}) berhasil diperbarui!";
        } catch (Exception $e) {
            $message = "Gagal memperbarui biodata: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle Hapus Karyawan (Deactivate or Delete)
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    try {
        if ($delId === $currentUser['id']) {
            $message = "Anda tidak dapat menghapus akun Anda sendiri!";
            $messageType = 'danger';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delId]);
            $message = "Karyawan berhasil dihapus dari sistem.";
        }
    } catch (Exception $e) {
        $stmt2 = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
        $stmt2->execute([$delId]);
        $message = "Karyawan dinonaktifkan (arsip data tetap tersimpan).";
    }
}

// Fetch all departments & sections
$departments = $pdo->query("SELECT id, code, name FROM departments ORDER BY id")->fetchAll();
$sections = $pdo->query("SELECT id, dept_id, code, name FROM sections ORDER BY dept_id, id")->fetchAll();

// Fetch all users with department & section details
$usersList = $pdo->query("
    SELECT u.*, d.name as dept_name, d.code as dept_code, s.name as section_name, s.code as section_code
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.id
    LEFT JOIN sections s ON u.section_id = s.id
    ORDER BY u.id DESC
")->fetchAll();
?>

<?php if (!empty($message)): ?>
    <div style="background: <?= $messageType === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $messageType === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $messageType === 'success' ? '#34d399' : '#f87171' ?>; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Form Tambah Karyawan dengan Dropdown Bagian Dinamis & Upload Foto -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            Tambah Data Karyawan Pabrik Baru
        </div>
    </div>

    <form method="POST" action="user_management.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_user">

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label required">1. NIK Karyawan</label>
                <input type="text" name="nik" class="form-input" placeholder="Contoh: GT-BLD-012" required>
            </div>
            <div class="form-group">
                <label class="form-label required">2. Nama Lengkap</label>
                <input type="text" name="full_name" class="form-input" placeholder="Nama lengkap karyawan" required>
            </div>
            <div class="form-group">
                <label class="form-label required">3. Role / Hak Akses</label>
                <select name="role" class="form-select" required>
                    <option value="operator">Operator Lapangan / PDT</option>
                    <option value="leader">Shift Leader / Foreman</option>
                    <option value="section_head">Section Head (Kepala Bagian)</option>
                    <option value="dept_head">Dept Head (Kepala Departemen)</option>
                    <option value="plant_head">Plant Head / Manager</option>
                    <option value="super_admin">Super Administrator</option>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label required">4. Departemen</label>
                <select name="dept_id" id="empDeptSelect" class="form-select" onchange="filterSectionsByDept()" required>
                    <option value="">-- Pilih Departemen --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dynamic Bagian (Section) Dropdown -->
            <div class="form-group">
                <label class="form-label required">5. Bagian (Section) Kerja</label>
                <select name="section_id" id="empSectionSelect" class="form-select" required>
                    <option value="">-- Pilih Departemen Dulu --</option>
                </select>
                <small style="color: var(--text-muted); font-size: 11px;">Opsi bagian otomatis tersaring sesuai departemen yang dipilih.</small>
            </div>

            <div class="form-group">
                <label class="form-label required">6. Password Default</label>
                <input type="text" name="password" class="form-input" value="password123" required>
            </div>
        </div>

        <div class="form-group" style="margin-top: 10px; max-width: 480px;">
            <label class="form-label">7. Upload Foto Karyawan (Opsional)</label>
            <input type="file" name="photo" class="form-input" accept="image/png, image/jpeg, image/jpg, image/webp" style="padding: 6px 10px; font-size: 12.5px;">
            <small style="color: var(--text-muted); font-size: 11px;">Foto akan tampil pada sidebar footer dan tabel profil karyawan.</small>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 14px;">
            Simpan Karyawan Baru
        </button>
    </form>
</div>

<!-- Tabel Daftar Personel & Opsi Detail / Edit / Hapus -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">
                Daftar Karyawan Terdaftar (Total: <?= count($usersList) ?> Personel)
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                Foto, profil lengkap, password akun, dan biodata personel terintegrasi dengan sidebar profil
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 56px; text-align: center;">Foto</th>
                    <th>NIK</th>
                    <th>Nama Lengkap</th>
                    <th>Role</th>
                    <th>Departemen</th>
                    <th>Bagian (Section)</th>
                    <th>Status</th>
                    <th style="min-width: 220px; text-align: center;">Aksi & Kredensial</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usersList as $u): ?>
                    <tr>
                        <td style="text-align: center; width: 56px;">
                            <?php if (!empty($u['photo']) && file_exists(__DIR__ . '/' . $u['photo'])): ?>
                                <img src="<?= htmlspecialchars($u['photo']) ?>" alt="Foto" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.18); display: inline-block; vertical-align: middle;">
                            <?php else: ?>
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: #3b82f6; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13.5px; vertical-align: middle;">
                                    <?= strtoupper(substr($u['full_name'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($u['nik']) ?></strong></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['dept_name'] ?? '-') ?></td>
                        <td><strong style="color: #60a5fa;"><?= htmlspecialchars($u['section_name'] ?? '-') ?></strong></td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['is_active'] ? 'AKTIF' : 'NON-AKTIF' ?>
                            </span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                <!-- Tombol Lihat Biodata Lengkap & Password -->
                                <button type="button" class="btn btn-secondary btn-sm" onclick='openDetailModal(<?= json_encode($u) ?>)' title="Lihat biodata lengkap & password akun">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    Detail & Password
                                </button>

                                <!-- Tombol Edit Biodata Karyawan -->
                                <button type="button" class="btn btn-primary btn-sm" onclick='openEditModal(<?= json_encode($u) ?>)' title="Edit biodata, foto & password">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit
                                </button>

                                <!-- Tombol Hapus / Nonaktifkan -->
                                <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <a href="user_management.php?delete_id=<?= $u['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus / menonaktifkan karyawan <?= htmlspecialchars($u['full_name']) ?> (NIK: <?= htmlspecialchars($u['nik']) ?>)?')">
                                        Hapus
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--text-muted);">(Akun Anda)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL 1: LIHAT BIODATA LENGKAP & PASSWORD KARYAWAN -->
<div id="detailUserModal" class="modal-overlay">
    <div class="modal-dialog" style="max-width: 560px;">
        <div class="modal-header">
            <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Biodata & Kredensial Akun Karyawan
            </div>
            <button type="button" class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px 24px;">
            <!-- Profil Foto & Nama Header -->
            <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px; background: rgba(255,255,255,0.03); padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color);">
                <img id="detailPhoto" src="assets/img/default-avatar.svg" alt="Foto Karyawan" style="width: 62px; height: 62px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; flex-shrink: 0; background: var(--bg-input);">
                <div style="flex: 1;">
                    <div id="detailFullNameHeader" style="font-size: 16px; font-weight: 700; color: var(--text-primary);">-</div>
                    <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 3px;">
                        NIK: <strong id="detailNikHeader" style="color: var(--color-accent);">-</strong> &bull; <span id="detailRoleBadge" class="badge badge-info">-</span>
                    </div>
                </div>
            </div>

            <!-- Box Kredensial Login (Password & Username) -->
            <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 11.5px; font-weight: 700; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Kredensial Login Sistem
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 2px;">Username / NIK Login:</div>
                        <strong id="detailUsername" style="font-family: monospace; font-size: 14px; color: var(--text-primary);">-</strong>
                    </div>

                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">Password Akun (Lihat jika karyawan lupa):</div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="password" id="detailPassInput" readonly class="form-input" style="font-family: monospace; font-size: 14.5px; letter-spacing: 1.5px; width: auto; min-width: 180px; max-width: 240px; background: rgba(0,0,0,0.3); font-weight: 700; color: #34d399;" value="">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnTogglePass" onclick="togglePassVisibility()">
                                👁️ Tampilkan
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnCopyPass" onclick="copyPassword()">
                                📋 Salin
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Biodata Karyawan -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">NIK Karyawan:</div>
                    <strong id="detailNik" style="font-size: 13.5px; color: var(--text-primary);">-</strong>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Nama Lengkap:</div>
                    <strong id="detailFullName" style="font-size: 13.5px; color: var(--text-primary);">-</strong>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Role / Hak Akses:</div>
                    <span id="detailRole" class="badge badge-info">-</span>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Status Akun:</div>
                    <span id="detailStatus">-</span>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Departemen:</div>
                    <strong id="detailDept" style="color: #3b82f6;">-</strong>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Bagian (Section):</div>
                    <strong id="detailSection" style="color: #60a5fa;">-</strong>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Terdaftar Sejak:</div>
                    <span id="detailCreated" style="color: var(--text-secondary); font-size: 12px;">-</span>
                </div>
                <div>
                    <div style="color: var(--text-muted); font-size: 11.5px;">Login Terakhir:</div>
                    <span id="detailLogin" style="color: var(--text-secondary); font-size: 12px;">-</span>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-primary btn-sm" id="btnEditFromDetail" onclick="switchToEditFromDetail()">
                ✏️ Edit Biodata & Foto
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeDetailModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL 2: EDIT BIODATA, FOTO & GANTI PASSWORD KARYAWAN -->
<div id="editUserModal" class="modal-overlay">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit Biodata, Foto & Akun Karyawan
            </div>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>

        <form method="POST" action="user_management.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId" value="">

            <div class="modal-body" style="padding: 20px 24px;">
                <!-- Foto Karyawan & Preview Upload -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px; margin-bottom: 18px; display: flex; gap: 16px; align-items: center;">
                    <img id="editPhotoPreview" src="assets/img/default-avatar.svg" alt="Preview Foto" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-accent); flex-shrink: 0; background: var(--bg-input);">
                    <div style="flex: 1;">
                        <label class="form-label" style="margin-bottom: 4px;">Ubah / Upload Foto Karyawan</label>
                        <input type="file" name="photo" id="editPhotoInput" class="form-input" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewEditPhoto(this)" style="padding: 6px 10px; font-size: 12.5px;">
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">
                            Foto akan tampil pada tabel karyawan dan footer sidebar akun masing-masing (Format: JPG, PNG, WEBP).
                        </small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">1. NIK Karyawan</label>
                        <input type="text" name="nik" id="editNik" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">2. Nama Lengkap</label>
                        <input type="text" name="full_name" id="editFullName" class="form-input" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">3. Username Login</label>
                        <input type="text" name="username" id="editUsername" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">4. Role / Hak Akses</label>
                        <select name="role" id="editRole" class="form-select" required>
                            <option value="operator">Operator Lapangan / PDT</option>
                            <option value="leader">Shift Leader / Foreman</option>
                            <option value="section_head">Section Head (Kepala Bagian)</option>
                            <option value="dept_head">Dept Head (Kepala Departemen)</option>
                            <option value="plant_head">Plant Head / Manager</option>
                            <option value="super_admin">Super Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">5. Departemen</label>
                        <select name="dept_id" id="editDeptSelect" class="form-select" onchange="filterEditSectionsByDept()" required>
                            <option value="">-- Pilih Departemen --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">6. Bagian (Section)</label>
                        <select name="section_id" id="editSectionSelect" class="form-select" required>
                            <option value="">-- Pilih Departemen Dulu --</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">7. Password Baru (Opsional)</label>
                        <input type="text" name="password" id="editPassword" class="form-input" placeholder="Isi password baru untuk mengganti">
                        <small style="color: var(--text-muted); font-size: 11px;">Password karyawan saat ini dapat dilihat pada tombol 'Detail & Password'.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">8. Status Keaktifan</label>
                        <select name="is_active" id="editIsActive" class="form-select" required>
                            <option value="1">AKTIF (Bisa Login Sistem)</option>
                            <option value="0">NON-AKTIF (Terkunci)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan Biodata & Foto</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================================================== -->
<!-- BAGIAN 5: JAVASCRIPT MODAL DETAIL, PENGATURAN PASSWORD & FILTER BERTINGKAT -->
<!-- ===================================================================== -->
<script>
// Master data sections dari PHP
const allSectionsData = <?= json_encode(array_values(array_map(function($s) {
    return [
        'id' => (int)$s['id'],
        'dept_id' => (int)$s['dept_id'],
        'code' => $s['code'],
        'name' => $s['name']
    ];
}, $sections))) ?>;

let currentSelectedUser = null;

// Filter Section dropdown based on selected Department in ADD form
function filterSectionsByDept(preselectedSectionId = null) {
    const deptId = parseInt(document.getElementById('empDeptSelect').value, 10);
    const sectionSelect = document.getElementById('empSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">-- Pilih Bagian (Section) --</option>';

    let hasSelection = false;
    allSectionsData.forEach(s => {
        if (!deptId || s.dept_id === deptId) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.name} (${s.code})`;
            if (preselectedSectionId && s.id === parseInt(preselectedSectionId, 10)) {
                opt.selected = true;
                hasSelection = true;
            }
            sectionSelect.appendChild(opt);
        }
    });

    if (!hasSelection && !preselectedSectionId) {
        sectionSelect.value = '';
    }
}

// Filter Section dropdown in EDIT modal
function filterEditSectionsByDept(preselectedSectionId = null) {
    const deptId = parseInt(document.getElementById('editDeptSelect').value, 10);
    const sectionSelect = document.getElementById('editSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">-- Pilih Bagian (Section) --</option>';

    let hasSelection = false;
    allSectionsData.forEach(s => {
        if (!deptId || s.dept_id === deptId) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.name} (${s.code})`;
            if (preselectedSectionId && s.id === parseInt(preselectedSectionId, 10)) {
                opt.selected = true;
                hasSelection = true;
            }
            sectionSelect.appendChild(opt);
        }
    });

    if (!hasSelection && !preselectedSectionId) {
        sectionSelect.value = '';
    }
}

// Preview foto saat memilih file pada edit form
function previewEditPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('editPhotoPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// MODAL DETAIL: Buka modal dan isi data
function openDetailModal(user) {
    currentSelectedUser = user;
    
    // Foto Profil Header
    const detailPhoto = document.getElementById('detailPhoto');
    detailPhoto.src = user.photo || 'assets/img/default-avatar.svg';
    document.getElementById('detailFullNameHeader').textContent = user.full_name || '-';
    document.getElementById('detailNikHeader').textContent = user.nik || '-';
    document.getElementById('detailRoleBadge').textContent = (user.role || '-').toUpperCase();

    document.getElementById('detailNik').textContent = user.nik || '-';
    document.getElementById('detailFullName').textContent = user.full_name || '-';
    document.getElementById('detailUsername').textContent = user.username || user.nik || '-';
    
    const passInput = document.getElementById('detailPassInput');
    passInput.value = user.password_plain || 'password123';
    passInput.type = 'password';
    document.getElementById('btnTogglePass').textContent = '👁️ Tampilkan';

    document.getElementById('detailRole').textContent = (user.role || '-').toUpperCase();
    document.getElementById('detailDept').textContent = (user.dept_name || '-') + (user.dept_code ? ' (' + user.dept_code + ')' : '');
    document.getElementById('detailSection').textContent = (user.section_name || '-') + (user.section_code ? ' (' + user.section_code + ')' : '');
    
    document.getElementById('detailStatus').innerHTML = user.is_active ? 
        '<span class="badge badge-success">AKTIF</span>' : 
        '<span class="badge badge-danger">NON-AKTIF</span>';

    document.getElementById('detailCreated').textContent = user.created_at ? user.created_at.substring(0, 19) : '-';
    document.getElementById('detailLogin').textContent = user.last_login ? user.last_login.substring(0, 19) : 'Belum Pernah Login';

    document.getElementById('detailUserModal').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detailUserModal').classList.remove('open');
}

// Toggle password visibility in Detail Modal
function togglePassVisibility() {
    const passInput = document.getElementById('detailPassInput');
    const btn = document.getElementById('btnTogglePass');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        btn.textContent = '🙈 Sembunyikan';
    } else {
        passInput.type = 'password';
        btn.textContent = '👁️ Tampilkan';
    }
}

// Salin Password ke Clipboard
function copyPassword() {
    const passInput = document.getElementById('detailPassInput');
    passInput.type = 'text';
    passInput.select();
    passInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(passInput.value).then(() => {
        const btn = document.getElementById('btnCopyPass');
        const orig = btn.textContent;
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => { btn.textContent = orig; }, 2000);
    });
}

// Buka Modal Edit langsung dari Detail Modal
function switchToEditFromDetail() {
    closeDetailModal();
    if (currentSelectedUser) {
        openEditModal(currentSelectedUser);
    }
}

// MODAL EDIT: Buka modal dan isi data form
function openEditModal(user) {
    currentSelectedUser = user;
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editNik').value = user.nik || '';
    document.getElementById('editFullName').value = user.full_name || '';
    document.getElementById('editUsername').value = user.username || user.nik || '';
    document.getElementById('editPassword').value = user.password_plain || '';
    document.getElementById('editRole').value = user.role || 'operator';
    
    // Foto Preview
    const photoPreview = document.getElementById('editPhotoPreview');
    photoPreview.src = user.photo || 'assets/img/default-avatar.svg';
    document.getElementById('editPhotoInput').value = '';

    const deptSelect = document.getElementById('editDeptSelect');
    deptSelect.value = user.dept_id || '';
    
    filterEditSectionsByDept(user.section_id);
    
    document.getElementById('editIsActive').value = user.is_active ? '1' : '0';

    document.getElementById('editUserModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('open');
}

// Close modals when clicking overlay background
window.addEventListener('click', function(e) {
    const detailModal = document.getElementById('detailUserModal');
    const editModal = document.getElementById('editUserModal');
    if (e.target === detailModal) closeDetailModal();
    if (e.target === editModal) closeEditModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
