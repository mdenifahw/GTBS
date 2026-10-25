package com.gt.barcodesystem.util;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.Build;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.webkit.JavascriptInterface;
import android.widget.Toast;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.sync.SyncManager;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class PdtBridge {

    private final Context context;
    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final SyncManager syncManager;
    private final ExecutorService executor;
    private final Vibrator vibrator;

    public interface BridgeEventListener {
        void onScanSavedLocally(String barcode, int pendingCount);
        void onSyncRequested();
    }

    private BridgeEventListener listener;

    public PdtBridge(Context context) {
        this.context = context;
        this.db = AppDatabase.getInstance(context);
        this.sessionManager = new SessionManager(context);
        this.syncManager = SyncManager.getInstance(context);
        this.executor = Executors.newSingleThreadExecutor();
        this.vibrator = (Vibrator) context.getSystemService(Context.VIBRATOR_SERVICE);
    }

    public void setListener(BridgeEventListener listener) {
        this.listener = listener;
    }

    @JavascriptInterface
    public void saveOfflineScan(String barcode, String stationCode, String details, double qty, String status) {
        if (barcode == null || barcode.trim().isEmpty()) {
            playErrorBeep();
            return;
        }

        executor.execute(() -> {
            try {
                SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
                String currentTime = sdf.format(new Date());

                OfflineScanEntity scan = new OfflineScanEntity();
                scan.setBarcode(barcode.trim().toUpperCase());
                scan.setActionType(stationCode != null ? stationCode : "PDT_SCAN");
                scan.setDeptId(sessionManager.getDeptId() > 0 ? sessionManager.getDeptId() : 1);
                scan.setSectionId(sessionManager.getSectionId() > 0 ? sessionManager.getSectionId() : 1);
                scan.setQty(qty > 0 ? qty : 1.0);
                scan.setUnit("Pcs");
                scan.setOperatorId(sessionManager.getUserId() > 0 ? sessionManager.getUserId() : 1);
                scan.setOperatorNik(sessionManager.getNik() != null ? sessionManager.getNik() : "OPERATOR");
                scan.setDetails(details != null ? details : "Scan Mode Lite PDT");
                scan.setCreatedAt(currentTime);
                scan.setShift(ShiftHelper.getCurrentShift());
                scan.setStatus(status != null ? status : "NORMAL");
                scan.setSyncStatus(0); // 0 = PENDING OFFLINE

                long id = db.offlineScanDao().insert(scan);
                scan.setLocalId(id);

                playSuccessBeep();
                triggerVibration(150);

                int pendingCount = db.offlineScanDao().getPendingCount();

                if (listener != null) {
                    listener.onScanSavedLocally(barcode, pendingCount);
                }

                // If currently connected, attempt background sync immediately
                if (isOnline()) {
                    syncManager.syncPendingScans(null);
                }

            } catch (Exception e) {
                playErrorBeep();
            }
        });
    }

    @JavascriptInterface
    public int getPendingCount() {
        try {
            return db.offlineScanDao().getPendingCount();
        } catch (Exception e) {
            return 0;
        }
    }

    @JavascriptInterface
    public void syncNow() {
        if (listener != null) {
            listener.onSyncRequested();
        }
        syncManager.syncPendingScans(null);
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
        if (vibrator != null) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator.vibrate(VibrationEffect.createOneShot(ms > 0 ? ms : 150, VibrationEffect.DEFAULT_AMPLITUDE));
            } else {
                vibrator.vibrate(ms > 0 ? ms : 150);
            }
        }
    }

    @JavascriptInterface
    public boolean isOnline() {
        ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm != null) {
            NetworkInfo activeNetwork = cm.getActiveNetworkInfo();
            return activeNetwork != null && activeNetwork.isConnectedOrConnecting();
        }
        return false;
    }

    @JavascriptInterface
    public void showToast(String message) {
        if (message != null) {
            Toast.makeText(context, message, Toast.LENGTH_SHORT).show();
        }
    }
}
