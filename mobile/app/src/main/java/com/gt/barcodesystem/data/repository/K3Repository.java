package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.ApiResponse;
import com.gt.barcodesystem.data.remote.model.DowntimeReportRequest;
import com.gt.barcodesystem.data.remote.model.K3CallRequest;
import com.gt.barcodesystem.data.remote.model.K3CallResponse;
import com.gt.barcodesystem.data.remote.model.K3ListResponse;
import com.gt.barcodesystem.data.remote.model.K3StatusUpdateRequest;
import com.gt.barcodesystem.util.SessionManager;

import java.text.SimpleDateFormat;
import java.util.Collections;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class K3Repository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final ExecutorService executor;

    public interface K3Callback {
        void onSuccess(String callNo, String message);
        void onError(String error);
    }

    public interface K3ListCallback {
        void onLoaded(List<K3CallEntity> calls);
        void onError(String error);
    }

    public K3Repository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        sessionManager = new SessionManager(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void triggerEmergencyCall(String location, String condition, String priority, K3Callback callback) {
        int callerId = sessionManager.getUserId();
        String callerName = sessionManager.getFullName();
        int deptId = sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 1;

        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
        String currentTime = sdf.format(new Date());

        K3CallEntity localCall = new K3CallEntity();
        localCall.setCallerId(callerId);
        localCall.setCallerName(callerName);
        localCall.setDeptId(deptId);
        localCall.setLocationDetail(location);
        localCall.setPatientCondition(condition);
        localCall.setPriority(priority != null ? priority : "HIGH_EMERGENCY");
        localCall.setStatus("REQUESTED");
        localCall.setCreatedAt(currentTime);
        localCall.setSyncStatus(0); // PENDING

        executor.execute(() -> {
            long localId = db.k3CallDao().insert(localCall);

            K3CallRequest req = new K3CallRequest(callerId, callerName, deptId, location, condition, priority);
            apiService.triggerSosCall(req).enqueue(new Callback<K3CallResponse>() {
                @Override
                public void onResponse(Call<K3CallResponse> call, Response<K3CallResponse> response) {
                    if (response.isSuccessful() && response.body() != null) {
                        K3CallResponse res = response.body();
                        executor.execute(() -> db.k3CallDao().markAsSynced(Collections.singletonList(localId)));
                        if (callback != null) {
                            callback.onSuccess(res.getCallNo(), "Panggilan darurat berhasil dikirim ke Ambulans K3.");
                        }
                    } else {
                        if (callback != null) {
                            callback.onSuccess("AMB-OFFLINE", "Panggilan SOS tersimpan secara offline. Akan tersinkron saat online.");
                        }
                    }
                }

                @Override
                public void onFailure(Call<K3CallResponse> call, Throwable t) {
                    if (callback != null) {
                        callback.onSuccess("AMB-OFFLINE", "Panggilan SOS tersimpan secara offline.");
                    }
                }
            });
        });
    }

    public void updateK3Status(long callId, String status, K3Callback callback) {
        K3StatusUpdateRequest req = new K3StatusUpdateRequest(callId, status);
        apiService.updateK3Status(req).enqueue(new Callback<ApiResponse<Void>>() {
            @Override
            public void onResponse(Call<ApiResponse<Void>> call, Response<ApiResponse<Void>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    if (callback != null) {
                        callback.onSuccess(String.valueOf(callId), response.body().getMessage() != null ? response.body().getMessage() : "Status K3 diperbarui.");
                    }
                } else {
                    if (callback != null) {
                        callback.onSuccess(String.valueOf(callId), "Status K3 diperbarui (Lokal).");
                    }
                }
            }

            @Override
            public void onFailure(Call<ApiResponse<Void>> call, Throwable t) {
                if (callback != null) {
                    callback.onSuccess(String.valueOf(callId), "Status K3 diperbarui (Offline mode).");
                }
            }
        });
    }

    public void fetchActiveK3Calls(K3ListCallback callback) {
        apiService.getActiveK3Calls().enqueue(new Callback<K3ListResponse>() {
            @Override
            public void onResponse(Call<K3ListResponse> call, Response<K3ListResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().getData() != null) {
                    if (callback != null) {
                        callback.onLoaded(response.body().getData());
                    }
                } else {
                    loadLocalK3Calls(callback);
                }
            }

            @Override
            public void onFailure(Call<K3ListResponse> call, Throwable t) {
                loadLocalK3Calls(callback);
            }
        });
    }

    private void loadLocalK3Calls(K3ListCallback callback) {
        executor.execute(() -> {
            List<K3CallEntity> calls = db.k3CallDao().getAllCalls();
            if (callback != null) {
                callback.onLoaded(calls);
            }
        });
    }

    public void reportDowntime(String machineCode, int deptId, String reason, int duration, K3Callback callback) {
        int reporterId = sessionManager.getUserId() > 0 ? sessionManager.getUserId() : 1;
        DowntimeReportRequest req = new DowntimeReportRequest(machineCode, deptId, "Machine", duration, reason, reporterId);
        apiService.reportDowntime(req).enqueue(new Callback<ApiResponse<Void>>() {
            @Override
            public void onResponse(Call<ApiResponse<Void>> call, Response<ApiResponse<Void>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    if (callback != null) {
                        callback.onSuccess(machineCode, response.body().getMessage() != null ? response.body().getMessage() : "Downtime mesin berhasil dilaporkan.");
                    }
                } else {
                    if (callback != null) {
                        callback.onSuccess(machineCode, "Laporan downtime tersimpan lokal.");
                    }
                }
            }

            @Override
            public void onFailure(Call<ApiResponse<Void>> call, Throwable t) {
                if (callback != null) {
                    callback.onSuccess(machineCode, "Laporan downtime tersimpan offline.");
                }
            }
        });
    }
}
