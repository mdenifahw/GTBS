package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class BarcodeGenerateRequest {
    @SerializedName("barcode_type")
    private String barcodeType;

    @SerializedName("product_code")
    private String productCode;

    @SerializedName("product_name")
    private String productName;

    @SerializedName("size_name")
    private String sizeName;

    @SerializedName("qty")
    private int qty;

    public BarcodeGenerateRequest(String barcodeType, String productCode, String productName, String sizeName, int qty) {
        this.barcodeType = barcodeType;
        this.productCode = productCode;
        this.productName = productName;
        this.sizeName = sizeName;
        this.qty = qty;
    }

    public String getBarcodeType() {
        return barcodeType;
    }

    public String getProductCode() {
        return productCode;
    }

    public String getProductName() {
        return productName;
    }

    public String getSizeName() {
        return sizeName;
    }

    public int getQty() {
        return qty;
    }
}
