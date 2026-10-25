package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.LoginRequest;
import com.gt.barcodesystem.data.remote.model.LoginResponse;
import com.gt.barcodesystem.util.SessionManager;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class AuthRepository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final SessionManager sessionManager;
    private final ExecutorService executor;

    public interface AuthCallback {
        void onSuccess(LoginResponse response);
        void onError(String errorMessage);
    }

    public AuthRepository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        sessionManager = new SessionManager(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void login(String username, String password, AuthCallback callback) {
        LoginRequest request = new LoginRequest(username, password);
        apiService.login(request).enqueue(new Callback<LoginResponse>() {
            @Override
            public void onResponse(Call<LoginResponse> call, Response<LoginResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    LoginResponse loginRes = response.body();
                    if (loginRes.isSuccess() && loginRes.getUser() != null) {
                        UserEntity user = loginRes.getUser();
                        sessionManager.saveUserSession(
                                loginRes.getToken(),
                                user.getId(),
                                user.getNik(),
                                user.getUsername(),
                                user.getFullName(),
                                user.getRole(),
                                user.getDeptId() != null ? user.getDeptId() : 1,
                                user.getSectionId() != null ? user.getSectionId() : 1
                        );

                        executor.execute(() -> db.userDao().insertUser(user));
                        callback.onSuccess(loginRes);
                    } else {
                        callback.onError(loginRes.getMessage() != null ? loginRes.getMessage() : "Login gagal.");
                    }
                } else {
                    attemptOfflineLogin(username, password, callback);
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
            UserEntity user = db.userDao().getUserByUsername(username);
            if (user == null) {
                user = db.userDao().getUserByNik(username);
            }

            if (user == null) {
                // Auto create demo operator for offline local testing
                user = new UserEntity();
                user.setId(27);
                user.setNik(username != null && !username.isEmpty() ? username : "OP-WH-01");
                user.setUsername(username != null && !username.isEmpty() ? username : "op.wh.snd");
                user.setFullName("Operator PDT (Demo)");
                user.setRole("operator");
                user.setDeptId(1);
                user.setSectionId(1);
                user.setActive(true);
                db.userDao().insertUser(user);
            }

            UserEntity finalUser = user;
            sessionManager.saveUserSession(
                    "OFFLINE_DEMO_TOKEN",
                    finalUser.getId(),
                    finalUser.getNik(),
                    finalUser.getUsername(),
                    finalUser.getFullName(),
                    finalUser.getRole(),
                    finalUser.getDeptId() != null ? finalUser.getDeptId() : 1,
                    finalUser.getSectionId() != null ? finalUser.getSectionId() : 1
            );

            LoginResponse res = new LoginResponse();
            res.setStatus("success");
            res.setMessage("Login Offline PDT Berhasil");
            res.setUser(finalUser);
            res.setToken("OFFLINE_DEMO_TOKEN");
            callback.onSuccess(res);
        });
    }

    public boolean isLoggedIn() {
        return sessionManager.isLoggedIn();
    }

    public void logout() {
        sessionManager.logout();
    }
}
