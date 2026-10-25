package com.gt.barcodesystem.util;

import android.content.Context;
import android.content.SharedPreferences;

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
    private static final String KEY_SECTION_ID = "sectionId";
    private static final String KEY_STATION_CODE = "stationCode";

    private final SharedPreferences pref;
    private final SharedPreferences.Editor editor;

    public SessionManager(Context context) {
        pref = context.getApplicationContext().getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        editor = pref.edit();
    }

    public void saveUserSession(String token, int userId, String nik, String username, String fullName, String role, int deptId, int sectionId) {
        editor.putBoolean(KEY_IS_LOGGED_IN, true);
        editor.putString(KEY_TOKEN, token);
        editor.putInt(KEY_USER_ID, userId);
        editor.putString(KEY_NIK, nik);
        editor.putString(KEY_USERNAME, username);
        editor.putString(KEY_FULL_NAME, fullName);
        editor.putString(KEY_ROLE, role);
        editor.putInt(KEY_DEPT_ID, deptId);
        editor.putInt(KEY_SECTION_ID, sectionId);
        editor.apply();
    }

    public boolean isLoggedIn() {
        return pref.getBoolean(KEY_IS_LOGGED_IN, false);
    }

    public String getToken() {
        return pref.getString(KEY_TOKEN, "");
    }

    public int getUserId() {
        return pref.getInt(KEY_USER_ID, 0);
    }

    public String getNik() {
        return pref.getString(KEY_NIK, "");
    }

    public String getUsername() {
        return pref.getString(KEY_USERNAME, "");
    }

    public String getFullName() {
        return pref.getString(KEY_FULL_NAME, "");
    }

    public String getRole() {
        return pref.getString(KEY_ROLE, "");
    }

    public int getDeptId() {
        return pref.getInt(KEY_DEPT_ID, 0);
    }

    public int getSectionId() {
        return pref.getInt(KEY_SECTION_ID, 0);
    }

    public String getStationCode() {
        return pref.getString(KEY_STATION_CODE, "MIXING");
    }

    public void setStationCode(String stationCode) {
        editor.putString(KEY_STATION_CODE, stationCode);
        editor.apply();
    }

    public void logout() {
        editor.clear();
        editor.apply();
    }
}
