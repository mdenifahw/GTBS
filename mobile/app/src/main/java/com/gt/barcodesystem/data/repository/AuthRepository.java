package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.LoginRequest;
import com.gt.barcodesystem.data.remote.model.LoginResponse;
import com.gt.barcodesystem.util.AuthVaultManager;
import com.gt.barcodesystem.util.SessionManager;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class AuthRepository {

    private final Context appContext;
    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final AuthVaultManager authVault;
    private final ExecutorService executor;

    public interface AuthCallback {
        void onSuccess(LoginResponse response);
        void onError(String errorMessage);
    }

    public AuthRepository(Context context) {
        this.appContext = context.getApplicationContext();
        this.db = AppDatabase.getInstance(appContext);
        this.sessionManager = new SessionManager(appContext);
        this.authVault = AuthVaultManager.getInstance(appContext);
        this.executor = Executors.newSingleThreadExecutor();
    }

    public void login(String username, String password, AuthCallback callback) {
        ApiService apiService = ApiClient.getApiService();
        LoginRequest request = new LoginRequest(username, password);
        apiService.login(request).enqueue(new Callback<LoginResponse>() {
            @Override
            public void onResponse(Call<LoginResponse> call, Response<LoginResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    LoginResponse loginRes = response.body();
                    if (loginRes.isSuccess() && loginRes.getUser() != null) {
                        UserEntity user = loginRes.getUser();
                        authVault.saveOnlineVerifiedLogin(user, password, loginRes.getToken());
                        sessionManager.saveFromUserEntity(loginRes.getToken(), user);
                        executor.execute(() -> db.userDao().insertUser(user));
                        callback.onSuccess(loginRes);
                    } else {
                        callback.onError(loginRes.getMessage() != null ? loginRes.getMessage() : "Kredensial tidak valid.");
                    }
                } else {
                    if (response.code() == 400 || response.code() == 401 || response.code() == 403) {
                        callback.onError("NIK/Username atau Password tidak valid.");
                    } else {
                        attemptOfflineLogin(username, password, callback);
                    }
                }
            }

            @Override
            public void onFailure(Call<LoginResponse> call, Throwable t) {
                attemptOfflineLogin(username, password, callback);
            }
        });
    }

    private void attemptOfflineLogin(String username, String password, AuthCallback callback) {
        executor.execute(() -> {
            AuthVaultManager.OfflineLoginResult result = authVault.verifyOfflineLogin(username, password);
            if (!result.success) {
                callback.onError(result.message);
                return;
            }
            UserEntity user = result.user;
            sessionManager.saveFromUserEntity("OFFLINE_VERIFIED_TOKEN", user);

            LoginResponse res = new LoginResponse();
            res.setStatus("success");
            res.setMessage("Login Offline Berhasil (Akun Terverifikasi).");
            res.setUser(user);
            res.setToken("OFFLINE_VERIFIED_TOKEN");
            callback.onSuccess(res);
        });
    }
}
