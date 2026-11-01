package com.gt.barcodesystem.data.remote.model;

import com.google.gson.JsonElement;
import com.google.gson.annotations.SerializedName;
import com.gt.barcodesystem.data.local.entity.UserEntity;

public class LoginResponse {
    @SerializedName("status")
    private String status;

    @SerializedName("message")
    private String message;

    @SerializedName("data")
    private LoginData data;

    // Fallback top-level fields if constructed locally
    @SerializedName("token")
    private String token;

    @SerializedName("user")
    private UserEntity user;

    @SerializedName("shift")
    private JsonElement shiftRaw;

    private int localShift = 1;

    public static class LoginData {
        @SerializedName("token")
        public String token;

        @SerializedName("user")
        public UserEntity user;

        @SerializedName("shift")
        public ShiftData shift;
    }

    public static class ShiftData {
        @SerializedName("shift_number")
        public int shiftNumber;

        @SerializedName("shift_name")
        public String shiftName;

        @SerializedName("date")
        public String date;
    }

    public boolean isSuccess() {
        return "success".equalsIgnoreCase(status);
    }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public String getMessage() { return message; }
    public void setMessage(String message) { this.message = message; }

    public String getToken() {
        if (data != null && data.token != null) return data.token;
        return token != null ? token : "";
    }
    public void setToken(String token) { this.token = token; }

    public UserEntity getUser() {
        if (data != null && data.user != null) return data.user;
        return user;
    }
    public void setUser(UserEntity user) { this.user = user; }

    public int getShift() {
        if (data != null && data.shift != null && data.shift.shiftNumber > 0) {
            return data.shift.shiftNumber;
        }
        if (shiftRaw != null && shiftRaw.isJsonPrimitive()) {
            try { return shiftRaw.getAsInt(); } catch (Exception ignored) {}
        }
        return localShift;
    }
    public void setShift(int shift) { this.localShift = shift; }
}
