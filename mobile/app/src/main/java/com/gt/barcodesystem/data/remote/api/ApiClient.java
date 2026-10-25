package com.gt.barcodesystem.data.remote.api;

import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

import java.util.concurrent.TimeUnit;

public class ApiClient {

    public static final String DEFAULT_BASE_URL = "http://10.0.2.2:8000/GT_Barcode_System/api/";

    private static Retrofit retrofit = null;
    private static String currentBaseUrl = DEFAULT_BASE_URL;

    public static ApiService getApiService() {
        return getClient(currentBaseUrl).create(ApiService.class);
    }

    public static void setBaseUrl(String baseUrl) {
        if (baseUrl != null && !baseUrl.endsWith("/")) {
            baseUrl += "/";
        }
        currentBaseUrl = baseUrl;
        retrofit = null; // Recreate Retrofit instance when base URL changes
    }

    public static String getBaseUrl() {
        return currentBaseUrl;
    }

    private static Retrofit getClient(String baseUrl) {
        if (retrofit == null) {
            HttpLoggingInterceptor interceptor = new HttpLoggingInterceptor();
            interceptor.setLevel(HttpLoggingInterceptor.Level.BODY);

            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(interceptor)
                    .connectTimeout(15, TimeUnit.SECONDS)
                    .readTimeout(15, TimeUnit.SECONDS)
                    .writeTimeout(15, TimeUnit.SECONDS)
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
