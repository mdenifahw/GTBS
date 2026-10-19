<?php
/**
 * ==============================================================================
 * API ENDPOINT - PANGGILAN DARURAT K3 / AMBULANS DISPATCH - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Menerima trigger panggilan darurat kecelakaan kerja atau medis dari lantai produksi.
 * - Memproses parameter lokasi kejadian dan catatan insiden dari form SOS.
 * - Memasukkan data ke tabel `k3_ambulance_calls` dengan status awal 'DISPATCHED'.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Mengintegrasikan SMS Gateway / Pager K3:
 *    Tambahkan panggilan API SMS/WA ke regu tanggap darurat di dalam blok `try` setelah insert database.
 * 2. Menambah Validasi Input:
 *    Tambahkan pengecekan jika parameter `location` kosong agar diberikan lokasi default.
 * ==============================================================================
 */
// GTBS - SOS Emergency Call API
require_once __DIR__ . '/../helpers/auth.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? 'Area Produksi');
    $notes = trim($_POST['incident_notes'] ?? 'Panggilan darurat K3');

    try {
        $pdo = Database::getConnection();
        // Insert into k3_ambulance_calls
        $stmt = $pdo->prepare("
            INSERT INTO k3_ambulance_calls (caller_name, caller_nik, caller_phone, location, status, incident_type, notes)
            VALUES (?, ?, ?, ?, 'DISPATCHED', 'EMERGENCY', ?)
        ");
        $stmt->execute([
            $user['full_name'],
            $user['nik'],
            '0812-XXXX-XXXX',
            $location,
            $notes
        ]);

        // Insert into broadcast_messages so everyone sees the alert
        $bStmt = $pdo->prepare("
            INSERT INTO broadcast_messages (sender_nik, sender_name, target_channel, title, message, priority)
            VALUES (?, ?, 'ALL', 'SOS DARURAT MEDIS / K3!', ?, 'SOS')
        ");
        $bStmt->execute([
            $user['nik'],
            $user['full_name'],
            "AMBULANS DIPANGGIL KE LOKASI: " . $location . ". Keterangan: " . $notes
        ]);

        header('Location: ../k3_emergency.php?sos_dispatched=1');
        exit;
    } catch (Exception $e) {
        die("Gagal mengirim panggilan SOS: " . $e->getMessage());
    }
} else {
    header('Location: ../dashboard.php');
    exit;
}
