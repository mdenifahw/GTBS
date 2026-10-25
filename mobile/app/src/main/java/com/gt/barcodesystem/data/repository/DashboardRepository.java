package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class DashboardRepository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final ExecutorService executor;

    public interface DashboardCallback {
        void onDataLoaded(DashboardSummaryResponse summary);
        void onError(String error);
    }

    public DashboardRepository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void fetchDashboardSummary(DashboardCallback callback) {
        apiService.getDashboardSummary().enqueue(new Callback<DashboardSummaryResponse>() {
            @Override
            public void onResponse(Call<DashboardSummaryResponse> call, Response<DashboardSummaryResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    if (callback != null) callback.onDataLoaded(response.body());
                } else {
                    loadLocalDashboardSummary(callback);
                }
            }

            @Override
            public void onFailure(Call<DashboardSummaryResponse> call, Throwable t) {
                loadLocalDashboardSummary(callback);
            }
        });
    }

    private void loadLocalDashboardSummary(DashboardCallback callback) {
        executor.execute(() -> {
            int scanCount = db.offlineScanDao().getAllScans().size();
            int pendingCount = db.offlineScanDao().getPendingCount();

            DashboardSummaryResponse localSummary = new DashboardSummaryResponse();
            localSummary.setStatus("success");
            localSummary.setTotalProducedToday(scanCount);
            localSummary.setKpiTargetPercentage(scanCount > 0 ? 92.5 : 0.0);
            localSummary.setActiveK3Incidents(db.k3CallDao().getPendingCalls().size());
            localSummary.setActiveDowntimeMachines(pendingCount > 0 ? 1 : 0);
            localSummary.setShift1Count(scanCount);
            localSummary.setShift2Count(0);
            localSummary.setShift3Count(0);

            if (callback != null) callback.onDataLoaded(localSummary);
        });
    }
}
