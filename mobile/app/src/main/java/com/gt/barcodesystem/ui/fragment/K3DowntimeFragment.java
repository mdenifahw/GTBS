package com.gt.barcodesystem.ui.fragment;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.google.android.material.button.MaterialButton;
import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.repository.K3Repository;

import java.util.List;

public class K3DowntimeFragment extends Fragment {

    private TextView tvAmbulanceStatus;
    private MaterialButton btnOnSite, btnCompleteSos, btnReportDowntime, btnTriggerSos;
    private EditText etMachineCode, etDowntimeDescription, etSosLocation, etSosCondition;
    private K3Repository k3Repository;
    private long currentActiveCallId = 0;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_k3_downtime, container, false);

        tvAmbulanceStatus = view.findViewById(R.id.tvAmbulanceStatus);
        btnOnSite = view.findViewById(R.id.btnOnSite);
        btnCompleteSos = view.findViewById(R.id.btnCompleteSos);
        btnReportDowntime = view.findViewById(R.id.btnReportDowntime);
        btnTriggerSos = view.findViewById(R.id.btnTriggerSos);

        etMachineCode = view.findViewById(R.id.etMachineCode);
        etDowntimeDescription = view.findViewById(R.id.etDowntimeDescription);
        etSosLocation = view.findViewById(R.id.etSosLocation);
        etSosCondition = view.findViewById(R.id.etSosCondition);

        k3Repository = new K3Repository(requireContext());

        setupListeners();
        loadActiveK3Status();

        return view;
    }

    private void setupListeners() {
        btnTriggerSos.setOnClickListener(v -> {
            String location = etSosLocation.getText().toString().trim();
            String condition = etSosCondition.getText().toString().trim();
            if (location.isEmpty() || condition.isEmpty()) {
                Toast.makeText(getContext(), "Harap isi Lokasi dan Kondisi Insiden", Toast.LENGTH_SHORT).show();
                return;
            }

            k3Repository.triggerEmergencyCall(location, condition, "HIGH_EMERGENCY", new K3Repository.K3Callback() {
                @Override
                public void onSuccess(String callNo, String message) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> {
                        Toast.makeText(getContext(), message + " (" + callNo + ")", Toast.LENGTH_LONG).show();
                        etSosLocation.setText("");
                        etSosCondition.setText("");
                        loadActiveK3Status();
                    });
                }

                @Override
                public void onError(String error) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> Toast.makeText(getContext(), "Gagal: " + error, Toast.LENGTH_SHORT).show());
                }
            });
        });

        btnOnSite.setOnClickListener(v -> {
            if (currentActiveCallId <= 0) {
                Toast.makeText(getContext(), "Tidak ada panggilan aktif untuk di-update.", Toast.LENGTH_SHORT).show();
                return;
            }
            k3Repository.updateK3Status(currentActiveCallId, "ON_SITE", new K3Repository.K3Callback() {
                @Override
                public void onSuccess(String callNo, String message) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> {
                        Toast.makeText(getContext(), "Status: TIBA DI TKP (ON SITE)", Toast.LENGTH_SHORT).show();
                        loadActiveK3Status();
                    });
                }

                @Override
                public void onError(String error) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> Toast.makeText(getContext(), "Gagal update: " + error, Toast.LENGTH_SHORT).show());
                }
            });
        });

        btnCompleteSos.setOnClickListener(v -> {
            if (currentActiveCallId <= 0) {
                Toast.makeText(getContext(), "Tidak ada panggilan aktif untuk diselesaikan.", Toast.LENGTH_SHORT).show();
                return;
            }
            k3Repository.updateK3Status(currentActiveCallId, "COMPLETED", new K3Repository.K3Callback() {
                @Override
                public void onSuccess(String callNo, String message) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> {
                        Toast.makeText(getContext(), "Insiden dinyatakan selesai (COMPLETED).", Toast.LENGTH_SHORT).show();
                        loadActiveK3Status();
                    });
                }

                @Override
                public void onError(String error) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> Toast.makeText(getContext(), "Gagal selesaikan: " + error, Toast.LENGTH_SHORT).show());
                }
            });
        });

        btnReportDowntime.setOnClickListener(v -> {
            String machine = etMachineCode.getText().toString().trim();
            String desc = etDowntimeDescription.getText().toString().trim();
            if (machine.isEmpty() || desc.isEmpty()) {
                Toast.makeText(getContext(), "Harap isi Kode Mesin & Deskripsi Kerusakan", Toast.LENGTH_SHORT).show();
                return;
            }

            k3Repository.reportDowntime(machine, 1, desc, 15, new K3Repository.K3Callback() {
                @Override
                public void onSuccess(String callNo, String message) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> {
                        Toast.makeText(getContext(), message, Toast.LENGTH_LONG).show();
                        etMachineCode.setText("");
                        etDowntimeDescription.setText("");
                    });
                }

                @Override
                public void onError(String error) {
                    if (getActivity() == null) return;
                    getActivity().runOnUiThread(() -> Toast.makeText(getContext(), "Gagal: " + error, Toast.LENGTH_SHORT).show());
                }
            });
        });
    }

    private void loadActiveK3Status() {
        k3Repository.fetchActiveK3Calls(new K3Repository.K3ListCallback() {
            @Override
            public void onLoaded(List<K3CallEntity> calls) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> {
                    if (calls != null && !calls.isEmpty()) {
                        K3CallEntity active = null;
                        for (K3CallEntity c : calls) {
                            if (!"COMPLETED".equalsIgnoreCase(c.getStatus()) && !"CANCELLED".equalsIgnoreCase(c.getStatus())) {
                                active = c;
                                break;
                            }
                        }
                        if (active != null) {
                            currentActiveCallId = active.getLocalId() > 0 ? active.getLocalId() : 1;
                            tvAmbulanceStatus.setText("Call #" + active.getCallNo() + " | Status: " + active.getStatus() + "\nLokasi: " + active.getLocationDetail() + "\nKondisi: " + active.getPatientCondition());
                        } else {
                            currentActiveCallId = 0;
                            tvAmbulanceStatus.setText("Status: Semua insiden telah selesai ditangani.");
                        }
                    } else {
                        currentActiveCallId = 0;
                        tvAmbulanceStatus.setText("Status: Tidak ada panggilan darurat aktif.");
                    }
                });
            }

            @Override
            public void onError(String error) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> tvAmbulanceStatus.setText("Status: Mode Offline"));
            }
        });
    }
}
