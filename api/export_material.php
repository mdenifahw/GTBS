<?php
/**
 * ==============================================================================
 * API EKSPOR LOG PRODUKSI DEPT MATERIAL PREPARATION (CSV) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengunduh data riwayat mixing Banbury, pelapisan kain/kawat calender, dan delivery kompon ke format CSV.
 * - Menyajikan: Waktu Olah, Shift, Kode Kompon, Berat (Kg), Batch No, Barcode Lot, NIK & Operator.
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
    WHERE pa.dept_id = 2
    ORDER BY pa.created_at DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = ['Waktu', 'Shift', 'Bagian', 'NIK Operator', 'Nama Operator', 'Jenis Transaksi', 'Rincian'];
ExportHelper::exportCSV('Dept_Material_Report_' . date('Ymd_His'), $headers, $rows);
