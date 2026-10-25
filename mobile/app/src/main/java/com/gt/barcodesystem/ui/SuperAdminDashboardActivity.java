package com.gt.barcodesystem.ui;

import android.content.Intent;
import android.os.Bundle;
import android.widget.ImageButton;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.fragment.app.Fragment;

import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.google.android.material.button.MaterialButton;
import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.repository.AuthRepository;
import com.gt.barcodesystem.data.repository.K3Repository;
import com.gt.barcodesystem.ui.fragment.BarcodeManagementFragment;
import com.gt.barcodesystem.ui.fragment.DashboardFragment;
import com.gt.barcodesystem.ui.fragment.K3DowntimeFragment;
import com.gt.barcodesystem.ui.fragment.UserManagementFragment;
import com.gt.barcodesystem.util.SessionManager;

public class SuperAdminDashboardActivity extends AppCompatActivity {

    private TextView tvAdminSubTitle;
    private MaterialButton btnSwitchScannerMode;
    private ImageButton btnSosAdmin, btnLogoutAdmin;
    private BottomNavigationView bottomNavigation;

    private AuthRepository authRepository;
    private K3Repository k3Repository;
    private SessionManager sessionManager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_super_admin_dashboard);

        sessionManager = new SessionManager(this);
        authRepository = new AuthRepository(this);
        k3Repository = new K3Repository(this);

        tvAdminSubTitle = findViewById(R.id.tvAdminSubTitle);
        btnSwitchScannerMode = findViewById(R.id.btnSwitchScannerMode);
        btnSosAdmin = findViewById(R.id.btnSosAdmin);
        btnLogoutAdmin = findViewById(R.id.btnLogoutAdmin);
        bottomNavigation = findViewById(R.id.bottomNavigation);

        String name = sessionManager.getFullName();
        if (name != null && !name.isEmpty()) {
            tvAdminSubTitle.setText("Super Admin: " + name + " (Kontrol Global)");
        }

        btnSwitchScannerMode.setOnClickListener(v -> {
            startActivity(new Intent(this, OperatorLiteActivity.class));
        });

        btnSosAdmin.setOnClickListener(v -> showSosDialog());

        btnLogoutAdmin.setOnClickListener(v -> {
            authRepository.logout();
            startActivity(new Intent(this, LoginActivity.class));
            finish();
        });

        bottomNavigation.setOnItemSelectedListener(item -> {
            int itemId = item.getItemId();
            Fragment selectedFragment = null;

            if (itemId == R.id.nav_dashboard) {
                selectedFragment = new DashboardFragment();
            } else if (itemId == R.id.nav_users) {
                selectedFragment = new UserManagementFragment();
            } else if (itemId == R.id.nav_barcodes) {
                selectedFragment = new BarcodeManagementFragment();
            } else if (itemId == R.id.nav_k3_downtime) {
                selectedFragment = new K3DowntimeFragment();
            }

            if (selectedFragment != null) {
                getSupportFragmentManager().beginTransaction()
                        .replace(R.id.fragmentContainer, selectedFragment)
                        .commit();
                return true;
            }
            return false;
        });

        bottomNavigation.setSelectedItemId(R.id.nav_dashboard);
    }

    private void showSosDialog() {
        new AlertDialog.Builder(this)
                .setTitle("🚨 PANGGILAN DARURAT K3 MEDIS")
                .setMessage("Apakah Anda ingin memicu Panggilan Darurat Medis K3 Global?")
                .setPositiveButton("KIRIM SOS SEKARANG", (dialog, which) -> {
                    k3Repository.triggerEmergencyCall("Global Plant Admin", "Panggilan Darurat Super Admin", "HIGH_EMERGENCY", new K3Repository.K3Callback() {
                        @Override
                        public void onSuccess(String callNo, String message) {
                            runOnUiThread(() -> Toast.makeText(SuperAdminDashboardActivity.this, message + " (" + callNo + ")", Toast.LENGTH_LONG).show());
                        }

                        @Override
                        public void onError(String error) {
                            runOnUiThread(() -> Toast.makeText(SuperAdminDashboardActivity.this, "Gagal SOS: " + error, Toast.LENGTH_SHORT).show());
                        }
                    });
                })
                .setNegativeButton("Batal", null)
                .show();
    }
}
