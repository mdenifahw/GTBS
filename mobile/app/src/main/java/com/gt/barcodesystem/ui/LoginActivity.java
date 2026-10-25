package com.gt.barcodesystem.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;
import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.remote.api.ApiClient;
import com.gt.barcodesystem.data.remote.model.LoginResponse;
import com.gt.barcodesystem.data.repository.AuthRepository;

public class LoginActivity extends AppCompatActivity {

    private TextInputEditText etUsername, etPassword, etServerUrl;
    private MaterialButton btnLogin;
    private ProgressBar progressBar;
    private AuthRepository authRepository;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);

        authRepository = new AuthRepository(this);

        if (authRepository.isLoggedIn()) {
            startActivity(new Intent(this, SuperAdminDashboardActivity.class));
            finish();
            return;
        }

        setContentView(R.layout.activity_login);

        etUsername = findViewById(R.id.etUsername);
        etPassword = findViewById(R.id.etPassword);
        etServerUrl = findViewById(R.id.etServerUrl);
        btnLogin = findViewById(R.id.btnLogin);
        progressBar = findViewById(R.id.progressBar);

        etServerUrl.setText(ApiClient.getBaseUrl());

        btnLogin.setOnClickListener(v -> handleLogin());
    }

    private void handleLogin() {
        String username = etUsername.getText() != null ? etUsername.getText().toString().trim() : "";
        String password = etPassword.getText() != null ? etPassword.getText().toString().trim() : "";
        String serverUrl = etServerUrl.getText() != null ? etServerUrl.getText().toString().trim() : "";

        if (username.isEmpty() || password.isEmpty()) {
            Toast.makeText(this, "Harap isi Username/NIK dan Password", Toast.LENGTH_SHORT).show();
            return;
        }

        if (!serverUrl.isEmpty()) {
            ApiClient.setBaseUrl(serverUrl);
        }

        btnLogin.setEnabled(false);
        progressBar.setVisibility(View.VISIBLE);

        authRepository.login(username, password, new AuthRepository.AuthCallback() {
            @Override
            public void onSuccess(LoginResponse response) {
                runOnUiThread(() -> {
                    progressBar.setVisibility(View.GONE);
                    btnLogin.setEnabled(true);
                    Toast.makeText(LoginActivity.this, "Selamat Datang, " + (response.getUser() != null ? response.getUser().getFullName() : username), Toast.LENGTH_SHORT).show();

                    String role = response.getUser() != null ? response.getUser().getRole() : "";
                    if ("super_admin".equalsIgnoreCase(role) || "plant_head".equalsIgnoreCase(role)) {
                        startActivity(new Intent(LoginActivity.this, SuperAdminDashboardActivity.class));
                    } else {
                        startActivity(new Intent(LoginActivity.this, OperatorLiteActivity.class));
                    }
                    finish();
                });
            }

            @Override
            public void onError(String errorMessage) {
                runOnUiThread(() -> {
                    progressBar.setVisibility(View.GONE);
                    btnLogin.setEnabled(true);
                    Toast.makeText(LoginActivity.this, errorMessage, Toast.LENGTH_LONG).show();
                });
            }
        });
    }
}
