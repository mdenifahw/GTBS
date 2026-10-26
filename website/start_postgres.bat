@echo off
title GT Barcode System - PostgreSQL Service Runner
echo ========================================================
echo   Menjalankan Database PostgreSQL PT Gajah Tunggal Tbk
echo   Port: 5433 ^| Database: gt_tire_plant
echo ========================================================

if exist "C:\xampp\htdocs\Tire_PlantV.3A\server\src\index.js" (
    cd /d "C:\xampp\htdocs\Tire_PlantV.3A\server"
    node src/index.js
    goto :end
)

if exist "%~dp0..\Tire_PlantV.3A\server\src\index.js" (
    cd /d "%~dp0..\Tire_PlantV.3A\server"
    node src/index.js
    goto :end
)

if exist "D:\laragon\www\Tire_PlantV.3A\server\src\index.js" (
    cd /d "D:\laragon\www\Tire_PlantV.3A\server"
    node src/index.js
    goto :end
)

echo [ERROR] Folder Tire_PlantV.3A\server tidak ditemukan!
pause

:end
