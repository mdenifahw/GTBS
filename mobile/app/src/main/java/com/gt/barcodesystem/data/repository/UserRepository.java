package com.gt.barcodesystem.data.repository;

import android.content.Context;

import com.gt.barcodesystem.data.local.AppDatabase;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.api.ApiService;
import com.gt.barcodesystem.data.remote.model.ApiResponse;
import com.gt.barcodesystem.data.remote.model.UserListResponse;
import com.gt.barcodesystem.data.remote.model.UserSaveRequest;

import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class UserRepository {

    private final ApiService apiService;
    private final AppDatabase db;
    private final ExecutorService executor;

    public interface UserListCallback {
        void onUsersLoaded(List<UserEntity> users);
        void onError(String error);
    }

    public interface UserSaveCallback {
        void onSuccess(String message);
        void onError(String error);
    }

    public UserRepository(Context context) {
        apiService = ApiClient.getApiService();
        db = AppDatabase.getInstance(context);
        executor = Executors.newSingleThreadExecutor();
    }

    public void fetchUsers(UserListCallback callback) {
        apiService.getUsers().enqueue(new Callback<UserListResponse>() {
            @Override
            public void onResponse(Call<UserListResponse> call, Response<UserListResponse> response) {
                if (response.isSuccessful() && response.body() != null && response.body().getUsers() != null) {
                    List<UserEntity> remoteUsers = response.body().getUsers();
                    executor.execute(() -> db.userDao().insertAllUsers(remoteUsers));
                    if (callback != null) callback.onUsersLoaded(remoteUsers);
                } else {
                    loadUsersFromLocalDb(callback);
                }
            }

            @Override
            public void onFailure(Call<UserListResponse> call, Throwable t) {
                loadUsersFromLocalDb(callback);
            }
        });
    }

    private void loadUsersFromLocalDb(UserListCallback callback) {
        executor.execute(() -> {
            List<UserEntity> localUsers = db.userDao().getAllUsers();
            if (callback != null) callback.onUsersLoaded(localUsers);
        });
    }

    public void saveUser(UserSaveRequest request, UserSaveCallback callback) {
        apiService.saveUser(request).enqueue(new Callback<ApiResponse<Void>>() {
            @Override
            public void onResponse(Call<ApiResponse<Void>> call, Response<ApiResponse<Void>> response) {
                if (response.isSuccessful()) {
                    executor.execute(() -> {
                        UserEntity user = new UserEntity();
                        if (request.getId() != null) user.setId(request.getId());
                        user.setNik(request.getNik());
                        user.setUsername(request.getUsername());
                        user.setFullName(request.getFullName());
                        user.setRole(request.getRole());
                        user.setDeptId(request.getDeptId());
                        user.setSectionId(request.getSectionId());
                        user.setPhone(request.getPhone());
                        user.setActive(true);
                        db.userDao().insertUser(user);
                    });
                    if (callback != null) callback.onSuccess("Data karyawan berhasil disimpan.");
                } else {
                    saveUserOffline(request, callback);
                }
            }

            @Override
            public void onFailure(Call<ApiResponse<Void>> call, Throwable t) {
                saveUserOffline(request, callback);
            }
        });
    }

    private void saveUserOffline(UserSaveRequest request, UserSaveCallback callback) {
        executor.execute(() -> {
            UserEntity user = new UserEntity();
            user.setId(request.getId() != null ? request.getId() : (int) (System.currentTimeMillis() / 1000));
            user.setNik(request.getNik());
            user.setUsername(request.getUsername());
            user.setFullName(request.getFullName());
            user.setRole(request.getRole());
            user.setDeptId(request.getDeptId());
            user.setSectionId(request.getSectionId());
            user.setPhone(request.getPhone());
            user.setActive(true);
            db.userDao().insertUser(user);
            if (callback != null) callback.onSuccess("Data karyawan tersimpan secara offline di PDT.");
        });
    }
}
