package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;
import com.gt.barcodesystem.data.sync.SyncManager;
import com.gt.barcodesystem.util.AudioBeepManager;
import com.gt.barcodesystem.util.SessionManager;
import com.gt.barcodesystem.util.ShiftHelper;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class ScanRepository {

    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final SyncManager syncManager;
    private final ExecutorService executor;

    public interface ScanCallback {
        void onScanSaved(OfflineScanEntity scanEntity, int pendingCount);
        void onError(String message);
    }

    public interface ScanHistoryCallback {
        void onHistoryLoaded(List<OfflineScanEntity> scans, int pendingCount);
    }

    public interface RackCheckCallback {
        void onResult(WarehouseRackEntity rack);
    }

    public ScanRepository(Context context) {
        db = AppDatabase.getInstance(context);
        sessionManager = new SessionManager(context);
        syncManager = SyncManager.getInstance(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void processBarcodeScan(String barcode, String actionType, String machineCode, String details, ScanCallback callback) {
        processDetailedScan(barcode, actionType, machineCode, details, 1.0, "Pcs", "NORMAL", callback);
    }

    public void processDetailedScan(String barcode, String actionType, String machineCode, String details, double qty, String unit, String status, ScanCallback callback) {
        if (barcode == null || barcode.trim().isEmpty()) {
            AudioBeepManager.playErrorBeep();
            if (callback != null) callback.onError("Barcode tidak boleh kosong");
            return;
        }

        executor.execute(() -> {
            try {
                SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
                String currentTime = sdf.format(new Date());

                OfflineScanEntity scan = new OfflineScanEntity();
                scan.setBarcode(barcode.trim());
                scan.setActionType(actionType != null ? actionType : "PDT_SCAN");
                scan.setDeptId(sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 1);
                scan.setSectionId(sessionManager.getSectionId() > 0 ? sessionManager.getSectionId() : 1);
                scan.setQty(qty > 0 ? qty : 1.0);
                scan.setUnit(unit != null ? unit : "Pcs");
                scan.setOperatorId(sessionManager.getUserId());
                scan.setOperatorNik(sessionManager.getNik());
                scan.setDetails(details != null ? details : "Scan PDT Mobile");
                scan.setMachineCode(machineCode);
                scan.setCreatedAt(currentTime);
                scan.setShift(ShiftHelper.getCurrentShift());
                scan.setStatus(status != null ? status : "NORMAL");
                scan.setSyncStatus(0); // PENDING

                long id = db.offlineScanDao().insert(scan);
                scan.setLocalId(id);

                AudioBeepManager.playSuccessBeep();

                int pendingCount = db.offlineScanDao().getPendingCount();

                if (callback != null) {
                    callback.onScanSaved(scan, pendingCount);
                }

                // Trigger background sync
                syncManager.syncPendingScans(null);

            } catch (Exception e) {
                AudioBeepManager.playErrorBeep();
                if (callback != null) {
                    callback.onError("Gagal menyimpan scan: " + e.getMessage());
                }
            }
        });
    }

    public void checkRackStatus(String rackCode, RackCheckCallback callback) {
        executor.execute(() -> {
            WarehouseRackEntity rack = db.masterDao().getRackByCode(rackCode);
            if (callback != null) {
                callback.onResult(rack);
            }
        });
    }

    public void assignRackOffline(String rackCode, String barcode, String itemName, double qty) {
        executor.execute(() -> {
            db.masterDao().assignRack(rackCode, barcode, itemName, qty);
        });
    }

    public void getScanHistory(ScanHistoryCallback callback) {
        executor.execute(() -> {
            List<OfflineScanEntity> history = db.offlineScanDao().getAllScans();
            int pendingCount = db.offlineScanDao().getPendingCount();
            if (callback != null) {
                callback.onHistoryLoaded(history, pendingCount);
            }
        });
    }
}
