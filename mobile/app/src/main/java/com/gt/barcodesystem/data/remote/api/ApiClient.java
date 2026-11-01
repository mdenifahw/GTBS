package com.gt.barcodesystem.data.remote.api;

import android.content.Context;
import android.content.SharedPreferences;

import java.util.concurrent.TimeUnit;

import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

public class ApiClient {

    public static final String CLOUD_WEB_ROOT = "https://gtbarcodesystem.vercel.app/";
    public static final String DEFAULT_BASE_URL = "https://gtbarcodesystem.vercel.app/api/";
    private static final String PREFS_NAME = "GTBS_API_PREFS";
    private static final String KEY_API_URL = "API_BASE_URL";

    private static Retrofit retrofit = null;
    private static String currentBaseUrl = DEFAULT_BASE_URL;

    public static synchronized void init(Context context) {
        if (context == null) return;
        SharedPreferences prefs = context.getApplicationContext().getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        String saved = prefs.getString(KEY_API_URL, DEFAULT_BASE_URL);
        if (saved == null || saved.isEmpty() || saved.contains("192.168.") || saved.contains("10.0.2.2") || saved.contains("localhost")) {
            saved = DEFAULT_BASE_URL;
            prefs.edit().putString(KEY_API_URL, saved).apply();
        }
        setBaseUrl(saved);
    }

    public static synchronized ApiService getApiService() {
        return getClient(currentBaseUrl).create(ApiService.class);
    }

    public static synchronized void setBaseUrl(String baseUrl) {
        if (baseUrl == null || baseUrl.trim().isEmpty()) {
            baseUrl = DEFAULT_BASE_URL;
        }
        baseUrl = baseUrl.trim();
        if (!baseUrl.endsWith("/")) {
            baseUrl += "/";
        }
        if (!baseUrl.equals(currentBaseUrl)) {
            currentBaseUrl = baseUrl;
            retrofit = null;
        }
    }

    public static synchronized void syncFromWebUrl(String webUrl) {
        setBaseUrl(deriveApiUrlFromWebUrl(webUrl));
    }

    public static synchronized void syncFromWebUrl(Context context, String webUrl) {
        String api = deriveApiUrlFromWebUrl(webUrl);
        setBaseUrl(api);
        if (context != null) {
            context.getApplicationContext()
                    .getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                    .edit()
                    .putString(KEY_API_URL, api)
                    .apply();
        }
    }

    public static String deriveApiUrlFromWebUrl(String webUrl) {
        if (webUrl == null || webUrl.trim().isEmpty()) {
            return DEFAULT_BASE_URL;
        }
        String u = webUrl.trim();
        int qIdx = u.indexOf('?');
        if (qIdx != -1) {
            u = u.substring(0, qIdx);
        }
        if (u.endsWith(".php") || u.endsWith(".html")) {
            int slash = u.lastIndexOf('/');
            if (slash > 8) {
                u = u.substring(0, slash + 1);
            }
        }
        if (!u.endsWith("/")) {
            u += "/";
        }
        if (!u.endsWith("api/")) {
            u += "api/";
        }
        return u;
    }

    public static String getBaseUrl() {
        return currentBaseUrl;
    }

    private static Retrofit getClient(String baseUrl) {
        if (retrofit == null) {
            HttpLoggingInterceptor interceptor = new HttpLoggingInterceptor();
            interceptor.setLevel(HttpLoggingInterceptor.Level.BASIC);

            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(interceptor)
                    .connectTimeout(15, TimeUnit.SECONDS)
                    .readTimeout(20, TimeUnit.SECONDS)
                    .writeTimeout(20, TimeUnit.SECONDS)
                    .retryOnConnectionFailure(true)
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(baseUrl)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build();
        }
        return retrofit;
    }
}
