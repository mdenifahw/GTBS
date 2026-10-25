package com.gt.barcodesystem.data.remote.model;

import com.google.gson.annotations.SerializedName;

public class UserSaveRequest {
    @SerializedName("id")
    private Integer id;

    @SerializedName("nik")
    private String nik;

    @SerializedName("username")
    private String username;

    @SerializedName("password")
    private String password;

    @SerializedName("full_name")
    private String fullName;

    @SerializedName("role")
    private String role;

    @SerializedName("dept_id")
    private int deptId;

    @SerializedName("section_id")
    private int sectionId;

    @SerializedName("phone")
    private String phone;

    public UserSaveRequest(Integer id, String nik, String username, String password, String fullName, String role, int deptId, int sectionId, String phone) {
        this.id = id;
        this.nik = nik;
        this.username = username;
        this.password = password;
        this.fullName = fullName;
        this.role = role;
        this.deptId = deptId;
        this.sectionId = sectionId;
        this.phone = phone;
    }

    public Integer getId() {
        return id;
    }

    public String getNik() {
        return nik;
    }

    public String getUsername() {
        return username;
    }

    public String getPassword() {
        return password;
    }

    public String getFullName() {
        return fullName;
    }

    public String getRole() {
        return role;
    }

    public int getDeptId() {
        return deptId;
    }

    public int getSectionId() {
        return sectionId;
    }

    public String getPhone() {
        return phone;
    }
}
