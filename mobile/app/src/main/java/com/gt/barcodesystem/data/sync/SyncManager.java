package com.gt.barcodesystem.data.sync;

import android.content.Context;
import android.util.Log;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.K3CallRequest;
import com.gt.barcodesystem.data.remote.model.K3CallResponse;
import com.gt.barcodesystem.data.remote.model.PushActivitiesRequest;
import com.gt.barcodesystem.data.remote.model.PushActivitiesResponse;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Response;

public class SyncManager {

    private static final String TAG = "SyncManager";
    private static SyncManager instance;

    private final AppDatabase db;
    private final ApiService apiService;
    private final ExecutorService executor;
    private boolean isSyncing = false;

    public interface SyncCallback {
        void onSyncCompleted(int syncedCount);
        void onSyncFailed(String error);
    }

    private SyncManager(Context context) {
        db = AppDatabase.getInstance(context);
        apiService = ApiClient.getApiService();
        executor = Executors.newSingleThreadExecutor();
    }

    public static synchronized SyncManager getInstance(Context context) {
        if (instance == null) {
            instance = new SyncManager(context.getApplicationContext());
        }
        return instance;
    }

    public void syncPendingScans(SyncCallback callback) {
        if (isSyncing) return;

        executor.execute(() -> {
            isSyncing = true;
            try {
                List<OfflineScanEntity> pendingScans = db.offlineScanDao().getPendingScans();
                if (pendingScans == null || pendingScans.isEmpty()) {
                    isSyncing = false;
                    if (callback != null) callback.onSyncCompleted(0);
                    return;
                }

                PushActivitiesRequest request = new PushActivitiesRequest(pendingScans);
                Response<PushActivitiesResponse> response = apiService.pushActivities(request).execute();

                if (response.isSuccessful() && response.body() != null) {
                    PushActivitiesResponse body = response.body();
                    List<Long> syncedIds = body.getSyncedLocalIds();

                    if (syncedIds != null && !syncedIds.isEmpty()) {
                        db.offlineScanDao().markAsSynced(syncedIds);
                        int count = syncedIds.size();
                        Log.d(TAG, "Successfully synced " + count + " pending scans.");
                        isSyncing = false;
                        if (callback != null) callback.onSyncCompleted(count);
                        return;
                    }
                }

                isSyncing = false;
                if (callback != null) callback.onSyncFailed("Sync response unsuccessful");
            } catch (Exception e) {
                Log.e(TAG, "Sync error: " + e.getMessage(), e);
                isSyncing = false;
                if (callback != null) callback.onSyncFailed(e.getMessage());
            }
        });
    }

    public void syncPendingK3Calls(SyncCallback callback) {
        executor.execute(() -> {
            try {
                List<K3CallEntity> pendingCalls = db.k3CallDao().getPendingCalls();
                if (pendingCalls == null || pendingCalls.isEmpty()) {
                    if (callback != null) callback.onSyncCompleted(0);
                    return;
                }

                List<Long> syncedIds = new ArrayList<>();
                for (K3CallEntity call : pendingCalls) {
                    K3CallRequest req = new K3CallRequest(
                            call.getCallerId(),
                            call.getCallerName(),
                            call.getDeptId(),
                            call.getLocationDetail(),
                            call.getPatientCondition(),
                            call.getPriority()
                    );
                    Response<K3CallResponse> res = apiService.pushK3Call(req).execute();
                    if (res.isSuccessful()) {
                        syncedIds.add(call.getLocalId());
                    }
                }

                if (!syncedIds.isEmpty()) {
                    db.k3CallDao().markAsSynced(syncedIds);
                }
                if (callback != null) callback.onSyncCompleted(syncedIds.size());
            } catch (Exception e) {
                Log.e(TAG, "K3 sync error: " + e.getMessage(), e);
                if (callback != null) callback.onSyncFailed(e.getMessage());
            }
        });
    }
}
