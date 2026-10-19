<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Konfigurasi & Koneksi Database (PostgreSQL)
 * File : config/database.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * File ini bertindak sebagai gerbang tunggal (Singleton Connection) penghubung aplikasi PHP Native 
 * ke basis data relasional PostgreSQL (gt_tire_plant).
 * 
 * [PANDUAN MODIFIKASI]:
 * - Jika menggunakan Laragon / XAMPP / Docker dengan port standar PostgreSQL, ubah $port = '5432'.
 * - Jika menggunakan Node.js backend runner khusus (seperti Tire_PlantV.3A), gunakan port '5433'.
 * - Password default database adalah 'password123' atau sesuaikan dengan password user 'postgres' Anda.
 */

class Database {
    // Menyimpan instance PDO tunggal selama lifecycle request (Pola Singleton)
    private static ?PDO $instance = null;

    /**
     * Mengambil instance koneksi PDO PostgreSQL yang aktif.
     * Jika belum ada koneksi, fungsi ini akan otomatis membuat koneksi baru.
     * 
     * @return PDO Instance PDO yang siap menjalankan query SQL
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Parameter konfigurasi database
            $host = '127.0.0.1';        // Alamat server database (localhost)
            $port = '5433';             // Port PostgreSQL aktif (port khusus runner pabrik)
            $dbname = 'gt_tire_plant';  // Nama database utama sistem
            $user = 'postgres';         // Username database
            $password = 'password123';  // Password database

            // Data Source Name (DSN) untuk driver PDO PostgreSQL
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            try {
                // Mencoba menghubungkan ke port 5433
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Lempar exception jika terjadi error SQL
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Kembalikan hasil query berupa array asosiatif
                    PDO::ATTR_EMULATE_PREPARES => false                 // Gunakan native prepared statements untuk keamanan dari SQL Injection
                ]);
            } catch (PDOException $e) {
                // Fallback: Jika port 5433 gagal, otomatis coba port default PostgreSQL (5432)
                try {
                    $dsn5432 = "pgsql:host={$host};port=5432;dbname={$dbname}";
                    self::$instance = new PDO($dsn5432, $user, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]);
                } catch (PDOException $e2) {
                    // Jika kedua port gagal terhubung, hentikan eksekusi dan berikan pesan JSON yang informatif
                    die(json_encode([
                        'status' => 'error',
                        'message' => 'Koneksi database PostgreSQL gagal: ' . $e->getMessage() . 
                                     '. Pastikan server PostgreSQL aktif pada port 5433 atau 5432.'
                    ]));
                }
            }
        }
        return self::$instance;
    }
}