package com.gt.barcodesystem.data.local.dao;

import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.OnConflictStrategy;
import androidx.room.Query;

import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.DepartmentEntity;
import com.gt.barcodesystem.data.local.entity.SectionEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;

import java.util.List;

@Dao
public interface MasterDao {

    // Departments
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertDepartments(List<DepartmentEntity> departments);

    @Query("SELECT * FROM departments WHERE is_active = 1")
    List<DepartmentEntity> getAllDepartments();

    @Query("SELECT * FROM departments WHERE code = :code LIMIT 1")
    DepartmentEntity getDepartmentByCode(String code);

    // Sections
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertSections(List<SectionEntity> sections);

    @Query("SELECT * FROM sections WHERE is_active = 1")
    List<SectionEntity> getAllSections();

    @Query("SELECT * FROM sections WHERE dept_id = :deptId AND is_active = 1")
    List<SectionEntity> getSectionsByDept(int deptId);

    // Barcodes
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertBarcodes(List<BarcodeEntity> barcodes);

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertBarcode(BarcodeEntity barcode);

    @Query("SELECT * FROM barcodes ORDER BY id DESC")
    List<BarcodeEntity> getAllBarcodes();

    @Query("SELECT * FROM barcodes WHERE barcode_type = :type ORDER BY id DESC")
    List<BarcodeEntity> getBarcodesByType(String type);

    @Query("SELECT * FROM barcodes WHERE barcode = :barcode LIMIT 1")
    BarcodeEntity getBarcode(String barcode);

    // Warehouse Racks
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertWarehouseRacks(List<WarehouseRackEntity> racks);

    @Query("SELECT * FROM warehouse_racks WHERE rack_code = :rackCode LIMIT 1")
    WarehouseRackEntity getRackByCode(String rackCode);

    @Query("SELECT * FROM warehouse_racks ORDER BY rack_code ASC")
    List<WarehouseRackEntity> getAllRacks();

    @Query("SELECT * FROM warehouse_racks WHERE is_occupied = 0 ORDER BY rack_code ASC")
    List<WarehouseRackEntity> getAvailableRacks();

    @Query("SELECT * FROM warehouse_racks WHERE current_barcode = :barcode LIMIT 1")
    WarehouseRackEntity getRackByBarcode(String barcode);

    @Query("UPDATE warehouse_racks SET is_occupied = 1, status = 'OCCUPIED', current_barcode = :barcode, current_item_name = :itemName, current_qty = :qty WHERE rack_code = :rackCode")
    void assignRack(String rackCode, String barcode, String itemName, double qty);
}
