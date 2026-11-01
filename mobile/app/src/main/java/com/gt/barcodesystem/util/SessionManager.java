package com.gt.barcodesystem.util;

import android.content.Context;
import android.content.SharedPreferences;

import com.gt.barcodesystem.data.local.entity.UserEntity;

/**
 * SessionManager: Antarmuka sesi pengguna yang terhubung langsung dengan AuthVaultManager
 * sehingga kebal terhadap pembersihan Cache HP (Clear Cache) maupun reset cookie WebView.
 */
public class SessionManager {

    private static final String PREF_NAME = "GT_PDT_Session";
    private static final String KEY_IS_LOGGED_IN = "isLoggedIn";
    private static final String KEY_TOKEN = "authToken";
    private static final String KEY_USER_ID = "userId";
    private static final String KEY_NIK = "nik";
    private static final String KEY_USERNAME = "username";
    private static final String KEY_FULL_NAME = "fullName";
    private static final String KEY_ROLE = "role";
    private static final String KEY_DEPT_ID = "deptId";
    private static final String KEY_DEPT_CODE = "deptCode";
    private static final String KEY_DEPT_NAME = "deptName";
    private static final String KEY_SECTION_ID = "sectionId";
    private static final String KEY_SECTION_CODE = "sectionCode";
    private static final String KEY_SECTION_NAME = "sectionName";
    private static final String KEY_STATION_CODE = "stationCode";
    private static final String KEY_LAST_URL = "lastVisitedUrl";

    private final SharedPreferences pref;
    private final AuthVaultManager vault;

    public SessionManager(Context context) {
        Context appCtx = context.getApplicationContext();
        this.pref = appCtx.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        this.vault = AuthVaultManager.getInstance(appCtx);
        selfHealPreferencesFromVault();
    }

    private void selfHealPreferencesFromVault() {
        if (!pref.getBoolean(KEY_IS_LOGGED_IN, false) && vault.isLoggedIn()) {
            AuthVaultManager.ActiveSession s = vault.getActiveSession();
            writePrefs(s);
        }
    }

    private void writePrefs(AuthVaultManager.ActiveSession s) {
        pref.edit()
                .putBoolean(KEY_IS_LOGGED_IN, s.isLoggedIn)
                .putString(KEY_TOKEN, s.token != null ? s.token : "")
                .putInt(KEY_USER_ID, s.userId)
                .putString(KEY_NIK, s.nik != null ? s.nik : "")
                .putString(KEY_USERNAME, s.username != null ? s.username : "")
                .putString(KEY_FULL_NAME, s.fullName != null ? s.fullName : "")
                .putString(KEY_ROLE, s.role != null ? s.role : "")
                .putInt(KEY_DEPT_ID, s.deptId)
                .putString(KEY_DEPT_CODE, s.deptCode != null ? s.deptCode : "")
                .putString(KEY_DEPT_NAME, s.deptName != null ? s.deptName : "")
                .putInt(KEY_SECTION_ID, s.sectionId)
                .putString(KEY_SECTION_CODE, s.sectionCode != null ? s.sectionCode : "")
                .putString(KEY_SECTION_NAME, s.sectionName != null ? s.sectionName : "")
                .putString(KEY_STATION_CODE, s.stationCode != null && !s.stationCode.isEmpty() ? s.stationCode : "CURING")
                .putString(KEY_LAST_URL, s.lastUrl != null ? s.lastUrl : "")
                .commit();
    }

    public void saveFromUserEntity(String token, UserEntity user) {
        if (user == null) return;
        AuthVaultManager.enrichUserEntityLabels(user);
        AuthVaultManager.ActiveSession s = vault.getActiveSession();
        s.isLoggedIn = true;
        s.token = token != null ? token : "";
        s.userId = user.getId() > 0 ? user.getId() : 1;
        s.nik = user.getNik() != null ? user.getNik() : "";
        s.username = user.getUsername() != null ? user.getUsername() : "";
        s.fullName = user.getFullName() != null ? user.getFullName() : "";
        s.role = user.getRole() != null ? user.getRole() : "operator";
        s.deptId = user.getDeptId() != null ? user.getDeptId() : 0;
        s.deptCode = user.getDeptCode() != null ? user.getDeptCode() : "";
        s.deptName = user.getDeptName() != null ? user.getDeptName() : "";
        s.sectionId = user.getSectionId() != null ? user.getSectionId() : 0;
        s.sectionCode = user.getSectionCode() != null ? user.getSectionCode() : "";
        s.sectionName = user.getSectionName() != null ? user.getSectionName() : "";
        if (!s.deptCode.isEmpty()) {
            s.stationCode = AuthVaultManager.mapDeptCodeToStation(s.deptCode);
        }
        vault.saveActiveSession(s);
        writePrefs(s);
    }

    public void saveUserSession(String token, int userId, String nik, String username, String fullName, String role, int deptId, int sectionId) {
        AuthVaultManager.ActiveSession s = vault.getActiveSession();
        s.isLoggedIn = true;
        s.token = token != null ? token : "";
        s.userId = userId > 0 ? userId : 1;
        s.nik = nik != null ? nik : "";
        s.username = username != null ? username : "";
        s.fullName = fullName != null ? fullName : "";
        s.role = role != null ? role : "";
        s.deptId = deptId;
        s.sectionId = sectionId;
        s.deptCode = AuthVaultManager.deptIdToCode(deptId);
        s.deptName = AuthVaultManager.deptIdToName(deptId);
        s.sectionCode = AuthVaultManager.sectionIdToCode(sectionId);
        s.sectionName = AuthVaultManager.sectionIdToName(sectionId);
        vault.saveActiveSession(s);
        writePrefs(s);
    }

    public boolean isLoggedIn() {
        selfHealPreferencesFromVault();
        return vault.isLoggedIn() || pref.getBoolean(KEY_IS_LOGGED_IN, false);
    }

    public String getToken() {
        selfHealPreferencesFromVault();
        return vault.getActiveSession().token;
    }

    public int getUserId() {
        selfHealPreferencesFromVault();
        int id = vault.getActiveSession().userId;
        return id > 0 ? id : pref.getInt(KEY_USER_ID, 0);
    }

    public String getNik() {
        selfHealPreferencesFromVault();
        String nik = vault.getActiveSession().nik;
        return (nik != null && !nik.isEmpty()) ? nik : pref.getString(KEY_NIK, "");
    }

    public String getUsername() {
        selfHealPreferencesFromVault();
        String u = vault.getActiveSession().username;
        return (u != null && !u.isEmpty()) ? u : pref.getString(KEY_USERNAME, "");
    }

    public String getFullName() {
        selfHealPreferencesFromVault();
        String fn = vault.getActiveSession().fullName;
        return (fn != null && !fn.isEmpty()) ? fn : pref.getString(KEY_FULL_NAME, "");
    }

    public String getRole() {
        selfHealPreferencesFromVault();
        String r = vault.getActiveSession().role;
        return (r != null && !r.isEmpty()) ? r : pref.getString(KEY_ROLE, "");
    }

    public int getDeptId() {
        selfHealPreferencesFromVault();
        int d = vault.getActiveSession().deptId;
        return d > 0 ? d : pref.getInt(KEY_DEPT_ID, 0);
    }

    public String getDeptCode() {
        selfHealPreferencesFromVault();
        String c = vault.getActiveSession().deptCode;
        if (c != null && !c.isEmpty()) return c;
        return AuthVaultManager.deptIdToCode(getDeptId());
    }

    public String getDeptName() {
        selfHealPreferencesFromVault();
        String n = vault.getActiveSession().deptName;
        if (n != null && !n.isEmpty()) return n;
        return AuthVaultManager.deptIdToName(getDeptId());
    }

    public int getSectionId() {
        selfHealPreferencesFromVault();
        int s = vault.getActiveSession().sectionId;
        return s > 0 ? s : pref.getInt(KEY_SECTION_ID, 0);
    }

    public String getSectionCode() {
        selfHealPreferencesFromVault();
        String c = vault.getActiveSession().sectionCode;
        if (c != null && !c.isEmpty()) return c;
        return AuthVaultManager.sectionIdToCode(getSectionId());
    }

    public String getSectionName() {
        selfHealPreferencesFromVault();
        String n = vault.getActiveSession().sectionName;
        if (n != null && !n.isEmpty()) return n;
        return AuthVaultManager.sectionIdToName(getSectionId());
    }

    public String getStationCode() {
        selfHealPreferencesFromVault();
        String sc = vault.getActiveSession().stationCode;
        return (sc != null && !sc.isEmpty()) ? sc : pref.getString(KEY_STATION_CODE, "CURING");
    }

    public void setStationCode(String stationCode) {
        if (stationCode == null || stationCode.isEmpty()) return;
        AuthVaultManager.ActiveSession s = vault.getActiveSession();
        s.stationCode = stationCode;
        vault.saveActiveSession(s);
        pref.edit().putString(KEY_STATION_CODE, stationCode).apply();
    }

    public String getLastUrl() {
        selfHealPreferencesFromVault();
        String u = vault.getActiveSession().lastUrl;
        return (u != null && !u.isEmpty()) ? u : pref.getString(KEY_LAST_URL, "");
    }

    public void setLastUrl(String url) {
        if (url != null && !url.contains("login.php") && !url.contains("logout.php") && !url.startsWith("file:///")) {
            AuthVaultManager.ActiveSession s = vault.getActiveSession();
            s.lastUrl = url;
            vault.saveActiveSession(s);
            pref.edit().putString(KEY_LAST_URL, url).apply();
        }
    }

    public void logout() {
        vault.clearActiveSessionOnExplicitLogout();
        pref.edit().clear().commit();
    }
}
