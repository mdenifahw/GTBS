/**
 * ==============================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Script Pendukung Mobile App Shell & Bottom Navigation
 * File: assets/js/mobile.js
 * ==============================================================================
 */

(function () {
    'use strict';

    // 1. Inisialisasi Deteksi Mobile App
    const isMobileApp = document.body.classList.contains('m-app');
    if (!isMobileApp) return;

    // 2. Monitoring Status Jaringan (Online / Offline)
    function updateNetworkStatus() {
        const netBadge = document.getElementById('mNetBadge');
        const isOnline = navigator.onLine;

        if (netBadge) {
            if (isOnline) {
                netBadge.className = 'm-net online';
                netBadge.innerHTML = '<i></i> <span>Online</span>';
            } else {
                netBadge.className = 'm-net offline';
                netBadge.innerHTML = '<i></i> <span>Offline (PDT)</span>';
            }
        }

        const banner = document.getElementById('mOfflineBanner');
        if (banner) {
            banner.style.display = isOnline ? 'none' : 'flex';
        }
    }

    window.addEventListener('online', () => {
        updateNetworkStatus();
        showMobileToast('Jaringan terhubung kembali. Memulai auto-sync...', 'success');
        triggerMobileSync(true);
    });

    window.addEventListener('offline', () => {
        updateNetworkStatus();
        showMobileToast('Mode Offline Aktif. Hasil scan disimpan di PDT.', 'warning');
    });

    // 3. Sistem Toast Notifikasi Mobile
    window.showMobileToast = function (message, type = 'info') {
        let wrap = document.getElementById('mToastWrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'mToastWrap';
            wrap.className = 'm-toast-wrap';
            document.body.appendChild(wrap);
        }

        const toast = document.createElement('div');
        toast.className = `m-toast ${type}`;
        toast.textContent = message;
        wrap.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    };

    // 4. Update Angka Pending Offline Scans
    window.updatePendingCount = function () {
        let count = 0;
        if (window.AndroidPDT && typeof window.AndroidPDT.getPendingCount === 'function') {
            count = window.AndroidPDT.getPendingCount();
        } else {
            count = parseInt(localStorage.getItem('gtbs_pending_count') || '0', 10);
        }

        const badges = document.querySelectorAll('.m-pending-count, #mSyncPendingBadge, #mBarcodeNavBadge');
        badges.forEach(el => {
            el.textContent = count;
            if (count > 0) {
                el.classList.add('has');
                el.classList.add('show');
            } else {
                el.classList.remove('has');
                el.classList.remove('show');
            }
        });
    };

    // 5. Eksekusi Sync Desk (Tombol Sinkronisasi)
    window.triggerMobileSync = function (isSilent = false) {
        const syncBtn = document.getElementById('mBtnSyncDesk');
        if (syncBtn) {
            syncBtn.classList.add('spinning');
            syncBtn.disabled = true;
        }

        if (!isSilent) {
            showMobileToast('Menyinkronkan data antrean ke server database...', 'info');
        }

        if (window.AndroidPDT && typeof window.AndroidPDT.syncNow === 'function') {
            window.AndroidPDT.syncNow();
            setTimeout(() => {
                if (syncBtn) {
                    syncBtn.classList.remove('spinning');
                    syncBtn.disabled = false;
                }
                updatePendingCount();
                if (!isSilent) {
                    showMobileToast('Sinkronisasi selesai diproses oleh background sync.', 'success');
                }
            }, 1500);
        } else {
            fetch('api/mobile_sync.php?action=pull_master')
                .then(r => r.json())
                .then(() => {
                    localStorage.setItem('gtbs_pending_count', '0');
                    if (syncBtn) {
                        syncBtn.classList.remove('spinning');
                        syncBtn.disabled = false;
                    }
                    updatePendingCount();
                    if (!isSilent) {
                        showMobileToast('Semua data berhasil sinkron dengan database server!', 'success');
                    }
                })
                .catch(() => {
                    if (syncBtn) {
                        syncBtn.classList.remove('spinning');
                        syncBtn.disabled = false;
                    }
                    if (!isSilent) {
                        showMobileToast('Gagal sinkron. Pastikan koneksi server aktif.', 'danger');
                    }
                });
        }
    };

    // 6. Tombol Scan Tengah (Center Floating Action Button)
    window.handleCenterScanClick = function (roleBarcodeUrl) {
        const scanInput = document.querySelector(
            '#barcodeScannerInput, input[name="barcode"], input[name="gt_barcode"], input[name="tire_barcode"], input[name="bladder_barcode"], input[name="item_barcode"], input[name="batch_barcode"]'
        );

        if (scanInput) {
            scanInput.focus();
            if (scanInput.select) scanInput.select();

            if (window.AndroidPDT) {
                if (typeof window.AndroidPDT.triggerVibration === 'function') {
                    window.AndroidPDT.triggerVibration(120);
                }
                if (typeof window.AndroidPDT.playSuccessBeep === 'function') {
                    window.AndroidPDT.playSuccessBeep();
                }
            }

            const hud = document.getElementById('mScanHud');
            if (hud) {
                hud.classList.add('show');
                clearTimeout(window.__hudTimer);
                window.__hudTimer = setTimeout(() => hud.classList.remove('show'), 4000);
            }

            scanInput.classList.add('m-scan-target');
            setTimeout(() => scanInput.classList.remove('m-scan-target'), 2500);

            showMobileToast('Scanner Siap! Arahkan laser PDT ke barcode ban/material.', 'success');
        } else {
            showMobileToast('Membuka halaman Barcode & Scanner...', 'info');
            const targetUrl = roleBarcodeUrl || 'operator_lite.php';
            window.location.href = targetUrl + (targetUrl.includes('?') ? '&' : '?') + 'autofocus_scan=1';
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        updateNetworkStatus();
        updatePendingCount();

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autofocus_scan') === '1') {
            setTimeout(() => {
                const inp = document.querySelector(
                    '#barcodeScannerInput, input[name="barcode"], input[name="gt_barcode"], input[name="tire_barcode"]'
                );
                if (inp) {
                    inp.focus();
                    if (inp.select) inp.select();
                    inp.classList.add('m-scan-target');
                    setTimeout(() => inp.classList.remove('m-scan-target'), 2000);
                }
            }, 300);
        }
    });

})();
