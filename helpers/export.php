<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Helper Ekspor Laporan Data ke CSV / Excel Bersih
 * File : helpers/export.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Menghasilkan file download berkas CSV berstandar UTF-8 dengan delimiter semicolon (;)
 * agar otomatis terbaca rapi dalam kolom-kolom Microsoft Excel di sistem operasi Windows.
 * 
 * [PANDUAN MODIFIKASI]:
 * - Header file CSV dikirim dengan BOM UTF-8 (Byte Order Mark) untuk mencegah karakter rusak/aneh di Excel.
 * - Semicolon (;) digunakan sebagai pemisah kolom agar cocok dengan regional format Indonesia/Eropa di Excel.
 */

class ExportHelper {
    /**
     * Mengekspor kumpulan data array ke file CSV yang dapat langsung diunduh pengguna.
     * 
     * @param string $filename Nama file yang diinginkan (tanpa ekstensi .csv)
     * @param array $headers Daftar judul kolom (header)
     * @param array $data Baris-baris data yang akan dicetak
     */
    public static function exportCSV(string $filename, array $headers, array $data): void {
        // Bersihkan output buffer sebelumnya agar file download tidak terkontaminasi spasi/HTML
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set HTTP header untuk memerintahkan browser mengunduh file
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Cetak UTF-8 BOM (Byte Order Mark) agar huruf/karakter dibaca sempurna oleh Excel Windows
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Tulis baris judul kolom (Header baris pertama)
        fputcsv($output, $headers, ';');

        // Tulis setiap baris data
        foreach ($data as $row) {
            $formattedRow = [];
            foreach ($row as $val) {
                // Format nilai khusus agar mudah dibaca pada lembar spreadsheet
                if (is_null($val)) {
                    $formattedRow[] = '-';
                } elseif (is_bool($val)) {
                    $formattedRow[] = $val ? 'YA' : 'TIDAK';
                } else {
                    $formattedRow[] = (string)$val;
                }
            }
            fputcsv($output, $formattedRow, ';');
        }

        fclose($output);
        exit;
    }
}