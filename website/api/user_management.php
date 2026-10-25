<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. LIST USERS
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT id, nik, username, full_name, phone, role, dept_id, section_id, photo, is_active 
            FROM users 
            ORDER BY id DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format is_active as boolean for Gson
        foreach ($users as &$u) {
            $u['id'] = (int)$u['id'];
            $u['is_active'] = (bool)$u['is_active'];
            $u['dept_id'] = $u['dept_id'] !== null ? (int)$u['dept_id'] : null;
            $u['section_id'] = $u['section_id'] !== null ? (int)$u['section_id'] : null;
        }

        echo json_encode([
            'status' => 'success',
            'users' => $users
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. SAVE / UPDATE USER
if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $id = !empty($input['id']) ? (int)$input['id'] : null;
    $nik = strtoupper(trim($input['nik'] ?? ''));
    $username = trim($input['username'] ?? strtolower($nik));
    $fullName = trim($input['full_name'] ?? '');
    $role = trim($input['role'] ?? 'operator');
    $deptId = !empty($input['dept_id']) ? (int)$input['dept_id'] : null;
    $sectionId = !empty($input['section_id']) ? (int)$input['section_id'] : null;
    $phone = trim($input['phone'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($nik) || empty($fullName)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'NIK dan Nama Lengkap wajib diisi.']);
        exit;
    }

    try {
        if ($id) {
            // Update
            if (!empty($password)) {
                $pwdHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, phone = ?, password = ?, password_plain = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$nik, $username, $fullName, $role, $deptId, $sectionId, $phone, $pwdHash, $password, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET nik = ?, username = ?, full_name = ?, role = ?, dept_id = ?, section_id = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$nik, $username, $fullName, $role, $deptId, $sectionId, $phone, $id]);
            }
            $msg = 'Data karyawan berhasil diperbarui.';
        } else {
            // Insert
            $defaultPwd = !empty($password) ? $password : 'password123';
            $pwdHash = password_hash($defaultPwd, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (nik, username, password, password_plain, full_name, role, dept_id, section_id, phone, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, true, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$nik, $username, $pwdHash, $defaultPwd, $fullName, $role, $deptId, $sectionId, $phone]);
            $msg = 'Karyawan baru berhasil ditambahkan.';
        }

        echo json_encode([
            'status' => 'success',
            'message' => $msg
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak didukung.']);
