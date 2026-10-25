package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.ApiResponse;
import com.gt.barcodesystem.data.remote.model.BarcodeGenerateRequest;
import com.gt.barcodesystem.data.remote.model.BarcodeListResponse;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class BarcodeRepository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final ExecutorService executor;

    public interface BarcodeListCallback {
        void onBarcodesLoaded(List<BarcodeEntity> barcodes);
        void onError(String error);
    }

    public interface BarcodeGenerateCallback {
        void onSuccess(String barcodeStr, String message);
        void onError(String error);
    }

    public BarcodeRepository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void fetchBarcodes(BarcodeListCallback callback) {
        apiService.getBarcodes().enqueue(new Callback<BarcodeListResponse>() {
            @Override
            public void onResponse(Call<BarcodeListResponse> call, Response<BarcodeListResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().getBarcodes() != null) {
                    List<BarcodeEntity> remoteBarcodes = response.body().getBarcodes();
                    executor.execute(() -> db.masterDao().insertBarcodes(remoteBarcodes));
                    if (callback != null) callback.onBarcodesLoaded(remoteBarcodes);
                } else {
                    loadBarcodesFromLocalDb(callback);
                }
            }

            @Override
            public void onFailure(Call<BarcodeListResponse> call, Throwable t) {
                loadBarcodesFromLocalDb(callback);
            }
        });
    }

    private void loadBarcodesFromLocalDb(BarcodeListCallback callback) {
        executor.execute(() -> {
            List<BarcodeEntity> localBarcodes = db.masterDao().getAllBarcodes();
            if (callback != null) callback.onBarcodesLoaded(localBarcodes);
        });
    }

    public void generateBarcode(BarcodeGenerateRequest request, BarcodeGenerateCallback callback) {
        apiService.generateBarcode(request).enqueue(new Callback<ApiResponse<BarcodeEntity>>() {
            @Override
            public void onResponse(Call<ApiResponse<BarcodeEntity>> call, Response<ApiResponse<BarcodeEntity>> response) {
                if (response.isSuccessful() && response.body() != null && response.body().getData() != null) {
                    BarcodeEntity generated = response.body().getData();
                    executor.execute(() -> db.masterDao().insertBarcode(generated));
                    if (callback != null) callback.onSuccess(generated.getBarcode(), "Barcode berhasil dibuat!");
                } else {
                    generateBarcodeOffline(request, callback);
                }
            }

            @Override
            public void onFailure(Call<ApiResponse<BarcodeEntity>> call, Throwable t) {
                generateBarcodeOffline(request, callback);
            }
        });
    }

    private void generateBarcodeOffline(BarcodeGenerateRequest request, BarcodeGenerateCallback callback) {
        executor.execute(() -> {
            SimpleDateFormat sdf = new SimpleDateFormat("YYMMddHHmmss", Locale.getDefault());
            String timeStamp = sdf.format(new Date());
            String generatedCode = "GT-" + (request.getBarcodeType() != null ? request.getBarcodeType() : "PRD") + "-" + timeStamp;

            BarcodeEntity entity = new BarcodeEntity();
            entity.setId((int) (System.currentTimeMillis() / 1000));
            entity.setBarcode(generatedCode);
            entity.setBarcodeType(request.getBarcodeType());
            entity.setProductCode(request.getProductCode());
            entity.setProductName(request.getProductName());
            entity.setSizeName(request.getSizeName());
            entity.setStatus("GENERATED");
            entity.setCurrentDept("WH");

            SimpleDateFormat dateSdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
            entity.setCreatedAt(dateSdf.format(new Date()));

            db.masterDao().insertBarcode(entity);

            if (callback != null) {
                callback.onSuccess(generatedCode, "Barcode offline berhasil digenerate di PDT.");
            }
        });
    }
}
