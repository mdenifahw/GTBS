package com.gt.barcodesystem.util;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.lang.reflect.Type;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;

/**
 * AuthVaultManager: Penyimpanan sesi & akun terverifikasi anti-Clear-Cache.
 * Menyimpan data secara redundan pada:
 *   1. context.getFilesDir()/gtbs_vault/ (Internal App Storage - TIDAK terhapus saat Clear Cache HP)
 *   2. context.getNoBackupFilesDir()/gtbs_vault/ (Backup Vault - TIDAK terhapus saat Clear Cache HP)
 *   3. SharedPreferences ("GT_PDT_Session")
 *
 * Aturan Keamanan Offline Login:
 * - Akun HANYA dicatat ke verified_accounts.json apabila pernah berhasil login saat ONLINE ke Supabase.
 * - Jika akun belum pernah login online di perangkat ini, percobaan login offline WAJIB ditolak.
 * - Sesi aktif TIDAK AKAN PERNAH logout sendiri saat offline, saat cache HP dibersihkan, atau saat
 *   cookie PHP serverless Vercel kadaluarsa. Logout hanya terjadi saat user menekan tombol Logout.
 */
public class AuthVaultManager {

    private static final String TAG = "AuthVaultManager";
    private static final String VAULT_DIR_NAME = "gtbs_vault";
    private static final String FILE_ACTIVE_SESSION = "active_session.json";
    private static final String FILE_VERIFIED_ACCOUNTS = "verified_accounts.json";
    private static final String FILE_DASHBOARD_CACHE = "dashboard_cache.json";
    private static final String FILE_PENDING_DOWNTIME = "pending_downtime.json";
    private static final String FILE_PENDING_BARCODES = "pending_barcodes.json";
    private static final String FILE_PENDING_MESSAGES = "pending_messages.json";

    private static AuthVaultManager instance;

    private final Context appContext;
    private final Gson gson;
    private final File primaryVaultDir;
    private final File secondaryVaultDir;

    public static class ActiveSession {
        public boolean isLoggedIn;
        public String token = "";
        public int userId;
        public String nik = "";
        public String username = "";
        public String fullName = "";
        public String role = "";
        public int deptId;
        public String deptCode = "";
        public String deptName = "";
        public int sectionId;
        public String sectionCode = "";
        public String sectionName = "";
        public String stationCode = "CURING";
        public String lastUrl = "";
        public String updatedAt = "";
    }

    public static class VerifiedAccountRecord {
        public UserEntity user;
        public String passwordSalt;
        public String passwordHash;
        public String verifiedAt;
        public int onlineLoginCount;
    }

    public static class SafeVerifiedAccountInfo {
        public int id;
        public String nik;
        public String username;
        public String fullName;
        public String role;
        public Integer deptId;
        public String deptCode;
        public String deptName;
        public Integer sectionId;
        public String sectionCode;
        public String sectionName;
        public String verifiedAt;
    }

    public static class OfflineLoginResult {
        public final boolean success;
        public final String message;
        public final UserEntity user;

        public OfflineLoginResult(boolean success, String message, UserEntity user) {
            this.success = success;
            this.message = message;
            this.user = user;
        }
    }

    public static class LocalDowntimeItem {
        public long localId;
        public String machineCode;
        public int deptId;
        public String deptCode;
        public String downtimeType;
        public int durationMinutes;
        public String description;
        public String operatorNik;
        public int reportedBy;
        public String createdAt;
        public int syncStatus; // 0 = pending, 1 = synced
    }

    public static class LocalBarcodeItem {
        public long localId;
        public String barcode;
        public String barcodeType;
        public String productCode;
        public String productName;
        public String sizeName;
        public int qty;
        public String currentDept;
        public String status;
        public String createdAt;
        public int syncStatus; // 0 = pending, 1 = synced
    }

    public static class LocalMessageItem {
        public long localId;
        public String senderNik;
        public String senderName;
        public String targetChannel;
        public String title;
        public String message;
        public String priority;
        public String createdAt;
        public int syncStatus;
    }

    private AuthVaultManager(Context context) {
        this.appContext = context.getApplicationContext();
        this.gson = new GsonBuilder().disableHtmlEscaping().create();
        this.primaryVaultDir = new File(appContext.getFilesDir(), VAULT_DIR_NAME);
        this.secondaryVaultDir = new File(appContext.getNoBackupFilesDir(), VAULT_DIR_NAME);
        if (!primaryVaultDir.exists()) primaryVaultDir.mkdirs();
        if (!secondaryVaultDir.exists()) secondaryVaultDir.mkdirs();
    }

    public static synchronized AuthVaultManager getInstance(Context context) {
        if (instance == null) {
            instance = new AuthVaultManager(context);
        }
        return instance;
    }

    // ==================== ACTIVE SESSION (CLEAR-CACHE IMMUNE) ====================

    public synchronized ActiveSession getActiveSession() {
        String raw = readRedundantFile(FILE_ACTIVE_SESSION);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                ActiveSession s = gson.fromJson(raw, ActiveSession.class);
                if (s != null) {
                    return s;
                }
            } catch (Exception e) {
                Log.w(TAG, "Failed parsing active_session.json: " + e.getMessage());
            }
        }
        // Fallback migration from legacy SharedPreferences if vault file doesn't exist yet
        SharedPreferences pref = appContext.getSharedPreferences("GT_PDT_Session", Context.MODE_PRIVATE);
        if (pref.getBoolean("isLoggedIn", false)) {
            ActiveSession migrated = new ActiveSession();
            migrated.isLoggedIn = true;
            migrated.token = pref.getString("authToken", "");
            migrated.userId = pref.getInt("userId", 1);
            migrated.nik = pref.getString("nik", "");
            migrated.username = pref.getString("username", "");
            migrated.fullName = pref.getString("fullName", "");
            migrated.role = pref.getString("role", "operator");
            migrated.deptId = pref.getInt("deptId", 0);
            migrated.deptCode = pref.getString("deptCode", "");
            migrated.deptName = pref.getString("deptName", "");
            migrated.sectionId = pref.getInt("sectionId", 0);
            migrated.sectionCode = pref.getString("sectionCode", "");
            migrated.sectionName = pref.getString("sectionName", "");
            migrated.stationCode = pref.getString("stationCode", "CURING");
            migrated.lastUrl = pref.getString("lastVisitedUrl", "");
            migrated.updatedAt = nowTimestamp();
            writeRedundantFile(FILE_ACTIVE_SESSION, gson.toJson(migrated));
            return migrated;
        }
        return new ActiveSession();
    }

    public synchronized void saveActiveSession(ActiveSession session) {
        if (session == null) return;
        enrichDeptAndSectionLabels(session);
        session.updatedAt = nowTimestamp();
        writeRedundantFile(FILE_ACTIVE_SESSION, gson.toJson(session));
    }

    public synchronized boolean isLoggedIn() {
        ActiveSession s = getActiveSession();
        return s != null && s.isLoggedIn && s.nik != null && !s.nik.isEmpty();
    }

    public synchronized void clearActiveSessionOnExplicitLogout() {
        ActiveSession empty = new ActiveSession();
        empty.isLoggedIn = false;
        empty.updatedAt = nowTimestamp();
        writeRedundantFile(FILE_ACTIVE_SESSION, gson.toJson(empty));
    }

    // ==================== VERIFIED ONLINE ACCOUNTS VAULT ====================

    public synchronized void saveOnlineVerifiedLogin(UserEntity user, String rawPassword, String token) {
        if (user == null) return;
        enrichUserEntityLabels(user);

        Map<String, VerifiedAccountRecord> map = loadVerifiedMapInternal();
        String primaryKey = normalizeAccountKey(user.getNik() != null && !user.getNik().isEmpty()
                ? user.getNik() : user.getUsername());

        VerifiedAccountRecord existing = map.get(primaryKey);
        VerifiedAccountRecord record = existing != null ? existing : new VerifiedAccountRecord();
        record.user = user;
        record.verifiedAt = nowTimestamp();
        record.onlineLoginCount = (existing != null ? existing.onlineLoginCount : 0) + 1;

        if (rawPassword != null && !rawPassword.isEmpty()) {
            String salt = generateSaltHex();
            record.passwordSalt = salt;
            record.passwordHash = sha256Hex(salt + ":" + rawPassword);
        }

        map.put(primaryKey, record);
        writeRedundantFile(FILE_VERIFIED_ACCOUNTS, gson.toJson(map));

        // Activate persistent session simultaneously
        ActiveSession s = getActiveSession();
        s.isLoggedIn = true;
        s.token = token != null ? token : "ONLINE_VERIFIED_TOKEN";
        s.userId = user.getId() > 0 ? user.getId() : 1;
        s.nik = nonNull(user.getNik());
        s.username = nonNull(user.getUsername());
        s.fullName = nonNull(user.getFullName());
        s.role = nonNull(user.getRole());
        s.deptId = user.getDeptId() != null ? user.getDeptId() : 0;
        s.deptCode = nonNull(user.getDeptCode());
        s.deptName = nonNull(user.getDeptName());
        s.sectionId = user.getSectionId() != null ? user.getSectionId() : 0;
        s.sectionCode = nonNull(user.getSectionCode());
        s.sectionName = nonNull(user.getSectionName());
        if (s.deptCode != null && !s.deptCode.isEmpty()) {
            s.stationCode = mapDeptCodeToStation(s.deptCode);
        }
        saveActiveSession(s);
    }

    public synchronized OfflineLoginResult verifyOfflineLogin(String usernameOrNik, String rawPassword) {
        if (usernameOrNik == null || usernameOrNik.trim().isEmpty()) {
            return new OfflineLoginResult(false, "Harap masukkan NIK atau Username karyawan.", null);
        }
        if (rawPassword == null || rawPassword.isEmpty()) {
            return new OfflineLoginResult(false, "Harap masukkan Password akun.", null);
        }

        Map<String, VerifiedAccountRecord> map = loadVerifiedMapInternal();
        if (map.isEmpty()) {
            return new OfflineLoginResult(
                    false,
                    "Login Offline Ditolak: Belum ada akun yang pernah login saat Online di perangkat ini. Wajib terhubung ke jaringan untuk login pertama kali.",
                    null
            );
        }

        VerifiedAccountRecord matched = findVerifiedRecord(map, usernameOrNik.trim());
        if (matched == null || matched.user == null) {
            return new OfflineLoginResult(
                    false,
                    "Login Offline Ditolak: Akun \"" + usernameOrNik.trim() + "\" belum pernah login dalam keadaan Online di perangkat ini. Silakan hubungkan ke internet terlebih dahulu.",
                    null
            );
        }

        if (matched.passwordHash == null || matched.passwordSalt == null
                || matched.passwordHash.isEmpty() || matched.passwordSalt.isEmpty()) {
            return new OfflineLoginResult(
                    false,
                    "Kredensial offline akun ini belum lengkap. Silakan login menggunakan NIK & Password satu kali saat Online.",
                    null
            );
        }

        String candidateHash = sha256Hex(matched.passwordSalt + ":" + rawPassword);
        if (!constantTimeEquals(matched.passwordHash, candidateHash)) {
            return new OfflineLoginResult(
                    false,
                    "Password salah untuk akun terverifikasi " + matched.user.getNik() + ".",
                    null
            );
        }

        UserEntity user = matched.user;
        enrichUserEntityLabels(user);

        // Activate persistent session
        ActiveSession s = getActiveSession();
        s.isLoggedIn = true;
        s.token = "OFFLINE_VERIFIED_" + System.currentTimeMillis();
        s.userId = user.getId() > 0 ? user.getId() : 1;
        s.nik = nonNull(user.getNik());
        s.username = nonNull(user.getUsername());
        s.fullName = nonNull(user.getFullName());
        s.role = nonNull(user.getRole());
        s.deptId = user.getDeptId() != null ? user.getDeptId() : 0;
        s.deptCode = nonNull(user.getDeptCode());
        s.deptName = nonNull(user.getDeptName());
        s.sectionId = user.getSectionId() != null ? user.getSectionId() : 0;
        s.sectionCode = nonNull(user.getSectionCode());
        s.sectionName = nonNull(user.getSectionName());
        if (!s.deptCode.isEmpty()) {
            s.stationCode = mapDeptCodeToStation(s.deptCode);
        }
        saveActiveSession(s);

        return new OfflineLoginResult(true, "Login Offline berhasil untuk " + user.getFullName(), user);
    }

    public synchronized boolean isAccountVerifiedOffline(String usernameOrNik) {
        if (usernameOrNik == null || usernameOrNik.trim().isEmpty()) return false;
        Map<String, VerifiedAccountRecord> map = loadVerifiedMapInternal();
        VerifiedAccountRecord rec = findVerifiedRecord(map, usernameOrNik.trim());
        return rec != null && rec.user != null && rec.passwordHash != null && !rec.passwordHash.isEmpty();
    }

    public synchronized List<SafeVerifiedAccountInfo> getSafeVerifiedAccounts() {
        Map<String, VerifiedAccountRecord> map = loadVerifiedMapInternal();
        List<SafeVerifiedAccountInfo> out = new ArrayList<>();
        for (VerifiedAccountRecord r : map.values()) {
            if (r == null || r.user == null) continue;
            enrichUserEntityLabels(r.user);
            SafeVerifiedAccountInfo info = new SafeVerifiedAccountInfo();
            info.id = r.user.getId();
            info.nik = r.user.getNik();
            info.username = r.user.getUsername();
            info.fullName = r.user.getFullName();
            info.role = r.user.getRole();
            info.deptId = r.user.getDeptId();
            info.deptCode = r.user.getDeptCode();
            info.deptName = r.user.getDeptName();
            info.sectionId = r.user.getSectionId();
            info.sectionCode = r.user.getSectionCode();
            info.sectionName = r.user.getSectionName();
            info.verifiedAt = r.verifiedAt;
            out.add(info);
        }
        return out;
    }

    private VerifiedAccountRecord findVerifiedRecord(Map<String, VerifiedAccountRecord> map, String rawInput) {
        String norm = normalizeAccountKey(rawInput);
        String strippedGt = norm.startsWith("gt-") ? norm.substring(3) : norm;

        if (map.containsKey(norm)) return map.get(norm);
        if (map.containsKey(strippedGt)) return map.get(strippedGt);

        for (VerifiedAccountRecord r : map.values()) {
            if (r == null || r.user == null) continue;
            String uNik = normalizeAccountKey(r.user.getNik());
            String uName = normalizeAccountKey(r.user.getUsername());
            if (norm.equals(uNik) || strippedGt.equals(uNik) || norm.equals(uName) || strippedGt.equals(uName)) {
                return r;
            }
        }
        return null;
    }

    private Map<String, VerifiedAccountRecord> loadVerifiedMapInternal() {
        String raw = readRedundantFile(FILE_VERIFIED_ACCOUNTS);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                Type type = new TypeToken<LinkedHashMap<String, VerifiedAccountRecord>>() {}.getType();
                Map<String, VerifiedAccountRecord> parsed = gson.fromJson(raw, type);
                if (parsed != null) return parsed;
            } catch (Exception e) {
                Log.w(TAG, "Failed parsing verified_accounts.json: " + e.getMessage());
            }
        }
        return new LinkedHashMap<>();
    }

    // ==================== DASHBOARD & OFFLINE QUEUES CACHE ====================

    public synchronized void saveDashboardSummaryCache(DashboardSummaryResponse summary) {
        if (summary == null) return;
        writeRedundantFile(FILE_DASHBOARD_CACHE, gson.toJson(summary));
    }

    public synchronized DashboardSummaryResponse getDashboardSummaryCache() {
        String raw = readRedundantFile(FILE_DASHBOARD_CACHE);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                DashboardSummaryResponse res = gson.fromJson(raw, DashboardSummaryResponse.class);
                if (res != null) return res;
            } catch (Exception ignored) {}
        }
        DashboardSummaryResponse fallback = new DashboardSummaryResponse();
        fallback.setStatus("cached");
        fallback.setTotalProducedToday(0);
        fallback.setKpiTargetPercentage(0.0);
        fallback.setActiveK3Incidents(0);
        fallback.setActiveDowntimeMachines(0);
        fallback.setShift1Count(0);
        fallback.setShift2Count(0);
        fallback.setShift3Count(0);
        return fallback;
    }

    // --- Offline Downtime Queue (4M Breakdown) ---
    public synchronized List<LocalDowntimeItem> getAllDowntimeItems() {
        String raw = readRedundantFile(FILE_PENDING_DOWNTIME);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                Type t = new TypeToken<ArrayList<LocalDowntimeItem>>() {}.getType();
                List<LocalDowntimeItem> list = gson.fromJson(raw, t);
                if (list != null) return list;
            } catch (Exception ignored) {}
        }
        return new ArrayList<>();
    }

    public synchronized LocalDowntimeItem addDowntimeItem(LocalDowntimeItem item) {
        List<LocalDowntimeItem> list = getAllDowntimeItems();
        item.localId = System.currentTimeMillis();
        if (item.createdAt == null || item.createdAt.isEmpty()) item.createdAt = nowTimestamp();
        list.add(0, item);
        if (list.size() > 100) list = new ArrayList<>(list.subList(0, 100));
        writeRedundantFile(FILE_PENDING_DOWNTIME, gson.toJson(list));
        return item;
    }

    public synchronized void markDowntimeSynced(List<Long> syncedIds) {
        if (syncedIds == null || syncedIds.isEmpty()) return;
        List<LocalDowntimeItem> list = getAllDowntimeItems();
        for (LocalDowntimeItem it : list) {
            if (syncedIds.contains(it.localId)) {
                it.syncStatus = 1;
            }
        }
        writeRedundantFile(FILE_PENDING_DOWNTIME, gson.toJson(list));
    }

    public synchronized int getPendingDowntimeCount() {
        int c = 0;
        for (LocalDowntimeItem it : getAllDowntimeItems()) {
            if (it.syncStatus == 0) c++;
        }
        return c;
    }

    // --- Offline Generated Barcodes Queue ---
    public synchronized List<LocalBarcodeItem> getAllGeneratedBarcodeItems() {
        String raw = readRedundantFile(FILE_PENDING_BARCODES);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                Type t = new TypeToken<ArrayList<LocalBarcodeItem>>() {}.getType();
                List<LocalBarcodeItem> list = gson.fromJson(raw, t);
                if (list != null) return list;
            } catch (Exception ignored) {}
        }
        return new ArrayList<>();
    }

    public synchronized LocalBarcodeItem addGeneratedBarcodeItem(LocalBarcodeItem item) {
        List<LocalBarcodeItem> list = getAllGeneratedBarcodeItems();
        item.localId = System.currentTimeMillis();
        if (item.createdAt == null || item.createdAt.isEmpty()) item.createdAt = nowTimestamp();
        list.add(0, item);
        if (list.size() > 150) list = new ArrayList<>(list.subList(0, 150));
        writeRedundantFile(FILE_PENDING_BARCODES, gson.toJson(list));
        return item;
    }

    public synchronized void markBarcodesSynced(List<Long> syncedIds) {
        if (syncedIds == null || syncedIds.isEmpty()) return;
        List<LocalBarcodeItem> list = getAllGeneratedBarcodeItems();
        for (LocalBarcodeItem it : list) {
            if (syncedIds.contains(it.localId)) {
                it.syncStatus = 1;
            }
        }
        writeRedundantFile(FILE_PENDING_BARCODES, gson.toJson(list));
    }

    public synchronized int getPendingBarcodesCount() {
        int c = 0;
        for (LocalBarcodeItem it : getAllGeneratedBarcodeItems()) {
            if (it.syncStatus == 0) c++;
        }
        return c;
    }

    // --- Local Broadcast Messages ---
    public synchronized List<LocalMessageItem> getAllMessages() {
        String raw = readRedundantFile(FILE_PENDING_MESSAGES);
        if (raw != null && !raw.trim().isEmpty()) {
            try {
                Type t = new TypeToken<ArrayList<LocalMessageItem>>() {}.getType();
                List<LocalMessageItem> list = gson.fromJson(raw, t);
                if (list != null && !list.isEmpty()) return list;
            } catch (Exception ignored) {}
        }
        // Seed default factory announcements so Message -> Broadcast is rich even offline
        List<LocalMessageItem> defaults = new ArrayList<>();
        LocalMessageItem m1 = new LocalMessageItem();
        m1.localId = 1001;
        m1.senderNik = "PH-001";
        m1.senderName = "Plant Head Control Room";
        m1.targetChannel = "ALL_PLANT";
        m1.title = "Target Kualitas & K3 Shift Aktif";
        m1.message = "Pastikan seluruh hasil scan Green Tire, Curing Press, dan Final Inspection tercatat via PDT. Area blank-spot otomatis tersimpan di Sync Desk.";
        m1.priority = "IMPORTANT";
        m1.createdAt = nowTimestamp();
        m1.syncStatus = 1;
        defaults.add(m1);

        LocalMessageItem m2 = new LocalMessageItem();
        m2.localId = 1002;
        m2.senderNik = "HEAD-CUR-01";
        m2.senderName = "Dept Head Curing";
        m2.targetChannel = "CUR";
        m2.title = "Pengecekan Usia Pakai Bladder Mold";
        m2.message = "Operator Bladder Set Up harap memverifikasi barcode bladder (B301-B320) sebelum perakitan mesin press.";
        m2.priority = "NORMAL";
        m2.createdAt = nowTimestamp();
        m2.syncStatus = 1;
        defaults.add(m2);
        return defaults;
    }

    public synchronized LocalMessageItem addMessageItem(LocalMessageItem msg) {
        List<LocalMessageItem> list = getAllMessages();
        msg.localId = System.currentTimeMillis();
        if (msg.createdAt == null || msg.createdAt.isEmpty()) msg.createdAt = nowTimestamp();
        list.add(0, msg);
        if (list.size() > 80) list = new ArrayList<>(list.subList(0, 80));
        writeRedundantFile(FILE_PENDING_MESSAGES, gson.toJson(list));
        return msg;
    }

    // ==================== HELPERS & CRYPTO ====================

    public static void enrichUserEntityLabels(UserEntity u) {
        if (u == null) return;
        int dId = u.getDeptId() != null ? u.getDeptId() : 0;
        if ((u.getDeptCode() == null || u.getDeptCode().isEmpty()) && dId > 0) {
            u.setDeptCode(deptIdToCode(dId));
        }
        if ((u.getDeptName() == null || u.getDeptName().isEmpty()) && dId > 0) {
            u.setDeptName(deptIdToName(dId));
        }
        int sId = u.getSectionId() != null ? u.getSectionId() : 0;
        if ((u.getSectionCode() == null || u.getSectionCode().isEmpty()) && sId > 0) {
            u.setSectionCode(sectionIdToCode(sId));
        }
        if ((u.getSectionName() == null || u.getSectionName().isEmpty()) && sId > 0) {
            u.setSectionName(sectionIdToName(sId));
        }
    }

    private static void enrichDeptAndSectionLabels(ActiveSession s) {
        if (s == null) return;
        if ((s.deptCode == null || s.deptCode.isEmpty()) && s.deptId > 0) {
            s.deptCode = deptIdToCode(s.deptId);
        }
        if ((s.deptName == null || s.deptName.isEmpty()) && s.deptId > 0) {
            s.deptName = deptIdToName(s.deptId);
        }
        if ((s.sectionCode == null || s.sectionCode.isEmpty()) && s.sectionId > 0) {
            s.sectionCode = sectionIdToCode(s.sectionId);
        }
        if ((s.sectionName == null || s.sectionName.isEmpty()) && s.sectionId > 0) {
            s.sectionName = sectionIdToName(s.sectionId);
        }
    }

    public static String deptIdToCode(int deptId) {
        switch (deptId) {
            case 1: return "WH";
            case 2: return "MAT";
            case 3: return "BLD";
            case 4: return "CUR";
            case 5: return "FI";
            default: return "";
        }
    }

    public static String deptIdToName(int deptId) {
        switch (deptId) {
            case 1: return "Dept Gudang Bahan";
            case 2: return "Dept Material";
            case 3: return "Dept Building";
            case 4: return "Dept Curing";
            case 5: return "Dept Final Inspection";
            default: return "Pusat Pabrik";
        }
    }

    public static String sectionIdToCode(int sectionId) {
        switch (sectionId) {
            case 1: return "WH-RCV";
            case 2: return "WH-SND";
            case 3: return "MAT-MIX";
            case 4: return "MAT-CAL";
            case 5: return "MAT-DEL";
            case 6: return "BLD-PCR";
            case 7: return "BLD-SUV";
            case 8: return "BLD-LTR";
            case 9: return "BLD-TBR";
            case 10: return "CUR-PRE";
            case 11: return "CUR-SUP-LGT";
            case 12: return "CUR-SUP-DEL";
            case 13: return "CUR-BSU";
            case 14: return "CUR-BLD";
            case 15: return "CUR-REP";
            case 16: return "FI-INS";
            case 17: return "FI-CUT";
            case 18: return "FI-DEL";
            default: return "";
        }
    }

    public static String sectionIdToName(int sectionId) {
        switch (sectionId) {
            case 1: return "Penerima Bahan Baku";
            case 2: return "Pengirim Bahan Baku";
            case 3: return "Mixing Compound";
            case 4: return "Calendering";
            case 5: return "Delivery Material";
            case 6: return "Building PCR (R13-R16)";
            case 7: return "Building PCR-HP / SUV (R17-R22)";
            case 8: return "Building LTR";
            case 9: return "Building TBR";
            case 10: return "Curing Tire Press";
            case 11: return "Supply Green Tire Lory";
            case 12: return "Supply Green Tire Delivery";
            case 13: return "Bladder Set Up";
            case 14: return "Bladder Injection";
            case 15: return "Repair Tire";
            case 16: return "Final Inspection";
            case 17: return "Cutting Hair Tire";
            case 18: return "Delivery Finished Goods";
            default: return "Semua Seksi";
        }
    }

    public static String mapDeptCodeToStation(String deptCode) {
        if (deptCode == null) return "CURING";
        switch (deptCode.toUpperCase(Locale.ROOT)) {
            case "WH": return "WAREHOUSE";
            case "MAT": return "MATERIAL";
            case "BLD": return "BUILDING";
            case "CUR": return "CURING";
            case "FI": return "FINAL_INSPECTION";
            default: return "CURING";
        }
    }

    private String normalizeAccountKey(String s) {
        return s == null ? "" : s.trim().toLowerCase(Locale.ROOT);
    }

    private String nonNull(String s) {
        return s != null ? s : "";
    }

    public static String nowTimestamp() {
        return new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.ROOT).format(new Date());
    }

    private String generateSaltHex() {
        byte[] bytes = new byte[16];
        new SecureRandom().nextBytes(bytes);
        StringBuilder sb = new StringBuilder(32);
        for (byte b : bytes) {
            sb.append(String.format(Locale.ROOT, "%02x", b));
        }
        return sb.toString();
    }

    private String sha256Hex(String input) {
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] digest = md.digest(input.getBytes(StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder(digest.length * 2);
            for (byte b : digest) {
                sb.append(String.format(Locale.ROOT, "%02x", b));
            }
            return sb.toString();
        } catch (Exception e) {
            return Integer.toHexString(input.hashCode());
        }
    }

    private boolean constantTimeEquals(String a, String b) {
        if (a == null || b == null || a.length() != b.length()) return false;
        int diff = 0;
        for (int i = 0; i < a.length(); i++) {
            diff |= a.charAt(i) ^ b.charAt(i);
        }
        return diff == 0;
    }

    private void writeRedundantFile(String fileName, String content) {
        writeSingleFile(new File(primaryVaultDir, fileName), content);
        writeSingleFile(new File(secondaryVaultDir, fileName), content);
    }

    private String readRedundantFile(String fileName) {
        File f1 = new File(primaryVaultDir, fileName);
        String c1 = readSingleFile(f1);
        if (c1 != null && !c1.trim().isEmpty()) {
            File f2 = new File(secondaryVaultDir, fileName);
            if (!f2.exists()) writeSingleFile(f2, c1);
            return c1;
        }
        File f2 = new File(secondaryVaultDir, fileName);
        String c2 = readSingleFile(f2);
        if (c2 != null && !c2.trim().isEmpty()) {
            // Self-heal primary file
            writeSingleFile(f1, c2);
            return c2;
        }
        return null;
    }

    private void writeSingleFile(File target, String content) {
        try {
            File parent = target.getParentFile();
            if (parent != null && !parent.exists()) parent.mkdirs();
            try (FileOutputStream fos = new FileOutputStream(target, false)) {
                fos.write(content.getBytes(StandardCharsets.UTF_8));
                fos.getFD().sync();
            }
        } catch (Exception e) {
            Log.e(TAG, "Vault write error (" + target.getName() + "): " + e.getMessage());
        }
    }

    private String readSingleFile(File target) {
        if (target == null || !target.exists() || target.length() == 0) return null;
        try (FileInputStream fis = new FileInputStream(target)) {
            byte[] buf = new byte[(int) target.length()];
            int read = fis.read(buf);
            if (read <= 0) return null;
            return new String(buf, 0, read, StandardCharsets.UTF_8);
        } catch (Exception e) {
            return null;
        }
    }
}
