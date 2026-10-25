package com.gt.barcodesystem.ui.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.UserEntity;

import java.util.ArrayList;
import java.util.List;

public class UserAdapter extends RecyclerView.Adapter<UserAdapter.UserViewHolder> {

    private final Context context;
    private List<UserEntity> userList = new ArrayList<>();

    public UserAdapter(Context context) {
        this.context = context;
    }

    public void setUserList(List<UserEntity> users) {
        this.userList = users != null ? users : new ArrayList<>();
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public UserViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(context).inflate(R.layout.item_user_card, parent, false);
        return new UserViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull UserViewHolder holder, int position) {
        UserEntity user = userList.get(position);
        holder.tvFullName.setText(user.getFullName() != null ? user.getFullName() : user.getUsername());
        holder.tvNikRole.setText("NIK: " + user.getNik() + " | Role: " + user.getRole());
        holder.tvStatusBadge.setText(user.isActive() ? "AKTIF" : "NONAKTIF");
    }

    @Override
    public int getItemCount() {
        return userList.size();
    }

    static class UserViewHolder extends RecyclerView.ViewHolder {
        TextView tvFullName, tvNikRole, tvStatusBadge;

        public UserViewHolder(@NonNull View itemView) {
            super(itemView);
            tvFullName = itemView.findViewById(R.id.tvUserFullName);
            tvNikRole = itemView.findViewById(R.id.tvUserNikRole);
            tvStatusBadge = itemView.findViewById(R.id.tvUserStatusBadge);
        }
    }
}
