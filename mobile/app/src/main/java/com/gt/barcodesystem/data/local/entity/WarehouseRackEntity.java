package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

import com.google.gson.annotations.SerializedName;

@Entity(tableName = "warehouse_racks")
public class WarehouseRackEntity {

    @PrimaryKey
    @SerializedName("id")
    private int id;

    @SerializedName("code")
    private String code;

    @ColumnInfo(name = "rack_code")
    @SerializedName("rack_code")
    private String rackCode;

    @SerializedName("zone")
    private String zone;

    @ColumnInfo(name = "row_num")
    @SerializedName("row_num")
    private int rowNum;

    @ColumnInfo(name = "tier_num")
    @SerializedName("tier_num")
    private int tierNum;

    @SerializedName("status")
    private String status;

    @ColumnInfo(name = "is_occupied")
    @SerializedName("is_occupied")
    private boolean isOccupied;

    @ColumnInfo(name = "current_barcode")
    @SerializedName("current_barcode")
    private String currentBarcode;

    @ColumnInfo(name = "current_item_name")
    @SerializedName("current_item_name")
    private String currentItemName;

    @ColumnInfo(name = "current_qty")
    @SerializedName("current_qty")
    private double currentQty;

    @ColumnInfo(name = "current_unit")
    @SerializedName("current_unit")
    private String currentUnit;

    @ColumnInfo(name = "last_updated")
    @SerializedName("last_updated")
    private String lastUpdated;

    public WarehouseRackEntity() {
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getCode() { return code; }
    public void setCode(String code) { this.code = code; }

    public String getRackCode() { return rackCode != null ? rackCode : code; }
    public void setRackCode(String rackCode) { this.rackCode = rackCode; }

    public String getZone() { return zone; }
    public void setZone(String zone) { this.zone = zone; }

    public int getRowNum() { return rowNum; }
    public void setRowNum(int rowNum) { this.rowNum = rowNum; }

    public int getTierNum() { return tierNum; }
    public void setTierNum(int tierNum) { this.tierNum = tierNum; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public boolean isOccupied() { return isOccupied; }
    public void setOccupied(boolean occupied) { isOccupied = occupied; }

    public String getCurrentBarcode() { return currentBarcode; }
    public void setCurrentBarcode(String currentBarcode) { this.currentBarcode = currentBarcode; }

    public String getCurrentItemName() { return currentItemName; }
    public void setCurrentItemName(String currentItemName) { this.currentItemName = currentItemName; }

    public double getCurrentQty() { return currentQty; }
    public void setCurrentQty(double currentQty) { this.currentQty = currentQty; }

    public String getCurrentUnit() { return currentUnit; }
    public void setCurrentUnit(String currentUnit) { this.currentUnit = currentUnit; }

    public String getLastUpdated() { return lastUpdated; }
    public void setLastUpdated(String lastUpdated) { this.lastUpdated = lastUpdated; }
}
