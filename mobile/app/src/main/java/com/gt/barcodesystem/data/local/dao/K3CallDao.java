package com.gt.barcodesystem.data.local.dao;

import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.OnConflictStrategy;
import androidx.room.Query;

import com.gt.barcodesystem.data.local.entity.K3CallEntity;

import java.util.List;

@Dao
public interface K3CallDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    long insert(K3CallEntity call);

    @Query("SELECT * FROM k3_ambulance_calls WHERE sync_status = 0 ORDER BY local_id ASC")
    List<K3CallEntity> getPendingCalls();

    @Query("SELECT * FROM k3_ambulance_calls ORDER BY local_id DESC LIMIT 30")
    List<K3CallEntity> getAllCalls();

    @Query("UPDATE k3_ambulance_calls SET status = :status, arrival_at = :arrivalAt WHERE local_id = :localId")
    void updateStatus(long localId, String status, String arrivalAt);

    @Query("UPDATE k3_ambulance_calls SET sync_status = 1 WHERE local_id IN (:localIds)")
    void markAsSynced(List<Long> localIds);
}
