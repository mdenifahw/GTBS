package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class K3CallRequest {
    @SerializedName("caller_id")
    private int callerId;

    @SerializedName("caller_name")
    private String callerName;

    @SerializedName("dept_id")
    private int deptId;

    @SerializedName("location")
    private String location;

    @SerializedName("condition")
    private String condition;

    @SerializedName("priority")
    private String priority;

    public K3CallRequest(int callerId, String callerName, int deptId, String location, String condition, String priority) {
        this.callerId = callerId;
        this.callerName = callerName;
        this.deptId = deptId;
        this.location = location;
        this.condition = condition;
        this.priority = priority;
    }

    public int getCallerId() {
        return callerId;
    }

    public String getCallerName() {
        return callerName;
    }

    public int getDeptId() {
        return deptId;
    }

    public String getLocation() {
        return location;
    }

    public String getCondition() {
        return condition;
    }

    public String getPriority() {
        return priority;
    }
}
