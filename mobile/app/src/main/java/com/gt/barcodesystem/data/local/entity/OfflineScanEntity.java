package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

import com.google.gson.annotations.SerializedName;

@Entity(tableName = "offline_scans")
public class OfflineScanEntity {

    @PrimaryKey(autoGenerate = true)
    @ColumnInfo(name = "local_id")
    @SerializedName("local_id")
    private long localId;

    @ColumnInfo(name = "barcode")
    @SerializedName("barcode")
    private String barcode;

    @ColumnInfo(name = "dept_id")
    @SerializedName("dept_id")
    private int deptId;

    @ColumnInfo(name = "section_id")
    @SerializedName("section_id")
    private int sectionId;

    @ColumnInfo(name = "qty")
    @SerializedName("qty")
    private double qty;

    @ColumnInfo(name = "unit")
    @SerializedName("unit")
    private String unit;

    @ColumnInfo(name = "operator_id")
    @SerializedName("operator_id")
    private int operatorId;

    @ColumnInfo(name = "operator_nik")
    @SerializedName("operator_nik")
    private String operatorNik;

    @ColumnInfo(name = "action_type")
    @SerializedName("action_type")
    private String actionType;

    @ColumnInfo(name = "details")
    @SerializedName("details")
    private String details;

    @ColumnInfo(name = "notes")
    @SerializedName("notes")
    private String notes;

    @ColumnInfo(name = "machine_code")
    @SerializedName("machine_code")
    private String machineCode;

    @ColumnInfo(name = "item_code")
    @SerializedName("item_code")
    private String itemCode;

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    private String createdAt;

    @ColumnInfo(name = "shift")
    @SerializedName("shift")
    private int shift;

    @ColumnInfo(name = "status")
    @SerializedName("status")
    private String status;

    @ColumnInfo(name = "sync_status")
    @SerializedName("sync_status")
    private int syncStatus; // 0: PENDING, 1: SYNCED

    public OfflineScanEntity() {
    }

    public long getLocalId() { return localId; }
    public void setLocalId(long localId) { this.localId = localId; }

    public String getBarcode() { return barcode; }
    public void setBarcode(String barcode) { this.barcode = barcode; }

    public int getDeptId() { return deptId; }
    public void setDeptId(int deptId) { this.deptId = deptId; }

    public int getSectionId() { return sectionId; }
    public void setSectionId(int sectionId) { this.sectionId = sectionId; }

    public double getQty() { return qty; }
    public void setQty(double qty) { this.qty = qty; }

    public String getUnit() { return unit; }
    public void setUnit(String unit) { this.unit = unit; }

    public int getOperatorId() { return operatorId; }
    public void setOperatorId(int operatorId) { this.operatorId = operatorId; }

    public String getOperatorNik() { return operatorNik; }
    public void setOperatorNik(String operatorNik) { this.operatorNik = operatorNik; }

    public String getActionType() { return actionType; }
    public void setActionType(String actionType) { this.actionType = actionType; }

    public String getDetails() { return details; }
    public void setDetails(String details) { this.details = details; }

    public String getNotes() { return notes; }
    public void setNotes(String notes) { this.notes = notes; }

    public String getMachineCode() { return machineCode; }
    public void setMachineCode(String machineCode) { this.machineCode = machineCode; }

    public String getItemCode() { return itemCode; }
    public void setItemCode(String itemCode) { this.itemCode = itemCode; }

    public String getCreatedAt() { return createdAt; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }

    public int getShift() { return shift; }
    public void setShift(int shift) { this.shift = shift; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public int getSyncStatus() { return syncStatus; }
    public void setSyncStatus(int syncStatus) { this.syncStatus = syncStatus; }
}
