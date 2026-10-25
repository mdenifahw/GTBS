<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/shift.php';

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username/NIK dan Password wajib diisi.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, d.name as dept_name, d.code as dept_code, s.name as section_name, s.code as section_code
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.id
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE (u.username = :user OR u.nik = :user) AND u.is_active = true
        LIMIT 1
    ");
    $stmt->execute([':user' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Pengguna tidak ditemukan atau akun dinonaktifkan.']);
        exit;
    }

    $valid = false;
    if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
        $valid = true;
    } elseif (!empty($user['password_plain']) && $user['password_plain'] === $password) {
        $valid = true;
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Password yang Anda masukkan salah.']);
        exit;
    }

    // Success response with user profile & shift info
    $shiftInfo = ShiftHelper::getShiftInfo();
    
    // Generate simple token for local mobile session
    $token = bin2hex(random_bytes(24));

    echo json_encode([
        'status' => 'success',
        'message' => 'Login berhasil.',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'nik' => $user['nik'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'dept_id' => $user['dept_id'] ? (int)$user['dept_id'] : null,
                'dept_code' => $user['dept_code'],
                'dept_name' => $user['dept_name'],
                'section_id' => $user['section_id'] ? (int)$user['section_id'] : null,
                'section_code' => $user['section_code'],
                'section_name' => $user['section_name']
            ],
            'shift' => [
                'shift_number' => (int)$shiftInfo['shift'],
                'shift_name' => $shiftInfo['label'],
                'date' => date('Y-m-d')
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan server: ' . $e->getMessage()]);
}
