package com.gt.barcodesystem.ui;

import android.content.Intent;
import android.os.Bundle;
import androidx.appcompat.app.AppCompatActivity;
import com.gt.barcodesystem.MainActivity;

/**
 * Pengalihan otomatis ke MainActivity (Hybrid Native Web Container)
 * Memastikan jika Android Studio menjalankan run configuration lama (LoginActivity),
 * pengguna akan langsung diarahkan ke layar web app yang 100% presisi.
 */
public class LoginActivity extends AppCompatActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Intent intent = new Intent(this, MainActivity.class);
        if (getIntent() != null && getIntent().getExtras() != null) {
            intent.putExtras(getIntent().getExtras());
        }
        startActivity(intent);
        finish();
    }
}
