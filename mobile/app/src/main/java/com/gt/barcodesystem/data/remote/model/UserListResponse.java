package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.UserEntity;

import java.util.List;

public class UserListResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("users")
    private List<UserEntity> users;

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public List<UserEntity> getUsers() {
        return users;
    }

    public void setUsers(List<UserEntity> users) {
        this.users = users;
    }
}
