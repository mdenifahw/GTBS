package com.gt.barcodesystem.data.remote.api;

import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.remote.model.ApiResponse;
import com.gt.barcodesystem.data.remote.model.BarcodeGenerateRequest;
import com.gt.barcodesystem.data.remote.model.BarcodeListResponse;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;
import com.gt.barcodesystem.data.remote.model.DowntimeReportRequest;
import com.gt.barcodesystem.data.remote.model.K3CallRequest;
import com.gt.barcodesystem.data.remote.model.K3CallResponse;
import com.gt.barcodesystem.data.remote.model.K3ListResponse;
import com.gt.barcodesystem.data.remote.model.K3StatusUpdateRequest;
import com.gt.barcodesystem.data.remote.model.LoginRequest;
import com.gt.barcodesystem.data.remote.model.LoginResponse;
import com.gt.barcodesystem.data.remote.model.MasterSyncResponse;
import com.gt.barcodesystem.data.remote.model.PushActivitiesRequest;
import com.gt.barcodesystem.data.remote.model.PushActivitiesResponse;
import com.gt.barcodesystem.data.remote.model.UserListResponse;
import com.gt.barcodesystem.data.remote.model.UserSaveRequest;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;

public interface ApiService {

    @POST("mobile_auth.php")
    Call<LoginResponse> login(@Body LoginRequest loginRequest);

    @GET("mobile_sync.php")
    Call<MasterSyncResponse> pullMasterData(@Query("action") String action);

    @POST("mobile_sync.php?action=push_activities")
    Call<PushActivitiesResponse> pushActivities(@Body PushActivitiesRequest pushRequest);

    @POST("mobile_sync.php?action=push_k3")
    Call<K3CallResponse> pushK3Call(@Body K3CallRequest k3Request);

    @POST("mobile_sync.php?action=update_k3_status")
    Call<ApiResponse<Void>> updateK3Status(@Body K3StatusUpdateRequest statusRequest);

    @GET("mobile_sync.php?action=get_active_k3")
    Call<K3ListResponse> getActiveK3Calls();

    @POST("report_downtime.php")
    Call<ApiResponse<Void>> reportDowntime(@Body DowntimeReportRequest downtimeRequest);

    @POST("sos_call.php")
    Call<K3CallResponse> triggerSosCall(@Body K3CallRequest sosRequest);

    // Super Admin & Management API Endpoints
    @GET("export_scada.php?format=json")
    Call<DashboardSummaryResponse> getDashboardSummary();

    @GET("user_management.php?action=list")
    Call<UserListResponse> getUsers();

    @POST("user_management.php?action=save")
    Call<ApiResponse<Void>> saveUser(@Body UserSaveRequest userSaveRequest);

    @GET("export_barcodes.php?format=json")
    Call<BarcodeListResponse> getBarcodes();

    @POST("barcode_management.php?action=generate")
    Call<ApiResponse<BarcodeEntity>> generateBarcode(@Body BarcodeGenerateRequest barcodeReq);
}
