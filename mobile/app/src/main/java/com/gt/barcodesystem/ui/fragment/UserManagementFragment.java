package com.gt.barcodesystem.ui.fragment;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AlertDialog;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.button.MaterialButton;
import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.remote.model.UserSaveRequest;
import com.gt.barcodesystem.data.repository.UserRepository;
import com.gt.barcodesystem.ui.adapter.UserAdapter;

import java.util.List;

public class UserManagementFragment extends Fragment {

    private RecyclerView rvUserList;
    private MaterialButton btnAddUser;
    private UserRepository userRepository;
    private UserAdapter userAdapter;

    private final String[] roles = {"operator", "leader", "section_head", "admin_dept", "dept_head", "super_admin"};

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_user_management, container, false);

        rvUserList = view.findViewById(R.id.rvUserList);
        btnAddUser = view.findViewById(R.id.btnAddUser);

        rvUserList.setLayoutManager(new LinearLayoutManager(getContext()));
        userAdapter = new UserAdapter(requireContext());
        rvUserList.setAdapter(userAdapter);

        userRepository = new UserRepository(requireContext());

        btnAddUser.setOnClickListener(v -> showAddUserDialog());

        loadUsers();

        return view;
    }

    private void loadUsers() {
        userRepository.fetchUsers(new UserRepository.UserListCallback() {
            @Override
            public void onUsersLoaded(List<UserEntity> users) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> userAdapter.setUserList(users));
            }

            @Override
            public void onError(String error) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> Toast.makeText(getContext(), error, Toast.LENGTH_SHORT).show());
            }
        });
    }

    private void showAddUserDialog() {
        if (getContext() == null) return;

        LinearLayout container = new LinearLayout(getContext());
        container.setOrientation(LinearLayout.VERTICAL);
        container.setPadding(40, 20, 40, 20);

        EditText etNik = new EditText(getContext());
        etNik.setHint("NIK (contoh: OP-WH-05)");
        container.addView(etNik);

        EditText etFullName = new EditText(getContext());
        etFullName.setHint("Nama Lengkap Karyawan");
        container.addView(etFullName);

        EditText etPhone = new EditText(getContext());
        etPhone.setHint("Nomor HP / WhatsApp");
        container.addView(etPhone);

        TextView tvRole = new TextView(getContext());
        tvRole.setText("Peran / Hak Akses (RBAC):");
        tvRole.setPadding(0, 16, 0, 4);
        container.addView(tvRole);

        Spinner spRole = new Spinner(getContext());
        ArrayAdapter<String> roleAdapter = new ArrayAdapter<>(getContext(), android.R.layout.simple_spinner_item, roles);
        roleAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spRole.setAdapter(roleAdapter);
        container.addView(spRole);

        new AlertDialog.Builder(requireContext())
                .setTitle("Tambah Karyawan Baru (Super Admin)")
                .setView(container)
                .setPositiveButton("Simpan", (dialog, which) -> {
                    String nik = etNik.getText().toString().trim().toUpperCase();
                    String name = etFullName.getText().toString().trim();
                    String phone = etPhone.getText().toString().trim();
                    String role = roles[spRole.getSelectedItemPosition()];

                    if (nik.isEmpty() || name.isEmpty()) {
                        Toast.makeText(getContext(), "Harap isi NIK dan Nama", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    UserSaveRequest req = new UserSaveRequest(null, nik, nik.toLowerCase(), "password123", name, role, 1, 1, phone.isEmpty() ? "0812345678" : phone);
                    userRepository.saveUser(req, new UserRepository.UserSaveCallback() {
                        @Override
                        public void onSuccess(String message) {
                            if (getActivity() == null) return;
                            getActivity().runOnUiThread(() -> {
                                Toast.makeText(getContext(), message, Toast.LENGTH_SHORT).show();
                                loadUsers();
                            });
                        }

                        @Override
                        public void onError(String error) {
                            if (getActivity() == null) return;
                            getActivity().runOnUiThread(() -> Toast.makeText(getContext(), error, Toast.LENGTH_SHORT).show());
                        }
                    });
                })
                .setNegativeButton("Batal", null)
                .show();
    }
}
