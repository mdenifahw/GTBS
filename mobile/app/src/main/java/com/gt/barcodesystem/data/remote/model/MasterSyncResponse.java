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

    @SerializedName("departments")
    private List<DepartmentEntity> departments;

    @SerializedName("sections")
    private List<SectionEntity> sections;

    @SerializedName("barcodes")
    private List<BarcodeEntity> barcodes;

    @SerializedName("racks")
    private List<WarehouseRackEntity> racks;

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public List<DepartmentEntity> getDepartments() {
        return departments;
    }

    public void setDepartments(List<DepartmentEntity> departments) {
        this.departments = departments;
    }

    public List<SectionEntity> getSections() {
        return sections;
    }

    public void setSections(List<SectionEntity> sections) {
        this.sections = sections;
    }

    public List<BarcodeEntity> getBarcodes() {
        return barcodes;
    }

    public void setBarcodes(List<BarcodeEntity> barcodes) {
        this.barcodes = barcodes;
    }

    public List<WarehouseRackEntity> getRacks() {
        return racks;
    }

    public void setRacks(List<WarehouseRackEntity> racks) {
        this.racks = racks;
    }
}
