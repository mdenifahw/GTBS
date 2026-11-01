package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.DepartmentEntity;
import com.gt.barcodesystem.data.local.entity.SectionEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;

import java.util.List;

public class MasterSyncResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("timestamp")
    private String timestamp;

    @SerializedName("data")
    private MasterDataPayload data;

    @SerializedName("departments")
    private List<DepartmentEntity> departments;

    @SerializedName("sections")
    private List<SectionEntity> sections;

    @SerializedName("barcodes")
    private List<BarcodeEntity> barcodes;

    @SerializedName("racks")
    private List<WarehouseRackEntity> racks;

    public static class MasterDataPayload {
        @SerializedName("departments")
        public List<DepartmentEntity> departments;

        @SerializedName("sections")
        public List<SectionEntity> sections;

        @SerializedName(value = "recent_barcodes", alternate = {"barcodes"})
        public List<BarcodeEntity> barcodes;

        @SerializedName(value = "warehouse_racks", alternate = {"racks"})
        public List<WarehouseRackEntity> racks;
    }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getTimestamp() { return timestamp; }
    public void setTimestamp(String timestamp) { this.timestamp = timestamp; }

    public List<DepartmentEntity> getDepartments() {
        if (data != null && data.departments != null) return data.departments;
        return departments;
    }
    public void setDepartments(List<DepartmentEntity> departments) { this.departments = departments; }

    public List<SectionEntity> getSections() {
        if (data != null && data.sections != null) return data.sections;
        return sections;
    }
    public void setSections(List<SectionEntity> sections) { this.sections = sections; }

    public List<BarcodeEntity> getBarcodes() {
        if (data != null && data.barcodes != null) return data.barcodes;
        return barcodes;
    }
    public List<BarcodeEntity> getRecentBarcodes() {
        return getBarcodes();
    }
    public void setBarcodes(List<BarcodeEntity> barcodes) { this.barcodes = barcodes; }

    public List<WarehouseRackEntity> getRacks() {
        if (data != null && data.racks != null) return data.racks;
        return racks;
    }
    public List<WarehouseRackEntity> getWarehouseRacks() {
        return getRacks();
    }
    public void setRacks(List<WarehouseRackEntity> racks) { this.racks = racks; }
}
