package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class DashboardSummaryResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("total_produced_today")
    private int totalProducedToday;

    @SerializedName("kpi_target_percentage")
    private double kpiTargetPercentage;

    @SerializedName("active_k3_incidents")
    private int activeK3Incidents;

    @SerializedName("active_downtime_machines")
    private int activeDowntimeMachines;

    @SerializedName("shift1_count")
    private int shift1Count;

    @SerializedName("shift2_count")
    private int shift2Count;

    @SerializedName("shift3_count")
    private int shift3Count;

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public int getTotalProducedToday() {
        return totalProducedToday;
    }

    public void setTotalProducedToday(int totalProducedToday) {
        this.totalProducedToday = totalProducedToday;
    }

    public double getKpiTargetPercentage() {
        return kpiTargetPercentage;
    }

    public void setKpiTargetPercentage(double kpiTargetPercentage) {
        this.kpiTargetPercentage = kpiTargetPercentage;
    }

    public int getActiveK3Incidents() {
        return activeK3Incidents;
    }

    public void setActiveK3Incidents(int activeK3Incidents) {
        this.activeK3Incidents = activeK3Incidents;
    }

    public int getActiveDowntimeMachines() {
        return activeDowntimeMachines;
    }

    public void setActiveDowntimeMachines(int activeDowntimeMachines) {
        this.activeDowntimeMachines = activeDowntimeMachines;
    }

    public int getShift1Count() {
        return shift1Count;
    }

    public void setShift1Count(int shift1Count) {
        this.shift1Count = shift1Count;
    }

    public int getShift2Count() {
        return shift2Count;
    }

    public void setShift2Count(int shift2Count) {
        this.shift2Count = shift2Count;
    }

    public int getShift3Count() {
        return shift3Count;
    }

    public void setShift3Count(int shift3Count) {
        this.shift3Count = shift3Count;
    }
}
