package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

import com.google.gson.annotations.SerializedName;

@Entity(tableName = "sections")
public class SectionEntity {

    @PrimaryKey
    @SerializedName("id")
    private int id;

    @ColumnInfo(name = "dept_id")
    @SerializedName("dept_id")
    private int deptId;

    @SerializedName("code")
    private String code;

    @SerializedName("name")
    private String name;

    @ColumnInfo(name = "is_active")
    @SerializedName("is_active")
    private boolean isActive = true;

    public SectionEntity() {
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getDeptId() { return deptId; }
    public void setDeptId(int deptId) { this.deptId = deptId; }

    public String getCode() { return code; }
    public void setCode(String code) { this.code = code; }

    public String getName() { return name; }
    public void setName(String name) { this.name = name; }

    public boolean isActive() { return isActive; }
    public void setActive(boolean active) { isActive = active; }
}
