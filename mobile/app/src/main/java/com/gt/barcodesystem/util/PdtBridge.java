package com.gt.barcodesystem.util;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkInfo;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.webkit.JavascriptInterface;
import android.widget.Toast;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.DepartmentEntity;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.local.entity.SectionEntity;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;
import com.gt.barcodesystem.data.remote.model.LoginRequest;
import com.gt.barcodesystem.data.remote.model.LoginResponse;
import com.gt.barcodesystem.data.sync.SyncManager;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Random;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Response;

public class PdtBridge {

    private final Context context;
    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final AuthVaultManager authVault;
    private final SyncManager syncManager;
    private final ExecutorService executor;
    private final Handler mainHandler;
    private final Vibrator vibrator;
    private final Gson gson;

    private volatile String stagedUsername = "";
    private volatile String stagedPassword = "";

    public interface BridgeEventListener {
        void onScanSavedLocally(String barcode, int pendingCount);
        void onSyncRequested();
        void onLogoutRequested();
        void onLoginCompleted(boolean success, boolean isOnlineMode, String message, UserEntity user);
        void onOpenServerConfigRequested();
        void onSwitchToOnlineModeRequested();
    }

    private BridgeEventListener listener;

    public PdtBridge(Context context) {
        this.context = context.getApplicationContext();
        this.db = AppDatabase.getInstance(this.context);
        this.sessionManager = new SessionManager(this.context);
        this.authVault = AuthVaultManager.getInstance(this.context);
        this.syncManager = SyncManager.getInstance(this.context);
        this.executor = Executors.newCachedThreadPool();
        this.mainHandler = new Handler(Looper.getMainLooper());
        this.vibrator = (Vibrator) this.context.getSystemService(Context.VIBRATOR_SERVICE);
        this.gson = new GsonBuilder().disableHtmlEscaping().create();
    }

    public void setListener(BridgeEventListener listener) {
        this.listener = listener;
    }

    public SessionManager getSessionManager() {
        return sessionManager;
    }

    // ==================== AUTHENTICATION & OFFLINE VAULT ====================

    @JavascriptInterface
    public void performLoginAsync(String usernameOrNik, String password) {
        final String uInput = usernameOrNik != null ? usernameOrNik.trim() : "";
        final String pInput = password != null ? password : "";

        if (uInput.isEmpty() || pInput.isEmpty()) {
            playErrorBeep();
            if (listener != null) {
                listener.onLoginCompleted(false, isOnline(), "Harap masukkan NIK/Username dan Password.", null);
            }
            return;
        }

        executor.execute(() -> {
            if (isOnline()) {
                try {
                    ApiService api = ApiClient.getApiService();
                    LoginResponse loginBody = tryOnlineLoginWithFallback(api, uInput, pInput);
                    if (loginBody != null && loginBody.isSuccess() && loginBody.getUser() != null) {
                        UserEntity user = loginBody.getUser();
                        AuthVaultManager.enrichUserEntityLabels(user);
                        String token = loginBody.getToken() != null ? loginBody.getToken() : "CLOUD_TOKEN_" + System.currentTimeMillis();

                        // Simpan ke Vault terverifikasi online (kebal Clear Cache!) + Room DB
                        authVault.saveOnlineVerifiedLogin(user, pInput, token);
                        sessionManager.saveFromUserEntity(token, user);
                        db.userDao().insertUser(user);

                        // Tarik master data terbaru di background agar cache offline siap 100%
                        syncManager.pullAllMasterDataAsync();

                        playSuccessBeep();
                        triggerVibration(100);
                        if (listener != null) {
                            listener.onLoginCompleted(true, true, "Login Online Berhasil: " + user.getFullName(), user);
                        }
                        return;
                    } else {
                        String msg = (loginBody != null && loginBody.getMessage() != null && !loginBody.getMessage().isEmpty())
                                ? loginBody.getMessage()
                                : "NIK/Username atau Password tidak valid di database server.";
                        playErrorBeep();
                        if (listener != null) {
                            listener.onLoginCompleted(false, true, msg, null);
                        }
                        return;
                    }
                } catch (Exception netErr) {
                    // Server unreachable despite local interface up -> fall through to strict offline verification
                }
            }

            // STRICT OFFLINE LOGIN: Hanya mengizinkan akun yang SUDAH PERNAH login online di perangkat ini!
            AuthVaultManager.OfflineLoginResult offRes = authVault.verifyOfflineLogin(uInput, pInput);
            if (offRes.success && offRes.user != null) {
                sessionManager.saveFromUserEntity("OFFLINE_VERIFIED_TOKEN", offRes.user);
                playSuccessBeep();
                triggerVibration(120);
                if (listener != null) {
                    listener.onLoginCompleted(true, false, offRes.message, offRes.user);
                }
            } else {
                playErrorBeep();
                triggerVibration(250);
                if (listener != null) {
                    listener.onLoginCompleted(false, false, offRes.message, null);
                }
            }
        });
    }

    private LoginResponse tryOnlineLoginWithFallback(ApiService api, String uInput, String pInput) throws Exception {
        Response<LoginResponse> res1 = api.login(new LoginRequest(uInput, pInput)).execute();
        if (res1.isSuccessful() && res1.body() != null && res1.body().isSuccess()) {
            return res1.body();
        }
        // Fallback jika user mengetik awalan "GT-" pada NIK (misal GT-SA-001 -> SA-001)
        String upper = uInput.toUpperCase(Locale.ROOT);
        if (upper.startsWith("GT-") && upper.length() > 3) {
            String stripped = upper.substring(3);
            Response<LoginResponse> res2 = api.login(new LoginRequest(stripped, pInput)).execute();
            if (res2.isSuccessful() && res2.body() != null && res2.body().isSuccess()) {
                return res2.body();
            }
        }
        return res1.body();
    }

    @JavascriptInterface
    public String getVerifiedAccountsJson() {
        try {
            return gson.toJson(authVault.getSafeVerifiedAccounts());
        } catch (Exception e) {
            return "[]";
        }
    }

    @JavascriptInterface
    public boolean isAccountVerifiedOffline(String usernameOrNik) {
        return authVault.isAccountVerifiedOffline(usernameOrNik);
    }

    @JavascriptInterface
    public void stageWebLoginCredentials(String username, String password) {
        this.stagedUsername = username != null ? username.trim() : "";
        this.stagedPassword = password != null ? password : "";
    }

    public void commitStagedCredentialsIfPresent() {
        final String u = stagedUsername;
        final String p = stagedPassword;
        if (u.isEmpty() || p.isEmpty()) return;
        stagedUsername = "";
        stagedPassword = "";
        executor.execute(() -> {
            try {
                LoginResponse res = tryOnlineLoginWithFallback(ApiClient.getApiService(), u, p);
                if (res != null && res.isSuccess() && res.getUser() != null) {
                    UserEntity user = res.getUser();
                    authVault.saveOnlineVerifiedLogin(user, p, res.getToken());
                    sessionManager.saveFromUserEntity(res.getToken(), user);
                    db.userDao().insertUser(user);
                    syncManager.pullAllMasterDataAsync();
                }
            } catch (Exception ignored) {}
        });
    }

    @JavascriptInterface
    public void saveUserSession(int userId, String nik, String username, String fullName, String role, int deptId, int sectionId, String stationCode) {
        sessionManager.saveUserSession(
                "token_pdt_" + System.currentTimeMillis(),
                userId > 0 ? userId : 1,
                nik != null ? nik : "OPERATOR",
                username != null ? username : (nik != null ? nik : "operator"),
                fullName != null ? fullName : "Operator PDT",
                role != null ? role : "operator",
                deptId > 0 ? deptId : 1,
                sectionId > 0 ? sectionId : 1
        );
        if (stationCode != null && !stationCode.isEmpty()) {
            sessionManager.setStationCode(stationCode);
        }
    }

    @JavascriptInterface
    public void syncWebSession(String fullName, String role, String currentUrl) {
        if (currentUrl != null && !currentUrl.isEmpty() && !currentUrl.contains("login.php") && !currentUrl.contains("logout.php")) {
            sessionManager.setLastUrl(currentUrl);
        }
        commitStagedCredentialsIfPresent();
    }

    @JavascriptInterface
    public boolean isLoggedIn() {
        return sessionManager.isLoggedIn();
    }

    @JavascriptInterface
    public String getSessionJson() {
        if (!sessionManager.isLoggedIn()) {
            return "{\"isLoggedIn\":false}";
        }
        Map<String, Object> m = new LinkedHashMap<>();
        m.put("isLoggedIn", true);
        m.put("userId", sessionManager.getUserId());
        m.put("nik", sessionManager.getNik());
        m.put("username", sessionManager.getUsername());
        m.put("fullName", sessionManager.getFullName());
        m.put("role", sessionManager.getRole());
        m.put("deptId", sessionManager.getDeptId());
        m.put("deptCode", sessionManager.getDeptCode());
        m.put("deptName", sessionManager.getDeptName());
        m.put("sectionId", sessionManager.getSectionId());
        m.put("sectionCode", sessionManager.getSectionCode());
        m.put("sectionName", sessionManager.getSectionName());
        m.put("stationCode", sessionManager.getStationCode());
        m.put("shift", ShiftHelper.getCurrentShift());
        m.put("isOnline", isOnline());
        m.put("apiBaseUrl", ApiClient.getBaseUrl());
        return gson.toJson(m);
    }

    // ==================== FULL OFFLINE/ONLINE BOOTSTRAP PAYLOAD ====================

    @JavascriptInterface
    public String getBootstrapDataJson() {
        try {
            Map<String, Object> root = new LinkedHashMap<>();

            // 1. Session
            Map<String, Object> sess = new LinkedHashMap<>();
            sess.put("isLoggedIn", sessionManager.isLoggedIn());
            sess.put("userId", sessionManager.getUserId());
            sess.put("nik", sessionManager.getNik());
            sess.put("username", sessionManager.getUsername());
            sess.put("fullName", sessionManager.getFullName());
            sess.put("role", sessionManager.getRole());
            sess.put("deptId", sessionManager.getDeptId());
            sess.put("deptCode", sessionManager.getDeptCode());
            sess.put("deptName", sessionManager.getDeptName());
            sess.put("sectionId", sessionManager.getSectionId());
            sess.put("sectionCode", sessionManager.getSectionCode());
            sess.put("sectionName", sessionManager.getSectionName());
            sess.put("stationCode", sessionManager.getStationCode());
            root.put("session", sess);

            // 2. Network & Shift
            root.put("isOnline", isOnline());
            root.put("apiBaseUrl", ApiClient.getBaseUrl());
            int shiftNum = ShiftHelper.getCurrentShift();
            Map<String, Object> shiftObj = new LinkedHashMap<>();
            shiftObj.put("number", shiftNum);
            shiftObj.put("hours", shiftNum == 1 ? "06:00 - 14:00" : (shiftNum == 2 ? "14:00 - 22:00" : "22:00 - 06:00"));
            shiftObj.put("time", new SimpleDateFormat("HH:mm:ss", Locale.ROOT).format(new Date()));
            root.put("shift", shiftObj);

            // 3. Pending counts
            int pScans = db.offlineScanDao().getPendingCount();
            int pK3 = db.k3CallDao().getPendingCalls().size();
            int pDt = authVault.getPendingDowntimeCount();
            int pBc = authVault.getPendingBarcodesCount();
            root.put("pendingCount", pScans + pK3 + pDt + pBc);
            Map<String, Integer> breakdown = new LinkedHashMap<>();
            breakdown.put("scans", pScans);
            breakdown.put("k3", pK3);
            breakdown.put("downtime", pDt);
            breakdown.put("barcodes", pBc);
            root.put("pendingBreakdown", breakdown);

            // 4. Scans & Dashboard KPIs
            List<OfflineScanEntity> allScans = db.offlineScanDao().getAllScans();
            if (allScans.size() > 60) allScans = allScans.subList(0, 60);
            root.put("scans", allScans);

            DashboardSummaryResponse dash = authVault.getDashboardSummaryCache();
            Map<String, Object> dashMap = new LinkedHashMap<>();
            int baseProd = dash.getTotalProducedToday();
            int totalWithPending = baseProd + pScans;
            double pct = totalWithPending > 0 ? Math.round((totalWithPending / 5000.0) * 1000.0) / 10.0 : dash.getKpiTargetPercentage();
            dashMap.put("totalProducedToday", totalWithPending);
            dashMap.put("kpiTargetPercentage", pct);
            dashMap.put("activeK3Incidents", dash.getActiveK3Incidents() + pK3);
            dashMap.put("activeDowntimeMachines", dash.getActiveDowntimeMachines() + pDt);
            dashMap.put("shift1Count", dash.getShift1Count() + (shiftNum == 1 ? pScans : 0));
            dashMap.put("shift2Count", dash.getShift2Count() + (shiftNum == 2 ? pScans : 0));
            dashMap.put("shift3Count", dash.getShift3Count() + (shiftNum == 3 ? pScans : 0));
            root.put("dashboard", dashMap);

            // 5. Master Data (Departments, Sections, Racks, Barcodes) with offline defaults
            List<DepartmentEntity> depts = db.masterDao().getAllDepartments();
            if (depts == null || depts.isEmpty()) depts = getDefaultDepartments();
            root.put("departments", depts);

            List<SectionEntity> secs = db.masterDao().getAllSections();
            if (secs == null || secs.isEmpty()) secs = getDefaultSections();
            root.put("sections", secs);

            List<WarehouseRackEntity> racks = db.masterDao().getAllRacks();
            if (racks != null && racks.size() > 60) racks = racks.subList(0, 60);
            root.put("racks", racks != null ? racks : new ArrayList<>());

            List<BarcodeEntity> barcodes = db.masterDao().getAllBarcodes();
            if (barcodes != null && barcodes.size() > 60) barcodes = barcodes.subList(0, 60);
            root.put("barcodes", barcodes != null ? barcodes : new ArrayList<>());
            root.put("generatedBarcodes", authVault.getAllGeneratedBarcodeItems());

            // 6. K3, Downtime, Messages, Users
            root.put("k3Calls", db.k3CallDao().getAllCalls());
            root.put("downtimeEvents", authVault.getAllDowntimeItems());
            root.put("messages", authVault.getAllMessages());

            List<UserEntity> users = db.userDao().getAllUsers();
            if (users != null) {
                for (UserEntity u : users) AuthVaultManager.enrichUserEntityLabels(u);
            }
            root.put("users", users != null ? users : new ArrayList<>());
            root.put("verifiedAccounts", authVault.getSafeVerifiedAccounts());

            return gson.toJson(root);
        } catch (Exception e) {
            return "{\"error\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    // ==================== OFFLINE-FIRST TRANSACTIONS & AUTO-SYNC ====================

    @JavascriptInterface
    public String submitStationScanJson(String payloadJson) {
        try {
            JsonObject obj = JsonParser.parseString(payloadJson).getAsJsonObject();
            String rawBarcode = getStr(obj, "barcode", "").trim().toUpperCase(Locale.ROOT);
            if (rawBarcode.isEmpty()) {
                playErrorBeep();
                return "{\"status\":\"error\",\"message\":\"Nomor barcode wajib diisi.\"}";
            }
            if (rawBarcode.length() > 40) rawBarcode = rawBarcode.substring(0, 40);

            int deptId = getInt(obj, "deptId", sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 4);
            int sectionId = getInt(obj, "sectionId", sessionManager.getSectionId() > 0 ? sessionManager.getSectionId() : defaultSectionForDept(deptId));
            String actionType = getStr(obj, "actionType", AuthVaultManager.mapDeptCodeToStation(AuthVaultManager.deptIdToCode(deptId)));
            String machineCode = getStr(obj, "machineCode", "");
            String itemCode = getStr(obj, "itemCode", "");
            String rackCode = getStr(obj, "rackCode", "").toUpperCase(Locale.ROOT);
            double qty = getDouble(obj, "qty", 1.0);
            String unit = getStr(obj, "unit", "Pcs");
            String status = getStr(obj, "status", "PASS");
            String details = getStr(obj, "details", "Input Stasiun PDT");
            String notes = getStr(obj, "notes", "");

            if (!machineCode.isEmpty() && !details.contains(machineCode)) {
                details = "[" + machineCode + "] " + details;
            }
            if (!rackCode.isEmpty() && !details.contains(rackCode)) {
                details = "[Rak " + rackCode + "] " + details;
            }
            if (!notes.isEmpty()) {
                details = details + " | Catatan: " + notes;
            }

            OfflineScanEntity scan = new OfflineScanEntity();
            scan.setBarcode(rawBarcode);
            scan.setDeptId(deptId > 0 ? deptId : 1);
            scan.setSectionId(sectionId > 0 ? sectionId : 1);
            scan.setMachineCode(machineCode);
            scan.setItemCode(itemCode);
            scan.setActionType(actionType);
            scan.setQty(qty > 0 ? qty : 1.0);
            scan.setUnit(unit);
            scan.setStatus(status);
            scan.setDetails(details);
            scan.setNotes(notes);
            scan.setOperatorId(sessionManager.getUserId() > 0 ? sessionManager.getUserId() : 1);
            scan.setOperatorNik(!sessionManager.getNik().isEmpty() ? sessionManager.getNik() : "OPERATOR");
            scan.setShift(ShiftHelper.getCurrentShift());
            scan.setCreatedAt(AuthVaultManager.nowTimestamp());
            scan.setSyncStatus(0);

            long localId = db.offlineScanDao().insert(scan);
            scan.setLocalId(localId);

            if (!rackCode.isEmpty()) {
                try {
                    db.masterDao().assignRack(rackCode, rawBarcode, !itemCode.isEmpty() ? itemCode : "Material/Ban", qty);
                } catch (Exception ignored) {}
            }

            playSuccessBeep();
            triggerVibration(130);

            int pendingTotal = syncManager.getTotalPendingCountSync();
            if (listener != null) {
                listener.onScanSavedLocally(rawBarcode, pendingTotal);
            }

            final String finalBarcode = rawBarcode;
            boolean onlineNow = isOnline();
            if (onlineNow) {
                syncManager.syncAll(new SyncManager.SyncCallback() {
                    @Override
                    public void onSyncCompleted(int syncedCount) {
                        if (listener != null) {
                            listener.onScanSavedLocally(finalBarcode, syncManager.getTotalPendingCountSync());
                        }
                    }
                    @Override
                    public void onSyncFailed(String error) {}
                });
            }

            Map<String, Object> resp = new LinkedHashMap<>();
            resp.put("status", "success");
            resp.put("localId", localId);
            resp.put("barcode", rawBarcode);
            resp.put("pendingCount", pendingTotal);
            resp.put("onlineTriggered", onlineNow);
            resp.put("message", onlineNow
                    ? "Tersimpan di perangkat & langsung disinkronkan ke Supabase!"
                    : "Tersimpan aman di memori perangkat (Offline Queue). Akan otomatis terkirim saat online.");
            return gson.toJson(resp);
        } catch (Exception e) {
            playErrorBeep();
            return "{\"status\":\"error\",\"message\":\"Gagal menyimpan scan: " + escapeJson(e.getMessage()) + "\"}";
        }
    }

    @JavascriptInterface
    public void saveOfflineScan(String barcode, String stationCode, String details, double qty, String status) {
        JsonObject o = new JsonObject();
        o.addProperty("barcode", barcode != null ? barcode : "");
        o.addProperty("actionType", stationCode != null ? stationCode : sessionManager.getStationCode());
        o.addProperty("details", details != null ? details : "Scan Mode Lite PDT");
        o.addProperty("qty", qty > 0 ? qty : 1.0);
        o.addProperty("status", status != null ? status : "NORMAL");
        executor.execute(() -> submitStationScanJson(o.toString()));
    }

    @JavascriptInterface
    public String submitDowntimeJson(String payloadJson) {
        try {
            JsonObject obj = JsonParser.parseString(payloadJson).getAsJsonObject();
            String machineCode = getStr(obj, "machineCode", "").trim().toUpperCase(Locale.ROOT);
            String category4m = getStr(obj, "downtimeType", "Machine");
            String description = getStr(obj, "description", "").trim();
            int durationMin = getInt(obj, "durationMinutes", 15);
            int deptId = getInt(obj, "deptId", sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 4);

            if (machineCode.isEmpty() || description.isEmpty()) {
                playErrorBeep();
                return "{\"status\":\"error\",\"message\":\"Kode mesin dan keterangan hambatan wajib diisi.\"}";
            }

            AuthVaultManager.LocalDowntimeItem dt = new AuthVaultManager.LocalDowntimeItem();
            dt.machineCode = machineCode;
            dt.deptId = deptId > 0 ? deptId : 4;
            dt.deptCode = AuthVaultManager.deptIdToCode(dt.deptId);
            dt.downtimeType = category4m;
            dt.durationMinutes = durationMin;
            dt.description = description;
            dt.operatorNik = !sessionManager.getNik().isEmpty() ? sessionManager.getNik() : "OPERATOR";
            dt.reportedBy = sessionManager.getUserId() > 0 ? sessionManager.getUserId() : 1;
            dt.syncStatus = 0;

            authVault.addDowntimeItem(dt);
            playSuccessBeep();
            triggerVibration(150);

            int pendingTotal = syncManager.getTotalPendingCountSync();
            if (listener != null) listener.onScanSavedLocally(machineCode, pendingTotal);

            boolean onlineNow = isOnline();
            if (onlineNow) syncManager.syncAll(null);

            Map<String, Object> resp = new LinkedHashMap<>();
            resp.put("status", "success");
            resp.put("pendingCount", pendingTotal);
            resp.put("message", onlineNow
                    ? "Laporan Hambatan 4M mesin " + machineCode + " terkirim ke SCADA Supabase!"
                    : "Laporan Hambatan 4M disimpan di perangkat (Offline) & akan disinkronkan saat online.");
            return gson.toJson(resp);
        } catch (Exception e) {
            playErrorBeep();
            return "{\"status\":\"error\",\"message\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    @JavascriptInterface
    public String submitK3SosJson(String payloadJson) {
        try {
            JsonObject obj = JsonParser.parseString(payloadJson).getAsJsonObject();
            String location = getStr(obj, "location", "").trim();
            String condition = getStr(obj, "condition", "").trim();
            String priority = getStr(obj, "priority", "HIGH_EMERGENCY");

            if (location.isEmpty() || condition.isEmpty()) {
                playErrorBeep();
                return "{\"status\":\"error\",\"message\":\"Lokasi kejadian dan kondisi darurat wajib diisi.\"}";
            }

            K3CallEntity call = new K3CallEntity();
            call.setCallNo("AMB-PDT-" + (1000 + new Random().nextInt(9000)));
            call.setCallerId(sessionManager.getUserId() > 0 ? sessionManager.getUserId() : 1);
            call.setCallerName(!sessionManager.getFullName().isEmpty() ? sessionManager.getFullName() : sessionManager.getNik());
            call.setDeptId(sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 4);
            call.setLocationDetail(location);
            call.setPatientCondition(condition);
            call.setPriority(priority);
            call.setStatus("REQUESTED");
            call.setCreatedAt(AuthVaultManager.nowTimestamp());
            call.setSyncStatus(0);

            db.k3CallDao().insert(call);
            playSuccessBeep();
            triggerVibration(350);

            int pendingTotal = syncManager.getTotalPendingCountSync();
            if (listener != null) listener.onScanSavedLocally(call.getCallNo(), pendingTotal);

            boolean onlineNow = isOnline();
            if (onlineNow) syncManager.syncAll(null);

            Map<String, Object> resp = new LinkedHashMap<>();
            resp.put("status", "success");
            resp.put("callNo", call.getCallNo());
            resp.put("pendingCount", pendingTotal);
            resp.put("message", onlineNow
                    ? "DARURAT K3 (" + call.getCallNo() + ") berhasil dikirim ke Tim Medis & Supabase!"
                    : "DARURAT K3 (" + call.getCallNo() + ") tercatat di PDT & akan otomatis dikirim begitu sinyal tersedia!");
            return gson.toJson(resp);
        } catch (Exception e) {
            playErrorBeep();
            return "{\"status\":\"error\",\"message\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    @JavascriptInterface
    public String generateBarcodeJson(String payloadJson) {
        try {
            JsonObject obj = JsonParser.parseString(payloadJson).getAsJsonObject();
            String barcodeType = getStr(obj, "barcodeType", "PRODUCT").toUpperCase(Locale.ROOT);
            String productCode = getStr(obj, "productCode", "RDL").toUpperCase(Locale.ROOT);
            String productName = getStr(obj, "productName", "Radial PCR");
            String sizeName = getStr(obj, "sizeName", "185/65 R15");
            int qty = Math.max(1, Math.min(20, getInt(obj, "qty", 1)));

            String prefix = "MACHINE".equals(barcodeType) ? "MCH-" : ("BLADDER".equals(barcodeType) ? "BLD-" : "GT-");
            String dateCode = new SimpleDateFormat("yyMMdd", Locale.ROOT).format(new Date());
            String sampleCode = prefix + productCode + "-" + dateCode + "-" + String.format(Locale.ROOT, "%05d", 10000 + new Random().nextInt(89999));

            AuthVaultManager.LocalBarcodeItem item = new AuthVaultManager.LocalBarcodeItem();
            item.barcode = sampleCode;
            item.barcodeType = barcodeType;
            item.productCode = productCode;
            item.productName = productName;
            item.sizeName = sizeName;
            item.qty = qty;
            item.currentDept = !sessionManager.getDeptCode().isEmpty() ? sessionManager.getDeptCode() : "PRODUKSI";
            item.status = "GENERATED";
            item.syncStatus = 0;
            authVault.addGeneratedBarcodeItem(item);

            // Insert locally into Room barcodes table so immediate Scan Terminal lookup finds it!
            BarcodeEntity bEntity = new BarcodeEntity();
            bEntity.setId((int) (System.currentTimeMillis() % Integer.MAX_VALUE));
            bEntity.setBarcode(sampleCode);
            bEntity.setBarcodeType(barcodeType);
            bEntity.setProductCode(productCode);
            bEntity.setProductName(productName);
            bEntity.setSizeName(sizeName);
            bEntity.setStatus("GENERATED");
            bEntity.setCurrentDept(item.currentDept);
            db.masterDao().insertBarcode(bEntity);

            playSuccessBeep();
            triggerVibration(100);

            int pendingTotal = syncManager.getTotalPendingCountSync();
            boolean onlineNow = isOnline();
            if (onlineNow) syncManager.syncAll(null);

            Map<String, Object> resp = new LinkedHashMap<>();
            resp.put("status", "success");
            resp.put("barcode", sampleCode);
            resp.put("qty", qty);
            resp.put("pendingCount", pendingTotal);
            resp.put("message", qty + " label barcode (" + sampleCode + ") berhasil dibuat!");
            return gson.toJson(resp);
        } catch (Exception e) {
            playErrorBeep();
            return "{\"status\":\"error\",\"message\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    @JavascriptInterface
    public String submitBroadcastJson(String payloadJson) {
        try {
            JsonObject obj = JsonParser.parseString(payloadJson).getAsJsonObject();
            String targetChannel = getStr(obj, "targetChannel", "ALL_PLANT");
            String title = getStr(obj, "title", "Info Operasional");
            String message = getStr(obj, "message", "").trim();
            String priority = getStr(obj, "priority", "NORMAL");

            if (message.isEmpty()) {
                return "{\"status\":\"error\",\"message\":\"Isi pesan broadcast wajib diisi.\"}";
            }

            AuthVaultManager.LocalMessageItem m = new AuthVaultManager.LocalMessageItem();
            m.senderNik = !sessionManager.getNik().isEmpty() ? sessionManager.getNik() : "USER";
            m.senderName = !sessionManager.getFullName().isEmpty() ? sessionManager.getFullName() : "Petugas GT";
            m.targetChannel = targetChannel;
            m.title = title;
            m.message = message;
            m.priority = priority;
            m.syncStatus = isOnline() ? 1 : 0;
            authVault.addMessageItem(m);

            playSuccessBeep();
            return "{\"status\":\"success\",\"message\":\"Pesan broadcast berhasil disimpan ke kanal " + escapeJson(targetChannel) + ".\"}";
        } catch (Exception e) {
            return "{\"status\":\"error\",\"message\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    @JavascriptInterface
    public String lookupBarcodeJson(String rawQuery) {
        String q = rawQuery != null ? rawQuery.trim().toUpperCase(Locale.ROOT) : "";
        if (q.isEmpty()) return "{\"found\":false}";
        try {
            Map<String, Object> out = new LinkedHashMap<>();
            BarcodeEntity bc = db.masterDao().getBarcode(q);
            WarehouseRackEntity rack = db.masterDao().getRackByBarcode(q);
            List<OfflineScanEntity> history = new ArrayList<>();
            for (OfflineScanEntity s : db.offlineScanDao().getAllScans()) {
                if (s.getBarcode() != null && s.getBarcode().equalsIgnoreCase(q)) {
                    history.add(s);
                }
            }
            boolean found = (bc != null) || (rack != null) || !history.isEmpty();
            out.put("found", found);
            out.put("query", q);
            out.put("barcodeMaster", bc);
            out.put("rack", rack);
            out.put("scanHistory", history);
            return gson.toJson(out);
        } catch (Exception e) {
            return "{\"found\":false,\"error\":\"" + escapeJson(e.getMessage()) + "\"}";
        }
    }

    // ==================== CONTROLS & HARDWARE ====================

    @JavascriptInterface
    public int getPendingCount() {
        return syncManager.getTotalPendingCountSync();
    }

    @JavascriptInterface
    public void syncNow() {
        if (listener != null) {
            listener.onSyncRequested();
        } else {
            syncManager.syncAll(null);
        }
    }

    @JavascriptInterface
    public void logout() {
        sessionManager.logout();
        if (listener != null) {
            listener.onLogoutRequested();
        }
    }

    @JavascriptInterface
    public void openServerConfig() {
        if (listener != null) {
            listener.onOpenServerConfigRequested();
        }
    }

    @JavascriptInterface
    public void switchToOnlineMode() {
        if (listener != null) {
            listener.onSwitchToOnlineModeRequested();
        }
    }

    @JavascriptInterface
    public String getStationCode() {
        return sessionManager.getStationCode();
    }

    @JavascriptInterface
    public void setStationCode(String stationCode) {
        if (stationCode != null && !stationCode.isEmpty()) {
            sessionManager.setStationCode(stationCode);
        }
    }

    @JavascriptInterface
    public int getCurrentShift() {
        return ShiftHelper.getCurrentShift();
    }

    @JavascriptInterface
    public void playSuccessBeep() {
        AudioBeepManager.playSuccessBeep();
    }

    @JavascriptInterface
    public void playErrorBeep() {
        AudioBeepManager.playErrorBeep();
    }

    @JavascriptInterface
    public void triggerVibration(long ms) {
        try {
            if (vibrator != null && vibrator.hasVibrator()) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createOneShot(ms > 0 ? ms : 150, VibrationEffect.DEFAULT_AMPLITUDE));
                } else {
                    vibrator.vibrate(ms > 0 ? ms : 150);
                }
            }
        } catch (Exception ignored) {}
    }

    @JavascriptInterface
    public boolean isOnline() {
        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return false;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                Network active = cm.getActiveNetwork();
                if (active == null) return false;
                NetworkCapabilities caps = cm.getNetworkCapabilities(active);
                if (caps == null) return false;
                return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                        && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
            } else {
                NetworkInfo info = cm.getActiveNetworkInfo();
                return info != null && info.isConnected();
            }
        } catch (Exception e) {
            return false;
        }
    }

    @JavascriptInterface
    public void showToast(String message) {
        if (message != null && !message.isEmpty()) {
            mainHandler.post(() -> Toast.makeText(context, message, Toast.LENGTH_SHORT).show());
        }
    }

    // ==================== BUILT-IN MASTER DEFAULTS FOR FIRST OFFLINE RUN ====================

    private List<DepartmentEntity> getDefaultDepartments() {
        List<DepartmentEntity> list = new ArrayList<>();
        list.add(makeDept(1, "WH", "Dept Gudang Bahan", "fa-warehouse", "blue"));
        list.add(makeDept(2, "MAT", "Dept Material", "fa-flask", "amber"));
        list.add(makeDept(3, "BLD", "Dept Building", "fa-gears", "emerald"));
        list.add(makeDept(4, "CUR", "Dept Curing", "fa-fire-flame-curved", "rose"));
        list.add(makeDept(5, "FI", "Dept Final Inspection", "fa-clipboard-check", "indigo"));
        return list;
    }

    private DepartmentEntity makeDept(int id, String code, String name, String icon, String color) {
        DepartmentEntity d = new DepartmentEntity();
        d.setId(id);
        d.setCode(code);
        d.setName(name);
        d.setIcon(icon);
        d.setColor(color);
        d.setActive(true);
        return d;
    }

    private List<SectionEntity> getDefaultSections() {
        List<SectionEntity> list = new ArrayList<>();
        int[][] map = {
                {1, 1}, {2, 1},
                {3, 2}, {4, 2}, {5, 2},
                {6, 3}, {7, 3}, {8, 3}, {9, 3},
                {10, 4}, {11, 4}, {12, 4}, {13, 4}, {14, 4}, {15, 4},
                {16, 5}, {17, 5}, {18, 5}
        };
        for (int[] pair : map) {
            SectionEntity s = new SectionEntity();
            s.setId(pair[0]);
            s.setDeptId(pair[1]);
            s.setCode(AuthVaultManager.sectionIdToCode(pair[0]));
            s.setName(AuthVaultManager.sectionIdToName(pair[0]));
            s.setActive(true);
            list.add(s);
        }
        return list;
    }

    private int defaultSectionForDept(int deptId) {
        switch (deptId) {
            case 1: return 1;
            case 2: return 3;
            case 3: return 6;
            case 4: return 10;
            case 5: return 16;
            default: return 1;
        }
    }

    private String getStr(JsonObject o, String key, String def) {
        if (o != null && o.has(key) && !o.get(key).isJsonNull()) {
            try { return o.get(key).getAsString(); } catch (Exception ignored) {}
        }
        return def;
    }

    private int getInt(JsonObject o, String key, int def) {
        if (o != null && o.has(key) && !o.get(key).isJsonNull()) {
            try { return o.get(key).getAsInt(); } catch (Exception ignored) {}
        }
        return def;
    }

    private double getDouble(JsonObject o, String key, double def) {
        if (o != null && o.has(key) && !o.get(key).isJsonNull()) {
            try { return o.get(key).getAsDouble(); } catch (Exception ignored) {}
        }
        return def;
    }

    private String escapeJson(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\")
                .replace("\"", "\\\"")
                .replace("\n", "\\n")
                .replace("\r", "\\r")
                .replace("\t", "\\t");
    }
}
