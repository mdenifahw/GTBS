# CATATAN AKTIVITAS PERUBAHAN PROYEK & PANDUAN ANTI-REGRESI
## GT Barcode System (GTBS) — PT Gajah Tunggal Tbk
**Website Cloud (Vercel + Supabase) & Aplikasi Mobile Android Offline-First (`com.gt.barcodesystem`)**

- **Pembaruan Terakhir**: 1 November 2026
- **URL Produksi Cloud (Vercel)**: https://gtbarcodesystem.vercel.app/
- **URL Unduhan APK Langsung**: https://gtbarcodesystem.vercel.app/gtbs-mobile.apk
- **Database Cloud**: Supabase PostgreSQL (Supavisor IPv4 Connection Pooler)
- **Repositori Lokal**: `D:\laragon\www\GT_Barcode_System` (`website/` & `mobile/`)

---

## 1. Ringkasan Kronologi Seluruh Fase Pengembangan (Fase 1 s/d Fase 18)

| Fase | Tanggal / Milestone | Fokus Perubahan | Status |
| :--- | :--- | :--- | :--- |
| **Fase 1** | Inisialisasi Sistem | Perancangan 24 tabel PostgreSQL (`gt_tire_plant`), struktur 5 Departemen Pabrik (`WH`, `MAT`, `BLD`, `CUR`, `FI`), dan 9 level jabatan RBAC (`SUPER_ADMIN` s/d `OPERATOR`). | Selesai |
| **Fase 2** | Mode Lite Operator | Pembuatan `operator_lite.php` untuk pemindaian cepat di lantai produksi tanpa distraksi grafik makro. | Selesai |
| **Fase 3** | Isolasi RBAC & SoD | Pengetatan `Auth::requireDeptAccess()`, pemisahan tampilan KPI Manajerial vs Operator (*Segregation of Duties*). | Selesai |
| **Fase 4** | Standarisasi Scanner PDT | Dukungan penuh hardware laser scanner fisik (input teks kilat + Enter otomatis) tanpa wajib kamera. | Selesai |
| **Fase 5** | Master Barcode 3 Kategori | Format standar Barcode Produk (`ItemCode+YY+MM+Seq`), Barcode Mesin, dan Barcode Aktivitas. | Selesai |
| **Fase 6** | Identitas Visual & Logo | Penerapan `Logo_Aplikasi.png` dan `Gajah_Tunggal.png` pada seluruh halaman web & favicon. | Selesai |
| **Fase 7–9** | Migrasi Laragon & UI Responsif | Portabilitas path relatif, akses Ngrok, dan tata letak responsif smartphone tanpa tabrakan elemen header. | Selesai |
| **Fase 10–13**| Hardening PHP 8.5 & Skema SQL | Penanganan `htmlspecialchars` null-safe, constraint `NOT NULL` pada `production_activities` & `k3_ambulance_calls`. | Selesai |
| **Fase 14–15**| Cloud Deployment (Vercel + Supabase) | Pembuatan Front Controller `api/index.php`, penyimpanan sesi serverless `/tmp`, dan koneksi IPv4 Supavisor Pooler ke Supabase PostgreSQL. | Selesai |
| **Fase 16** | Mobile UI Architecture (Bottom Nav) | Desain UI/UX Mobile App (`mobile.css` & `mobile.js`) dengan `.m-appbar`, `.m-bottom-nav` 5 menu + tombol tengah **Scan** berikon barcode, serta pembagian menu spesifik per Role. | Selesai |
| **Fase 17** | Hybrid Container & Room SQLite | Integrasi awal WebView Android + Room Database lokal (`offline_scans`) + JavaScript Bridge `AndroidPDT`. | Selesai |
| **Fase 18** | **Penyempurnaan Total Mobile App v2.0 (Online + Offline Paripurna & Cloud Sync)** | Solusi menyeluruh 6 kebutuhan kritis: Zero Blank Screen Offline (`<20ms`), GUI 1:1 Identik Online vs Offline, Login Offline Khusus Akun Terverifikasi Online, Kekebalan *Clear Cache* (`AuthVaultManager`), Cloud Sync penuh ke Vercel + Supabase tanpa PC lokal harus menyala, serta Dokumentasi Anti-Regresi. | **SELESAI (Aktif)** |

---

## 2. Detail Perubahan Fase 18 (Penyempurnaan Mobile App v2.0)

> **Catatan Kebijakan Kode**: Folder `website/` dinyatakan **beku (frozen)** dari sisi tampilan dan fitur karena sudah sesuai kebutuhan pabrik. Satu-satunya perubahan pada `website/` di Fase 18 hanyalah perbaikan bug kolom SQL pada `website/api/user_management.php`, pembaruan paket unduhan `website/gtbs-mobile.apk`, dan pembaruan log dokumentasi ini.

### A. Solusi Masalah #1 — Aplikasi Tidak Lagi Blank / Hitam Saat Dibuka Offline
- **Akar Masalah Sebelumnya**: `MainActivity` langsung memanggil `webView.loadUrl("https://...")` atau IP lokal tanpa memeriksa validitas koneksi internet (`NET_CAPABILITY_VALIDATED`) dan tanpa *timeout watchdog*, sehingga layar tertahan kosong/hitam menunggu *network timeout* 20–30 detik.
- **Perbaikan yang Diterapkan** (`MainActivity.java`):
  1. Saat aplikasi dibuka, `isNetworkValidated()` memeriksa status jaringan nyata (`NET_CAPABILITY_INTERNET` + `NET_CAPABILITY_VALIDATED`).
  2. Jika perangkat **Offline**, aplikasi **tidak menyentuh jaringan sama sekali** dan langsung memuat aset bundel lokal dalam `<20ms`:
     - Jika sudah login (`sessionManager.isLoggedIn()`) -> langsung membuka `file:///android_asset/www/index.html`.
     - Jika belum login -> langsung membuka `file:///android_asset/www/login.html`.
  3. Jika jaringan terdeteksi lambat / *captive portal* (Wi-Fi tersambung tapi internet mati), **HTTP Watchdog Timer 4.5 detik** otomatis memutus penantian dan mengalihkan layar secara mulus ke GUI Offline 1:1 tanpa layar putih atau pesan error bawaan Android WebView.

### B. Solusi Masalah #2 — GUI Offline 100% Sama Persis dengan GUI Online (1:1 Parity)
- **Akar Masalah Sebelumnya**: Halaman `assets/www/index.html` versi lama adalah halaman darurat sederhana yang berbeda jauh dari website, atau memanggil ikon/font CDN luar yang gagal dimuat saat offline (menyebabkan `Logo_Aplikasi.png` berubah menjadi ikon bawaan rusak dan tombol **Scan** berubah menjadi tanda `+`).
- **Perbaikan yang Diterapkan** (`assets/www/login.html`, `assets/www/index.html`, `deploy_to_repo.ps1`):
  1. Menyalin langsung berkas produksi `website/assets/css/style.css`, `website/assets/css/mobile.css`, `Logo_Aplikasi.png`, `Gajah_Tunggal.png`, dan `default-avatar.svg` ke dalam `mobile/app/src/main/assets/www/assets/`.
  2. Membangun ulang `assets/www/login.html` dan `assets/www/index.html` menggunakan struktur DOM yang **100% identik** dengan website mobile (`<body class="m-app">`, `.m-appbar` dengan `Logo_Aplikasi.png`, `.m-chips` pill sub-tab, `.m-hero` ber-gradien merah industri, `.m-sync-card` **Sync Desk**, dan `.m-bottom-nav` 5 menu dengan tombol melayang tengah **Scan** berikon **Barcode Scanner SVG tajam**).
  3. **Zero External CDN Dependency**: Seluruh ikon menggunakan inline SVG vektor presisi tinggi — tidak ada satu pun pemanggilan FontAwesome/Google Fonts eksternal, sehingga **online maupun offline tampil 100% kembar identik**.
  4. Menyediakan seluruh modul sesuai hak akses jabatan (*Super Admin*, *Manajerial PH/APH/DH/ADH*, *Admin Dept/SH/Leader*, dan *Operator*):
     - **Home**: Dashboard KPI & Shift + SCADA Monitoring.
     - **Hasil / Barcode**: Pusat Barcode, Scan Terminal, Generate Barcode, Mode Lite Operator, serta kelima Departemen Pabrik (*Gudang Bahan WH, Dept Material MAT, Dept Building BLD, Dept Curing CUR, Final Inspection FI*) otomatis menyesuaikan departemen & seksi akun!
     - **Tombol Scan Tengah**: Mengaktifkan pemindai PDT / kamera barcode; untuk Operator otomatis mengarahkan ke halaman Barcode stasiun kerjanya terlebih dahulu sesuai spesifikasi.
     - **Message**: Hambatan Mesin (4M Downtime), Pesan & Broadcast, serta K3 & SOS Ambulans Darurat.
     - **Akun**: Profil Saya, Pengaturan Server Cloud, Status Vault Akun Terverifikasi, dan Kelola Karyawan (khusus Super Admin).

### C. Solusi Masalah #3 — Login Offline Wajib Khusus Akun yang Pernah Login Online
- **Akar Masalah Sebelumnya**: Belum ada brankas kriptografi lokal yang membedakan secara ketat antara akun yang sudah terverifikasi server Supabase vs akun yang belum pernah login di HP tersebut.
- **Perbaikan yang Diterapkan** (`AuthVaultManager.java` & `PdtBridge.java`):
  1. Saat user melakukan login dalam keadaan **Online**, `PdtBridge.performLoginAsync()` memverifikasi NIK & Password langsung ke endpoint Cloud Vercel/Supabase (`api/auth.php`).
  2. **Hanya jika server Supabase menyatakan sukses**, `AuthVaultManager.recordOnlineVerifiedLogin()` menyimpan profil lengkap akun beserta **hash password ter-salt (`SHA-256` + random 16-byte salt)** ke dalam `verified_accounts.json`.
  3. Saat user mencoba login dalam keadaan **Offline**:
     - `AuthVaultManager.verifyOfflineLogin(nik, password)` mengecek apakah NIK tersebut tercatat di `verified_accounts.json` dengan flag `verifiedOnline = true`.
     - **Jika BELUM PERNAH login online di perangkat tersebut**: Login **ditolak tegas** dengan pesan jelas bahwa login pertama untuk akun baru wajib terhubung ke jaringan internet (Online).
     - **Jika SUDAH PERNAH login online di perangkat tersebut**: Hash password diverifikasi secara lokal. Bila cocok, user langsung berhasil masuk secara offline lengkap dengan hak akses jabatan, departemen, dan seksinya!

### D. Solusi Masalah #4 — Anti-Logout Saat Offline & Kebal Hapus Cache HP (*Clear Cache Immunity*)
- **Akar Masalah Sebelumnya**:
  1. Saat pengguna menekan **Pengaturan HP -> Aplikasi -> Hapus Cache (*Clear Cache*)** atau pembersih memori otomatis HP berjalan, direktori `getCacheDir()` dan cookie sesi WebView ikut terhapus.
  2. Di sisi server Vercel Serverless, sesi PHP disimpan di `/tmp` yang otomatis terhapus saat *cold start* container fungsi serverless, sehingga WebView diarahkan paksa kembali ke halaman login.
- **Perbaikan yang Diterapkan** (`AuthVaultManager.java`, `SessionManager.java`, `MainActivity.java`):
  1. **Triple-Redundant Persistent Vault**: Sesi login aktif disimpan serentak di **tiga lokasi permanen non-cache** milik sistem Android:
     - `context.getFilesDir()/gtbs_vault/active_session.json`
     - `context.getNoBackupFilesDir()/gtbs_vault/active_session.json`
     - `SharedPreferences ("GT_BARCODE_SESSION")`
     Ketiga lokasi ini **100% TIDAK ikut terhapus** saat pengguna melakukan **Hapus Cache (*Clear Cache*)** pada Handphone!
  2. **Self-Healing Session**: Apabila salah satu penyimpanan hilang, `SessionManager.isLoggedIn()` otomatis memulihkan sesi dari salinan brankas lainnya tanpa meminta user login ulang.
  3. **Silent WebView Session Re-Authentication**: Apabila saat online container Vercel mereset sesi `/tmp` dan mengarahkan WebView ke `login.php`, `MainActivity` menangkap intersepsi URL tersebut dan secara otomatis memulihkan sesi di belakang layar (`silentRestoreWebSession`), atau langsung berpindah ke antarmuka lokal yang identik — **pantang me-logout akun user secara sepihak**.
  4. **Explicit Logout Only**: Sesi akun hanya akan dihapus apabila pengguna secara sadar menekan tombol **"Keluar Akun (Logout)"** (`PdtBridge.logout()` -> `AuthVaultManager.clearActiveSessionOnly()`), sementara riwayat `verified_accounts.json` tetap tersimpan agar akun tersebut tetap bisa login kembali secara offline di kemudian hari.

### E. Solusi Masalah #5 — Integrasi Penuh Cloud Vercel + Supabase Tanpa Ketergantungan PC Lokal
- **Akar Masalah Sebelumnya**: `ApiClient.java` sebelumnya masih mengarah ke IP lokal laptop (`http://10.0.2.2:8000/...` atau `192.168.x.x`), sehingga aplikasi tidak bisa mengirim data jika laptop pengembang dimatikan. Selain itu terdapat ketidakcocokan penamaan field camelCase vs snake_case pada payload sinkronisasi.
- **Perbaikan yang Diterapkan** (`ApiClient.java`, `SyncManager.java`, Entity Room, `website/api/user_management.php`):
  1. Default URL produksi di `ApiClient.java` dan `MainActivity.java` ditetapkan langsung ke **`https://gtbarcodesystem.vercel.app/`** dan **`https://gtbarcodesystem.vercel.app/api/`** (dengan migrasi otomatis URL lokal lama ke URL Cloud Vercel). Laptop pengembang **tidak perlu menyala** agar aplikasi mobile maupun website dapat digunakan kapan saja dan di mana saja!
  2. Seluruh input yang dilakukan saat **Offline** disimpan aman di penyimpanan perangkat (Room SQLite + Vault Queue) dan begitu perangkat **Online**, `SyncManager.syncAll()` otomatis mendorong (*push*) 4 antrean ke Supabase melalui API Vercel:
     - **Scan Produksi & Stasiun Departemen** -> `POST api/mobile_sync.php?action=push_activities` (tabel `production_activities` & `production_scada_logs`).
     - **Panggilan Darurat K3 / SOS Ambulans** -> `POST api/mobile_sync.php?action=push_k3` (tabel `k3_ambulance_calls`).
     - **Laporan Hambatan Mesin 4M (Downtime)** -> `POST api/downtime.php` (tabel `downtime_events`).
     - **Generate Barcode Offline** -> `POST api/generate_barcode.php` (tabel `barcodes`).
  3. Sekaligus menarik (*pull*) data master terbaru (`departments`, `sections`, `warehouse_racks`, `recent_barcodes`, `dashboard_summary`, dan `users`) dari Supabase ke cache lokal perangkat.
  4. Memperbaiki bug `website/api/user_management.php` yang sebelumnya gagal (`SQLSTATE[42703]: column "photo" does not exist`) di Supabase PostgreSQL.

---

## 3. Daftar Berkas yang Dibuat / Diperbarui pada Fase 18

### A. Proyek Android (`mobile/app/src/main/`)
1. [`AndroidManifest.xml`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/AndroidManifest.xml) — Izin `INTERNET`, `ACCESS_NETWORK_STATE`, `VIBRATE`, `CAMERA`, dan konfigurasi `MainActivity`.
2. [`res/layout/activity_main.xml`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/res/layout/activity_main.xml) — Kontainer bersih full-screen dengan penanganan `WindowInsets` sistem bar Android.
3. [`util/AuthVaultManager.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/util/AuthVaultManager.java) — Brankas persisten 3-lapis kebal *Clear Cache*, verifikasi hash `SHA-256` akun offline, cache KPI dashboard, dan antrean offline tambahan.
4. [`util/SessionManager.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/util/SessionManager.java) — Pengelola sesi pengguna dengan pemulihan otomatis (*self-healing*) dari `AuthVaultManager`.
5. [`util/PdtBridge.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/util/PdtBridge.java) — Jembatan `@JavascriptInterface` (`AndroidPDT`) lengkap untuk login hybrid, bootstrap data offline, input scan stasiun 5 departemen, 4M downtime, K3 SOS, generate barcode, broadcast, dan trigger Sync Desk.
6. [`MainActivity.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/MainActivity.java) — Boot instan `<20ms` saat offline, watchdog 4.5 detik, silent session restore, auto-sync `NetworkCallback`, dan injeksi scanner PDT fisik.
7. [`data/remote/api/ApiClient.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/data/remote/api/ApiClient.java) — Klien Retrofit HTTPS ke `https://gtbarcodesystem.vercel.app/api/`.
8. [`data/sync/SyncManager.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/data/sync/SyncManager.java) — Mesin sinkronisasi dua arah 4 antrean offline ke Supabase + penarikan Master Data & KPI.
9. [`data/local/AppDatabase.java`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/java/com/gt/barcodesystem/data/local/AppDatabase.java) & seluruh kelas `entity/*` + `model/*` — Pemetaan `@SerializedName` snake_case presisi untuk PostgreSQL/Supabase.
10. [`assets/www/login.html`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/assets/www/login.html) & [`assets/www/index.html`](file:///D:/laragon/www/GT_Barcode_System/mobile/app/src/main/assets/www/index.html) beserta `assets/css/style.css`, `assets/css/mobile.css`, `assets/img/Logo_Aplikasi.png` — Antarmuka 1:1 Online/Offline tanpa ketergantungan CDN luar.

### B. Proyek Website / Cloud API (`website/`)
1. [`website/api/user_management.php`](file:///D:/laragon/www/GT_Barcode_System/website/api/user_management.php) — Perbaikan query daftar pengguna & kolom `password_hash` untuk kompatibilitas tabel `users` Supabase.
2. [`website/gtbs-mobile.apk`](file:///D:/laragon/www/GT_Barcode_System/website/gtbs-mobile.apk) — Paket instalasi APK terbaru agar dapat langsung diunduh ke Handphone melalui URL Vercel.
3. [`website/PROJECT_PROGRESS_LOG.md`](file:///D:/laragon/www/GT_Barcode_System/website/PROJECT_PROGRESS_LOG.md) — Pembaruan log progres Fase 18 & tabel pencegahan kesalahan.

---

## 4. Aturan Emas Pencegahan Kesalahan di Masa Depan (Anti-Regression Rules)

1. **Pantang Menyimpan Berkas PHP/Java dengan UTF-8 BOM**: Selalu tulis file menggunakan `UTF8Encoding($false)` agar output JSON API bersih dan tidak merusak parser Gson Android maupun header HTTP PHP.
2. **Pantang Memanggil `WebView.loadUrl(https://...)` Tanpa Cek Validasi Jaringan & Watchdog**: Selalu pastikan jalur offline memuat `file:///android_asset/www/...` secara instan tanpa menunggu timeout DNS.
3. **Pantang Menggunakan Link CDN Eksternal pada Aset Mobile (`assets/www/`)**: Seluruh CSS, logo (`Logo_Aplikasi.png`), dan ikon SVG harus terbundel lokal di dalam APK agar tampilan offline 100% identik dengan online.
4. **Pantang Menyimpan Status Login Hanya di Cache atau Cookie WebView**: Wajib gunakan `AuthVaultManager` (`getFilesDir()` + `getNoBackupFilesDir()`) agar sesi kebal *Clear Cache* HP dan *cold start* serverless Vercel.
5. **Pantang Mengizinkan Login Offline Tanpa Riwayat Login Online**: Verifikasi selalu melalui `verified_accounts.json` (`verifiedOnline == true` + kecocokan hash SHA-256).
6. **Pantang Mengirim Payload JSON Tanpa `@SerializedName` Snake-Case & Validasi Panjang Kolom**: Pastikan `barcode` dibatasi `<= 40` karakter sesuai `VARCHAR(40)` pada tabel `production_activities` Supabase, serta `dept_id >= 1`, `section_id >= 1`, dan `operator_id >= 1`.
7. **Pertahankan Kebekuan UI Website (`website/`)**: Jangan mengubah halaman/tampilan website kecuali diminta secara eksplisit untuk perbaikan bug oleh pemilik proyek.
