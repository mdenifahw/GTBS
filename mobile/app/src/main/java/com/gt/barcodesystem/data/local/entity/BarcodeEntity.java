package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

import com.google.gson.annotations.SerializedName;

@Entity(tableName = "barcodes")
public class BarcodeEntity {

    @PrimaryKey
    @SerializedName("id")
    private int id;

    @SerializedName("barcode")
    private String barcode;

    @ColumnInfo(name = "product_code")
    @SerializedName("product_code")
    private String productCode;

    @ColumnInfo(name = "product_name")
    @SerializedName("product_name")
    private String productName;

    @ColumnInfo(name = "size_name")
    @SerializedName("size_name")
    private String sizeName;

    @ColumnInfo(name = "barcode_type")
    @SerializedName("barcode_type")
    private String barcodeType;

    @SerializedName("status")
    private String status;

    @ColumnInfo(name = "current_dept")
    @SerializedName("current_dept")
    private String currentDept;

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    private String createdAt;

    public BarcodeEntity() {
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getBarcode() { return barcode; }
    public void setBarcode(String barcode) { this.barcode = barcode; }

    public String getProductCode() { return productCode; }
    public void setProductCode(String productCode) { this.productCode = productCode; }

    public String getProductName() { return productName; }
    public void setProductName(String productName) { this.productName = productName; }

    public String getSizeName() { return sizeName; }
    public void setSizeName(String sizeName) { this.sizeName = sizeName; }

    public String getBarcodeType() { return barcodeType; }
    public void setBarcodeType(String barcodeType) { this.barcodeType = barcodeType; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getCurrentDept() { return currentDept; }
    public void setCurrentDept(String currentDept) { this.currentDept = currentDept; }

    public String getCreatedAt() { return createdAt; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }
}
