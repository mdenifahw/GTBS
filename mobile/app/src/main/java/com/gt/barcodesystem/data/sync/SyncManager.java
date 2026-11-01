package com.gt.barcodesystem.data.sync;

import android.content.Context;
import android.util.Log;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.ApiResponse;
import com.gt.barcodesystem.data.remote.model.BarcodeGenerateRequest;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;
import com.gt.barcodesystem.data.remote.model.DowntimeReportRequest;
import com.gt.barcodesystem.data.remote.model.K3CallRequest;
import com.gt.barcodesystem.data.remote.model.K3CallResponse;
import com.gt.barcodesystem.data.remote.model.MasterSyncResponse;
import com.gt.barcodesystem.data.remote.model.PushActivitiesRequest;
import com.gt.barcodesystem.data.remote.model.PushActivitiesResponse;
import com.gt.barcodesystem.data.remote.model.UserListResponse;
import com.gt.barcodesystem.util.AuthVaultManager;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

import retrofit2.Response;

/**
 * SyncManager: Mesin sinkronisasi dua arah (Push Offline Queue -> Supabase Cloud via Vercel,
 * dan Pull Master Data + KPI + Users -> Room SQLite + AuthVaultManager).
 */
public class SyncManager {

    private static final String TAG = "SyncManager";
    private static SyncManager instance;

    private final Context appContext;
    private final AppDatabase db;
    private final AuthVaultManager vault;
    private final ExecutorService executor;
    private final AtomicBoolean isSyncing = new AtomicBoolean(false);

    public interface SyncCallback {
        void onSyncCompleted(int syncedCount);
        void onSyncFailed(String error);
    }

    private SyncManager(Context context) {
        this.appContext = context.getApplicationContext();
        this.db = AppDatabase.getInstance(appContext);
        this.vault = AuthVaultManager.getInstance(appContext);
        this.executor = Executors.newSingleThreadExecutor();
    }

    public static synchronized SyncManager getInstance(Context context) {
        if (instance == null) {
            instance = new SyncManager(context.getApplicationContext());
        }
        return instance;
    }

    public int getTotalPendingCountSync() {
        try {
            int scans = db.offlineScanDao().getPendingCount();
            int k3 = db.k3CallDao().getPendingCalls().size();
            int dt = vault.getPendingDowntimeCount();
            int bc = vault.getPendingBarcodesCount();
            return scans + k3 + dt + bc;
        } catch (Exception e) {
            return 0;
        }
    }

    /**
     * Sinkronisasi komprehensif seluruh antrean offline (Scans, K3 SOS, Downtime 4M, Generate Barcode)
     * sekaligus memperbarui Master Data lokal dari Supabase!
     */
    public void syncAll(SyncCallback callback) {
        if (!isSyncing.compareAndSet(false, true)) {
            if (callback != null) callback.onSyncCompleted(0);
            return;
        }

        executor.execute(() -> {
            int totalSynced = 0;
            String lastError = null;
            ApiService apiService = ApiClient.getApiService();

            try {
                // 1. PUSH OFFLINE PRODUCTION SCANS
                List<OfflineScanEntity> pendingScans = db.offlineScanDao().getPendingScans();
                if (pendingScans != null && !pendingScans.isEmpty()) {
                    for (OfflineScanEntity s : pendingScans) {
                        if (s.getBarcode() != null && s.getBarcode().length() > 40) {
                            s.setBarcode(s.getBarcode().substring(0, 40));
                        }
                        if (s.getDeptId() <= 0) s.setDeptId(1);
                        if (s.getSectionId() <= 0) s.setSectionId(1);
                        if (s.getOperatorId() <= 0) s.setOperatorId(1);
                    }
                    PushActivitiesRequest req = new PushActivitiesRequest(pendingScans);
                    Response<PushActivitiesResponse> res = apiService.pushActivities(req).execute();
                    if (res.isSuccessful() && res.body() != null) {
                        List<Long> syncedIds = res.body().getSyncedLocalIds();
                        if (syncedIds != null && !syncedIds.isEmpty()) {
                            db.offlineScanDao().markAsSynced(syncedIds);
                            totalSynced += syncedIds.size();
                        }
                    } else {
                        lastError = "HTTP " + res.code() + " saat mengirim scan produksi.";
                    }
                }

                // 2. PUSH OFFLINE K3 SOS CALLS
                List<K3CallEntity> pendingK3 = db.k3CallDao().getPendingCalls();
                if (pendingK3 != null && !pendingK3.isEmpty()) {
                    List<Long> syncedK3Ids = new ArrayList<>();
                    for (K3CallEntity call : pendingK3) {
                        K3CallRequest k3Req = new K3CallRequest(
                                call.getCallerId() > 0 ? call.getCallerId() : 1,
                                call.getCallerName() != null ? call.getCallerName() : "Operator PDT",
                                call.getDeptId() > 0 ? call.getDeptId() : 1,
                                call.getLocationDetail() != null ? call.getLocationDetail() : "Area Pabrik",
                                call.getPatientCondition() != null ? call.getPatientCondition() : "Darurat Medis K3",
                                call.getPriority() != null ? call.getPriority() : "HIGH_EMERGENCY"
                        );
                        Response<K3CallResponse> k3Res = apiService.pushK3Call(k3Req).execute();
                        if (k3Res.isSuccessful()) {
                            syncedK3Ids.add(call.getLocalId());
                        }
                    }
                    if (!syncedK3Ids.isEmpty()) {
                        db.k3CallDao().markAsSynced(syncedK3Ids);
                        totalSynced += syncedK3Ids.size();
                    }
                }

                // 3. PUSH OFFLINE 4M DOWNTIME REPORTS
                List<AuthVaultManager.LocalDowntimeItem> allDt = vault.getAllDowntimeItems();
                List<Long> syncedDtIds = new ArrayList<>();
                for (AuthVaultManager.LocalDowntimeItem dt : allDt) {
                    if (dt.syncStatus != 0) continue;
                    DowntimeReportRequest dtReq = new DowntimeReportRequest(
                            dt.machineCode,
                            dt.deptId > 0 ? dt.deptId : 1,
                            dt.downtimeType != null ? dt.downtimeType : "Machine",
                            dt.durationMinutes,
                            dt.description,
                            dt.operatorNik != null && !dt.operatorNik.isEmpty() ? dt.operatorNik : "OPERATOR"
                    );
                    Response<ApiResponse<Void>> dtRes = apiService.reportDowntime(dtReq).execute();
                    if (dtRes.isSuccessful()) {
                        syncedDtIds.add(dt.localId);
                    }
                }
                if (!syncedDtIds.isEmpty()) {
                    vault.markDowntimeSynced(syncedDtIds);
                    totalSynced += syncedDtIds.size();
                }

                // 4. PUSH OFFLINE GENERATED BARCODES
                List<AuthVaultManager.LocalBarcodeItem> allBc = vault.getAllGeneratedBarcodeItems();
                List<Long> syncedBcIds = new ArrayList<>();
                for (AuthVaultManager.LocalBarcodeItem bc : allBc) {
                    if (bc.syncStatus != 0) continue;
                    BarcodeGenerateRequest bcReq = new BarcodeGenerateRequest(
                            bc.barcodeType != null ? bc.barcodeType : "PRODUCT",
                            bc.productCode != null ? bc.productCode : "RDL",
                            bc.productName != null ? bc.productName : "Ban Radial",
                            bc.sizeName != null ? bc.sizeName : "185/65 R15",
                            Math.max(1, bc.qty)
                    );
                    Response<ApiResponse<BarcodeEntity>> bcRes = apiService.generateBarcode(bcReq).execute();
                    if (bcRes.isSuccessful()) {
                        syncedBcIds.add(bc.localId);
                    }
                }
                if (!syncedBcIds.isEmpty()) {
                    vault.markBarcodesSynced(syncedBcIds);
                    totalSynced += syncedBcIds.size();
                }

                // 5. PULL FRESH MASTER DATA, SCADA SUMMARY & USERS INTO ROOM + VAULT CACHE
                pullMasterAndDashboardInternal(apiService);

                isSyncing.set(false);
                if (lastError != null && totalSynced == 0) {
                    if (callback != null) callback.onSyncFailed(lastError);
                } else {
                    if (callback != null) callback.onSyncCompleted(totalSynced);
                }
            } catch (Exception e) {
                Log.e(TAG, "syncAll error: " + e.getMessage(), e);
                isSyncing.set(false);
                if (callback != null) callback.onSyncFailed(e.getMessage() != null ? e.getMessage() : "Koneksi ke server cloud gagal.");
            }
        });
    }

    public void pullAllMasterDataAsync() {
        executor.execute(() -> {
            try {
                pullMasterAndDashboardInternal(ApiClient.getApiService());
            } catch (Exception e) {
                Log.w(TAG, "Background master pull skipped: " + e.getMessage());
            }
        });
    }

    private void pullMasterAndDashboardInternal(ApiService apiService) {
        try {
            Response<MasterSyncResponse> masterRes = apiService.pullMasterData("pull_master").execute();
            if (masterRes.isSuccessful() && masterRes.body() != null) {
                MasterSyncResponse body = masterRes.body();
                if (body.getDepartments() != null && !body.getDepartments().isEmpty()) {
                    db.masterDao().insertDepartments(body.getDepartments());
                }
                if (body.getSections() != null && !body.getSections().isEmpty()) {
                    db.masterDao().insertSections(body.getSections());
                }
                if (body.getWarehouseRacks() != null && !body.getWarehouseRacks().isEmpty()) {
                    db.masterDao().insertWarehouseRacks(body.getWarehouseRacks());
                }
                if (body.getRecentBarcodes() != null && !body.getRecentBarcodes().isEmpty()) {
                    db.masterDao().insertBarcodes(body.getRecentBarcodes());
                }
            }
        } catch (Exception e) {
            Log.w(TAG, "pullMasterData failed: " + e.getMessage());
        }

        try {
            Response<DashboardSummaryResponse> dashRes = apiService.getDashboardSummary().execute();
            if (dashRes.isSuccessful() && dashRes.body() != null) {
                vault.saveDashboardSummaryCache(dashRes.body());
            }
        } catch (Exception e) {
            Log.w(TAG, "getDashboardSummary failed: " + e.getMessage());
        }

        try {
            Response<UserListResponse> usersRes = apiService.getUsers().execute();
            if (usersRes.isSuccessful() && usersRes.body() != null && usersRes.body().getUsers() != null) {
                List<UserEntity> users = usersRes.body().getUsers();
                for (UserEntity u : users) {
                    AuthVaultManager.enrichUserEntityLabels(u);
                }
                db.userDao().insertAllUsers(users);
            }
        } catch (Exception e) {
            Log.w(TAG, "getUsers failed: " + e.getMessage());
        }
    }

    // Backwards-compatible wrappers for native fragments
    public void syncPendingScans(SyncCallback callback) {
        syncAll(callback);
    }

    public void syncPendingK3Calls(SyncCallback callback) {
        syncAll(callback);
    }
}
