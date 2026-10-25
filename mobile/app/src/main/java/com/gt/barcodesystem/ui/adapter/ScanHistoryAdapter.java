package com.gt.barcodesystem.ui.adapter;

import android.content.Context;
import android.graphics.Color;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;

import java.util.ArrayList;
import java.util.List;

public class ScanHistoryAdapter extends RecyclerView.Adapter<ScanHistoryAdapter.ScanViewHolder> {

    private final Context context;
    private List<OfflineScanEntity> scanList = new ArrayList<>();

    public ScanHistoryAdapter(Context context) {
        this.context = context;
    }

    public void setScanList(List<OfflineScanEntity> scans) {
        this.scanList = scans != null ? scans : new ArrayList<>();
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public ScanViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_scan_history, parent, false);
        return new ScanViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ScanViewHolder holder, int position) {
        OfflineScanEntity item = scanList.get(position);
        holder.tvBarcode.setText(item.getBarcode());
        holder.tvActionTime.setText(item.getActionType() + " | " + item.getCreatedAt());

        if (item.getSyncStatus() == 1) {
            holder.tvSyncStatus.setText("SYNCED");
            holder.tvSyncStatus.setTextColor(Color.parseColor("#10b981"));
        } else {
            holder.tvSyncStatus.setText("PENDING");
            holder.tvSyncStatus.setTextColor(Color.parseColor("#f59e0b"));
        }
    }

    @Override
    public int getItemCount() {
        return scanList.size();
    }

    static class ScanViewHolder extends RecyclerView.ViewHolder {
        TextView tvBarcode, tvActionTime, tvSyncStatus;

        public ScanViewHolder(@NonNull View itemView) {
            super(itemView);
            tvBarcode = itemView.findViewById(R.id.tvItemBarcode);
            tvActionTime = itemView.findViewById(R.id.tvItemActionTime);
            tvSyncStatus = itemView.findViewById(R.id.tvItemSyncStatus);
        }
    }
}
