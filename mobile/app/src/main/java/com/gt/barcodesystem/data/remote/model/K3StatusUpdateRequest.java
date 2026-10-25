package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class K3StatusUpdateRequest {
    @SerializedName("call_id")
    private long callId;

    @SerializedName("status")
    private String status;

    public K3StatusUpdateRequest(long callId, String status) {
        this.callId = callId;
        this.status = status;
    }

    public long getCallId() {
        return callId;
    }

    public String getStatus() {
        return status;
    }
}
