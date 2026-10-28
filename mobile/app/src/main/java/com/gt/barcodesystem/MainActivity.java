package com.gt.barcodesystem;

import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.os.Build;
import android.os.Bundle;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.CookieManager;
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
import com.gt.barcodesystem.util.SessionManager;

public class MainActivity extends AppCompatActivity {

    private static final String PREFS_NAME = "GTBS_PREFS";
    private static final String KEY_SERVER_URL = "SERVER_BASE_URL";
    private static final String DEFAULT_EMULATOR_URL = "http://10.0.2.2:8000/GT_Barcode_System/website/login.php";
    private static final String DEFAULT_WIFI_URL = "http://192.168.1.100:8000/GT_Barcode_System/website/login.php";
    private static final String OFFLINE_DASHBOARD_URL = "file:///android_asset/www/index.html";
    private static final String OFFLINE_LOGIN_URL = "file:///android_asset/www/login.html";

    private WebView webView;
    private ProgressBar progressBar;
    private MaterialCardView cardOfflineBanner;
    private TextView tvOfflineBannerText;
    private MaterialButton btnBannerSync;
    private FloatingActionButton fabSettings;

    private PdtBridge pdtBridge;
    private SessionManager sessionManager;
    private SyncManager syncManager;
    private SharedPreferences prefs;
    private boolean isOfflinePageLoaded = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_main);

        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        sessionManager = new SessionManager(this);
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

            @Override
            public void onLogoutRequested() {
                runOnUiThread(() -> {
                    sessionManager.logout();
                    CookieManager.getInstance().removeAllCookies(null);
                    CookieManager.getInstance().flush();
                    Toast.makeText(MainActivity.this, "Berhasil Keluar Akun", Toast.LENGTH_SHORT).show();
                    if (pdtBridge.isOnline()) {
                        loadActiveServerUrl();
                    } else {
                        loadOfflineLoginPage();
                    }
                });
            }
        });
    }

    private void setupWebView() {
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setUseWideViewPort(true);
        webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);

        // User-agent custom identification
        String defaultUa = webSettings.getUserAgentString();
        webSettings.setUserAgentString(defaultUa + " GTBarcodeMobile/1.0 PDT_Android");

        // Persistent Cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        // Native PDT Javascript Bridge
        webView.addJavascriptInterface(pdtBridge, "AndroidPDT");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                if (url.contains("logout.php")) {
                    sessionManager.logout();
                    CookieManager.getInstance().removeAllCookies(null);
                    CookieManager.getInstance().flush();
                    if (pdtBridge.isOnline()) {
                        view.loadUrl(getLoginUrl());
                    } else {
                        loadOfflineLoginPage();
                    }
                    return true;
                }
                return false;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                progressBar.setVisibility(View.VISIBLE);
                if (url.contains("logout.php")) {
                    sessionManager.logout();
                }
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);

                isOfflinePageLoaded = url.startsWith("file:///");
                if (isOfflinePageLoaded) {
                    checkPendingScansAndUpdateBanner();
                } else {
                    cardOfflineBanner.setVisibility(View.GONE);
                    CookieManager.getInstance().flush();

                    // If logged in on the web, capture session and record URL
                    if (!url.contains("login.php") && !url.contains("logout.php")) {
                        sessionManager.setLastUrl(url);
                        injectSessionExtractorScript();
                    }
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

    private boolean isRunningOnEmulator() {
        return Build.FINGERPRINT.startsWith("generic")
                || Build.FINGERPRINT.startsWith("unknown")
                || Build.MODEL.contains("google_sdk")
                || Build.MODEL.contains("Emulator")
                || Build.MODEL.contains("Android SDK built for x86")
                || Build.MANUFACTURER.contains("Genymotion")
                || (Build.BRAND.startsWith("generic") && Build.DEVICE.startsWith("generic"))
                || "google_sdk".equals(Build.PRODUCT);
    }

    private String getBaseServerUrl() {
        String savedUrl = prefs.getString(KEY_SERVER_URL, "");
        if (savedUrl.isEmpty() || !savedUrl.contains("/website/")) {
            savedUrl = isRunningOnEmulator() ? DEFAULT_EMULATOR_URL : DEFAULT_WIFI_URL;
            prefs.edit().putString(KEY_SERVER_URL, savedUrl).apply();
        }
        return savedUrl;
    }

    private String getLoginUrl() {
        String base = getBaseServerUrl();
        if (base.endsWith("login.php")) return base;
        int idx = base.lastIndexOf('/');
        return (idx != -1 ? base.substring(0, idx + 1) : base) + "login.php";
    }

    private void loadInitialUrl() {
        boolean online = pdtBridge.isOnline();
        if (sessionManager.isLoggedIn()) {
            if (online) {
                String targetUrl = sessionManager.getLastUrl();
                if (targetUrl.isEmpty() || targetUrl.contains("login.php")) {
                    String base = getBaseServerUrl();
                    String baseDir = base.substring(0, base.lastIndexOf('/') + 1);
                    String role = sessionManager.getRole();
                    if ("operator".equalsIgnoreCase(role) || "leader".equalsIgnoreCase(role) || "section_head".equalsIgnoreCase(role)) {
                        targetUrl = baseDir + "operator_lite.php";
                    } else {
                        targetUrl = baseDir + "dashboard.php";
                    }
                }
                webView.loadUrl(targetUrl);
            } else {
                loadOfflineDashboardPage();
            }
        } else {
            if (online) {
                loadActiveServerUrl();
            } else {
                loadOfflineLoginPage();
            }
        }
    }

    private void loadActiveServerUrl() {
        String url = getLoginUrl();
        webView.loadUrl(url);
    }

    private void loadOfflineDashboardPage() {
        runOnUiThread(() -> {
            isOfflinePageLoaded = true;
            cardOfflineBanner.setVisibility(View.VISIBLE);
            webView.loadUrl(OFFLINE_DASHBOARD_URL);
            checkPendingScansAndUpdateBanner();
            Toast.makeText(this, "Mode Offline PDT Aktif", Toast.LENGTH_SHORT).show();
        });
    }

    private void loadOfflineLoginPage() {
        runOnUiThread(() -> {
            isOfflinePageLoaded = true;
            cardOfflineBanner.setVisibility(View.VISIBLE);
            webView.loadUrl(OFFLINE_LOGIN_URL);
            checkPendingScansAndUpdateBanner();
        });
    }

    private void loadOfflineLocalPage() {
        runOnUiThread(() -> {
            if (!isOfflinePageLoaded) {
                if (sessionManager.isLoggedIn()) {
                    loadOfflineDashboardPage();
                } else {
                    loadOfflineLoginPage();
                }
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
            tvOfflineBannerText.setText("MODE OFFLINE PDT (Lokal Room DB) • " + pendingCount + " Pending");
        } else {
            cardOfflineBanner.setVisibility(View.GONE);
        }
    }

    private void triggerSync() {
        Toast.makeText(this, "Menyinkronkan antrean scan offline...", Toast.LENGTH_SHORT).show();
        syncManager.syncPendingScans(new SyncManager.SyncCallback() {
            @Override
            public void onSyncCompleted(int syncedCount) {
                runOnUiThread(() -> {
                    Toast.makeText(MainActivity.this, "Sukses sinkron: " + syncedCount + " scan.", Toast.LENGTH_SHORT).show();
                    checkPendingScansAndUpdateBanner();
                    if (isOfflinePageLoaded && pdtBridge.isOnline()) {
                        loadInitialUrl();
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
                            loadInitialUrl();
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
        etUrl.setHint("http://192.168.1.100:8000/GT_Barcode_System/website/login.php");
        container.addView(etUrl);

        new AlertDialog.Builder(this)
                .setTitle("Pengaturan Alamat Server")
                .setView(container)
                .setPositiveButton("Simpan & Muat", (dialog, which) -> {
                    String newUrl = etUrl.getText().toString().trim();
                    if (!newUrl.isEmpty()) {
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

    private void injectSessionExtractorScript() {
        String js = "(function() {" +
                "  try {" +
                "    var uName = document.querySelector('.user-name') || document.querySelector('.user-profile-name') || document.querySelector('.user-meta .user-name');" +
                "    var rName = document.querySelector('.user-role') || document.querySelector('.user-meta .user-role');" +
                "    var fullName = uName ? uName.innerText.trim() : '';" +
                "    var role = rName ? rName.innerText.trim().toLowerCase() : '';" +
                "    if (fullName && window.AndroidPDT) {" +
                "      window.AndroidPDT.syncWebSession(fullName, role, window.location.href);" +
                "    }" +
                "  } catch(e) {}" +
                "})();";
        webView.evaluateJavascript(js, null);
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
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        if (intent != null && intent.getData() != null && webView != null) {
            webView.loadUrl(intent.getData().toString());
        }
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
