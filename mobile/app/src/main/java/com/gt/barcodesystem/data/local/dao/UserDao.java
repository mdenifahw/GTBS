package com.gt.barcodesystem.data.local.dao;

import androidx.room.Dao;
import androidx.room.Insert;
import androidx.room.OnConflictStrategy;
import androidx.room.Query;
import androidx.room.Update;

import com.gt.barcodesystem.data.local.entity.UserEntity;

import java.util.List;

@Dao
public interface UserDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertUser(UserEntity user);

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    void insertAllUsers(List<UserEntity> users);

    @Query("SELECT * FROM users ORDER BY full_name ASC")
    List<UserEntity> getAllUsers();

    @Query("SELECT * FROM users WHERE dept_id = :deptId ORDER BY full_name ASC")
    List<UserEntity> getUsersByDept(int deptId);

    @Query("SELECT * FROM users WHERE username = :username LIMIT 1")
    UserEntity getUserByUsername(String username);

    @Query("SELECT * FROM users WHERE nik = :nik LIMIT 1")
    UserEntity getUserByNik(String nik);

    @Query("UPDATE users SET is_active = :isActive WHERE id = :userId")
    void updateUserStatus(int userId, boolean isActive);

    @Update
    void updateUser(UserEntity user);

    @Query("DELETE FROM users")
    void clearUsers();
}
