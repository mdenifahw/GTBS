<?php
/**
 * ==============================================================================================
 * SISTEM BARCODE PABRIK BAN (GTBS) - PT GAJAH TUNGGAL TBK
 * Modul: Mobile App Shell Helper (Deteksi Aplikasi Android + Model Bottom Navigation)
 * File : helpers/mobile.php
 * ==============================================================================================
 *
 * [FUNGSI & KEGUNAAN]:
 * - Mendeteksi apakah request berasal dari aplikasi Android GT Barcode (User-Agent "GTBarcodeMobile").
 * - Menyusun struktur Bottom Navbar (Home, Barcode, Scan, Message, Akun) sesuai jabatan pengguna.
 * - Menentukan tab aktif & sub-menu (chips) berdasarkan halaman yang sedang dibuka.
 *
 * [PRATINJAU DI BROWSER DESKTOP]:
 * Buka halaman apa pun dengan parameter ?app=1 untuk mengaktifkan tampilan mobile app (disimpan di cookie),
 * dan ?app=0 untuk kembali ke tampilan website biasa.
 */

if (!function_exists('gtbs_is_mobile_app')) {

    function gtbs_is_mobile_app(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;

        // Toggle pratinjau via query string (?app=1 / ?app=0)
        if (isset($_GET['app']) && !headers_sent()) {
            if ($_GET['app'] === '1') {
                setcookie('gtbs_app', '1', time() + 86400 * 30, '/');
                $_COOKIE['gtbs_app'] = '1';
            } elseif ($_GET['app'] === '0') {
                setcookie('gtbs_app', '', time() - 3600, '/');
                unset($_COOKIE['gtbs_app']);
            }
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $cached = (stripos($ua, 'GTBarcodeMobile') !== false) || (($_COOKIE['gtbs_app'] ?? '') === '1');
        return $cached;
    }

    /** Ikon SVG garis (stroke) untuk navigasi mobile. */
    function gtbs_m_icon(string $name, int $size = 22): string {
        $paths = [
            'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
            'barcode'  => '<path d="M4 6v12M7 6v12M11 6v12M14 6v12M17 6v12M20 6v12"/><path d="M2 4h3M19 4h3M2 20h3M19 20h3" opacity=".55"/>',
            'scan'     => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
            'message'  => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'dashboard'=> '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'scada'    => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'tag'      => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
            'bolt'     => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
            'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
            'layers'   => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'build'    => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>',
            'flame'    => '<path d="M12 22a7 7 0 0 0 7-7c0-4-3-6-4-9-1 2-2 3-4 3 0-2-1-4-2-5-1 4-4 6-4 11a7 7 0 0 0 7 7Z"/>',
            'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
            'chat'     => '<path d="M4 5h16v11H8l-4 4V5Z"/><path d="M8 10h8M8 13h5"/>',
            'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="M12 9v6M9 12h6"/>',
            'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
            'sync'     => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 12A9 9 0 0 1 18.5 5.8L21 8"/><path d="M3 21v-5h5M21 3v5h-5"/>',
            'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 17-5-5 5-5M5 12h11"/>',
            'moon'     => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
            'sos'      => '<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/>',
            'chevron'  => '<path d="m9 6 6 6-6 6"/>',
            'wifi'     => '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a15 15 0 0 1 20 0"/><path d="M12 19.5h.01"/>',
            'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'id'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.5 16a3.5 3.5 0 0 1 7 0M15 10h3M15 13h3"/>',
        ];
        $p = $paths[$name] ?? $paths['box'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }

    /** Metadata 5 departemen pabrik (kode => [label, url, ikon]). */
    function gtbs_m_dept_meta(): array {
        return [
            'WH'  => ['Gudang Bahan',     'dept_warehouse.php',        'box'],
            'MAT' => ['Dept Material',    'dept_material.php',         'layers'],
            'BLD' => ['Dept Building',    'dept_building.php',         'build'],
            'CUR' => ['Dept Curing',      'dept_curing.php',           'flame'],
            'FI'  => ['Final Inspection', 'dept_final_inspection.php', 'check'],
        ];
    }

    /** URL input langsung per seksi kerja (identik dengan sub-menu sidebar website). */
    function gtbs_m_section_url(string $sectionCode): ?string {
        $map = [
            'WH-RCV' => 'dept_warehouse.php?tab=penerima', 'WH-SND' => 'dept_warehouse.php?tab=pengirim',
            'MAT-MIX' => 'dept_material.php?tab=mixing', 'MAT-CAL' => 'dept_material.php?tab=calendery', 'MAT-DEL' => 'dept_material.php?tab=delivery',
            'BLD-PCR' => 'dept_building.php?size=pcr', 'BLD-SUV' => 'dept_building.php?size=suv', 'BLD-LTR' => 'dept_building.php?size=ltr', 'BLD-TBR' => 'dept_building.php?size=tbr',
            'CUR-PRE' => 'dept_curing.php?tab=curing_tire', 'CUR-SUP-LGT' => 'dept_curing.php?tab=supply_lory', 'CUR-SUP-DEL' => 'dept_curing.php?tab=supply_delivery',
            'CUR-BSU' => 'dept_curing.php?tab=bladder_installer', 'CUR-BLD' => 'dept_curing.php?tab=bladder_injection', 'CUR-REP' => 'dept_curing.php?tab=repair_tire',
            'FI-INS' => 'dept_final_inspection.php?tab=inspection', 'FI-CUT' => 'dept_final_inspection.php?tab=cutting_hair', 'FI-DEL' => 'dept_final_inspection.php?tab=delivery',
        ];
        return $map[$sectionCode] ?? null;
    }

    /** Apakah jabatan ini merupakan petugas lapangan (langsung ke halaman input stasiun)? */
    function gtbs_m_is_field_role(?array $user): bool {
        return in_array($user['role'] ?? '', ['operator', 'leader', 'section_head'], true);
    }

    /**
     * Menyusun model Bottom Navbar berdasarkan jabatan.
     * Setiap tab: ['key','label','icon','url' (tujuan saat tab ditekan),'items' => [[label,url,icon,desc]]]
     */
    function gtbs_mobile_nav(?array $user): array {
        $role = $user['role'] ?? '';
        $allowedDepts = class_exists('Auth') ? Auth::getAllowedDeptPages() : [];
        $deptMeta = gtbs_m_dept_meta();
        $isField = gtbs_m_is_field_role($user);

        // ===== HOME =====
        $home = [
            ['Dashboard', 'dashboard.php', 'dashboard', 'Ringkasan KPI produksi realtime'],
            ['SCADA Monitoring', 'scada.php', 'scada', 'Pantau mesin & lini produksi'],
        ];

        // ===== BARCODE =====
        $barcode = [];
        if ($isField) {
            $barcode[] = ['Input Stasiun', 'operator_lite.php', 'bolt', 'Form scan stasiun kerja Anda'];
            $deptCode = strtoupper($user['dept_code'] ?? '');
            if ($deptCode && isset($deptMeta[$deptCode]) && in_array($deptCode, $allowedDepts, true)) {
                $secUrl = gtbs_m_section_url($user['section_code'] ?? '') ?? $deptMeta[$deptCode][1];
                $barcode[] = [$deptMeta[$deptCode][0], $secUrl, $deptMeta[$deptCode][2], 'Halaman departemen ' . ($user['section_name'] ?? '')];
            }
            $barcode[] = ['Scan Terminal', 'scan_terminal.php', 'search', 'Lacak riwayat barcode'];
            $barcodeDest = 'operator_lite.php';
        } else {
            $barcode[] = ['Pusat Barcode', 'mobile_barcode.php', 'barcode', 'Scan cepat & Sync Desk'];
            $barcode[] = ['Scan Terminal', 'scan_terminal.php', 'search', 'Lacak riwayat barcode'];
            if (class_exists('Auth') && Auth::canGenerateBarcode()) {
                $barcode[] = ['Generate Barcode', 'barcode_management.php', 'tag', 'Buat & cetak label barcode'];
            }
            if ($role === 'super_admin') {
                $barcode[] = ['Mode Lite', 'operator_lite.php', 'bolt', 'Uji form input operator'];
            }
            foreach ($deptMeta as $code => $meta) {
                if (in_array($code, $allowedDepts, true)) {
                    $barcode[] = [$meta[0], $meta[1], $meta[2], 'Departemen pabrik'];
                }
            }
            $barcodeDest = 'mobile_barcode.php';
        }

        // ===== MESSAGE =====
        $message = [
            ['Hambatan Mesin', 'downtime_monitor.php', 'alert', 'Laporan breakdown 4M'],
            ['Pesan & Broadcast', 'communication.php', 'chat', 'Komunikasi antar departemen'],
            ['K3 & Darurat', 'k3_emergency.php', 'shield', 'Panggilan darurat & K3'],
        ];

        // ===== AKUN =====
        $akun = [['Profil Saya', 'mobile_account.php', 'id', 'Identitas & pengaturan akun']];
        if (class_exists('Auth') && Auth::canManageUsers()) {
            $akun[] = ['Kelola Karyawan', 'user_management.php', 'users', 'Tambah & atur akun karyawan'];
        }
        // Super Admin: tab Akun langsung membuka Kelola Karyawan (sesuai kebutuhan)
        $akunDest = ($role === 'super_admin') ? 'user_management.php' : 'mobile_account.php';

        return [
            'home'    => ['key' => 'home',    'label' => 'Home',    'icon' => 'home',    'url' => 'dashboard.php',        'items' => $home],
            'barcode' => ['key' => 'barcode', 'label' => 'Barcode', 'icon' => 'barcode', 'url' => $barcodeDest,           'items' => $barcode],
            'message' => ['key' => 'message', 'label' => 'Message', 'icon' => 'message', 'url' => 'downtime_monitor.php', 'items' => $message],
            'akun'    => ['key' => 'akun',    'label' => 'Akun',    'icon' => 'user',    'url' => $akunDest,              'items' => $akun],
        ];
    }

    /** Halaman yang memiliki kolom scanner (tombol Scan langsung aktif di halaman ini). */
    function gtbs_m_is_scan_page(string $page): bool {
        return in_array($page, ['operator_lite.php', 'scan_terminal.php', 'mobile_barcode.php'], true)
            || strpos($page, 'dept_') === 0;
    }

    /** Mencocokkan URL item (dengan query opsional) terhadap halaman aktif. */
    function gtbs_m_item_matches(string $url, string $page): bool {
        $parts = parse_url($url);
        if (($parts['path'] ?? '') !== $page) return false;
        if (empty($parts['query'])) return true;
        parse_str($parts['query'], $q);
        foreach ($q as $k => $v) {
            if (($_GET[$k] ?? null) !== $v) return false;
        }
        return true;
    }

    /** Menentukan tab aktif berdasarkan halaman saat ini. */
    function gtbs_mobile_active_tab(array $nav, string $page): string {
        foreach ($nav as $key => $tab) {
            foreach ($tab['items'] as $item) {
                if (parse_url($item[1], PHP_URL_PATH) === $page) return $key;
            }
        }
        return gtbs_m_is_scan_page($page) ? 'barcode' : 'home';
    }
}
