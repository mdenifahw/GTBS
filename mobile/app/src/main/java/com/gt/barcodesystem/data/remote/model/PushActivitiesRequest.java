package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;

import java.util.List;

public class PushActivitiesRequest {
    @SerializedName("items")
    private List<OfflineScanEntity> items;

    public PushActivitiesRequest(List<OfflineScanEntity> items) {
        this.items = items;
    }

    public List<OfflineScanEntity> getItems() {
        return items;
    }

    public void setItems(List<OfflineScanEntity> items) {
        this.items = items;
    }
}
