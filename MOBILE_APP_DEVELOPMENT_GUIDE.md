# PANDUAN PENGEMBANGAN MOBILE APP (ANDROID STUDIO - JAVA)
## Sistem Barcode Pabrik Ban (GTBS) - PT Gajah Tunggal Tbk
**Arsitektur: Local-First / Offline Data Sync untuk Perangkat PDT Industri**

---

> **CATATAN PENTING UNTUK AI & DEVELOPER DI ANDROID STUDIO:**  
> Dokumen ini adalah acuan resmi arsitektur mobile. Seluruh asisten AI atau developer yang bekerja di Android Studio **WAJIB** membaca dan mematuhi aturan dalam dokumen ini agar tidak mengulangi kesalahan konfigurasi sebelumnya.

---

## 1. Pemisahan Direktori Proyek (Architectural Boundary)

| Komponen | Lokasi Direktori | Fungsi & Lingkungan |
| :--- | :--- | :--- |
| **Website & Backend API** | `D:\laragon\www\GT_Barcode_System\` | Web Server Nginx/Laragon (Port 8000) & Cloud Vercel |
| **Mobile App (Android Studio)** | `D:\AndroidProjects\GT_Barcode_Mobile\` | Proyek Android Native (Java) khusus kompilasi `.apk` |

> ⚠️ **ATURAN MUTLAK:**  
> **JANGAN PERNAH** membuat atau memindahkan file Android Studio (`build.gradle`, `app/`, `.gradle`, dll.) ke dalam folder `D:\laragon\www\GT_Barcode_System\`. Folder `www` khusus untuk file PHP web server!

---

## 2. Matriks Kesalahan Masa Lalu & Solusi Pencegahan (DOs & DON'Ts)

1. **JANGAN gunakan `http://localhost:8000` di dalam kode Java Android!**  
   - *Penyebab*: Di dalam Android Emulator, `localhost` merujuk ke mesin virtual Android itu sendiri, bukan ke laptop/PC Anda.
   - *Solusi*:
     - **Di Emulator Android**: Gunakan `http://10.0.2.2:8000/GT_Barcode_System/api/`
     - **Di HP / PDT Fisik (Satu WiFi)**: Gunakan IP laptop Anda, misalnya `http://192.168.1.X:8000/GT_Barcode_System/api/`
     - **Di Cloud (Vercel)**: Gunakan `https://[PROJECT-VERCEL].vercel.app/api/`

2. **WAJIB menambahkan `android:usesCleartextTraffic="true"` di `AndroidManifest.xml`**  
   - *Penyebab*: Android 9 (API 28) ke atas secara default memblokir semua request HTTP (non-HTTPS). Jika tidak diaktifkan, request ke Laragon lokal akan selalu menghasilkan `java.io.IOException: Cleartext HTTP traffic not permitted`.

3. **Prinsip Local-First: JANGAN PERNAH memblokir scanner menunggu respon server!**  
   - *Penyebab*: Di area pabrik dan gudang ban (*dead zone*), sinyal sering hilang. Jika aplikasi menunggu HTTP response sebelum mencatat scan, operator akan mengalami freeze/lag parah.
   - *Solusi*: Simpan scan **SEKETIKA** ke database lokal (**Room Database / SQLite**) dengan status `sync_status = 0 (PENDING)`. Beri respon audio "BEEP" dan visual hijau seketika. Proses upload ke server dilakukan oleh `SyncManager` di latar belakang (background thread).

4. **Hindari Direct IPv6 Supabase di Lingkungan Mobile / Cloud**  
   - *Penyebab*: Host `db.[ref].supabase.co` adalah IPv6-only. Selalu gunakan **Connection Pooler** (`aws-0-[region].pooler.supabase.com:6543`) atau gunakan endpoint REST API yang telah disediakan di folder `api/`.

---

## 3. Spesifikasi Kontrak REST API Backend (Laragon Lokal & Vercel)

Base URL Lokal (Emulator): `http://10.0.2.2:8000/GT_Barcode_System/api/`

### A. Endpoint Login Mobile (`POST mobile_auth.php`)
- **URL**: `mobile_auth.php`
- **Method**: `POST`
- **Headers**: `Content-Type: application/json`
- **Request Body**:
  ```json
  {
    "username": "op.wh.snd",
    "password": "password123"
  }
  ```
- **Response Success (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Login berhasil.",
    "data": {
      "token": "fa54eb10f8ec0cc98093548db82761798303dfb176d84d2a",
      "user": {
        "id": 27,
        "nik": "OP-WH-02",
        "username": "op.wh.snd",
        "full_name": "Agil Mulyono (Operator Pengirim)",
        "role": "operator",
        "dept_id": 1,
        "dept_code": "WH",
        "dept_name": "Dept Gudang Bahan",
        "section_id": 2,
        "section_code": "WH-SND",
        "section_name": "Pengirim"
      },
      "shift": {
        "shift_number": 1,
        "shift_name": "Shift 1 (07:00 - 15:00)",
        "date": "2026-10-25"
      }
    }
  }
  ```

---

### B. Endpoint Download Cache Master Offline (`GET mobile_sync.php?action=pull_master`)
- **URL**: `mobile_sync.php?action=pull_master`
- **Method**: `GET`
- **Tujuan**: Mengunduh daftar master nomor rak gudang, departemen, seksi, dan barcode terdaftar untuk disimpan ke SQLite perangkat PDT agar validasi input dapat dilakukan saat offline tanpa sinyal.
- **Response Success (200 OK)**:
  ```json
  {
    "status": "success",
    "timestamp": "2026-10-25T12:54:20+07:00",
    "data": {
      "departments": [
        {"id": 1, "code": "WH", "name": "Dept Gudang Bahan"},
        {"id": 2, "code": "MAT", "name": "Dept Material"},
        {"id": 3, "code": "BLD", "name": "Dept Building"},
        {"id": 4, "code": "CUR", "name": "Dept Curing"},
        {"id": 5, "code": "FI", "name": "Dept Final Inspection"}
      ],
      "sections": [ ... ],
      "warehouse_racks": [
        {
          "id": 1,
          "rack_code": "RAK-WH-A01",
          "zone": "ZONE-A",
          "status": "AVAILABLE",
          "is_occupied": false,
          "current_barcode": null,
          "current_qty": 0
        }
      ],
      "recent_barcodes": [ ... ]
    }
  }
  ```

---

### C. Endpoint Sinkronisasi Batch Aktivitas Produksi (`POST mobile_sync.php?action=push_activities`)
- **URL**: `mobile_sync.php?action=push_activities`
- **Method**: `POST`
- **Headers**: `Content-Type: application/json`
- **Request Body (Batch Items dari SQLite PDT)**:
  ```json
  {
    "items": [
      {
        "local_id": 101,
        "barcode": "GT-PCR-10001",
        "dept_id": 2,
        "section_id": 3,
        "qty": 25.5,
        "unit": "KG",
        "operator_id": 27,
        "operator_nik": "OP-WH-02",
        "action_type": "MIXING_PRODUCED",
        "details": "Mixing Kompon Tread Mesin Banbury-01",
        "created_at": "2026-10-25 12:45:00",
        "shift": 1,
        "status": "NORMAL"
      }
    ]
  }
  ```
- **Response Success (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "1 aktivitas berhasil disinkronisasi ke server.",
    "synced_local_ids": [101],
    "failed_items": []
  }
  ```
  *(Aplikasi Android kemudian mengupdate `sync_status = 1 (SYNCED)` untuk semua `local_id` yang ada dalam array `synced_local_ids`).*

---

### D. Endpoint Panggilan Darurat K3 (`POST mobile_sync.php?action=push_k3`)
- **URL**: `mobile_sync.php?action=push_k3`
- **Method**: `POST`
- **Request Body**:
  ```json
  {
    "caller_id": 27,
    "caller_name": "Agil Mulyono (Operator)",
    "dept_id": 2,
    "location": "Line Banbury 02",
    "condition": "Operator pingsan di dekat mixer",
    "priority": "HIGH_EMERGENCY"
  }
  ```

---

## 4. Referensi Implementasi Java Android Studio

### A. Entity Database Lokal (Room)
```java
package com.gt.barcodesystem.data.local;

import androidx.room.Entity;
import androidx.room.PrimaryKey;

@Entity(tableName = "offline_scans")
public class ScanRecord {
    @PrimaryKey(autoGenerate = true)
    public int localId;

    public String barcode;
    public int deptId;
    public int sectionId;
    public double qty;
    public String unit;
    public int operatorId;
    public String operatorNik;
    public String actionType;
    public String details;
    public String createdAt;
    public int shift;
    public String status;

    // 0 = PENDING (Tersimpan di HP), 1 = SYNCED (Sudah di Server)
    public int syncStatus; 
}
```

### B. Interface Retrofit
```java
package com.gt.barcodesystem.data.remote;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;
import java.util.Map;

public interface ApiService {
    @POST("mobile_auth.php")
    Call<Map<String, Object>> login(@Body Map<String, String> credentials);

    @GET("mobile_sync.php")
    Call<Map<String, Object>> pullMaster(@Query("action") String action);

    @POST("mobile_sync.php?action=push_activities")
    Call<Map<String, Object>> pushActivities(@Body Map<String, Object> payload);

    @POST("mobile_sync.php?action=push_k3")
    Call<Map<String, Object>> pushK3Emergency(@Body Map<String, Object> payload);
}
```

### C. Alur Kerja Sinkronisasi Data (SyncManager Workflow)
```text
[ Operator Scan Barcode ]
          │
          ▼
[ Simpan ke Room Database (syncStatus = 0) ] ──► Tampilkan Audio/Visual Beep Sukses
          │
          ▼
[ Cek Konektivitas Jaringan ]
          ├────────────────────────┬────────────────────────┤
          ▼ (Jika Offline)                                  ▼ (Jika Online)
Tetap simpan di SQLite.                            Kirim batch via Retrofit ke
Tampilkan badge "⚡ N Data Pending".               POST mobile_sync.php?action=push_activities
Menunggu Auto-Sync saat WiFi aktif.                         │
                                                            ▼
                                                   Terima 'synced_local_ids'
                                                            │
                                                            ▼
                                                   Update Room: syncStatus = 1
```

---

_Dokumen ini dibuat otomatis sebagai panduan resmi arsitektur mobile GT Barcode System._