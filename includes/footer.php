<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Footer & Modal Global (Layout Footer & Global Popups)
 * File : includes/footer.php
 * ==============================================================================================
 * 
 * [FUNGSI & KEGUNAAN]:
 * Menutup pembungkus halaman (content-body & main-wrapper), menyediakan modal global:
 * 1. Modal Cepat Pelaporan Hambatan Mesin 4M (Breakdown)
 * 2. Modal Darurat Panggilan Ambulans / K3 (SOS)
 * Serta meng-import berkas script JavaScript utama (assets/js/main.js).
 */
?>
    </div> <!-- Penutup .content-body -->
</div> <!-- Penutup .main-wrapper -->

<!-- MODAL GLOBAL 1: Form Cepat Pelaporan Hambatan Produksi (4M / Breakdown) -->
<div class="modal-overlay" id="downtimeModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Lapor Hambatan Produksi (4M / Breakdown)</h3>
            <button class="modal-close" onclick="closeModal('downtimeModal')">&times;</button>
        </div>
        <form id="quickDowntimeForm">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">NIK Pelapor</label>
                        <input type="text" name="operator_nik" class="form-input" value="<?= htmlspecialchars($currentUser['nik'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Kategori 4M</label>
                        <select name="category_4m" class="form-select" required>
                            <option value="Machine">Machine (Mesin Rusak / Error)</option>
                            <option value="Material">Material (Bahan Kurang / Cacat)</option>
                            <option value="Method">Method (SOP / Parameter Salah)</option>
                            <option value="Man">Man (Kekurangan Operator / Skill)</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">Departemen</label>
                        <select name="dept_id" class="form-select" id="dtDeptSelect" required>
                            <?php if (Auth::isPlantLevel()): ?>
                                <option value="WH">Dept Gudang Bahan (WH)</option>
                                <option value="MAT">Dept Material (MAT)</option>
                                <option value="BLD">Dept Building (BLD)</option>
                                <option value="CUR">Dept Curing (CUR)</option>
                                <option value="FI">Dept Final Inspection (FI)</option>
                            <?php else: ?>
                                <option value="<?= htmlspecialchars($currentUser['dept_code'] ?? 'CUR') ?>" selected>
                                    <?= htmlspecialchars($currentUser['dept_name'] ?? $currentUser['dept_code']) ?> (<?= htmlspecialchars($currentUser['dept_code'] ?? '') ?>)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Kode / Nomor Mesin</label>
                        <input type="text" name="machine_id" class="form-input" placeholder="Contoh: BLD-M02 / CUR-P05" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label required">Keterangan Masalah / Gejala</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Jelaskan detail kendala, indikator alarm, atau hambatan yang terjadi..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('downtimeModal')">Batal</button>
                <button type="submit" class="btn btn-danger">Kirim Laporan Hambatan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL GLOBAL 2: Form Panggilan Darurat Medis & Tanggap K3 (SOS) -->
<div class="modal-overlay" id="sosEmergencyModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header" style="background: rgba(239, 68, 68, 0.1);">
            <h3 class="modal-title" style="color: #ef4444;">Panggilan Darurat Medis & K3 (SOS)</h3>
            <button class="modal-close" onclick="closeModal('sosEmergencyModal')">&times;</button>
        </div>
        <form action="api/sos_call.php" method="POST">
            <div class="modal-body">
                <p style="font-size: 13.5px; color: var(--text-secondary); margin-bottom: 16px;">
                    Peringatan: Gunakan fitur ini hanya dalam situasi kecelakaan kerja, kebakaran, atau keadaan darurat medis kritis! Ambulans dan tim tanggap darurat K3 akan segera diberangkatkan.
                </p>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label required">Lokasi Kejadian (Dept / Area)</label>
                    <input type="text" name="location" class="form-input" placeholder="Contoh: Gedung Curing Line B" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label required">Deskripsi Insiden</label>
                    <textarea name="incident_notes" class="form-textarea" rows="2" placeholder="Korban pingsan / terbakar / luka berat..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('sosEmergencyModal')">Tutup</button>
                <button type="submit" class="btn btn-danger" style="font-weight: 700;">PANGGIL AMBULANS SEKARANG</button>
            </div>
        </form>
    </div>
</div>

<!-- Berkas JavaScript Utama (Live Clock, Dark Mode Toggle, & Interaksi Modal) -->
<script src="assets/js/main.js"></script>
</body>
</html>