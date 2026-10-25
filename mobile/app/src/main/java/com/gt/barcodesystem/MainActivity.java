package com.gt.barcodesystem;

import android.app.AlertDialog;
import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.os.Bundle;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.sync.SyncManager;
import com.gt.barcodesystem.util.PdtBridge;

public class MainActivity extends AppCompatActivity {

    private static final String PREFS_NAME = "GTBS_PREFS";
    private static final String KEY_SERVER_URL = "SERVER_BASE_URL";
    private static final String DEFAULT_EMULATOR_URL = "http://localhost:8000/GT_Barcode_System/";
    private static final String DEFAULT_WIFI_URL = "http://192.168.1.100:8000/GT_Barcode_System/";
    private static final String OFFLINE_ASSET_URL = "file:///android_asset/www/index.html";

    private WebView webView;
    private ProgressBar progressBar;
    private MaterialCardView cardOfflineBanner;
    private TextView tvOfflineBannerText;
    private MaterialButton btnBannerSync;
    private FloatingActionButton fabSettings;

    private PdtBridge pdtBridge;
    private SyncManager syncManager;
    private SharedPreferences prefs;
    private boolean isOfflinePageLoaded = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_main);

        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        syncManager = SyncManager.getInstance(this);

        initViews();
        setupWebView();
        setupNetworkMonitoring();
        setupListeners();

        loadInitialUrl();
    }

    private void initViews() {
        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        cardOfflineBanner = findViewById(R.id.cardOfflineBanner);
        tvOfflineBannerText = findViewById(R.id.tvOfflineBannerText);
        btnBannerSync = findViewById(R.id.btnBannerSync);
        fabSettings = findViewById(R.id.fabSettings);

        pdtBridge = new PdtBridge(this);
        pdtBridge.setListener(new PdtBridge.BridgeEventListener() {
            @Override
            public void onScanSavedLocally(String barcode, int pendingCount) {
                runOnUiThread(() -> updateOfflineBanner(pendingCount));
            }

            @Override
            public void onSyncRequested() {
                runOnUiThread(() -> triggerSync());
            }
        });
    }

    private void setupWebView() {
        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);
        ws.setLoadWithOverviewMode(true);
        ws.setUseWideViewPort(true);
        ws.setBuiltInZoomControls(false);
        ws.setDisplayZoomControls(false);
        ws.setUserAgentString(ws.getUserAgentString() + " GTBarcodeMobile/2.0");
        webView.setBackgroundColor(android.graphics.Color.parseColor("#0b0f19"));
        WebView.setWebContentsDebuggingEnabled(true);

        webView.addJavascriptInterface(pdtBridge, "AndroidPDT");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);

                isOfflinePageLoaded = url.startsWith("file:///");
                if (isOfflinePageLoaded) {
                    checkPendingScansAndUpdateBanner();
                } else {
                    cardOfflineBanner.setVisibility(View.GONE);
                }

                // Inject offline interceptor script to web pages
                injectPdtOfflineScript();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    loadOfflineLocalPage();
                }
            }


        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                progressBar.setProgress(newProgress);
                if (newProgress == 100) {
                    progressBar.setVisibility(View.GONE);
                }
            }
        });
    }

    private void setupListeners() {


        fabSettings.setOnClickListener(v -> showServerConfigDialog());

        btnBannerSync.setOnClickListener(v -> triggerSync());
    }

    private void loadInitialUrl() {
        String savedUrl = prefs.getString(KEY_SERVER_URL, "");
        if (savedUrl.isEmpty()) {
            savedUrl = DEFAULT_EMULATOR_URL;
            prefs.edit().putString(KEY_SERVER_URL, savedUrl).apply();
        }
        webView.loadUrl(savedUrl);
    }

    private void loadActiveServerUrl() {
        String url = prefs.getString(KEY_SERVER_URL, DEFAULT_EMULATOR_URL);
        webView.loadUrl(url);
    }

    private void loadOfflineLocalPage() {
        runOnUiThread(() -> {
            if (!isOfflinePageLoaded) {
                isOfflinePageLoaded = true;
                cardOfflineBanner.setVisibility(View.VISIBLE);
                webView.loadUrl(OFFLINE_ASSET_URL);
                checkPendingScansAndUpdateBanner();
                Toast.makeText(this, "Mode Offline PDT (Lokal Room DB)", Toast.LENGTH_SHORT).show();
            }
        });
    }

    private void checkPendingScansAndUpdateBanner() {
        new Thread(() -> {
            int pending = AppDatabase.getInstance(this).offlineScanDao().getPendingCount();
            runOnUiThread(() -> updateOfflineBanner(pending));
        }).start();
    }

    private void updateOfflineBanner(int pendingCount) {
        if (pendingCount > 0 || isOfflinePageLoaded) {
            cardOfflineBanner.setVisibility(View.VISIBLE);
            tvOfflineBannerText.setText("MODE OFFLINE PDT (Lokal Room DB) ? " + pendingCount + " Pending");
        } else {
            cardOfflineBanner.setVisibility(View.GONE);
        }
    }

    private void triggerSync() {
        Toast.makeText(this, "Menyinkronkan data antrean offline...", Toast.LENGTH_SHORT).show();
        syncManager.syncPendingScans(new SyncManager.SyncCallback() {
            @Override
            public void onSyncCompleted(int syncedCount) {
                runOnUiThread(() -> {
                    Toast.makeText(MainActivity.this, "Sukses sinkron: " + syncedCount + " scan.", Toast.LENGTH_SHORT).show();
                    checkPendingScansAndUpdateBanner();
                    if (isOfflinePageLoaded) {
                        loadActiveServerUrl();
                    }
                });
            }

            @Override
            public void onSyncFailed(String error) {
                runOnUiThread(() -> Toast.makeText(MainActivity.this, "Gagal sinkron / server offline: " + error, Toast.LENGTH_SHORT).show());
            }
        });
    }

    private void setupNetworkMonitoring() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm != null) {
            NetworkRequest request = new NetworkRequest.Builder()
                    .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                    .build();
            cm.registerNetworkCallback(request, new ConnectivityManager.NetworkCallback() {
                @Override
                public void onAvailable(Network network) {
                    runOnUiThread(() -> {
                        // Background auto-sync when network is back
                        syncManager.syncPendingScans(null);
                        if (isOfflinePageLoaded) {
                            loadActiveServerUrl();
                        }
                    });
                }
            });
        }
    }

    private void showServerConfigDialog() {
        String currentUrl = prefs.getString(KEY_SERVER_URL, DEFAULT_EMULATOR_URL);

        LinearLayout container = new LinearLayout(this);
        container.setOrientation(LinearLayout.VERTICAL);
        container.setPadding(40, 20, 40, 20);

        TextView tvDesc = new TextView(this);
        tvDesc.setText("Pilih atau masukkan alamat URL Server Web Backend:");
        tvDesc.setPadding(0, 0, 0, 16);
        container.addView(tvDesc);

        EditText etUrl = new EditText(this);
        etUrl.setText(currentUrl);
        etUrl.setHint("http://192.168.1.100:8000/GT_Barcode_System/");
        container.addView(etUrl);

        new AlertDialog.Builder(this)
                .setTitle("Pengaturan Alamat Server")
                .setView(container)
                .setPositiveButton("Simpan & Muat", (dialog, which) -> {
                    String newUrl = etUrl.getText().toString().trim();
                    if (!newUrl.isEmpty()) {
                        if (!newUrl.endsWith("/")) newUrl += "/";
                        prefs.edit().putString(KEY_SERVER_URL, newUrl).apply();
                        webView.loadUrl(newUrl);
                    }
                })
                .setNeutralButton("WiFi HP (192.168.1.100)", (dialog, which) -> {
                    prefs.edit().putString(KEY_SERVER_URL, DEFAULT_WIFI_URL).apply();
                    webView.loadUrl(DEFAULT_WIFI_URL);
                })
                .setNegativeButton("Emulator (10.0.2.2)", (dialog, which) -> {
                    prefs.edit().putString(KEY_SERVER_URL, DEFAULT_EMULATOR_URL).apply();
                    webView.loadUrl(DEFAULT_EMULATOR_URL);
                })
                .show();
    }

    private void injectPdtOfflineScript() {
        String js = "(function() {" +
                "  if (window.__pdtInjected) return;" +
                "  window.__pdtInjected = true;" +
                "  window.addEventListener('offline', function() {" +
                "    console.log('Jaringan terputus, mengaktifkan Local-First offline cache.');" +
                "  });" +
                "})();";
        webView.evaluateJavascript(js, null);
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }
}
