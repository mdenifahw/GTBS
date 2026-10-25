package com.gt.barcodesystem.data.local.dao;

import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.OnConflictStrategy;
import androidx.room.Query;
import androidx.room.Update;

import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;

import java.util.List;

@Dao
public interface OfflineScanDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    long insert(OfflineScanEntity scan);

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertAll(List<OfflineScanEntity> scans);

    @Query("SELECT * FROM offline_scans WHERE sync_status = 0 ORDER BY local_id ASC")
    List<OfflineScanEntity> getPendingScans();

    @Query("UPDATE offline_scans SET sync_status = 1 WHERE local_id IN (:localIds)")
    void markAsSynced(List<Long> localIds);

    @Query("SELECT * FROM offline_scans ORDER BY local_id DESC")
    List<OfflineScanEntity> getAllScans();

    @Query("SELECT COUNT(*) FROM offline_scans WHERE sync_status = 0")
    int getPendingCount();

    @Update
    void update(OfflineScanEntity scan);
}
