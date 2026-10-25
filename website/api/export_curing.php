<?php
/**
 * ==============================================================================
 * API EKSPOR LOG PRODUKSI DEPT CURING (CSV) - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Mengunduh seluruh riwayat proses pemasakan/vulkanisasi ban dan perakitan bladder ke format CSV.
 * - Menyajikan: Waktu Pemasakan, Shift, Nomor Mesin Press, Mold Cavity, Barcode Ban, Suhu Steam, Waktu Curing.
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
    WHERE pa.dept_id = 4
    ORDER BY pa.created_at DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = ['Waktu', 'Shift', 'Bagian Curing', 'NIK Operator', 'Nama Operator', 'Jenis Transaksi', 'Rincian'];
ExportHelper::exportCSV('Dept_Curing_Report_' . date('Ymd_His'), $headers, $rows);
