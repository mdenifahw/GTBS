@echo off
title GT Barcode System - Ngrok Launcher
color 0A
cls
echo =====================================================================
echo    GT BARCODE SYSTEM - PT GAJAH TUNGGAL TBK
echo    Publikasi Web Server Lokal ke Jaringan Internet via Ngrok
echo =====================================================================
echo.
echo Menghubungkan ke Web Server Laragon (Port 8000)...
echo.
echo Pastikan Laragon dan Service PostgreSQL sudah menyala!
echo.
"D:\laragon\bin\ngrok\ngrok.exe" http --host-header=rewrite GT_Barcode_System.test:8000
pause
