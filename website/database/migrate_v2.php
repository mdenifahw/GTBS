<?php
/**
 * ==============================================================================
 * SKRIP MIGRASI SKEMA DATABASE POSTGRESQL V2 - PT GAJAH TUNGGAL TBK
 * ==============================================================================
 * Fungsi File:
 * - Menjalankan DDL migrasi otomatis untuk memastikan struktur tabel PostgreSQL selalu mutakhir.
 * - Memastikan tabel-tabel berikut ada dan memiliki kolom pendukung yang dibutuhkan:
 *   1. `barcode_master`: Master katalog kode barang, kategori, satuan, dan deskripsi.
 *   2. `barcodes`: Kolom `barcode_type`, `spec`, `lot_number`, status ketersediaan.
 *   3. `users`: Penambahan kolom `password_plain` (untuk lihat password) & `avatar_url` (foto profil).
 *   4. `production_activities`: Log aktivitas perakitan, vulkanisasi, dan inspeksi.
 *   5. `warehouse_racks`: Kolom `is_occupied`, `barcode`, `item_name`, kapasitas rak.
 *   6. `production_scada_logs`: Log telemetri dan produksi per jam/hari/bulan untuk analitik SCADA.
 * 
 * Panduan Pengeditan / Kustomisasi:
 * 1. Menambah Kolom Baru ke Tabel Tertentu:
 *    Gunakan sintaks `ALTER TABLE nama_tabel ADD COLUMN IF NOT EXISTS nama_kolom TIPE_DATA;`
 * 2. Menjalankan Ulang Migrasi:
 *    Skrip ini aman dijalankan berulang kali (Idempotent) via CLI: `php database/migrate_v2.php`.
 * ==============================================================================
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
    echo "Connected to PostgreSQL successfully!\n";

    // 1. barcode_master alterations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barcode_master (
            id SERIAL PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(50) DEFAULT 'PRODUCT', -- 'PRODUCT' or 'ACTIVITY'
            dept_id VARCHAR(50),
            section_id VARCHAR(50),
            description TEXT,
            unit VARCHAR(20) DEFAULT 'PCS',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    
    // Add columns if table already existed without them
    $cols = [
        "ALTER TABLE barcode_master ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'PRODUCT'",
        "ALTER TABLE barcode_master ADD COLUMN IF NOT EXISTS dept_id VARCHAR(50)",
        "ALTER TABLE barcode_master ADD COLUMN IF NOT EXISTS section_id VARCHAR(50)",
        "ALTER TABLE barcode_master ADD COLUMN IF NOT EXISTS description TEXT",
        "ALTER TABLE barcode_master ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'PCS'"
    ];
    foreach ($cols as $sql) {
        $pdo->exec($sql);
    }
    echo "barcode_master updated.\n";

    // 2. barcodes table adjustments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barcodes (
            id SERIAL PRIMARY KEY,
            barcode VARCHAR(100) UNIQUE NOT NULL,
            product_code VARCHAR(100),
            barcode_type VARCHAR(50) DEFAULT 'PRODUCT', -- 'PRODUCT' or 'ACTIVITY'
            dept_id VARCHAR(50),
            section_id VARCHAR(50),
            seq_number INTEGER,
            month_year VARCHAR(20),
            status VARCHAR(50) DEFAULT 'REGISTERED',
            created_by VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $bcols = [
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS barcode_type VARCHAR(50) DEFAULT 'PRODUCT'",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS dept_id VARCHAR(50)",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS section_id VARCHAR(50)",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS seq_number INTEGER",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS month_year VARCHAR(20)",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'REGISTERED'",
        "ALTER TABLE barcodes ADD COLUMN IF NOT EXISTS created_by VARCHAR(50)"
    ];
    foreach ($bcols as $sql) {
        $pdo->exec($sql);
    }
    echo "barcodes table updated.\n";

    // 3. warehouse_racks
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS warehouse_racks (
            id SERIAL PRIMARY KEY,
            rack_code VARCHAR(50) UNIQUE NOT NULL,
            zone VARCHAR(20) NOT NULL,
            row_num INTEGER NOT NULL,
            col_num INTEGER NOT NULL,
            tier_num INTEGER NOT NULL,
            is_occupied BOOLEAN DEFAULT FALSE,
            current_item_name VARCHAR(150),
            current_barcode VARCHAR(100),
            current_qty NUMERIC(10,2) DEFAULT 0,
            current_unit VARCHAR(20) DEFAULT 'PALLET',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $rcols = [
        "ALTER TABLE warehouse_racks ADD COLUMN IF NOT EXISTS current_item_name VARCHAR(150)",
        "ALTER TABLE warehouse_racks ADD COLUMN IF NOT EXISTS current_barcode VARCHAR(100)",
        "ALTER TABLE warehouse_racks ADD COLUMN IF NOT EXISTS current_qty NUMERIC(10,2) DEFAULT 0",
        "ALTER TABLE warehouse_racks ADD COLUMN IF NOT EXISTS current_unit VARCHAR(20) DEFAULT 'PALLET'",
        "ALTER TABLE warehouse_racks ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];
    foreach ($rcols as $sql) {
        $pdo->exec($sql);
    }
    
    // Check if 300 racks exist, if not seed them
    $stmt = $pdo->query("SELECT COUNT(*) FROM warehouse_racks");
    $rackCount = $stmt->fetchColumn();
    if ($rackCount < 300) {
        echo "Seeding warehouse racks to 300...\n";
        $zones = ['A', 'B', 'C', 'D', 'E', 'F'];
        $ins = $pdo->prepare("INSERT INTO warehouse_racks (rack_code, zone, row_num, col_num, tier_num, is_occupied) 
                              VALUES (?, ?, ?, ?, ?, FALSE) ON CONFLICT (rack_code) DO NOTHING");
        foreach ($zones as $z) {
            for ($r = 1; $r <= 5; $r++) {
                for ($c = 1; $c <= 5; $c++) {
                    for ($t = 1; $t <= 2; $t++) {
                        $code = sprintf("RK-%s-%02d-%02d-T%d", $z, $r, $c, $t);
                        $ins->execute([$code, $z, $r, $c, $t]);
                    }
                }
            }
        }
        echo "Racks seeded.\n";
    }

    // 4. curing_gt_delivery_transfer
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS curing_gt_delivery_transfer (
            id SERIAL PRIMARY KEY,
            lorry_id VARCHAR(100) NOT NULL,
            qty_tire INTEGER NOT NULL,
            tire_size VARCHAR(100) NOT NULL,
            operator_nik VARCHAR(50) NOT NULL,
            notes TEXT,
            transfer_date DATE DEFAULT CURRENT_DATE,
            shift VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "curing_gt_delivery_transfer table verified.\n";

    // 5. curing_bladder_assembly
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS curing_bladder_assembly (
            id SERIAL PRIMARY KEY,
            upper_rim_size VARCHAR(50) NOT NULL,
            lower_rim_size VARCHAR(50) NOT NULL,
            bladder_barcode VARCHAR(100) NOT NULL,
            operator_nik VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'ASSEMBLED',
            notes TEXT,
            assembly_date DATE DEFAULT CURRENT_DATE,
            shift VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "curing_bladder_assembly table verified.\n";

    // 6. curing_compound_registry
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS curing_compound_registry (
            id SERIAL PRIMARY KEY,
            mixer_machine VARCHAR(50) NOT NULL,
            compound_code VARCHAR(100) NOT NULL,
            qty_kg NUMERIC(10,2) NOT NULL,
            target_machine VARCHAR(50) NOT NULL,
            operator_nik VARCHAR(50) NOT NULL,
            notes TEXT,
            reg_date DATE DEFAULT CURRENT_DATE,
            shift VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "curing_compound_registry table verified.\n";

    // 7. downtime_events / 4M records
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS downtime_events (
            id SERIAL PRIMARY KEY,
            dept_id VARCHAR(50) NOT NULL,
            section_id VARCHAR(50),
            machine_id VARCHAR(50) NOT NULL,
            category_4m VARCHAR(20) NOT NULL, -- Machine, Man, Method, Material
            description TEXT NOT NULL,
            status VARCHAR(30) DEFAULT 'ACTIVE', -- ACTIVE, IN_PROGRESS, RESOLVED
            operator_nik VARCHAR(50) NOT NULL,
            start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            end_time TIMESTAMP,
            duration_minutes INTEGER DEFAULT 0,
            action_taken TEXT,
            shift VARCHAR(20)
        );
    ");
    echo "downtime_events table verified.\n";

    // 8. production_scada_logs for tracking hourly trends and live scans
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_scada_logs (
            id SERIAL PRIMARY KEY,
            barcode VARCHAR(100) NOT NULL,
            dept_id VARCHAR(50) NOT NULL,
            section_id VARCHAR(50) NOT NULL,
            machine_id VARCHAR(50),
            operator_nik VARCHAR(50) NOT NULL,
            item_code VARCHAR(100),
            qty NUMERIC(10,2) DEFAULT 1,
            quality_status VARCHAR(20) DEFAULT 'PASS', -- PASS, REJECT, REPAIR
            log_date DATE DEFAULT CURRENT_DATE,
            shift VARCHAR(20),
            log_hour INTEGER DEFAULT EXTRACT(HOUR FROM CURRENT_TIME),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "production_scada_logs table verified.\n";

    // 9. messages / broadcasts
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS broadcast_messages (
            id SERIAL PRIMARY KEY,
            sender_nik VARCHAR(50) NOT NULL,
            sender_name VARCHAR(100) NOT NULL,
            target_channel VARCHAR(50) NOT NULL, -- ALL, WH, MAT, BLD, CUR, FI
            title VARCHAR(150),
            message TEXT NOT NULL,
            priority VARCHAR(20) DEFAULT 'NORMAL', -- NORMAL, URGENT, SOS
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "broadcast_messages table verified.\n";

    // 10. Seed some master data if empty
    $chkMaster = $pdo->query("SELECT COUNT(*) FROM barcode_master")->fetchColumn();
    if ($chkMaster == 0) {
        $seedMasters = [
            ['RM-NR-001', 'Natural Rubber Standard RSS-1', 'PRODUCT', 'WH', 'penerima', 'Bahan baku karet alam lembaran', 'KG'],
            ['RM-CB-002', 'Carbon Black N220 High Grade', 'PRODUCT', 'WH', 'penerima', 'Filler penguat kompon ban', 'KG'],
            ['RM-SC-003', 'Steel Cord Wire 2x0.30 HT', 'PRODUCT', 'WH', 'penerima', 'Kawat baja untuk sabuk penguat ban', 'ROLL'],
            ['CMP-TR-01', 'Tread Compound TC-205', 'PRODUCT', 'MAT', 'mixing', 'Kompon tapak ban performa tinggi', 'KG'],
            ['CAL-SW-01', 'Calendered Sidewall Sheet', 'PRODUCT', 'MAT', 'calendery', 'Lembaran ply dinding samping ban', 'ROLL'],
            ['GT-185-65-15', 'Green Tire 185/65 R15 Champiro', 'PRODUCT', 'BLD', 'bld_pcr', 'Ban mentah passenger car radial', 'PCS'],
            ['GT-205-55-16', 'Green Tire 205/55 R16 Savero', 'PRODUCT', 'BLD', 'bld_suv', 'Ban mentah SUV radial', 'PCS'],
            ['BLD-15-STD', 'Bladder Curing Mold 15 Inch', 'PRODUCT', 'CUR', 'bladder_injection', 'Bladder vulkanisasi mold 15 inch', 'PCS'],
            ['ACT-SETUP-01', 'Setup Tooling & Mold Cleaning', 'ACTIVITY', 'CUR', 'curing_tire', 'Aktivitas pembersihan dan ganti mold', 'LOT'],
            ['ACT-CALIB-02', 'Kalibrasi Balancing Machine', 'ACTIVITY', 'FI', 'inspection', 'Aktivitas kalibrasi rutin sensor dinamis', 'LOT']
        ];
        $mIns = $pdo->prepare("INSERT INTO barcode_master (code, name, category, dept_id, section_id, description, unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($seedMasters as $sm) {
            $mIns->execute($sm);
        }
        echo "Initial barcode_master data seeded.\n";
    }

    echo "ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
