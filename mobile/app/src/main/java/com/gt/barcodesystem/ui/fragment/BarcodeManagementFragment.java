package com.gt.barcodesystem.ui.fragment;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.button.MaterialButton;
import com.gt.barcodesystem.R;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.remote.model.BarcodeGenerateRequest;
import com.gt.barcodesystem.data.repository.BarcodeRepository;
import com.gt.barcodesystem.ui.adapter.BarcodeAdapter;

import java.util.List;

public class BarcodeManagementFragment extends Fragment {

    private Spinner spinnerBarcodeType;
    private EditText etProductCode, etProductName;
    private MaterialButton btnGenerateBarcode;
    private RecyclerView rvBarcodeList;

    private BarcodeRepository barcodeRepository;
    private BarcodeAdapter barcodeAdapter;

    private final String[] types = {"PRODUCT", "MACHINE", "PALLET", "BLADDER"};

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_barcode_management, container, false);

        spinnerBarcodeType = view.findViewById(R.id.spinnerBarcodeType);
        etProductCode = view.findViewById(R.id.etProductCode);
        etProductName = view.findViewById(R.id.etProductName);
        btnGenerateBarcode = view.findViewById(R.id.btnGenerateBarcode);
        rvBarcodeList = view.findViewById(R.id.rvBarcodeList);

        ArrayAdapter<String> typeAdapter = new ArrayAdapter<>(requireContext(), android.R.layout.simple_spinner_item, types);
        typeAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerBarcodeType.setAdapter(typeAdapter);

        rvBarcodeList.setLayoutManager(new LinearLayoutManager(getContext()));
        barcodeAdapter = new BarcodeAdapter(requireContext());
        rvBarcodeList.setAdapter(barcodeAdapter);

        barcodeRepository = new BarcodeRepository(requireContext());

        btnGenerateBarcode.setOnClickListener(v -> generateBarcode());

        loadBarcodes();

        return view;
    }

    private void loadBarcodes() {
        barcodeRepository.fetchBarcodes(new BarcodeRepository.BarcodeListCallback() {
            @Override
            public void onBarcodesLoaded(List<BarcodeEntity> barcodes) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> barcodeAdapter.setBarcodeList(barcodes));
            }

            @Override
            public void onError(String error) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> Toast.makeText(getContext(), error, Toast.LENGTH_SHORT).show());
            }
        });
    }

    private void generateBarcode() {
        String type = types[spinnerBarcodeType.getSelectedItemPosition()];
        String code = etProductCode.getText().toString().trim();
        String name = etProductName.getText().toString().trim();

        if (code.isEmpty() || name.isEmpty()) {
            Toast.makeText(getContext(), "Harap isi Kode dan Nama Produk", Toast.LENGTH_SHORT).show();
            return;
        }

        BarcodeGenerateRequest req = new BarcodeGenerateRequest(type, code, name, "185/65 R15", 1);
        barcodeRepository.generateBarcode(req, new BarcodeRepository.BarcodeGenerateCallback() {
            @Override
            public void onSuccess(String barcodeStr, String message) {
                if (getActivity() == null) return;
                getActivity().runOnUiThread(() -> {
                    Toast.makeText(getContext(), message + " (" + barcodeStr + ")", Toast.LENGTH_SHORT).show();
                    etProductCode.setText("");
                    etProductName.setText("");
                    loadBarcodes();
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
