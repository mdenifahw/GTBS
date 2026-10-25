package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.DepartmentEntity;
import com.gt.barcodesystem.data.local.entity.SectionEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.MasterSyncResponse;

import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class MasterRepository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final ExecutorService executor;

    public interface MasterCallback {
        void onSuccess(String message);
        void onError(String error);
    }

    public interface RackCallback {
        void onRackLoaded(WarehouseRackEntity rack);
    }

    public interface BarcodeCallback {
        void onBarcodeLoaded(BarcodeEntity barcode);
    }

    public MasterRepository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void pullMasterData(MasterCallback callback) {
        apiService.pullMasterData("pull_master").enqueue(new Callback<MasterSyncResponse>() {
            @Override
            public void onResponse(Call<MasterSyncResponse> call, Response<MasterSyncResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    MasterSyncResponse body = response.body();
                    executor.execute(() -> {
                        if (body.getDepartments() != null) {
                            db.masterDao().insertDepartments(body.getDepartments());
                        }
                        if (body.getSections() != null) {
                            db.masterDao().insertSections(body.getSections());
                        }
                        if (body.getBarcodes() != null) {
                            db.masterDao().insertBarcodes(body.getBarcodes());
                        }
                        if (body.getRacks() != null) {
                            db.masterDao().insertWarehouseRacks(body.getRacks());
                        }
                    });
                    if (callback != null) callback.onSuccess("Master data sukses diperbarui.");
                } else {
                    if (callback != null) callback.onError("Gagal mengunduh master data dari server.");
                }
            }

            @Override
            public void onFailure(Call<MasterSyncResponse> call, Throwable t) {
                if (callback != null) callback.onError("Koneksi gagal: " + t.getMessage());
            }
        });
    }

    public void findRackByCode(String rackCode, RackCallback callback) {
        executor.execute(() -> {
            WarehouseRackEntity rack = db.masterDao().getRackByCode(rackCode);
            if (callback != null) callback.onRackLoaded(rack);
        });
    }

    public void getBarcodeInfo(String barcodeStr, BarcodeCallback callback) {
        executor.execute(() -> {
            BarcodeEntity barcode = db.masterDao().getBarcode(barcodeStr);
            if (callback != null) callback.onBarcodeLoaded(barcode);
        });
    }
}
