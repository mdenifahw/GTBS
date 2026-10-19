<?php
/**
 * ==============================================================================
 * API EKSPOR REGISTRASI BARCODE KE SPREADSHEET (CSV) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengunduh daftar seluruh barcode yang telah dibuat dan terdaftar di sistem.
 * - Menyajikan kolom: Barcode, Tipe/Kategori, Kode Produk, Nama Barang, Nomor Lot, Status, Waktu Dibuat.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengatur Hak Akses Ekspor:
 *    Secara default memerlukan login (`Auth::requireLogin()`). Anda bisa membatasi hanya untuk `admin` jika perlu.
 * 2. Menambah Filter Kustom:
 *    Tambahkan klausa WHERE untuk memfilter rentang tanggal atau status tertentu (AVAILABLE / FINISHED).
 * ==============================================================================
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/export.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$filterType = $_GET['filter_type'] ?? '';
$filterDept = $_GET['filter_dept'] ?? '';

$where = "1=1";
$params = [];

if (!empty($filterType)) {
    $where .= " AND b.barcode_type = ?";
    $params[] = $filterType;
}
if (!empty($filterDept)) {
    $where .= " AND b.dept_id = ?";
    $params[] = $filterDept;
}

$stmt = $pdo->prepare("
    SELECT 
        b.barcode,
        b.barcode_type,
        b.dept_id,
        b.section_id,
        b.product_code,
        bm.name as item_name,
        bm.unit,
        b.seq_number,
        b.month_year,
        b.status,
        b.created_by,
        b.created_at
    FROM barcodes b
    LEFT JOIN barcode_master bm ON b.product_code = bm.code
    WHERE {$where}
    ORDER BY b.dept_id, b.section_id, b.barcode_type, b.seq_number ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    'Nomor Barcode',
    'Kategori (PRODUK / AKTIVITAS / MESIN)',
    'Departemen',
    'Bagian (Section)',
    'Kode Item Master',
    'Nama Item Master',
    'Satuan',
    'Nomor Urut (Seq)',
    'Periode Bulan',
    'Status Registrasi',
    'Didaftarkan Oleh (NIK)',
    'Waktu Pendaftaran'
];

ExportHelper::exportCSV('Barcode_Master_Registry_' . date('Ymd_His'), $headers, $rows);
