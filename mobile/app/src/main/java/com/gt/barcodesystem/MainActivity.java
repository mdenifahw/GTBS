package com.gt.barcodesystem;

import android.Manifest;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.sync.SyncManager;
import com.gt.barcodesystem.util.AuthVaultManager;
import com.gt.barcodesystem.util.PdtBridge;
import com.gt.barcodesystem.util.SessionManager;

import java.util.Locale;

/**
 * MainActivity: Hybrid Cloud + Offline-First Container untuk GT Barcode System Mobile App.
 *
 * Fitur Utama:
 * 1. Zero Blank Screen Offline: Langsung memuat UI lokal 1:1 dalam <20ms saat tanpa jaringan,
 *    dilengkapi Watchdog Timer 4 detik apabila jaringan seluler/WiFi tiba-tiba RTO.
 * 2. Cloud-Native Vercel + Supabase: Terhubung langsung ke https://gtbarcodesystem.vercel.app/
 *    tanpa membutuhkan PC lokal hidup.
 * 3. Clear-Cache Immunity: Apabila cache HP dihapus (WebView cookie hilang) atau sesi serverless
 *    Vercel kadaluarsa, sesi dipulihkan otomatis dari AuthVaultManager tanpa meminta login ulang!
 * 4. Verified-Only Offline Login: Hanya akun yang pernah login saat Online di perangkat ini yang
 *    diizinkan masuk saat Offline.
 */
public class MainActivity extends AppCompatActivity {

    private static final String PREFS_NAME = "GTBS_PREFS";
    private static final String KEY_SERVER_URL = "SERVER_BASE_URL";
    private static final String KEY_URL_MIGRATED_CLOUD = "URL_MIGRATED_CLOUD_V2";
    private static final String DEFAULT_CLOUD_URL = "https://gtbarcodesystem.vercel.app/";
    private static final String OFFLINE_DASHBOARD_URL = "file:///android_asset/www/index.html";
    private static final String OFFLINE_LOGIN_URL = "file:///android_asset/www/login.html";

    private static final int REQ_CAMERA_PERMISSION = 201;
    private static final long WATCHDOG_TIMEOUT_MS = 4500L;

    private WebView webView;
    private ProgressBar progressBar;

    private PdtBridge pdtBridge;
    private SessionManager sessionManager;
    private AuthVaultManager authVault;
    private SyncManager syncManager;
    private SharedPreferences prefs;

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private Runnable pageLoadWatchdog;
    private boolean isOfflinePageLoaded = false;
    private long lastSilentRestoreMs = 0L;
    private PermissionRequest pendingWebPermissionRequest;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_main);

        configureSystemBarsAndInsets();

        ApiClient.init(this);
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        migrateLegacyLocalUrlToCloud();

        authVault = AuthVaultManager.getInstance(this);
        sessionManager = new SessionManager(this);
        syncManager = SyncManager.getInstance(this);

        initViewsAndBridge();
        setupWebView();
        setupNetworkMonitoring();

        loadInitialDestination();
    }

    private void configureSystemBarsAndInsets() {
        getWindow().setStatusBarColor(Color.parseColor("#0b0f19"));
        getWindow().setNavigationBarColor(Color.parseColor("#0b0f19"));
        WindowInsetsControllerCompat controller = new WindowInsetsControllerCompat(getWindow(), getWindow().getDecorView());
        controller.setAppearanceLightStatusBars(false);
        controller.setAppearanceLightNavigationBars(false);

        View root = findViewById(R.id.rootMainLayout);
        if (root != null) {
            ViewCompat.setOnApplyWindowInsetsListener(root, (v, windowInsets) -> {
                Insets bars = windowInsets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.ime());
                v.setPadding(bars.left, bars.top, bars.right, bars.bottom);
                return WindowInsetsCompat.CONSUMED;
            });
        }
    }

    private void migrateLegacyLocalUrlToCloud() {
        String saved = prefs.getString(KEY_SERVER_URL, "");
        boolean migrated = prefs.getBoolean(KEY_URL_MIGRATED_CLOUD, false);
        if (!migrated || saved.isEmpty() || saved.contains("192.168.") || saved.contains("10.0.2.2") || saved.contains("localhost")) {
            prefs.edit()
                    .putString(KEY_SERVER_URL, DEFAULT_CLOUD_URL)
                    .putBoolean(KEY_URL_MIGRATED_CLOUD, true)
                    .apply();
            saved = DEFAULT_CLOUD_URL;
        }
        ApiClient.syncFromWebUrl(this, saved);
    }

    private String getWebRootUrl() {
        String base = prefs.getString(KEY_SERVER_URL, DEFAULT_CLOUD_URL).trim();
        if (base.isEmpty()) base = DEFAULT_CLOUD_URL;
        if (base.endsWith(".php")) {
            int slash = base.lastIndexOf('/');
            if (slash > 8) base = base.substring(0, slash + 1);
        }
        if (!base.endsWith("/")) base += "/";
        return base;
    }

    private String getRoleOnlinePage(String role, String deptCode) {
        String r = role != null ? role.toLowerCase(Locale.ROOT) : "";
        String d = deptCode != null ? deptCode.toUpperCase(Locale.ROOT) : "";
        if ("operator".equals(r)) {
            return "operator_lite.php";
        }
        if ("dept_head".equals(r) || "asst_dept_head".equals(r) || "admin_dept".equals(r)
                || "section_head".equals(r) || "leader".equals(r)) {
            switch (d) {
                case "WH": return "dept_warehouse.php";
                case "MAT": return "dept_material.php";
                case "BLD": return "dept_building.php";
                case "CUR": return "dept_curing.php";
                case "FI": return "dept_final_inspection.php";
            }
        }
        return "dashboard.php";
    }

    private void initViewsAndBridge() {
        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);

        View banner = findViewById(R.id.cardOfflineBanner);
        if (banner != null) banner.setVisibility(View.GONE);
        View fab = findViewById(R.id.fabSettings);
        if (fab != null) fab.setVisibility(View.GONE);

        pdtBridge = new PdtBridge(this);
        pdtBridge.setListener(new PdtBridge.BridgeEventListener() {
            @Override
            public void onScanSavedLocally(String barcode, int pendingCount) {
                runOnUiThread(() -> notifyWebPendingChanged(pendingCount));
            }

            @Override
            public void onSyncRequested() {
                runOnUiThread(() -> triggerFullSync(false));
            }

            @Override
            public void onLogoutRequested() {
                runOnUiThread(() -> performExplicitLogout());
            }

            @Override
            public void onLoginCompleted(boolean success, boolean isOnlineMode, String message, UserEntity user) {
                runOnUiThread(() -> {
                    String safeMsg = escapeJsSingle(message);
                    if (!success || user == null) {
                        webView.evaluateJavascript(
                                "if(window.onNativeLoginResult){window.onNativeLoginResult(false,'" + safeMsg + "');}",
                                null
                        );
                        return;
                    }

                    webView.evaluateJavascript(
                            "if(window.onNativeLoginResult){window.onNativeLoginResult(true,'" + safeMsg + "');}",
                            null
                    );

                    if (isOnlineMode && pdtBridge.isOnline()) {
                        String nik = Uri.encode(user.getNik());
                        loadCloudUrlWithWatchdog(getWebRootUrl() + "login.php?quick_nik=" + nik);
                    } else {
                        loadLocalAssetUrl(OFFLINE_DASHBOARD_URL);
                    }
                });
            }

            @Override
            public void onOpenServerConfigRequested() {
                runOnUiThread(() -> showServerConfigDialog());
            }

            @Override
            public void onSwitchToOnlineModeRequested() {
                runOnUiThread(() -> {
                    if (pdtBridge.isOnline() && sessionManager.isLoggedIn()) {
                        String nik = Uri.encode(sessionManager.getNik());
                        loadCloudUrlWithWatchdog(getWebRootUrl() + "login.php?quick_nik=" + nik);
                    } else {
                        Toast.makeText(MainActivity.this, "Perangkat masih Offline.", Toast.LENGTH_SHORT).show();
                    }
                });
            }
        });
    }

    private void setupWebView() {
        webView.setBackgroundColor(Color.parseColor("#0b0f19"));
        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setAllowFileAccessFromFileURLs(true);
        ws.setAllowUniversalAccessFromFileURLs(true);
        ws.setLoadWithOverviewMode(true);
        ws.setUseWideViewPort(true);
        ws.setMediaPlaybackRequiresUserGesture(false);
        ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);

        String defaultUa = ws.getUserAgentString();
        ws.setUserAgentString(defaultUa + " GTBarcodeMobile/2.0 PDT_Android");

        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        cm.setAcceptThirdPartyCookies(webView, true);

        webView.addJavascriptInterface(pdtBridge, "AndroidPDT");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();

                // 1. Explicit Logout dari halaman Web Online
                if (url.contains("logout.php")) {
                    performExplicitLogout();
                    return true;
                }

                // 2. Jika internet tiba-tiba mati saat user menekan menu di Web Online -> alihkan mulus ke Offline GUI 1:1!
                if ((url.startsWith("http://") || url.startsWith("https://")) && !pdtBridge.isOnline()) {
                    cancelWatchdog();
                    String tabHint = mapOnlineUrlToOfflineHash(url);
                    loadLocalAssetUrl(OFFLINE_DASHBOARD_URL + tabHint);
                    return true;
                }

                // 3. Clear-Cache / Serverless Session Expiry Immunity:
                // Jika user SUDAH LOGIN di Vault, tetapi WebView diarahkan ke login.php (karena cache/cookie dihapus),
                // lakukan Silent Auto-Restore sesi tanpa memaksa user login ulang!
                if (isUnauthenticatedLoginRedirect(url)) {
                    triggerSilentCloudSessionRestore();
                    return true;
                }

                if (url.startsWith("http://") || url.startsWith("https://")) {
                    startWatchdog();
                }
                return false;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                if (url != null && url.contains("logout.php")) {
                    performExplicitLogout();
                    return;
                }
                if (url != null && isUnauthenticatedLoginRedirect(url)) {
                    view.stopLoading();
                    triggerSilentCloudSessionRestore();
                    return;
                }
                if (url != null && url.startsWith("file:///")) {
                    isOfflinePageLoaded = true;
                    cancelWatchdog();
                    progressBar.setVisibility(View.GONE);
                } else {
                    progressBar.setVisibility(View.VISIBLE);
                }
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);
                if (url == null) return;

                if (url.startsWith("file:///")) {
                    isOfflinePageLoaded = true;
                    cancelWatchdog();
                    return;
                }

                // Guard: Jika halaman offline sudah dimuat oleh fallback error, abaikan event terlambat dari URL HTTP yang gagal!
                if (isOfflinePageLoaded) {
                    return;
                }

                cancelWatchdog();
                CookieManager.getInstance().flush();

                if (!url.contains("login.php") && !url.contains("logout.php")) {
                    sessionManager.setLastUrl(url);
                    pdtBridge.commitStagedCredentialsIfPresent();
                }
                injectOnlineEnhancementScript();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request != null && request.isForMainFrame()) {
                    cancelWatchdog();
                    view.stopLoading();
                    fallbackToLocalAppPreserveSession(request.getUrl() != null ? request.getUrl().toString() : "");
                }
            }

            @Override
            public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
                if (request != null && request.isForMainFrame() && errorResponse != null && errorResponse.getStatusCode() >= 502) {
                    cancelWatchdog();
                    view.stopLoading();
                    fallbackToLocalAppPreserveSession(request.getUrl() != null ? request.getUrl().toString() : "");
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                progressBar.setProgress(newProgress);
                if (newProgress >= 100) {
                    progressBar.setVisibility(View.GONE);
                }
            }

            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                for (String res : request.getResources()) {
                    if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(res)) {
                        if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA)
                                == PackageManager.PERMISSION_GRANTED) {
                            request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
                        } else {
                            pendingWebPermissionRequest = request;
                            ActivityCompat.requestPermissions(
                                    MainActivity.this,
                                    new String[]{Manifest.permission.CAMERA},
                                    REQ_CAMERA_PERMISSION
                            );
                        }
                        return;
                    }
                }
                request.deny();
            }
        });
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == REQ_CAMERA_PERMISSION && pendingWebPermissionRequest != null) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                pendingWebPermissionRequest.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
            } else {
                pendingWebPermissionRequest.deny();
            }
            pendingWebPermissionRequest = null;
        }
    }

    // ==================== BOOT & ROUTING ENGINE (ZERO BLANK SCREEN) ====================

    private void loadInitialDestination() {
        boolean online = pdtBridge.isOnline();
        boolean loggedIn = sessionManager.isLoggedIn();

        if (!loggedIn) {
            // Baik Online maupun Offline, muat login.html lokal (1:1 identik dengan login.php) dalam <20ms!
            // Saat Online, login.html memverifikasi langsung ke Supabase & mencatat hash akun ke AuthVaultManager.
            // Saat Offline, login.html memverifikasi dari AuthVaultManager (hanya akun yang pernah login Online).
            loadLocalAssetUrl(OFFLINE_LOGIN_URL);
            return;
        }

        // Pengguna SUDAH LOGIN (terjaga di AuthVaultManager walaupun Cache HP habis dibersihkan!)
        if (!online) {
            // 0ms Offline Instant Boot -> langsung buka GUI lengkap 1:1 tanpa blank screen!
            loadLocalAssetUrl(OFFLINE_DASHBOARD_URL);
            return;
        }

        // Online + Logged In -> tarik sinkronisasi di background & buka halaman Cloud Vercel
        syncManager.syncAll(null);
        String target = sessionManager.getLastUrl();
        String webRoot = getWebRootUrl();
        if (target == null || target.isEmpty() || !target.startsWith(webRoot) || target.contains("login.php")) {
            target = webRoot + getRoleOnlinePage(sessionManager.getRole(), sessionManager.getDeptCode());
        }
        loadCloudUrlWithWatchdog(target);
    }

    private void loadLocalAssetUrl(String assetUrl) {
        cancelWatchdog();
        isOfflinePageLoaded = true;
        progressBar.setVisibility(View.GONE);
        webView.loadUrl(assetUrl);
    }

    private void loadCloudUrlWithWatchdog(String cloudUrl) {
        isOfflinePageLoaded = false;
        startWatchdog();
        webView.loadUrl(cloudUrl);
    }

    private void startWatchdog() {
        cancelWatchdog();
        pageLoadWatchdog = () -> {
            if (!isOfflinePageLoaded && webView != null) {
                webView.stopLoading();
                fallbackToLocalAppPreserveSession("");
            }
        };
        mainHandler.postDelayed(pageLoadWatchdog, WATCHDOG_TIMEOUT_MS);
    }

    private void cancelWatchdog() {
        if (pageLoadWatchdog != null) {
            mainHandler.removeCallbacks(pageLoadWatchdog);
            pageLoadWatchdog = null;
        }
    }

    private boolean isUnauthenticatedLoginRedirect(String url) {
        if (url == null) return false;
        return sessionManager.isLoggedIn()
                && url.contains("login.php")
                && !url.contains("quick_nik=");
    }

    private void triggerSilentCloudSessionRestore() {
        long now = System.currentTimeMillis();
        String nik = sessionManager.getNik();
        if (nik != null && !nik.isEmpty() && pdtBridge.isOnline() && (now - lastSilentRestoreMs > 8000L)) {
            lastSilentRestoreMs = now;
            loadCloudUrlWithWatchdog(getWebRootUrl() + "login.php?quick_nik=" + Uri.encode(nik));
        } else {
            // Jika quick_nik gagal atau sinyal drop, pertahankan sesi di UI lokal tanpa pernah logout!
            loadLocalAssetUrl(OFFLINE_DASHBOARD_URL);
        }
    }

    private void fallbackToLocalAppPreserveSession(String failedUrl) {
        if (sessionManager.isLoggedIn()) {
            String hash = mapOnlineUrlToOfflineHash(failedUrl);
            loadLocalAssetUrl(OFFLINE_DASHBOARD_URL + hash);
        } else {
            loadLocalAssetUrl(OFFLINE_LOGIN_URL);
        }
    }

    private String mapOnlineUrlToOfflineHash(String url) {
        if (url == null || url.isEmpty()) return "";
        if (url.contains("scada.php")) return "#home/scada";
        if (url.contains("scan_terminal.php")) return "#barcode/terminal";
        if (url.contains("barcode_management.php")) return "#barcode/generate";
        if (url.contains("dept_warehouse.php")) return "#barcode/dept-WH";
        if (url.contains("dept_material.php")) return "#barcode/dept-MAT";
        if (url.contains("dept_building.php")) return "#barcode/dept-BLD";
        if (url.contains("dept_curing.php")) return "#barcode/dept-CUR";
        if (url.contains("dept_final_inspection.php")) return "#barcode/dept-FI";
        if (url.contains("operator_lite.php") || url.contains("mobile_barcode.php")) return "#barcode";
        if (url.contains("downtime_monitor.php")) return "#message/downtime";
        if (url.contains("communication.php")) return "#message/broadcast";
        if (url.contains("k3_emergency.php")) return "#message/k3";
        if (url.contains("user_management.php")) return "#akun/users";
        if (url.contains("mobile_account.php")) return "#akun/profile";
        return "";
    }

    private void performExplicitLogout() {
        cancelWatchdog();
        sessionManager.logout();
        CookieManager.getInstance().removeAllCookies(null);
        CookieManager.getInstance().flush();
        Toast.makeText(this, "Berhasil keluar dari sesi akun.", Toast.LENGTH_SHORT).show();
        loadLocalAssetUrl(OFFLINE_LOGIN_URL);
    }

    // ==================== BACKGROUND SYNC & NETWORK MONITORING ====================

    private void triggerFullSync(boolean silent) {
        if (!pdtBridge.isOnline()) {
            if (!silent) {
                Toast.makeText(this, "Perangkat sedang Offline. Data aman tersimpan di memori PDT.", Toast.LENGTH_SHORT).show();
            }
            notifyWebSyncFinished(0, syncManager.getTotalPendingCountSync(), false);
            return;
        }

        if (!silent) {
            Toast.makeText(this, "Menyinkronkan data ke Cloud Supabase...", Toast.LENGTH_SHORT).show();
        }

        syncManager.syncAll(new SyncManager.SyncCallback() {
            @Override
            public void onSyncCompleted(int syncedCount) {
                int remaining = syncManager.getTotalPendingCountSync();
                runOnUiThread(() -> {
                    if (!silent) {
                        String msg = syncedCount > 0
                                ? ("Sukses sinkron " + syncedCount + " data ke Supabase!")
                                : "Seluruh data telah sinkron dengan Cloud Supabase.";
                        Toast.makeText(MainActivity.this, msg, Toast.LENGTH_SHORT).show();
                    }
                    notifyWebSyncFinished(syncedCount, remaining, true);
                });
            }

            @Override
            public void onSyncFailed(String error) {
                int remaining = syncManager.getTotalPendingCountSync();
                runOnUiThread(() -> {
                    if (!silent) {
                        Toast.makeText(MainActivity.this, "Sinkronisasi ditunda: " + error, Toast.LENGTH_SHORT).show();
                    }
                    notifyWebSyncFinished(0, remaining, false);
                });
            }
        });
    }

    private void notifyWebPendingChanged(int pendingCount) {
        if (webView == null) return;
        webView.evaluateJavascript(
                "if(window.updatePendingCount){window.updatePendingCount();} if(window.onNativePendingUpdated){window.onNativePendingUpdated(" + pendingCount + ");}",
                null
        );
    }

    private void notifyWebSyncFinished(int syncedCount, int remainingCount, boolean ok) {
        if (webView == null) return;
        webView.evaluateJavascript(
                "if(window.updatePendingCount){window.updatePendingCount();} if(window.onNativeSyncFinished){window.onNativeSyncFinished("
                        + syncedCount + "," + remainingCount + "," + ok + ");}",
                null
        );
    }

    private void setupNetworkMonitoring() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return;

        NetworkRequest request = new NetworkRequest.Builder()
                .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                .build();

        cm.registerNetworkCallback(request, new ConnectivityManager.NetworkCallback() {
            @Override
            public void onCapabilitiesChanged(@NonNull Network network, @NonNull NetworkCapabilities caps) {
                boolean validated = caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                        && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
                if (validated) {
                    runOnUiThread(() -> {
                        // Otomatis kirim antrean offline ke Supabase di background tanpa merusak form yang sedang diketik!
                        triggerFullSync(true);
                        if (webView != null) {
                            webView.evaluateJavascript("if(window.onNativeNetworkStateChanged){window.onNativeNetworkStateChanged(true);}", null);
                        }
                    });
                }
            }

            @Override
            public void onLost(@NonNull Network network) {
                runOnUiThread(() -> {
                    if (webView != null) {
                        webView.evaluateJavascript("if(window.onNativeNetworkStateChanged){window.onNativeNetworkStateChanged(false);}", null);
                    }
                });
            }
        });
    }

    private void injectOnlineEnhancementScript() {
        String js = "(function(){"
                + "if(window.__gtbsNativeEnhanced) return;"
                + "window.__gtbsNativeEnhanced = true;"
                // Tangkap form login web jika user membuka login.php secara langsung
                + "var lf = document.getElementById('loginForm');"
                + "if(lf){"
                + "  lf.addEventListener('submit', function(){"
                + "    var u = document.getElementById('usernameInput');"
                + "    var p = document.getElementById('passwordInput');"
                + "    if(u && p && window.AndroidPDT){ window.AndroidPDT.stageWebLoginCredentials(u.value, p.value); }"
                + "  });"
                + "}"
                // Intercept form scan saat sinyal mendadak terputus di tengah halaman web online
                + "document.addEventListener('submit', function(ev){"
                + "  var f = ev.target;"
                + "  if(!f || f.id==='loginForm') return;"
                + "  if(!navigator.onLine && window.AndroidPDT){"
                + "    var bcInput = f.querySelector('input[name*=\"barcode\"], #barcodeScannerInput');"
                + "    if(bcInput && bcInput.value){"
                + "      ev.preventDefault();"
                + "      window.AndroidPDT.saveOfflineScan(bcInput.value, document.title || 'WEB_OFFLINE', 'Auto-Intercept Offline Form', 1, 'PASS');"
                + "      bcInput.value='';"
                + "      if(window.showMobileToast) window.showMobileToast('Offline: Scan disimpan aman di memori PDT!', 'warning');"
                + "      if(window.updatePendingCount) setTimeout(window.updatePendingCount, 200);"
                + "    }"
                + "  }"
                + "}, true);"
                + "})();";
        webView.evaluateJavascript(js, null);
    }

    private void showServerConfigDialog() {
        String currentUrl = prefs.getString(KEY_SERVER_URL, DEFAULT_CLOUD_URL);

        LinearLayout container = new LinearLayout(this);
        container.setOrientation(LinearLayout.VERTICAL);
        container.setPadding(48, 24, 48, 16);

        TextView tvDesc = new TextView(this);
        tvDesc.setText("Server Utama menggunakan Cloud Vercel + Supabase agar berjalan 24/7 tanpa memerlukan PC lokal hidup:");
        tvDesc.setPadding(0, 0, 0, 16);
        container.addView(tvDesc);

        EditText etUrl = new EditText(this);
        etUrl.setText(currentUrl);
        etUrl.setHint(DEFAULT_CLOUD_URL);
        container.addView(etUrl);

        new AlertDialog.Builder(this)
                .setTitle("Konfigurasi Cloud Server")
                .setView(container)
                .setPositiveButton("Simpan", (dialog, which) -> {
                    String newUrl = etUrl.getText().toString().trim();
                    if (!newUrl.isEmpty()) {
                        prefs.edit().putString(KEY_SERVER_URL, newUrl).putBoolean(KEY_URL_MIGRATED_CLOUD, true).apply();
                        ApiClient.syncFromWebUrl(this, newUrl);
                        Toast.makeText(this, "Server diperbarui: " + ApiClient.getBaseUrl(), Toast.LENGTH_SHORT).show();
                        loadInitialDestination();
                    }
                })
                .setNeutralButton("Reset Cloud (Vercel)", (dialog, which) -> {
                    prefs.edit().putString(KEY_SERVER_URL, DEFAULT_CLOUD_URL).putBoolean(KEY_URL_MIGRATED_CLOUD, true).apply();
                    ApiClient.syncFromWebUrl(this, DEFAULT_CLOUD_URL);
                    Toast.makeText(this, "Kembali ke Server Cloud Vercel + Supabase", Toast.LENGTH_SHORT).show();
                    loadInitialDestination();
                })
                .setNegativeButton("Batal", null)
                .show();
    }

    private String escapeJsSingle(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("'", "\\'").replace("\n", " ").replace("\r", "");
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
        if (keyCode == KeyEvent.KEYCODE_BACK && webView != null) {
            if (isOfflinePageLoaded) {
                webView.evaluateJavascript(
                        "(function(){ return window.handleOfflineBack ? window.handleOfflineBack() : false; })()",
                        val -> {
                            if (!"true".equals(val)) {
                                if (webView.canGoBack()) webView.goBack();
                                else finish();
                            }
                        }
                );
                return true;
            } else if (webView.canGoBack()) {
                webView.goBack();
                return true;
            }
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onDestroy() {
        cancelWatchdog();
        super.onDestroy();
    }
}
