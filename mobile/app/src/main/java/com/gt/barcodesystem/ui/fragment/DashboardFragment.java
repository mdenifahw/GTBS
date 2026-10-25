package com.gt.barcodesystem.ui.fragment;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.remote.model.DashboardSummaryResponse;
import com.gt.barcodesystem.data.repository.DashboardRepository;

import java.util.Locale;

public class DashboardFragment extends Fragment {

    private TextView tvKpiProduced, tvKpiTarget, tvKpiK3, tvKpiDowntime;
    private DashboardRepository dashboardRepository;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_dashboard, container, false);

        tvKpiProduced = view.findViewById(R.id.tvKpiProduced);
        tvKpiTarget = view.findViewById(R.id.tvKpiTarget);
        tvKpiK3 = view.findViewById(R.id.tvKpiK3);
        tvKpiDowntime = view.findViewById(R.id.tvKpiDowntime);

        dashboardRepository = new DashboardRepository(requireContext());

        loadDashboardData();

        return view;
    }

    private void loadDashboardData() {
        dashboardRepository.fetchDashboardSummary(new DashboardRepository.DashboardCallback() {
            @Override
            public void onDataLoaded(DashboardSummaryResponse summary) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> {
                    tvKpiProduced.setText(summary.getTotalProducedToday() + " Pcs");
                    tvKpiTarget.setText(String.format(Locale.getDefault(), "%.1f %%", summary.getKpiTargetPercentage()));
                    tvKpiK3.setText(summary.getActiveK3Incidents() + " Insiden");
                    tvKpiDowntime.setText(summary.getActiveDowntimeMachines() + " Mesin");
                });
            }

            @Override
            public void onError(String error) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> Toast.makeText(getContext(), error, Toast.LENGTH_SHORT).show());
            }
        });
    }
}
