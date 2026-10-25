<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Helper Penentuan Shift Kerja & Jam Operasional Pabrik
 * File : helpers/shift.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Mengatur pembagian shift kerja 24 jam operasional pabrik ban PT Gajah Tunggal Tbk.
 * Semua modul (Dashboard, SCADA, Terminal Scan, dan Laporan Downtime) merujuk ke kelas ini.
 * 
 * [ATURAN SHIFT PABRIK]:
 * - Shift 1 (Pagi/Siang) : 07:00:00 s/d 14:59:59 (Label: 07:00 - 15:00)
 * - Shift 2 (Sore/Malam) : 15:00:00 s/d 21:59:59 (Label: 15:00 - 22:00)
 * - Shift 3 (Malam/Dini) : 22:00:00 s/d 06:59:59 (Label: 22:00 - 07:00)
 */

class ShiftHelper {
    /**
     * Mendeteksi nomor shift aktif saat ini (1, 2, atau 3) berdasarkan jam WIB terkini.
     * 
     * @return int 1, 2, atau 3
     */
    public static function getCurrentShift(): int {
        // Set zona waktu seragam ke Waktu Indonesia Barat (WIB)
        date_default_timezone_set('Asia/Jakarta');
        $hour = (int)date('G'); // Format 24 jam tanpa leading zero (0 sampai 23)

        if ($hour >= 7 && $hour < 15) {
            return 1; // Shift 1 (07:00 - 15:00)
        } elseif ($hour >= 15 && $hour < 22) {
            return 2; // Shift 2 (15:00 - 22:00)
        } else {
            return 3; // Shift 3 (22:00 - 07:00)
        }
    }

    /**
     * Mengembalikan teks label lengkap untuk shift tertentu.
     * 
     * @param int $shift Nomor shift (1, 2, atau 3)
     * @return string Label shift lengkap dengan jam operasional
     */
    public static function getShiftLabel(int $shift): string {
        switch ($shift) {
            case 1: return "Shift 1 (07:00 - 15:00)";
            case 2: return "Shift 2 (15:00 - 22:00)";
            case 3: return "Shift 3 (22:00 - 07:00)";
            default: return "Shift " . $shift;
        }
    }

    /**
     * Mengembalikan daftar array jam (integer) yang termasuk ke dalam shift tersebut.
     * Berguna untuk filter grafik output SCADA per shift.
     * 
     * @param int $shift Nomor shift
     * @return array Daftar jam
     */
    public static function getShiftHours(int $shift): array {
        switch ($shift) {
            case 1: return [7, 8, 9, 10, 11, 12, 13, 14];
            case 2: return [15, 16, 17, 18, 19, 20, 21];
            case 3: return [22, 23, 0, 1, 2, 3, 4, 5, 6];
            default: return range(0, 23);
        }
    }

    /**
     * Mengambil paket informasi lengkap shift berjalan (Nomor shift, Label, Tanggal, Jam, dan Bulan/Tahun).
     * 
     * @return array Data shift terstruktur
     */
    public static function getShiftInfo(): array {
        date_default_timezone_set('Asia/Jakarta');
        $shift = self::getCurrentShift();
        return [
            'shift'          => $shift,
            'label'          => self::getShiftLabel($shift),
            'date'           => date('Y-m-d'),          // Format database: 2026-10-18
            'date_formatted' => date('d M Y'),          // Format tampilan: 18 Okt 2026
            'time'           => date('H:i:s'),          // Jam menit detik aktif
            'month_year'     => date('Y-m')             // Periode bulan: 2026-10
        ];
    }
}