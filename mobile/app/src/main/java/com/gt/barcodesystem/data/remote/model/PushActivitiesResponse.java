package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

import java.util.List;

public class PushActivitiesResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("message")
    private String message;

    @SerializedName("synced_local_ids")
    private List<Long> syncedLocalIds;

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public String getMessage() {
        return message;
    }

    public void setMessage(String message) {
        this.message = message;
    }

    public List<Long> getSyncedLocalIds() {
        return syncedLocalIds;
    }

    public void setSyncedLocalIds(List<Long> syncedLocalIds) {
        this.syncedLocalIds = syncedLocalIds;
    }
}
