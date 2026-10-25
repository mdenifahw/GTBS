package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

@Entity(tableName = "k3_ambulance_calls")
public class K3CallEntity {

    @PrimaryKey(autoGenerate = true)
    @ColumnInfo(name = "local_id")
    private long localId;

    @ColumnInfo(name = "call_no")
    private String callNo;

    @ColumnInfo(name = "caller_id")
    private int callerId;

    @ColumnInfo(name = "caller_name")
    private String callerName;

    @ColumnInfo(name = "dept_id")
    private int deptId;

    @ColumnInfo(name = "location_detail")
    private String locationDetail;

    @ColumnInfo(name = "patient_condition")
    private String patientCondition;

    @ColumnInfo(name = "priority")
    private String priority; // HIGH_EMERGENCY, EMERGENCY, etc.

    @ColumnInfo(name = "status")
    private String status; // REQUESTED, DISPATCHED, ON_SITE, COMPLETED

    @ColumnInfo(name = "arrival_at")
    private String arrivalAt;

    @ColumnInfo(name = "notes")
    private String notes;

    @ColumnInfo(name = "created_at")
    private String createdAt;

    @ColumnInfo(name = "sync_status")
    private int syncStatus; // 0: PENDING, 1: SYNCED

    public K3CallEntity() {
    }

    public long getLocalId() {
        return localId;
    }

    public void setLocalId(long localId) {
        this.localId = localId;
    }

    public String getCallNo() {
        return callNo;
    }

    public void setCallNo(String callNo) {
        this.callNo = callNo;
    }

    public int getCallerId() {
        return callerId;
    }

    public void setCallerId(int callerId) {
        this.callerId = callerId;
    }

    public String getCallerName() {
        return callerName;
    }

    public void setCallerName(String callerName) {
        this.callerName = callerName;
    }

    public int getDeptId() {
        return deptId;
    }

    public void setDeptId(int deptId) {
        this.deptId = deptId;
    }

    public String getLocationDetail() {
        return locationDetail;
    }

    public void setLocationDetail(String locationDetail) {
        this.locationDetail = locationDetail;
    }

    public String getPatientCondition() {
        return patientCondition;
    }

    public void setPatientCondition(String patientCondition) {
        this.patientCondition = patientCondition;
    }

    public String getPriority() {
        return priority;
    }

    public void setPriority(String priority) {
        this.priority = priority;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public String getArrivalAt() {
        return arrivalAt;
    }

    public void setArrivalAt(String arrivalAt) {
        this.arrivalAt = arrivalAt;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    public String getCreatedAt() {
        return createdAt;
    }

    public void setCreatedAt(String createdAt) {
        this.createdAt = createdAt;
    }

    public int getSyncStatus() {
        return syncStatus;
    }

    public void setSyncStatus(int syncStatus) {
        this.syncStatus = syncStatus;
    }
}
