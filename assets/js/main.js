// GTBS - Main JavaScript

document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle
    const currentTheme = localStorage.getItem('gtbs_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const now = document.documentElement.getAttribute('data-theme');
            const target = now === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('gtbs_theme', target);
        });
    }

    // Sidebar Mobile Toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.querySelector('.sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== mobileToggle) {
                sidebar.classList.remove('open');
            }
        });
    }

    // Live Clock & Shift Calculator
    function updateClock() {
        const clockEl = document.getElementById('liveClock');
        if (!clockEl) return;

        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        clockEl.textContent = `${hours}:${minutes}:${seconds}`;

        // Calculate shift: Shift 1: 07-15, Shift 2: 15-22, Shift 3: 22-07
        const h = now.getHours();
        let shiftNum = 1;
        let shiftHours = "07:00 - 15:00";
        if (h >= 15 && h < 22) {
            shiftNum = 2;
            shiftHours = "15:00 - 22:00";
        } else if (h >= 22 || h < 7) {
            shiftNum = 3;
            shiftHours = "22:00 - 07:00";
        }

        const shiftNameEl = document.getElementById('shiftName');
        const shiftHoursEl = document.getElementById('shiftHours');
        if (shiftNameEl) shiftNameEl.textContent = `Shift ${shiftNum}`;
        if (shiftHoursEl) shiftHoursEl.textContent = `(${shiftHours})`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Modal Helper
    window.openModal = function(id) {
        const m = document.getElementById(id);
        if (m) m.classList.add('open');
    };

    window.closeModal = function(id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('open');
    };

    // Close modal on click outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
            }
        });
    });

    // Quick Downtime 4M submit handler
    const downtimeForm = document.getElementById('quickDowntimeForm');
    if (downtimeForm) {
        downtimeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(downtimeForm);
            try {
                const res = await fetch('api/report_downtime.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert('Laporan Hambatan 4M berhasil dicatat ke SCADA!');
                    closeModal('downtimeModal');
                    downtimeForm.reset();
                } else {
                    alert('Gagal: ' + json.message);
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan.');
            }
        });
    }

    // =========================================================================
    // BARCODE SCANNER WORKFLOW HANDLER (SHOP FLOOR / OPERATOR INPUT)
    // =========================================================================
    // Seluruh penginputan barcode scanner mengirim teks + Enter.
    // Script ini secara cerdas memindahkan fokus ke input teks berikutnya,
    // meminimalkan sentuhan tangan pada device, dan fokus ke tombol 'Simpan Proses'
    // saat input data barcode terakhir selesai dipindai.
    document.querySelectorAll('form').forEach(form => {
        const textInputs = Array.from(form.querySelectorAll('input[type="text"]:not([readonly]):not([type="hidden"])'));
        if (textInputs.length > 0) {
            // Berikan autofocus ke input scanner pertama
            const activeInput = form.querySelector('input[autofocus]') || textInputs[0];
            if (activeInput && (!document.activeElement || !document.activeElement.matches('input, textarea, select'))) {
                setTimeout(() => {
                    activeInput.focus();
                    if (activeInput.select) activeInput.select();
                }, 100);
            }

            textInputs.forEach((input, idx) => {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const nextInput = textInputs[idx + 1];
                        if (nextInput) {
                            e.preventDefault();
                            nextInput.focus();
                            if (nextInput.select) nextInput.select();
                        } else {
                            // Pada input terakhir, fokuskan tombol submit 'Simpan Proses'
                            const submitBtn = form.querySelector('button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.focus();
                            }
                        }
                    }
                });
            });
        }
    });
});
