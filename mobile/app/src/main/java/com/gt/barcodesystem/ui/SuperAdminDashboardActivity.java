package com.gt.barcodesystem.ui;

import android.content.Intent;
import android.os.Bundle;
import androidx.appcompat.app.AppCompatActivity;
import com.gt.barcodesystem.MainActivity;

public class SuperAdminDashboardActivity extends AppCompatActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Intent intent = new Intent(this, MainActivity.class);
        intent.putExtra("TARGET_PAGE", "dashboard.php");
        startActivity(intent);
        finish();
    }
}
