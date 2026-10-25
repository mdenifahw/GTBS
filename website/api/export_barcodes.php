<?php
/**
 * ==============================================================================
 * API EKSPOR & LIST REGISTRASI BARCODE - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

// Format JSON untuk Android Mobile App
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    try {
        $stmt = $pdo->query("
            SELECT id, barcode, product_code, product_name, size_name, barcode_type, status, current_dept, created_at 
            FROM barcodes 
            ORDER BY id DESC 
            LIMIT 200
        ");
        $barcodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($barcodes as &$b) {
            $b['id'] = (int)$b['id'];
        }

        echo json_encode([
            'status' => 'success',
            'barcodes' => $barcodes
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Format CSV untuk Export Spreadsheet Web
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/export.php';

Auth::requireLogin();
$stmt = $pdo->query("SELECT barcode, barcode_type, product_code, product_name, status, created_at FROM barcodes ORDER BY id DESC LIMIT 500");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$headers = ['Nomor Barcode', 'Tipe', 'Kode Produk', 'Nama Produk', 'Status', 'Waktu'];
ExportHelper::exportCSV('Barcode_Registry_' . date('Ymd_His'), $headers, $rows);
