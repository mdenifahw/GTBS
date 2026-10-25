package com.gt.barcodesystem.data.local;

import android.content.Context;

import androidx.room.Database;
import androidx.room.Room;
import androidx.room.RoomDatabase;

import com.gt.barcodesystem.data.local.dao.K3CallDao;
import com.gt.barcodesystem.data.local.dao.MasterDao;
import com.gt.barcodesystem.data.local.dao.OfflineScanDao;
import com.gt.barcodesystem.data.local.dao.UserDao;
import com.gt.barcodesystem.data.local.entity.BarcodeEntity;
import com.gt.barcodesystem.data.local.entity.DepartmentEntity;
import com.gt.barcodesystem.data.local.entity.K3CallEntity;
import com.gt.barcodesystem.data.local.entity.OfflineScanEntity;
import com.gt.barcodesystem.data.local.entity.SectionEntity;
import com.gt.barcodesystem.data.local.entity.UserEntity;
import com.gt.barcodesystem.data.local.entity.WarehouseRackEntity;

@Database(
        entities = {
                OfflineScanEntity.class,
                UserEntity.class,
                DepartmentEntity.class,
                SectionEntity.class,
                BarcodeEntity.class,
                WarehouseRackEntity.class,
                K3CallEntity.class
        },
        version = 1,
        exportSchema = false
)
public abstract class AppDatabase extends RoomDatabase {

    private static final String DB_NAME = "gt_barcode_pdt.db";
    private static volatile AppDatabase INSTANCE;

    public abstract OfflineScanDao offlineScanDao();
    public abstract UserDao userDao();
    public abstract MasterDao masterDao();
    public abstract K3CallDao k3CallDao();

    public static AppDatabase getInstance(Context context) {
        if (INSTANCE == null) {
            synchronized (AppDatabase.class) {
                if (INSTANCE == null) {
                    INSTANCE = Room.databaseBuilder(
                            context.getApplicationContext(),
                            AppDatabase.class,
                            DB_NAME
                    ).fallbackToDestructiveMigration().build();
                }
            }
        }
        return INSTANCE;
    }
}
