<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. GENERATE BARCODE
if ($action === 'generate') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $type = strtoupper(trim($input['barcode_type'] ?? 'PRODUCT'));
    $productCode = strtoupper(trim($input['product_code'] ?? 'TIRE'));
    $productName = trim($input['product_name'] ?? 'Ban Radial');
    $sizeName = trim($input['size_name'] ?? $productName);
    $qty = max(1, min(50, (int)($input['qty'] ?? 1)));

    try {
        $pdo->beginTransaction();

        $prefix = $type === 'MACHINE' ? 'MCH-' : ($type === 'BLADDER' ? 'BLD-' : 'GT-');
        $dateCode = date('ymd');

        $generated = [];
        for ($i = 0; $i < $qty; $i++) {
            $randomSeq = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $barcodeStr = $prefix . $productCode . '-' . $dateCode . '-' . $randomSeq;

            $stmt = $pdo->prepare("
                INSERT INTO barcodes (
                    barcode, product_code, product_name, size_name, 
                    barcode_type, status, current_dept, created_at
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, 'GENERATED', 'PRODUKSI', CURRENT_TIMESTAMP
                ) RETURNING id, barcode, product_code, product_name, size_name, barcode_type, status, current_dept, created_at
            ");
            $stmt->execute([$barcodeStr, $productCode, $productName, $sizeName, $type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $generated[] = $row;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => count($generated) . ' barcode berhasil dibuat.',
            'data' => $generated[0] ?? null,
            'barcodes' => $generated
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak didukung.']);
