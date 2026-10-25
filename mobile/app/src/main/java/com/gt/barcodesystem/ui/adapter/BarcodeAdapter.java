package com.gt.barcodesystem.ui.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;

import java.util.ArrayList;
import java.util.List;

public class BarcodeAdapter extends RecyclerView.Adapter<BarcodeAdapter.BarcodeViewHolder> {

    private final Context context;
    private List<BarcodeEntity> barcodeList = new ArrayList<>();

    public BarcodeAdapter(Context context) {
        this.context = context;
    }

    public void setBarcodeList(List<BarcodeEntity> barcodes) {
        this.barcodeList = barcodes != null ? barcodes : new ArrayList<>();
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public BarcodeViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_barcode_card, parent, false);
        return new BarcodeViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull BarcodeViewHolder holder, int position) {
        BarcodeEntity item = barcodeList.get(position);
        holder.tvCode.setText(item.getBarcode());
        String details = (item.getProductName() != null ? item.getProductName() : "") +
                (item.getSizeName() != null ? " | " + item.getSizeName() : "");
        holder.tvProductName.setText(details.isEmpty() ? "Standard Master" : details);
        holder.tvTypeBadge.setText(item.getBarcodeType() != null ? item.getBarcodeType() : "PRODUCT");
    }

    @Override
    public int getItemCount() {
        return barcodeList.size();
    }

    static class BarcodeViewHolder extends RecyclerView.ViewHolder {
        TextView tvCode, tvProductName, tvTypeBadge;

        public BarcodeViewHolder(@NonNull View itemView) {
            super(itemView);
            tvCode = itemView.findViewById(R.id.tvBarcodeCode);
            tvProductName = itemView.findViewById(R.id.tvBarcodeProductName);
            tvTypeBadge = itemView.findViewById(R.id.tvBarcodeTypeBadge);
        }
    }
}
