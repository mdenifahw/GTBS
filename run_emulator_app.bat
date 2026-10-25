@echo off
title GT Barcode Mobile - Standalone Emulator Runner
echo ========================================================
echo  MENJALANKAN EMULATOR & GT BARCODE MOBILE APP
echo  (Tanpa Perlu Membuka Android Studio)
echo ========================================================

set "SDK_PATH=%LOCALAPPDATA%\Android\Sdk"
set "EMULATOR=%SDK_PATH%\emulator\emulator.exe"
set "ADB=%SDK_PATH%\platform-tools\adb.exe"
set "APK_PATH=%~dp0mobile\app\build\outputs\apk\debug\app-debug.apk"

echo [1/3] Memeriksa status Emulator...
"%ADB%" devices | findstr "emulator" >nul
if %ERRORLEVEL% equ 0 (
    echo [*] Emulator sudah aktif berjalan!
) else (
    echo [*] Menjalankan Android Emulator Medium_Phone...
    start "" "%EMULATOR%" -avd Medium_Phone
    echo [*] Menunggu emulator selesai booting...
    "%ADB%" wait-for-device
)

echo [2/3] Memasang aplikasi APK terbaru...
"%ADB%" install -r "%APK_PATH%"

echo [3/3] Membuka GT Barcode System di Emulator...
"%ADB%" shell am start -n com.gt.barcodesystem/.MainActivity

echo.
echo ========================================================
echo  SUKSES! Aplikasi telah aktif di layar Emulator.
echo ========================================================
pause
