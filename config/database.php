<?php
require_once __DIR__ . '/app.php';

/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Konfigurasi & Koneksi Database (PostgreSQL / Supabase / Neon / Cloud DB)
 * File : config/database.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * File ini bertindak sebagai gerbang tunggal (Singleton Connection) penghubung aplikasi PHP Native 
 * ke basis data relasional PostgreSQL (gt_tire_plant) baik di lingkungan lokal (Laragon / Runner)
 * maupun Cloud Database (Supabase / Neon / Vercel Postgres / Render) via Environment Variables.
 * 
 * [PANDUAN ENVIRONMENT VARIABLES CLOUD (Vercel / Supabase / Neon)]:
 * - DATABASE_URL atau POSTGRES_URL: DSN lengkap (misal: postgres://user:pass@host:5432/dbname?sslmode=require)
 * - Atau individual: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSLMODE
 * - Jika tidak ada env di atas, sistem otomatis fallback ke localhost port 5433 / 5432.
 */

class Database {
    // Menyimpan instance PDO tunggal selama lifecycle request (Pola Singleton)
    private static ?PDO $instance = null;

    /**
     * Mengambil instance koneksi PDO PostgreSQL yang aktif.
     * Mendukung Cloud Connection (Supabase/Neon) otomatis via Env Variables.
     * 
     * @return PDO Instance PDO yang siap menjalankan query SQL
     */
    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // 1. Cek DATABASE_URL / POSTGRES_URL (Standar Supabase, Neon, Vercel, Railway, Render)
        $databaseUrl = getenv('DATABASE_URL') ?: (getenv('POSTGRES_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_ENV['POSTGRES_URL'] ?? null)));

        if (!empty($databaseUrl)) {
            $parsed = parse_url($databaseUrl);
            if ($parsed !== false && isset($parsed['host'])) {
                $host = $parsed['host'];
                $port = $parsed['port'] ?? 5432;
                $dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'postgres';
                $user = isset($parsed['user']) ? urldecode($parsed['user']) : 'postgres';
                $password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

                // Periksa parameter sslmode
                $sslMode = 'require';
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $queryParams);
                    if (isset($queryParams['sslmode'])) {
                        $sslMode = $queryParams['sslmode'];
                    }
                }

                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslMode}";

                try {
                    self::$instance = new PDO($dsn, $user, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]);
                    return self::$instance;
                } catch (PDOException $e) {
                    die(json_encode([
                        'status' => 'error',
                        'message' => 'Koneksi Cloud PostgreSQL (DATABASE_URL) gagal: ' . $e->getMessage()
                    ]));
                }
            }
        }

        // 2. Cek Individual Environment Variables (DB_HOST / PGHOST, dll)
        $envHost = getenv('DB_HOST') ?: (getenv('PGHOST') ?: ($_ENV['DB_HOST'] ?? ($_ENV['PGHOST'] ?? null)));
        if (!empty($envHost)) {
            $host = $envHost;
            $port = getenv('DB_PORT') ?: (getenv('PGPORT') ?: '5432');
            $dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('PGDATABASE') ?: 'gt_tire_plant'));
            $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('PGUSER') ?: 'postgres'));
            $password = getenv('DB_PASSWORD') ?: (getenv('PGPASSWORD') ?: '');
            $sslMode = getenv('DB_SSLMODE') ?: ($host !== '127.0.0.1' && $host !== 'localhost' ? 'require' : 'disable');

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            if ($sslMode !== 'disable') {
                $dsn .= ";sslmode={$sslMode}";
            }

            try {
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                return self::$instance;
            } catch (PDOException $e) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Koneksi Cloud PostgreSQL (DB_HOST) gagal: ' . $e->getMessage()
                ]));
            }
        }

        // 3. Fallback Lingkungan Lokal (Port 5433 Runner / Port 5432 Laragon)
        $host = '127.0.0.1';
        $port = '5433';
        $dbname = 'gt_tire_plant';
        $user = 'postgres';
        $password = 'password123';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        try {
            self::$instance = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            // Fallback port standar 5432
            try {
                $dsn5432 = "pgsql:host={$host};port=5432;dbname={$dbname}";
                self::$instance = new PDO($dsn5432, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e2) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Koneksi database PostgreSQL lokal gagal: ' . $e->getMessage() . 
                                 '. Pastikan server PostgreSQL aktif pada port 5433 atau 5432.'
                ]));
            }
        }

        return self::$instance;
    }
}