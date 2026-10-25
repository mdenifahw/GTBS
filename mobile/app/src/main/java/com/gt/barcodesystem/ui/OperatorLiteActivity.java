package com.gt.barcodesystem.ui;

import android.content.Intent;
import android.os.Bundle;
import android.view.KeyEvent;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.LinearLayout;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;

import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.repository.AuthRepository;
import com.gt.barcodesystem.data.repository.K3Repository;
import com.gt.barcodesystem.data.repository.ScanRepository;
import com.gt.barcodesystem.data.sync.SyncManager;
import com.gt.barcodesystem.ui.adapter.ScanHistoryAdapter;
import com.gt.barcodesystem.util.SessionManager;
import com.gt.barcodesystem.util.ShiftHelper;

public class OperatorLiteActivity extends AppCompatActivity {

    private TextView tvOperatorName, tvShiftLabel, tvPendingSyncBadge;
    private Spinner spinnerStation;
    private EditText etBarcodeScan;
    private MaterialButton btnSubmitScan, btnSyncNow;
    private ImageButton btnSos, btnLogout;
    private MaterialCardView cardLastScan;
    private TextView tvScanTitle, tvScanDetails;

    // Departmental contextual layouts & controls
    private TextView tvParamTitle;
    private LinearLayout layoutMixingParams, layoutBuildingParams, layoutCuringParams, layoutInspectionParams, layoutWarehouseParams;
    private Spinner spinnerCompound, spinnerSpecSize, spinnerCavity, spinnerCuringDefect, spinnerQcGrade;
    private EditText etParamWeight, etBuildingMachine, etInspectionDefect, etTargetRack;
    private MaterialButton btnCheckRack;
    private TextView tvRackValidation;

    private ScanRepository scanRepository;
    private AuthRepository authRepository;
    private K3Repository k3Repository;
    private SessionManager sessionManager;
    private ScanHistoryAdapter historyAdapter;

    private final String[] stations = {
            "MIXING (Dept Material)",
            "CALENDERING (Dept Material)",
            "BUILDING_GT (Dept Building)",
            "LORI_CURING (Dept Building)",
            "CURING_PRESS (Dept Curing)",
            "REPAIR (Dept Curing)",
            "FINAL_INSPECTION (Dept FI)",
            "WAREHOUSE (Gudang Ban Jadi)"
    };

    private final String[] stationCodes = {
            "MIXING_PRODUCED",
            "CALENDERING_PRODUCED",
            "BUILDING_GT_PRODUCED",
            "LORI_CURING_TRANSFER",
            "CURING_PRESS_CURED",
            "TIRE_REPAIRED",
            "FINAL_INSPECTION_RECORDED",
            "WAREHOUSE_STORED"
    };

    private String activeStationCode = "MIXING_PRODUCED";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_operator_lite);

        sessionManager = new SessionManager(this);
        scanRepository = new ScanRepository(this);
        authRepository = new AuthRepository(this);
        k3Repository = new K3Repository(this);

        initViews();
        setupStationSpinner();
        setupDepartmentSpinners();
        setupListeners();
        loadUserData();
        loadScanHistory();
    }

    private void initViews() {
        tvOperatorName = findViewById(R.id.tvOperatorName);
        tvShiftLabel = findViewById(R.id.tvShiftLabel);
        tvPendingSyncBadge = findViewById(R.id.tvPendingSyncBadge);
        spinnerStation = findViewById(R.id.spinnerStation);
        etBarcodeScan = findViewById(R.id.etBarcodeScan);
        btnSubmitScan = findViewById(R.id.btnSubmitScan);
        btnSyncNow = findViewById(R.id.btnSyncNow);
        btnSos = findViewById(R.id.btnSos);
        btnLogout = findViewById(R.id.btnLogout);
        cardLastScan = findViewById(R.id.cardLastScan);
        tvScanTitle = findViewById(R.id.tvScanTitle);
        tvScanDetails = findViewById(R.id.tvScanDetails);

        tvParamTitle = findViewById(R.id.tvParamTitle);
        layoutMixingParams = findViewById(R.id.layoutMixingParams);
        layoutBuildingParams = findViewById(R.id.layoutBuildingParams);
        layoutCuringParams = findViewById(R.id.layoutCuringParams);
        layoutInspectionParams = findViewById(R.id.layoutInspectionParams);
        layoutWarehouseParams = findViewById(R.id.layoutWarehouseParams);

        spinnerCompound = findViewById(R.id.spinnerCompound);
        spinnerSpecSize = findViewById(R.id.spinnerSpecSize);
        spinnerCavity = findViewById(R.id.spinnerCavity);
        spinnerCuringDefect = findViewById(R.id.spinnerCuringDefect);
        spinnerQcGrade = findViewById(R.id.spinnerQcGrade);

        etParamWeight = findViewById(R.id.etParamWeight);
        etBuildingMachine = findViewById(R.id.etBuildingMachine);
        etInspectionDefect = findViewById(R.id.etInspectionDefect);
        etTargetRack = findViewById(R.id.etTargetRack);
        btnCheckRack = findViewById(R.id.btnCheckRack);
        tvRackValidation = findViewById(R.id.tvRackValidation);

        RecyclerView rvScanHistory = findViewById(R.id.rvScanHistory);
        rvScanHistory.setLayoutManager(new LinearLayoutManager(this));
        historyAdapter = new ScanHistoryAdapter(this);
        rvScanHistory.setAdapter(historyAdapter);
    }

    private void setupDepartmentSpinners() {
        // Compounds
        String[] compounds = {"CMP-TREAD-01 (Tread PCR)", "CMP-SIDE-02 (Sidewall)", "CMP-BASE-01 (Base)", "CMP-TBR-01 (Truck/Bus)"};
        ArrayAdapter<String> cmpAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, compounds);
        cmpAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerCompound.setAdapter(cmpAdapter);

        // Building Specs
        String[] specs = {"185/65 R15 ECO", "225/65 R17 HP", "195/75 R16C LTR", "175/65 R14 B-Series"};
        ArrayAdapter<String> specAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, specs);
        specAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerSpecSize.setAdapter(specAdapter);

        // Cavity
        String[] cavities = {"LEFT (Cavity Kiri)", "RIGHT (Cavity Kanan)"};
        ArrayAdapter<String> cavAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, cavities);
        cavAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerCavity.setAdapter(cavAdapter);

        // Curing Defect
        String[] defects = {"None (Tidak Ada Cacat)", "Blister (Gelembung)", "Tread crack (Retak)", "Sidewall scratch", "Bead bend"};
        ArrayAdapter<String> defectAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, defects);
        defectAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerCuringDefect.setAdapter(defectAdapter);

        // QC Grade
        String[] grades = {"PASS (OK - Lolos QC)", "REPAIR (Perlu Perbaikan)", "SCRAP (Cacat Berat / Buang)"};
        ArrayAdapter<String> gradeAdapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, grades);
        gradeAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerQcGrade.setAdapter(gradeAdapter);
    }

    private void setupStationSpinner() {
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, stations);
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerStation.setAdapter(adapter);

        spinnerStation.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                activeStationCode = stationCodes[position];
                sessionManager.setStationCode(activeStationCode);
                updateContextualParams(position);
                requestFocusOnScanner();
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void updateContextualParams(int position) {
        layoutMixingParams.setVisibility(View.GONE);
        layoutBuildingParams.setVisibility(View.GONE);
        layoutCuringParams.setVisibility(View.GONE);
        layoutInspectionParams.setVisibility(View.GONE);
        layoutWarehouseParams.setVisibility(View.GONE);

        switch (position) {
            case 0: // MIXING
            case 1: // CALENDERING
                tvParamTitle.setText("PARAMETER MATERIAL (COMPOUND & BERAT)");
                layoutMixingParams.setVisibility(View.VISIBLE);
                break;
            case 2: // BUILDING_GT
            case 3: // LORI_CURING
                tvParamTitle.setText("PARAMETER PERAKITAN TBM (UKURAN & MESIN)");
                layoutBuildingParams.setVisibility(View.VISIBLE);
                break;
            case 4: // CURING_PRESS
            case 5: // REPAIR
                tvParamTitle.setText("PARAMETER CURING & REPARASI (CAVITY & DEFECT)");
                layoutCuringParams.setVisibility(View.VISIBLE);
                break;
            case 6: // FINAL_INSPECTION
                tvParamTitle.setText("PARAMETER FINAL INSPECTION (GRADE QC)");
                layoutInspectionParams.setVisibility(View.VISIBLE);
                break;
            case 7: // WAREHOUSE
                tvParamTitle.setText("SMART RACK ROUTING (ALOKASI RAK GUDANG)");
                layoutWarehouseParams.setVisibility(View.VISIBLE);
                break;
        }
    }

    private void setupListeners() {
        btnSubmitScan.setOnClickListener(v -> performScan());

        btnCheckRack.setOnClickListener(v -> {
            String rackCode = etTargetRack.getText().toString().trim().toUpperCase();
            if (rackCode.isEmpty()) {
                tvRackValidation.setText("Harap masukkan kode rak (misal: RAK-A01)");
                return;
            }
            scanRepository.checkRackStatus(rackCode, rack -> runOnUiThread(() -> {
                if (rack != null) {
                    if (rack.isOccupied()) {
                        tvRackValidation.setTextColor(0xFFDC2626);
                        tvRackValidation.setText("Peringatan: Rak " + rackCode + " TERISI (" + rack.getCurrentBarcode() + ")");
                    } else {
                        tvRackValidation.setTextColor(0xFF059669);
                        tvRackValidation.setText("Status: Rak " + rackCode + " KOSONG & TERSEDIA (Zona " + rack.getZone() + ")");
                    }
                } else {
                    tvRackValidation.setTextColor(0xFFD97706);
                    tvRackValidation.setText("Rak " + rackCode + " siap digunakan.");
                }
            }));
        });

        btnSyncNow.setOnClickListener(v -> {
            Toast.makeText(this, "Memulai sinkronisasi data...", Toast.LENGTH_SHORT).show();
            SyncManager.getInstance(this).syncPendingScans(new SyncManager.SyncCallback() {
                @Override
                public void onSyncCompleted(int syncedCount) {
                    runOnUiThread(() -> {
                        Toast.makeText(OperatorLiteActivity.this, "Sukses menyinkronkan " + syncedCount + " scan.", Toast.LENGTH_SHORT).show();
                        loadScanHistory();
                    });
                }

                @Override
                public void onSyncFailed(String error) {
                    runOnUiThread(() -> Toast.makeText(OperatorLiteActivity.this, "Sinkron offline: " + error, Toast.LENGTH_SHORT).show());
                }
            });
        });

        btnSos.setOnClickListener(v -> showSosEmergencyDialog());

        btnLogout.setOnClickListener(v -> {
            authRepository.logout();
            startActivity(new Intent(this, LoginActivity.class));
            finish();
        });

        etBarcodeScan.setOnEditorActionListener((v, actionId, event) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE || actionId == EditorInfo.IME_ACTION_UNSPECIFIED ||
                (event != null && event.getKeyCode() == KeyEvent.KEYCODE_ENTER && event.getAction() == KeyEvent.ACTION_DOWN)) {
                performScan();
                return true;
            }
            return false;
        });
    }

    private void loadUserData() {
        String name = sessionManager.getFullName();
        String nik = sessionManager.getNik();
        tvOperatorName.setText("Operator: " + (name != null && !name.isEmpty() ? name : nik));

        int currentShift = ShiftHelper.getCurrentShift();
        tvShiftLabel.setText(ShiftHelper.getShiftLabel(currentShift));
    }

    private void performScan() {
        String barcode = etBarcodeScan.getText() != null ? etBarcodeScan.getText().toString().trim() : "";
        if (barcode.isEmpty()) {
            Toast.makeText(this, "Masukkan / Pemindai Barcode!", Toast.LENGTH_SHORT).show();
            return;
        }

        int stationPos = spinnerStation.getSelectedItemPosition();
        String details = "Scan Stasiun " + activeStationCode;
        String machineCode = "PDT-DEV-01";
        double qty = 1.0;
        String unit = "Pcs";
        String status = "NORMAL";

        // Extract context based on station
        if (stationPos == 0 || stationPos == 1) { // Mixing / Calendering
            String cmp = (String) spinnerCompound.getSelectedItem();
            String weightStr = etParamWeight.getText().toString().trim();
            if (!weightStr.isEmpty()) {
                try { qty = Double.parseDouble(weightStr); } catch (Exception ignored) {}
            }
            unit = "Kg";
            details = "Material: " + cmp + " (" + qty + " Kg)";
        } else if (stationPos == 2 || stationPos == 3) { // Building
            String spec = (String) spinnerSpecSize.getSelectedItem();
            String mch = etBuildingMachine.getText().toString().trim();
            if (!mch.isEmpty()) machineCode = mch;
            details = "Spec: " + spec + " | Mesin: " + machineCode;
        } else if (stationPos == 4 || stationPos == 5) { // Curing
            String cav = (String) spinnerCavity.getSelectedItem();
            String def = (String) spinnerCuringDefect.getSelectedItem();
            details = "Cavity: " + cav + " | Defect: " + def;
            if (stationPos == 5) status = "REPAIR";
        } else if (stationPos == 6) { // Final Inspection
            String grade = (String) spinnerQcGrade.getSelectedItem();
            String defNotes = etInspectionDefect.getText().toString().trim();
            status = grade.startsWith("PASS") ? "PASS" : (grade.startsWith("REPAIR") ? "REPAIR" : "SCRAP");
            details = "Grade QC: " + status + " (" + defNotes + ")";
        } else if (stationPos == 7) { // Warehouse
            String targetRack = etTargetRack.getText().toString().trim().toUpperCase();
            if (targetRack.isEmpty()) targetRack = "RAK-A01";
            details = "Alokasi Rak: " + targetRack;
            // Update rack locally in Room DB
            scanRepository.assignRackOffline(targetRack, barcode, "Ban Jadi Radial", 1.0);
        }

        scanRepository.processDetailedScan(barcode, activeStationCode, machineCode, details, qty, unit, status, new ScanRepository.ScanCallback() {
            @Override
            public void onScanSaved(OfflineScanEntity scanEntity, int pendingCount) {
                runOnUiThread(() -> {
                    etBarcodeScan.setText("");
                    cardLastScan.setVisibility(View.VISIBLE);
                    tvScanTitle.setText("SCAN BERHASIL (LOCAL ROOM DB)");
                    tvScanDetails.setText("Barcode: " + scanEntity.getBarcode() + " | Stasiun: " + scanEntity.getActionType() + " | Detail: " + scanEntity.getDetails());
                    tvPendingSyncBadge.setText("Pending: " + pendingCount);

                    loadScanHistory();
                    requestFocusOnScanner();
                });
            }

            @Override
            public void onError(String message) {
                runOnUiThread(() -> {
                    Toast.makeText(OperatorLiteActivity.this, message, Toast.LENGTH_SHORT).show();
                    requestFocusOnScanner();
                });
            }
        });
    }

    private void loadScanHistory() {
        scanRepository.getScanHistory((scans, pendingCount) -> runOnUiThread(() -> {
            tvPendingSyncBadge.setText("Pending: " + pendingCount);
            historyAdapter.setScanList(scans);
        }));
    }

    private void requestFocusOnScanner() {
        etBarcodeScan.requestFocus();
    }

    private void showSosEmergencyDialog() {
        new AlertDialog.Builder(this)
                .setTitle("PANGGILAN DARURAT K3 MEDIS")
                .setMessage("Apakah Anda ingin mengirim Panggilan Darurat Medis / Ambulans ke Pabrik?")
                .setPositiveButton("KIRIM SOS SEKARANG", (dialog, which) -> {
                    k3Repository.triggerEmergencyCall("Stasiun " + activeStationCode, "Kecelakaan Kerja PDT", "HIGH_EMERGENCY", new K3Repository.K3Callback() {
                        @Override
                        public void onSuccess(String callNo, String message) {
                            runOnUiThread(() -> Toast.makeText(OperatorLiteActivity.this, message + " (" + callNo + ")", Toast.LENGTH_LONG).show());
                        }

                        @Override
                        public void onError(String error) {
                            runOnUiThread(() -> Toast.makeText(OperatorLiteActivity.this, "Gagal SOS: " + error, Toast.LENGTH_SHORT).show());
                        }
                    });
                })
                .setNegativeButton("Batal", null)
                .show();
    }

    @Override
    protected void onResume() {
        super.onResume();
        requestFocusOnScanner();
        loadScanHistory();
    }
}
