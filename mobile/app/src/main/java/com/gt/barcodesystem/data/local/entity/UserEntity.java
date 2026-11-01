package com.gt.barcodesystem.data.local.entity;

import androidx.room.ColumnInfo;
import androidx.room.Entity;
import androidx.room.PrimaryKey;

import com.google.gson.annotations.SerializedName;

@Entity(tableName = "users")
public class UserEntity {

    @PrimaryKey
    @SerializedName("id")
    private int id;

    @SerializedName("nik")
    private String nik;

    @SerializedName("username")
    private String username;

    @ColumnInfo(name = "full_name")
    @SerializedName("full_name")
    private String fullName;

    @SerializedName("phone")
    private String phone;

    @SerializedName("role")
    private String role;

    @ColumnInfo(name = "dept_id")
    @SerializedName("dept_id")
    private Integer deptId;

    @ColumnInfo(name = "dept_code")
    @SerializedName("dept_code")
    private String deptCode;

    @ColumnInfo(name = "dept_name")
    @SerializedName("dept_name")
    private String deptName;

    @ColumnInfo(name = "section_id")
    @SerializedName("section_id")
    private Integer sectionId;

    @ColumnInfo(name = "section_code")
    @SerializedName("section_code")
    private String sectionCode;

    @ColumnInfo(name = "section_name")
    @SerializedName("section_name")
    private String sectionName;

    @SerializedName("photo")
    private String photo;

    @ColumnInfo(name = "is_active")
    @SerializedName("is_active")
    private boolean isActive = true;

    public UserEntity() {
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getNik() { return nik; }
    public void setNik(String nik) { this.nik = nik; }

    public String getUsername() { return username; }
    public void setUsername(String username) { this.username = username; }

    public String getFullName() { return fullName; }
    public void setFullName(String fullName) { this.fullName = fullName; }

    public String getPhone() { return phone; }
    public void setPhone(String phone) { this.phone = phone; }

    public String getRole() { return role; }
    public void setRole(String role) { this.role = role; }

    public Integer getDeptId() { return deptId; }
    public void setDeptId(Integer deptId) { this.deptId = deptId; }

    public String getDeptCode() { return deptCode; }
    public void setDeptCode(String deptCode) { this.deptCode = deptCode; }

    public String getDeptName() { return deptName; }
    public void setDeptName(String deptName) { this.deptName = deptName; }

    public Integer getSectionId() { return sectionId; }
    public void setSectionId(Integer sectionId) { this.sectionId = sectionId; }

    public String getSectionCode() { return sectionCode; }
    public void setSectionCode(String sectionCode) { this.sectionCode = sectionCode; }

    public String getSectionName() { return sectionName; }
    public void setSectionName(String sectionName) { this.sectionName = sectionName; }

    public String getPhoto() { return photo; }
    public void setPhoto(String photo) { this.photo = photo; }

    public boolean isActive() { return isActive; }
    public void setActive(boolean active) { isActive = active; }
}
