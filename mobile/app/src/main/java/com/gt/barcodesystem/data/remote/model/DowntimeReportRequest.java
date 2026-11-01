package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class DowntimeReportRequest {
    @SerializedName("machine_code")
    private String machineCode;

    @SerializedName("dept_id")
    private int deptId;

    @SerializedName("downtime_type")
    private String downtimeType;

    @SerializedName("duration_minutes")
    private int durationMinutes;

    @SerializedName("description")
    private String description;

    @SerializedName("reported_by")
    private String reportedBy;

    @SerializedName("operator_nik")
    private String operatorNik;

    public DowntimeReportRequest(String machineCode, int deptId, String downtimeType, int durationMinutes, String description, int reportedBy) {
        this.machineCode = machineCode;
        this.deptId = deptId;
        this.downtimeType = downtimeType;
        this.durationMinutes = durationMinutes;
        this.description = description;
        this.reportedBy = String.valueOf(reportedBy);
        this.operatorNik = "OP-" + reportedBy;
    }

    public DowntimeReportRequest(String machineCode, int deptId, String downtimeType, int durationMinutes, String description, String operatorNik) {
        this.machineCode = machineCode;
        this.deptId = deptId;
        this.downtimeType = downtimeType;
        this.durationMinutes = durationMinutes;
        this.description = description;
        this.reportedBy = operatorNik != null && !operatorNik.isEmpty() ? operatorNik : "OPERATOR";
        this.operatorNik = this.reportedBy;
    }

    public String getMachineCode() {
        return machineCode;
    }

    public int getDeptId() {
        return deptId;
    }

    public String getDowntimeType() {
        return downtimeType;
    }

    public int getDurationMinutes() {
        return durationMinutes;
    }

    public String getDescription() {
        return description;
    }

    public String getReportedBy() {
        return reportedBy;
    }

    public String getOperatorNik() {
        return operatorNik;
    }
}
