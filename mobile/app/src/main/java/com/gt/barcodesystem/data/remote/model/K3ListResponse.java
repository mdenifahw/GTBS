package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;

import java.util.List;

public class K3ListResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("data")
    private List<K3CallEntity> data;

    public String getStatus() {
        return status;
    }

    public List<K3CallEntity> getData() {
        return data;
    }
}
