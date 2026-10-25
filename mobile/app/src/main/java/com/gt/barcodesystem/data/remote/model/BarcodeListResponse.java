package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;

import java.util.List;

public class BarcodeListResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("barcodes")
    private List<BarcodeEntity> barcodes;

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public List<BarcodeEntity> getBarcodes() {
        return barcodes;
    }

    public void setBarcodes(List<BarcodeEntity> barcodes) {
        this.barcodes = barcodes;
    }
}
