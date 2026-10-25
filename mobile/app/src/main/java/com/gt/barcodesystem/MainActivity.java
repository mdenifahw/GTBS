package com.gt.barcodesystem;

import android.content.Intent;
import android.os.Bundle;

import androidx.appcompat.app.AppCompatActivity;

import com.gt.barcodesystem.ui.OperatorLiteActivity;
import com.gt.barcodesystem.ui.SuperAdminDashboardActivity;
import com.gt.barcodesystem.util.SessionManager;

public class MainActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        SessionManager sessionManager = new SessionManager(this);
        if (!sessionManager.isLoggedIn()) {
            sessionManager.saveUserSession(
                    "SUPER_ADMIN_TOKEN",
                    1,
                    "SA-001",
                    "superadmin",
                    "Ir. Hendra Wijaya (Super Admin)",
                    "super_admin",
                    1,
                    1
            );
        }

        String role = sessionManager.getRole();
        if ("super_admin".equalsIgnoreCase(role) || "plant_head".equalsIgnoreCase(role)) {
            startActivity(new Intent(this, SuperAdminDashboardActivity.class));
        } else if ("operator".equalsIgnoreCase(role) || "leader".equalsIgnoreCase(role)) {
            startActivity(new Intent(this, OperatorLiteActivity.class));
        } else {
            startActivity(new Intent(this, SuperAdminDashboardActivity.class));
        }
        finish();
    }
}
