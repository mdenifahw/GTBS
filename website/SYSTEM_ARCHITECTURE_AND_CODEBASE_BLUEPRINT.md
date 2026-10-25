# BLUEPRINT ARSITEKTUR SISTEM & SPESIFIKASI TEKNIS KODE LENGKAP
## Sistem Barcode Pabrik Ban (GTBS) - PT Gajah Tunggal Tbk
**Dokumen Induk Resmi untuk Pengembangan Web & Mobile Native (Android Studio Java)**
*Versi Dokumen: 2.5 (Enterprise Production Ready) | Update: 25 Oktober 2026*

---

> 📌 **PETUNJUK PENGGUNAAN UNTUK AI & SOFTWARE ENGINEER:**  
> Dokumen ini memuat seluruh spesifikasi teknis, fungsi setiap berkas dan folder, skema basis data PostgreSQL, hierarki hak akses (RBAC), serta alur kerja industri pabrik ban PT Gajah Tunggal Tbk. Gunakan dokumen ini sebagai acuan tunggal (*single source of truth*) dalam membangun atau menyelaraskan aplikasi mobile Android Studio agar memiliki fitur, logika, dan integritas data yang **100% identik** dengan aplikasi web.

---

## DAFTAR ISI
1. [Arsitektur Sistem & Topologi Jaringan](#1-arsitektur-sistem--topologi-jaringan)
2. [Hierarki Hak Akses (Role-Based Access Control / RBAC)](#2-hierarki-hak-akses-role-based-access-control--rbac)
3. [Katalog Struktur Folder Proyek](#3-katalog-struktur-folder-proyek)
4. [Kamus Lengkap File per File (Fungsi, Logika & Database)](#4-kamus-lengkap-file-per-file-fungsi-logika--database)
5. [Skema Lengkap Basis Data PostgreSQL (25 Tabel)](#5-skema-lengkap-basis-data-postgresql-25-tabel)
6. [Alur Operasional 5 Departemen Pabrik Ban](#6-alur-operasional-5-departemen-pabrik-ban)
7. [Modul K3, Downtime & Komunikasi Inter-Departemen](#7-modul-k3-downtime--komunikasi-inter-departemen)
8. [Spesifikasi Kontrak REST API untuk Mobile Sync](#8-spesifikasi-kontrak-rest-api-untuk-mobile-sync)
9. [Arsitektur Local-First & Offline Sync di Android Studio (Java)](#9-arsitektur-local-first--offline-sync-di-android-studio-java)
10. [Design System, Token Warna & Audio Beep Scanner](#10-design-system-token-warna--audio-beep-scanner)

---

## 1. Arsitektur Sistem & Topologi Jaringan

Sistem Barcode Pabrik Ban dirancang untuk mendukung operasional industri 24 jam dengan topologi *Dual-Environment* (Lokal Pabrik & Cloud):

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           CLOUD DEPLOYMENT (VERCEL & SUPABASE)                 │
│                                                                                 │
│   [ Pengguna Internet / Mobile Luar ]                                           │
│                 │                                                               │
│                 ▼ HTTPS                                                         │
│   ┌───────────────────────────┐         PostgreSQL DSN (Pooler Port 6543)       │
│   │   Vercel Serverless       │ ─────────────────────────────────────────────┐  │
│   │   (api/index.php Gateway) │                                              │  │
│   └───────────────────────────┘                                              ▼  │
│                                                            ┌───────────────────┐│
│                                                            │ Supabase Cloud DB ││
│                                                            │ (gt_tire_plant)   ││
│   ┌───────────────────────────┐         PostgreSQL DSN     │ 25 Tabel Relasi   ││
│   │   Laragon Web Server      │ ───────────────────────────► 100% Relasional   ││
│   │   (Port 8000 / PHP 8.5)   │   (atau Local Port 5433)   └───────────────────┘│
│   └───────────────────────────┘                                              ▲  │
│                 ▲                                                            │  │
│                 │ HTTP (10.0.2.2 / 192.168.x.x)                              │  │
│   [ Perangkat PDT / Android Studio ] ────────────────────────────────────────┘  │
│     Room Database (Offline-First)           Direct Sync (Masa Depan)            │
└─────────────────────────────────────────────────────────────────────────────────┘
```

- **Web Server Lokal**: Nginx/Apache Laragon pada port `8000`.
- **Database Lokal**: PostgreSQL pada port `5433` (atau port default `5432`).
- **Cloud Hosting Web**: Vercel Serverless dengan runtime `vercel-php@0.7.2` dipandu oleh Front Controller Gateway [`api/index.php`](file:///D:/laragon/www/GT_Barcode_System/api/index.php).
- **Cloud Database**: Supabase PostgreSQL menggunakan **Connection Pooler (Supavisor)** pada host `aws-0-[region].pooler.supabase.com:6543` dengan user `postgres.[project-ref]` untuk kompatibilitas penuh IPv4.
- **Mobile PDT Handheld**: Aplikasi Java Native Android Studio yang berjalan di perangkat industri (Zebra, Honeywell, Chainway, Urovo) dengan database lokal **Room (SQLite)** untuk menjamin kerja 100% saat offline di *dead zone*.

---

## 2. Hierarki Hak Akses (Role-Based Access Control / RBAC)

Sistem menerapkan prinsip *Segregation of Duties (SoD)* ketat berdasarkan struktur organisasi pabrik ban:

| Role (Kode) | Label Jabatan | Cakupan Wewenang | Akses Mode Lite | Akses Departemen Pabrik | Akses Kelola Karyawan |
| :--- | :--- | :--- | :---: | :---: | :---: |
| `super_admin` | **Super Admin** | Kontrol Penuh Global (Semua Modul & Konfigurasi) | ✅ Ya (Bebas Ganti Stasiun) | ✅ Semua (WH, MAT, BLD, CUR, FI) | ✅ Ya (Eksklusif) |
| `plant_head` | **Plant Head** | Monitoring Global Eksekutif (Dashboard & SCADA) | ❌ Dilarang (Read-Only) | ❌ Monitoring via SCADA | ❌ Tidak |
| `asst_plant_head` | **Assistant Plant Head** | Membantu Plant Head dalam monitoring global | ❌ Dilarang (Read-Only) | ❌ Monitoring via SCADA | ❌ Tidak |
| `dept_head` | **Dept Head** | Monitoring dan supervisi departemen naungannya | ❌ Dilarang (Read-Only) | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |
| `asst_dept_head` | **Assistant Dept Head** | Membantu Dept Head mengawasi departemen | ❌ Dilarang (Read-Only) | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |
| `admin_dept` | **Admin Dept** | Registrasi Barcode Master & input administrasi | ❌ Tidak | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |
| `section_head` | **Section Head** | Pengawasan teknis lini bagian produksi | ✅ Ya (Seksi Sendiri) | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |
| `leader` | **Shift Leader** | Supervisi operasional shift di lantai pabrik | ✅ Ya (Seksi Sendiri) | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |
| `operator` | **Operator** | Pelaksana input scan barcode PDT di lapangan | ✅ Ya (Stasiun Sendiri) | ⚠️ Terbatas Dept Sendiri | ❌ Tidak |

---

## 3. Katalog Struktur Folder Proyek

```text
GT_Barcode_System/
├── api/                             # Kumpulan REST API Endpoint JSON
│   ├── export_barcodes.php          # Ekspor CSV data barcode terdaftar
│   ├── export_building.php          # Ekspor CSV aktivitas perakitan ban (Building)
│   ├── export_curing.php            # Ekspor CSV aktivitas vulkanisasi (Curing)
│   ├── export_inspection.php        # Ekspor CSV hasil QC Final Inspection
│   ├── export_material.php          # Ekspor CSV mixing & calendering material
│   ├── export_scada.php             # Ekspor CSV telemetri log mesin SCADA
│   ├── index.php                    # Front Controller Gateway untuk Vercel Serverless
│   ├── mobile_auth.php              # Endpoint login mobile, validasi NIK & shift
│   ├── mobile_sync.php              # Endpoint sinkronisasi offline (Pull & Push batch)
│   ├── report_downtime.php          # Endpoint pelaporan mesin breakdown/downtime
│   └── sos_call.php                 # Endpoint panggilan darurat medis ambulans K3
│
├── assets/                          # Aset Statis Front-End
│   ├── css/
│   │   └── style.css                # Master CSS Design System (Tema Gelap Industri)
│   ├── img/
│   │   ├── Gajah_Tunggal.png        # Logo resmi PT Gajah Tunggal Tbk
│   │   ├── Logo_Aplikasi.png        # Favicon & logo branding aplikasi
│   │   └── default-avatar.svg       # Avatar default pengguna
│   └── js/
│       └── main.js                  # Script global (Audio beeper, real-time clock)
│
├── config/                          # Konfigurasi Inti Sistem
│   ├── app.php                      # Pengaturan zona waktu, error handler, escaping e(), session
│   └── database.php                 # Koneksi Singleton PDO (Lokal & Supabase Pooler)
│
├── database/                        # Skema & Migrasi Basis Data
│   ├── gt_tire_plant.sql            # Master dump struktur & data awal
│   ├── gt_tire_plant_cloud_ready.sql# Master SQL Cloud-Ready (Inisialisasi sequences & setval)
│   └── migrate_v2.php               # Skrip migrasi kolom & pembaruan skema
│
├── helpers/                         # Pustaka Bantuan (Business Logic Helper)
│   ├── auth.php                     # Kelas otentikasi sesi & autorisasi RBAC
│   ├── export.php                   # Helper ekspor CSV kompatibel PHP 8.4+
│   └── shift.php                    # Helper deteksi 3 shift kerja pabrik ban
│
├── includes/                        # Komponen UI Terfragmentasi (Layout Partial)
│   ├── footer.php                   # Penutup halaman, audio player, modal SOS
│   ├── header.php                   # Bar atas, jam digital WIB, avatar, status darurat
│   └── sidebar.php                  # Menu navigasi kiri dengan proteksi RBAC dinamis
│
├── storage/                         # Penyimpanan Berkas Internal
│   ├── .htaccess                    # Proteksi berkas internal dari akses publik
│   └── logs/
│       └── php_error.log            # Catatan error PHP lingkungan produksi
│
├── uploads/                         # Direktori Berkas Unggahan Pengguna
│   └── avatars/                     # Foto profil pengguna (.jpg, .svg)
│
├── barcode_management.php           # Modul Cetak & Registrasi Barcode Master
├── communication.php                # Modul Komunikasi Antar-Departemen & Intercom
├── dashboard.php                    # Dashboard Pemantauan Produksi & KPI Eksekutif
├── dept_building.php                # Modul Produksi Dept Building (TBM & Lori)
├── dept_curing.php                  # Modul Produksi Dept Curing (Press, Bladder, Repair)
├── dept_final_inspection.php        # Modul Produksi Dept FI (QC Pos, Trimmer, Finish Good)
├── dept_material.php                # Modul Produksi Dept Material (Mixing, Roll, Delivery)
├── dept_warehouse.php               # Modul Dept Gudang (Visual Rak 300 Slot & Auto-Routing)
├── downtime_monitor.php             # Modul Pemantauan Mesin Rusak & Downtime
├── index.php                        # Root Router Utama Aplikasi Web
├── k3_emergency.php                 # Modul Tanggap Darurat K3 Medis ("Tiba di TKP")
├── login.php                        # Halaman Autentikasi Masuk Pengguna
├── logout.php                       # Halaman Keluar & Pemusnahan Sesi
├── operator_lite.php                # Mode Lite: Stasiun Kerja Pemindai Barcode Cepat
├── scada.php                        # Modul Telemetri IoT & Pemantauan Mesin SCADA
├── scan_terminal.php                # Terminal Pemindai Barcode Standar
├── user_management.php              # Modul Manajemen Karyawan (Super Admin Only)
├── vercel.json                      # Konfigurasi Build & Routing Vercel Serverless
├── .vercelignore                    # Daftar filter berkas yang diabaikan Vercel
├── .gitignore                       # Daftar filter berkas yang diabaikan Git
├── MOBILE_APP_DEVELOPMENT_GUIDE.md  # Panduan Praktis Khusus Android Studio
├── PROJECT_PROGRESS_LOG.md          # Catatan Riwayat Seluruh Fase Pengerjaan (Metode 3W)
└── SYSTEM_ARCHITECTURE_AND_CODEBASE_BLUEPRINT.md # Dokumen Master Blueprint Ini
```

---

## 4. Kamus Lengkap File per File (Fungsi, Logika & Database)

### A. Modul Konfigurasi & Infrastruktur
1. [`config/app.php`](file:///D:/laragon/www/GT_Barcode_System/config/app.php):
   - **Fungsi**: Titik awal konfigurasi global.
   - **Logika**: Menetapkan zona waktu WIB (`Asia/Jakarta`), mematikan `display_errors` di produksi untuk mencegah kebocoran path server, mencatat error ke `storage/logs/php_error.log`, mendefinisikan fungsi sanitasi aman `e()` dan `h()` terhadap nilai `null` (kebal warning PHP 8.1 - 8.5+), serta mengonfigurasi penyimpanan sesi di `/tmp` saat berada di lingkungan serverless Vercel.

2. [`config/database.php`](file:///D:/laragon/www/GT_Barcode_System/config/database.php):
   - **Fungsi**: Gateway koneksi basis data tunggal (*Singleton Pattern*).
   - **Logika**: 
     - Membaca `DATABASE_URL` atau `POSTGRES_URL` jika dideploy di Cloud (Supabase/Neon).
     - Otomatis mengaktifkan `sslmode=require` untuk koneksi internet.
     - Mendeteksi port Supabase Pooler (`6543`) dan otomatis mengaktifkan `PDO::ATTR_EMULATE_PREPARES => true` agar query PDO tidak ditolak oleh pgBouncer transaction mode.
     - Fallback otomatis ke `127.0.0.1:5433` (dan port `5432`) jika berjalan offline di laptop lokal Laragon.
     - Memberikan pesan diagnostik pintar jika terdeteksi koneksi ke host IPv6 direct Supabase yang tidak didukung Vercel.

3. [`api/index.php`](file:///D:/laragon/www/GT_Barcode_System/api/index.php):
   - **Fungsi**: Front Controller Gateway Vercel Serverless.
   - **Logika**: Menjalankan `chdir(dirname(__DIR__))` agar root proyek menjadi working directory aktif. Memetakan URL bersih (`/login`, `/dashboard`, `/dept_material`) ke file `.php` terkait di direktori root tanpa memicu error `[unused_function]`.

---

### B. Modul Helper (Logika Bisnis)
4. [`helpers/auth.php`](file:///D:/laragon/www/GT_Barcode_System/helpers/auth.php):
   - **Fungsi**: Manajemen otentikasi dan otorisasi sesi.
   - **Metode Utama**:
     - `Auth::check()`: Mengetahui apakah pengguna sedang login.
     - `Auth::user()`: Mengambil array sesi user lengkap (id, nik, nama, role, dept, section).
     - `Auth::requireRole($roles)`: Membatasi akses halaman hanya untuk peran tertentu, jika melanggar langsung lempar HTTP 403 Forbidden.
     - `Auth::canAccessDeptPage($deptCode)`: Super Admin dapat membuka semua departemen; peran lain hanya dapat membuka departemen miliknya sendiri.
     - `Auth::requireDeptAccess($deptCode)`: Blokir akses departemen yang bukan haknya.

5. [`helpers/shift.php`](file:///D:/laragon/www/GT_Barcode_System/helpers/shift.php):
   - **Fungsi**: Standarisasi 3 shift kerja pabrik ban PT Gajah Tunggal Tbk.
   - **Jadwal Shift**:
     - **Shift 1**: 07:00:00 - 14:59:59 WIB (Label: `Shift 1 (07:00 - 15:00)`)
     - **Shift 2**: 15:00:00 - 21:59:59 WIB (Label: `Shift 2 (15:00 - 22:00)`)
     - **Shift 3**: 22:00:00 - 06:59:59 WIB (Label: `Shift 3 (22:00 - 07:00)`)
   - **Metode**: `ShiftHelper::getCurrentShift()`, `ShiftHelper::getShiftLabel()`, `ShiftHelper::getShiftInfo()`.

---

### C. Modul Antarmuka Inti
6. [`login.php`](file:///D:/laragon/www/GT_Barcode_System/login.php):
   - **Fungsi**: Formulir autentikasi masuk.
   - **Logika**: Menerima input Username/NIK dan Password. Memverifikasi dengan `password_verify()` terhadap `users.password_hash` (serta fallback `users.password_plain`). Menyimpan data sesi dan mengalihkan ke `dashboard.php`.

7. [`dashboard.php`](file:///D:/laragon/www/GT_Barcode_System/dashboard.php):
   - **Fungsi**: Halaman utama pemantauan eksekutif dan ringkasan pabrik.
   - **Logika**: Menghitung total ban diproduksi hari ini (`production_activities`), persentase pencapaian target, total insiden darurat K3 aktif, ringkasan downtime per departemen, grafik perbandingan shift, dan tautan cepat ke stasiun kerja.

8. [`operator_lite.php`](file:///D:/laragon/www/GT_Barcode_System/operator_lite.php):
   - **Fungsi**: Antarmuka pemindai barcode super cepat untuk operator lini produksi & Super Admin.
   - **Fitur Kritis**:
     - *Focus Exclusion*: Input pemindai barcode otomatis fokus kembali ke kolom teks kecuali saat pengguna mengklik menu dropdown `.station-switcher-box` atau elemen interaktif form.
     - *RBAC Guard*: Melarang dan me-redirect peran manajerial (`plant_head`, `asst_plant_head`, `dept_head`, `asst_dept_head`) ke dashboard agar integritas data pemantauan terjaga.
     - Menangani 8 jenis aksi barcode sekaligus: Mixing, Calendering, Building Green Tire, Lori Curing, Pemasangan Bladder, Tire Repair, Gudang Bahan, dan Final Inspection.

---

### D. Modul 5 Departemen Pabrik
9. [`dept_material.php`](file:///D:/laragon/www/GT_Barcode_System/dept_material.php):
   - **Bagian 1: Mixing (Banbury)**: Input batch kompon karet, kode mesin banbury, berat timbang (KG). Menyimpan ke `production_activities` (`action_type = 'MIXING_PRODUCED'`) dan mencatat log SCADA.
   - **Bagian 2: Calendering**: Input lembaran karet/kain ban, tebal (mm), jumlah roll (`CALENDERING_PRODUCED`).
   - **Bagian 3: Material Delivery**: Pengiriman kompon/roll ke Dept Building atau Curing dengan nomor lori/pallet.

10. [`dept_building.php`](file:///D:/laragon/www/GT_Barcode_System/dept_building.php):
    - **Bagian 1: Green Tire Assembly (TBM)**: Perakitan kompon, bead wire, dan ply menjadi Green Tire (ban mentah). Memasukkan barcode ban mentah, ukuran (misal: 185/65 R15), pattern, mesin TBM, status QC.
    - **Bagian 2: Pemuatan ke Lori Curing**: Memuat sejumlah ban mentah ke lori transfer menuju Dept Curing.

11. [`dept_curing.php`](file:///D:/laragon/www/GT_Barcode_System/dept_curing.php):
    - **Bagian 1: Pemasangan Bladder**: Memasang bladder pemanas pada mesin press curing (`BLADDER_INSTALLED`).
    - **Bagian 2: Perakitan Bladder Assembly**: Pasang upper rim dan lower rim.
    - **Bagian 3: Bladder Injection**: Pencetakan bladder baru.
    - **Bagian 4: Registrasi Kompon Curing**: Pendaftaran batch kompon vulkanisasi.
    - **Bagian 5: Tire Repair**: Penanganan ban yang memiliki cacat minor. Memilih jenis cacat, tindakan perbaikan, dan hasil (`REPAIRED_OK` -> status barcode jadi `'FI'`, atau `AFKIR` -> afkir ban).

12. [`dept_final_inspection.php`](file:///D:/laragon/www/GT_Barcode_System/dept_final_inspection.php):
    - **Bagian 1: Pos QC Inspection**: Pos 1 / Pos 2 inspeksi visual dan balance dinamis. Input grade kualitas (`PASS`, `REPAIR`, `SCRAP`).
    - **Bagian 2: Cutting Hair (Spews)**: Pembersihan rambut sisa cetakan ban pada mesin trimmer.
    - **Bagian 3: Finish Goods Delivery**: Pengiriman ban jadi ke Gudang Ban Jadi dengan nomor pallet. Mengubah status barcode menjadi `'DELIVERED_FG'`.

13. [`dept_warehouse.php`](file:///D:/laragon/www/GT_Barcode_System/dept_warehouse.php):
    - **Visual Matrix**: Visualisasi interaktif 300 slot rak penyimpanan (Zone A sampai Zone F).
    - **Smart Validation**: Memberikan dialog peringatan jika operator mencoba menaruh barang ke rak yang sudah penuh/terisi.
    - **Smart Barcode Routing (Pengeluaran Barang)**: Saat operator memasukkan barcode ban jadi yang dicari, sistem otomatis mencari lokasi rak penyimpanan ban tersebut dan mengarahkan operator langsung ke nomor rak target.

---

### E. Modul Keselamatan Kerja & Komunikasi
14. [`k3_emergency.php`](file:///D:/laragon/www/GT_Barcode_System/k3_emergency.php):
    - **Fungsi**: Pusat komando penanganan kecelakaan kerja dan ambulans pabrik.
    - **Logika & Constraint**:
      - Menerima panggilan darurat baru dengan status `'REQUESTED'` atau `'DISPATCHED'`.
      - **Tombol "🚑 Tiba di TKP"**: Mengubah status menjadi `'ON_SITE'` (sesuai check constraint PostgreSQL), mencatat waktu presisi ke kolom `arrival_at = CURRENT_TIMESTAMP`, memunculkan label jam tiba (`H:i:s WIB`), serta menampilkan banner hijau sukses.
      - **Tombol "✅ Insiden Selesai"**: Mengubah status menjadi `'COMPLETED'` setelah penanganan medis tuntas seluruhnya.

15. [`downtime_monitor.php`](file:///D:/laragon/www/GT_Barcode_System/downtime_monitor.php):
    - **Fungsi**: Pencatatan henti lini (*stop line*) dan kerusakan mesin.
    - **Atribut**: Mesin, departemen, jenis downtime (Mechanical, Electrical, Material Shortage, Quality Issue, Utility), durasi menit, tindakan perbaikan.

16. [`communication.php`](file:///D:/laragon/www/GT_Barcode_System/communication.php):
    - **Fungsi**: Intercom pesan cepat antar-departemen (`ALL`, `WH`, `MAT`, `BLD`, `CUR`, `FI`).

17. [`barcode_management.php`](file:///D:/laragon/www/GT_Barcode_System/barcode_management.php):
    - **Fungsi**: Generator master barcode 3 kategori (Produk/Ban, Mesin/Aset, Pallet/Lori) dengan preview SVG Code 128 dan cetak stiker label.

18. [`user_management.php`](file:///D:/laragon/www/GT_Barcode_System/user_management.php):
    - **Fungsi**: Manajemen data master akun pengguna (Khusus Super Admin).

---

## 5. Skema Lengkap Basis Data PostgreSQL (25 Tabel)

Master skema cloud-ready tersimpan di [`database/gt_tire_plant_cloud_ready.sql`](file:///D:/laragon/www/GT_Barcode_System/database/gt_tire_plant_cloud_ready.sql).

### 1. `departments` (Master Departemen)
- `id` (INTEGER, PK, auto-increment via `departments_id_seq`)
- `code` (VARCHAR(20), NOT NULL, UNIQUE) -> `WH`, `MAT`, `BLD`, `CUR`, `FI`
- `name` (VARCHAR(100), NOT NULL)
- `description` (TEXT)
- `icon` (VARCHAR(50), default 'fa-industry')
- `color` (VARCHAR(20), default 'blue')
- `is_active` (BOOLEAN, default true)
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)

### 2. `sections` (Master Bagian / Seksi Kerja)
- `id` (INTEGER, PK, auto-increment via `sections_id_seq`)
- `dept_id` (INTEGER, FK -> `departments.id`, NOT NULL)
- `code` (VARCHAR(20), NOT NULL) -> contoh: `MAT-MIX`, `MAT-CAL`, `BLD-TBM`, `CUR-PRS`
- `name` (VARCHAR(100), NOT NULL)
- `is_active` (BOOLEAN, default true)
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)

### 3. `users` (Master Karyawan & Akun Login)
- `id` (INTEGER, PK, auto-increment via `users_id_seq`)
- `nik` (VARCHAR(30), NOT NULL, UNIQUE)
- `username` (VARCHAR(50), NOT NULL, UNIQUE)
- `password_hash` (VARCHAR(255), NOT NULL)
- `password_plain` (VARCHAR(100)) -> fallback pengujian lokal
- `full_name` (VARCHAR(100), NOT NULL)
- `phone` (VARCHAR(20))
- `role` (VARCHAR(30), NOT NULL) -> `super_admin`, `plant_head`, `asst_plant_head`, `dept_head`, `asst_dept_head`, `admin_dept`, `section_head`, `leader`, `operator`
- `dept_id` (INTEGER, FK -> `departments.id`)
- `section_id` (INTEGER, FK -> `sections.id`)
- `leader_id` (INTEGER, FK -> `users.id`)
- `photo` (VARCHAR(255))
- `is_active` (BOOLEAN, default true)
- `last_login` (TIMESTAMPTZ)
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)

### 4. `barcodes` (Master Barcode Terdaftar)
- `id` (INTEGER, PK, auto-increment via `barcodes_id_seq`)
- `barcode` (VARCHAR(100), NOT NULL, UNIQUE)
- `product_code` (VARCHAR(50))
- `product_name` (VARCHAR(100))
- `size_name` (VARCHAR(100))
- `barcode_type` (VARCHAR(30)) -> `PRODUCT`, `MACHINE`, `PALLET`, `BLADDER`
- `status` (VARCHAR(30), default 'GENERATED') -> `GENERATED`, `BUILDING`, `CURING`, `FI`, `DELIVERED_FG`, `REPAIR`, `AFKIR`
- `current_dept` (VARCHAR(30))
- `dept_id` (VARCHAR(30))
- `section_id` (VARCHAR(30))
- `created_at` (TIMESTAMPTZ)

### 5. `production_activities` (Transaksi Entri Produksi Lini Lapangan)
> ⚠️ **Tabel Paling Kritis! Wajib melengkapi kolom NOT NULL saat query INSERT:**
- `id` (INTEGER, PK, auto-increment via `production_activities_id_seq`)
- `barcode` (VARCHAR(100))
- `dept_id` (INTEGER, NOT NULL, FK -> `departments.id`)
- `section_id` (INTEGER, NOT NULL, FK -> `sections.id`)
- `prod_date` (DATE, NOT NULL, default CURRENT_DATE)
- `shift` (INTEGER, NOT NULL) -> nilai integer numerik murni: `1`, `2`, atau `3`
- `status` (VARCHAR(30), NOT NULL, default 'NORMAL') -> `NORMAL`, `PASS`, `REPAIR`, `SCRAP`
- `qty` (NUMERIC, default 1)
- `unit` (VARCHAR(20), default 'Pcs') -> `Pcs`, `KG`, `ROLL`, `PALLET`, `SET`, `BLADDER`
- `operator_id` (INTEGER, NOT NULL, FK -> `users.id`)
- `operator_nik` (VARCHAR(30))
- `action_type` (VARCHAR(50), NOT NULL) -> contoh: `MIXING_PRODUCED`, `CALENDERING_PRODUCED`, `BUILDING_GT_PRODUCED`, `BLADDER_INSTALLED`, `FINAL_INSPECTION_RECORDED`, `PDT_SCAN`
- `details` (TEXT)
- `notes` (TEXT)
- `machine_code` (VARCHAR(50))
- `item_code` (VARCHAR(50))
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)

### 6. `production_scada_logs` (Telemetri SCADA & Output Per Jam)
- `id` (INTEGER, PK, auto-increment via `production_scada_logs_id_seq`)
- `barcode` (VARCHAR(100))
- `dept_id` (VARCHAR(30))
- `section_id` (INTEGER)
- `machine_id` (VARCHAR(50))
- `operator_nik` (VARCHAR(30))
- `item_code` (VARCHAR(50))
- `qty` (NUMERIC, default 1)
- `quality_status` (VARCHAR(20), default 'PASS')
- `log_date` (DATE, default CURRENT_DATE)
- `shift` (VARCHAR(30)) -> teks 'Shift 1'
- `log_hour` (INTEGER) -> 0 s/d 23
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)

### 7. `warehouse_racks` (Master 300 Slot Rak Gudang)
- `id` (INTEGER, PK, auto-increment via `warehouse_racks_id_seq`)
- `code` (VARCHAR(50))
- `rack_code` (VARCHAR(50), UNIQUE) -> contoh: `RAK-WH-A01` s/d `RAK-WH-F50`
- `zone` (VARCHAR(20)) -> `ZONE-A`, `ZONE-B`, `ZONE-C`, `ZONE-D`, `ZONE-E`, `ZONE-F`
- `row_num` (INTEGER)
- `tier_num` (INTEGER)
- `status` (VARCHAR(30), default 'AVAILABLE') -> `AVAILABLE`, `OCCUPIED`, `MAINTENANCE`
- `is_occupied` (BOOLEAN, default false)
- `current_barcode` (VARCHAR(100))
- `current_item_name` (VARCHAR(100))
- `current_qty` (NUMERIC, default 0)
- `current_unit` (VARCHAR(20), default 'Pcs')
- `last_updated` (TIMESTAMP)

### 8. `k3_ambulance_calls` (Panggilan Ambulans & Tim Medis K3)
- `id` (INTEGER, PK, auto-increment via `k3_ambulance_calls_id_seq`)
- `call_no` (VARCHAR(40), NOT NULL, UNIQUE) -> format `AMB-YYMMDDNNNN`
- `caller_id` (INTEGER, NOT NULL, FK -> `users.id`)
- `caller_name` (VARCHAR(100))
- `dept_id` (INTEGER, FK -> `departments.id`)
- `location_detail` (VARCHAR(150), NOT NULL)
- `patient_condition` (TEXT, NOT NULL)
- `priority` (VARCHAR(20), default 'EMERGENCY') -> `HIGH_EMERGENCY`, `EMERGENCY`, `STANDARD`, `HIGH`, `LOW`
- `status` (VARCHAR(20), default 'REQUESTED') -> **CONSTRAINT**: hanya boleh `REQUESTED`, `DISPATCHED`, `ON_SITE`, `TRANSFERRED`, `COMPLETED`, `CANCELLED`, `DONE`
- `dispatched_at` (TIMESTAMPTZ)
- `arrival_at` (TIMESTAMPTZ) -> terisi saat tombol "Tiba di TKP" diklik
- `dispatched_by` (INTEGER, FK -> `users.id`)
- `driver_name` (VARCHAR(100))
- `notes` (TEXT)
- `created_at` (TIMESTAMPTZ, default CURRENT_TIMESTAMP)
- `updated_at` (TIMESTAMPTZ)

### 9. Tabel Penunjang Lainnya
- `downtime_records` / `downtime_events`: Riwayat kerusakan mesin pabrik.
- `communication_messages` / `broadcast_messages`: Pesan intercom antar-seksi.
- `curing_bladder_assembly`: Catatan rakit rim bladder.
- `curing_bladder_injection`: Catatan injeksi bladder.
- `curing_repairs`: Catatan riwayat reparasi ban.

---

## 6. Alur Operasional 5 Departemen Pabrik Ban

```text
[ GUDANG BAHAN ] (Penerimaan Bahan Baku Karet, Karbon, Kimia di Rak)
       │
       ▼
[ DEPT MATERIAL ] (Mixing Banbury -> Lembaran Compound -> Calendering Roll)
       │
       ▼
[ DEPT BUILDING ] (Perakitan Green Tire pada Mesin TBM -> Masuk Lori)
       │
       ▼
[ DEPT CURING ] (Pemasangan Bladder -> Cetak Panas Vulkanisasi Press -> Repair)
       │
       ▼
[ DEPT FINAL INSPECTION ] (Pos QC Visual & Balance -> Pemotongan Rambut Ban)
       │
       ▼
[ GUDANG BAN JADI ] (Penyimpanan ke Rak Target -> Pengiriman / Order Dispatch)
```

---

## 7. Modul K3, Downtime & Komunikasi Inter-Departemen

### Siklus Status Panggilan Darurat Medis K3:
1. **REQUESTED / DISPATCHED**: Panggilan darurat dikirim oleh operator lantai pabrik melalui modal header web atau tombol darurat PDT mobile. Tim medis K3 bergerak menuju lokasi (*Status Badge Merah: "MENUJU TKP"*).
2. **ON_SITE**: Tim medis tiba di tempat kejadian. Tombol **"🚑 Tiba di TKP"** ditekan. Sistem mencatat `arrival_at = CURRENT_TIMESTAMP` (*Status Badge Kuning: "TIBA DI TKP (ON SITE)"* dan jam tiba ditampilkan presisi).
3. **COMPLETED**: Penanganan korban tuntas. Tombol **"✅ Insiden Selesai"** ditekan (*Status Badge Hijau: "SELESAI (COMPLETED)"*).

---

## 8. Spesifikasi Kontrak REST API untuk Mobile Sync

Base URL (Lokal Emulator): `http://10.0.2.2:8000/GT_Barcode_System/api/`  
Base URL (Lokal PDT Fisik): `http://192.168.1.X:8000/GT_Barcode_System/api/`  
Base URL (Cloud Vercel): `https://[PROJECT-VERCEL].vercel.app/api/`

### 1. `POST mobile_auth.php` (Login Mobile)
- **Request Body**:
  ```json
  {
    "username": "op.wh.snd",
    "password": "password123"
  }
  ```
- **Response**: Mengembalikan token autentikasi, NIK, nama lengkap, role, kode departemen, dan shift aktif.

### 2. `GET mobile_sync.php?action=pull_master` (Download Cache Master)
- **Fungsi**: Mengunduh master data departemen, seksi, rak gudang, dan barcode terdaftar untuk disimpan ke SQLite PDT lokal.

### 3. `POST mobile_sync.php?action=push_activities` (Upload Batch Offline)
- **Request Body**:
  ```json
  {
    "items": [
      {
        "local_id": 1,
        "barcode": "GT-PCR-10001",
        "dept_id": 1,
        "section_id": 1,
        "qty": 1.0,
        "unit": "Pcs",
        "operator_id": 27,
        "operator_nik": "OP-WH-02",
        "action_type": "PDT_SCAN",
        "details": "Scan offline di lorong rak",
        "created_at": "2026-10-25 13:00:00",
        "shift": 1,
        "status": "NORMAL"
      }
    ]
  }
  ```
- **Response**: Mengembalikan array `synced_local_ids: [1]` sehingga aplikasi Android menandai baris tersebut sebagai `SYNCED`.

### 4. `POST mobile_sync.php?action=push_k3` (Panggilan Darurat K3)
- **Request Body**:
  ```json
  {
    "caller_id": 27,
    "caller_name": "Operator PDT",
    "dept_id": 1,
    "location": "Rak A-05",
    "condition": "Terpeleset pallet",
    "priority": "HIGH_EMERGENCY"
  }
  ```

---

## 9. Arsitektur Local-First & Offline Sync di Android Studio (Java)

### Aturan Wajib di Android Studio:
1. Simpan proyek di folder terpisah: `D:\AndroidProjects\GT_Barcode_Mobile\`.
2. Tambahkan `android:usesCleartextTraffic="true"` di `AndroidManifest.xml`.
3. Gunakan **Room Database** dengan tabel `offline_scans`:
   - `localId` (PK, autoGenerate)
   - `barcode`, `deptId`, `sectionId`, `qty`, `unit`, `operatorId`, `operatorNik`, `actionType`, `details`, `createdAt`, `shift`, `status`
   - `syncStatus`: `0` (PENDING), `1` (SYNCED)
4. Saat operator memindai barcode:
   - Simpan langsung ke Room dengan `syncStatus = 0`.
   - Bunyikan audio beep sukses dan tampilkan hijau di layar seketika tanpa menunggu koneksi internet.
   - Panggil `SyncManager.syncPendingScans()`. Jika ada internet, kirim batch data ke `mobile_sync.php?action=push_activities` dan perbarui `syncStatus = 1`. Jika tidak ada internet, biarkan data aman di memori HP.

---

## 10. Design System, Token Warna & Audio Beep Scanner

- **Background Utama**: `#0b0f19` (Gelap Pekat Industri)
- **Card / Kontainer**: `#111827` dengan border tipis `rgba(255, 255, 255, 0.08)`
- **Warna Aksen**:
  - Biru Primer: `#3b82f6` (Tombol Aksi & Navigasi)
  - Hijau Sukses: `#10b981` (Scan Berhasil, Insiden Selesai, Status PASS)
  - Kuning Peringatan: `#f59e0b` / `#fbbf24` (Tiba di TKP, Status REPAIR)
  - Merah Bahaya: `#ef4444` (SOS K3, Mesin Breakdown, Status AFKIR/SCRAP)
- **Audio Feedback**:
  - Beep Sukses (Frekuensi tinggi pendek ~1800Hz, 150ms)
  - Beep Error (Frekuensi rendah berulang ~400Hz, 400ms)

---

_Dokumen ini disusun sebagai cetak biru teknis permanen untuk menjamin keselarasan sempurna antara Web GT Barcode System dan Mobile App Android Studio._