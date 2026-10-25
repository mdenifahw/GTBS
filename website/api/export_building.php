<?php
/**
 * ==============================================================================
 * API EKSPOR LOG PRODUKSI DEPT BUILDING (CSV) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengunduh seluruh riwayat aktivitas perakitan ban mentah (Green Tire Assembly) ke format CSV.
 * - Menyajikan: Waktu Transaksi, Shift, Nama Seksi, NIK & Nama Operator, Mesin TBM, Barcode Ban, Status Mutu.
 * ==============================================================================
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/export.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$stmt = $pdo->query("
    SELECT 
        pa.created_at,
        pa.shift,
        s.name as section_name,
        pa.operator_nik,
        u.full_name as operator_name,
        pa.action_type,
        pa.details
    FROM production_activities pa
    LEFT JOIN sections s ON pa.section_id = s.id
    LEFT JOIN users u ON pa.operator_nik = u.nik
    WHERE pa.dept_id = 3
    ORDER BY pa.created_at DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = ['Waktu', 'Shift', 'Kategori Size', 'NIK Operator', 'Nama Operator', 'Jenis Transaksi', 'Rincian'];
ExportHelper::exportCSV('Dept_Building_Report_' . date('Ymd_His'), $headers, $rows);
