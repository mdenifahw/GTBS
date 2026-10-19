@echo off
title GT Barcode System - PostgreSQL Service Runner
echo ========================================================
echo  Menjalankan Database PostgreSQL PT Gajah Tunggal Tbk
echo  Port: 5433 | Database: gt_tire_plant
echo ========================================================
cd /d "%~dp0..\Tire_PlantV.3A\server"
node src/index.js
pause
