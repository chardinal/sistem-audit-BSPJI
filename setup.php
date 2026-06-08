<?php
// setup.php — Inisialisasi & Seeding Database
// File ini dipanggil otomatis saat container startup atau secara manual via web

require_once __DIR__ . '/config/database.php';

// Agar response bisa di-render langsung saat running via web
header('Content-Type: text/plain; charset=utf-8');

echo "=========================================\n";
echo "AMS — Database Auto Setup & Seeder\n";
echo "=========================================\n\n";

try {
    $db = getDB();
    echo "[+] Terkoneksi ke database server: " . DB_HOST . "\n";
    
    // ── Cek Keamanan: Apakah data sudah ada? ──────────────────
    $isSeeded = false;
    try {
        $checkAdmin = $db->query("SELECT COUNT(*) FROM admins");
        if ($checkAdmin && $checkAdmin->fetchColumn() > 0) {
            $isSeeded = true;
        }
    } catch (PDOException $e) {
        // Tabel belum terbentuk, tidak masalah
    }

    if ($isSeeded && !isset($_GET['force']) && !in_array('--force', $argv ?? [])) {
        echo "[!] Database sudah berisi data. Proses setup dibatalkan agar data tidak terhapus.\n";
        echo "[!] Jika ingin mereset ulang secara paksa, silakan akses:\n";
        echo "    - Web: http://localhost:8080/setup.php?force=true\n";
        echo "    - CLI: php setup.php --force\n";
        exit;
    }

    // ── 1. Import Schema ──────────────────────────────────────
    $schemaPath = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception("File schema.sql tidak ditemukan di: " . $schemaPath);
    }
    
    echo "[+] Membaca database/schema.sql...\n";
    $sql = file_get_contents($schemaPath);
    
    // Matikan check foreign key saat recreate database
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Eksekusi schema (multiline SQL)
    $db->exec($sql);
    echo "[✓] Schema berhasil di-import (semua tabel di-reset)\n";
    
    // ── 2. Seeding Admin ──────────────────────────────────────
    echo "[+] Seeding data Admin...\n";
    $adminPasswordHash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmtAdmin = $db->prepare("INSERT INTO admins (id, nama, email, password_hash) VALUES (:id, :nama, :email, :pass)");
    $stmtAdmin->execute([
        'id' => 'A00001',
        'nama' => 'Administrator Utama',
        'email' => 'admin@gmail.com',
        'pass' => $adminPasswordHash
    ]);
    echo "[✓] Admin seeded: admin@gmail.com / admin123\n";
    
    // ── 3. Seeding Roles ──────────────────────────────────────
    echo "[+] Seeding data Roles...\n";
    $roles = [
        ['R00001', 'Auditor Logam', 1],
        ['R00002', 'Auditor Mamin & Pupuk', 1],
        ['R00003', 'Auditor Elektronika', 1],
        ['R00004', 'Auditor LSSM', 1],
        ['R00005', 'Auditor Industri Hijau', 1],
        ['R00006', 'Auditor Luar Negeri', 1],
        ['R00007', 'Verifikator TKDN', 1],
        ['R00008', 'Halal', 1],
        ['R00009', 'PPC Logam', 1],
        ['R00010', 'PPC Mamin & Pupuk', 1],
        ['R00011', 'PPC Elektronika', 1],
        ['R00012', 'PPC Petroganik', 1]
    ];
    
    $stmtRole = $db->prepare("INSERT INTO role (id, nama_role, aktif) VALUES (?, ?, ?)");
    foreach ($roles as $role) {
        $stmtRole->execute($role);
    }
    echo "[✓] 12 Roles seeded\n";
    
    // ── 4. Seeding Pegawai (88 Pegawai) ───────────────────────
    echo "[+] Seeding 88 data Pegawai...\n";
    $pegawaiPasswordHash = password_hash('pegawai123', PASSWORD_BCRYPT);
    
    $stmtPegawai = $db->prepare("INSERT INTO pegawai (id, nama, email, password_hash) VALUES (:id, :nama, :email, :pass)");
    $stmtPegawaiRole = $db->prepare("INSERT INTO pegawai_role (pegawai_id, role_id) VALUES (?, ?)");
    
    for ($i = 1; $i <= 88; $i++) {
        $idStr = 'P' . str_pad($i, 5, '0', STR_PAD_LEFT);
        $nama = "Pegawai " . $i;
        $email = "p" . $i . "@bspji.go.id";
        
        $stmtPegawai->execute([
            'id' => $idStr,
            'nama' => $nama,
            'email' => $email,
            'pass' => $pegawaiPasswordHash
        ]);
        
        // Pemetaan role secara teratur agar algorithm.php memiliki opsi kandidat yang cukup
        // P1-P10: Auditor Logam (R00001)
        if ($i >= 1 && $i <= 10) {
            $stmtPegawaiRole->execute([$idStr, 'R00001']);
        }
        // P11-P20: Auditor Mamin & Pupuk (R00002)
        elseif ($i >= 11 && $i <= 20) {
            $stmtPegawaiRole->execute([$idStr, 'R00002']);
        }
        // P21-P30: Auditor Elektronika (R00003)
        elseif ($i >= 21 && $i <= 30) {
            $stmtPegawaiRole->execute([$idStr, 'R00003']);
        }
        // P31-P40: Auditor LSSM (R00004)
        elseif ($i >= 31 && $i <= 40) {
            $stmtPegawaiRole->execute([$idStr, 'R00004']);
        }
        // P41-P50: Auditor Industri Hijau (R00005)
        elseif ($i >= 41 && $i <= 50) {
            $stmtPegawaiRole->execute([$idStr, 'R00005']);
        }
        // P51-P55: Auditor Luar Negeri (R00006)
        elseif ($i >= 51 && $i <= 55) {
            $stmtPegawaiRole->execute([$idStr, 'R00006']);
        }
        // P56-P60: Verifikator TKDN (R00007)
        elseif ($i >= 56 && $i <= 60) {
            $stmtPegawaiRole->execute([$idStr, 'R00007']);
        }
        // P61-P65: Halal (R00008)
        elseif ($i >= 61 && $i <= 65) {
            $stmtPegawaiRole->execute([$idStr, 'R00008']);
        }
        // P66-P70: PPC Logam (R00009)
        elseif ($i >= 66 && $i <= 70) {
            $stmtPegawaiRole->execute([$idStr, 'R00009']);
        }
        // P71-P75: PPC Mamin & Pupuk (R00010)
        elseif ($i >= 71 && $i <= 75) {
            $stmtPegawaiRole->execute([$idStr, 'R00010']);
        }
        // P76-P80: PPC Elektronika (R00011)
        elseif ($i >= 76 && $i <= 80) {
            $stmtPegawaiRole->execute([$idStr, 'R00011']);
        }
        // P81-P88: PPC Petroganik (R00012)
        elseif ($i >= 81 && $i <= 88) {
            $stmtPegawaiRole->execute([$idStr, 'R00012']);
        }
    }
    echo "[✓] 88 Pegawai seeded (p1@bspji.go.id s.d p88@bspji.go.id, password: pegawai123)\n";
    
    // ── 5. Seeding Jenis Audit & Formasi ──────────────────────
    echo "[+] Seeding Jenis Audit & Formasi...\n";
    $jenisAudit = [
        ['J00001', 'LS Pro - Logam', 'Audit kesesuaian produk logam (SNI)', 1],
        ['J00002', 'LS Pro - Mamin & Pupuk', 'Audit kesesuaian produk makanan/minuman dan pupuk', 1],
        ['J00003', 'LS Pro - Elektronika', 'Audit kesesuaian produk elektronika (SNI)', 1],
        ['J00004', 'LSSM', 'Audit Sistem Manajemen Mutu (ISO 9001)', 1],
        ['J00005', 'Industri Hijau', 'Sertifikasi industri ramah lingkungan', 1],
        ['J00006', 'Audit Luar Negeri', 'Verifikasi audit pabrik di luar negeri', 1],
        ['J00007', 'Verifikasi TKDN', 'Verifikasi Tingkat Komponen Dalam Negeri', 1],
        ['J00008', 'Sertifikasi Halal', 'Audit dan pemeriksaan produk halal', 1],
        ['J00009', 'Petroganik', 'Audit spesifik pupuk Petroganik', 1]
    ];
    
    $stmtJenis = $db->prepare("INSERT INTO jenis_audit (id, nama, deskripsi, aktif) VALUES (?, ?, ?, ?)");
    foreach ($jenisAudit as $ja) {
        $stmtJenis->execute($ja);
    }
    
    $formasi = [
        // LS Pro Logam: 1x Auditor Logam, 1x PPC Logam
        ['F00001', 'J00001', 'R00001', 1],
        ['F00002', 'J00001', 'R00009', 1],
        // LS Pro Mamin & Pupuk: 1x Auditor Mamin & Pupuk, 1x PPC Mamin & Pupuk
        ['F00003', 'J00002', 'R00002', 1],
        ['F00004', 'J00002', 'R00010', 1],
        // LS Pro Elektronika: 1x Auditor Elektronika, 1x PPC Elektronika
        ['F00005', 'J00003', 'R00003', 1],
        ['F00006', 'J00003', 'R00011', 1],
        // LSSM: 2x Auditor LSSM
        ['F00007', 'J00004', 'R00004', 2],
        // Industri Hijau: 1x Auditor Industri Hijau
        ['F00008', 'J00005', 'R00005', 1],
        // Luar Negeri: 1x Auditor Luar Negeri
        ['F00009', 'J00006', 'R00006', 1],
        // TKDN: 1x Verifikator TKDN
        ['F00010', 'J00007', 'R00007', 1],
        // Halal: 1x Halal
        ['F00011', 'J00008', 'R00008', 1],
        // Petroganik: 2x PPC Petroganik
        ['F00012', 'J00009', 'R00012', 2],
    ];
    
    $stmtFormasi = $db->prepare("INSERT INTO formasi_audit (id, jenis_audit_id, role_id, jumlah_slot) VALUES (?, ?, ?, ?)");
    foreach ($formasi as $f) {
        $stmtFormasi->execute($f);
    }
    echo "[✓] Jenis Audit & Formasi seeded\n";
    
    // ── 6. Seeding Perusahaan ─────────────────────────────────
    echo "[+] Seeding Perusahaan...\n";
    $perusahaans = [
        ['C00001', 'PT Logam Jaya Abadi', 'Kawasan Industri Gresik Kav 12, Gresik'],
        ['C00002', 'PT Pangan Makmur Sentosa', 'Jl. Rungkut Industri III No. 45, Surabaya'],
        ['C00003', 'PT Elektronika Nusantara', 'Kawasan SIER Blok F-8, Surabaya'],
        ['C00004', 'PT Sinergi Hijau Lestari', 'Jl. Ahmad Yani No. 102, Sidoarjo'],
        ['C00005', 'PT Global Ekspor Mandiri', 'Kawasan Industri Margomulyo Permai Blok G-4, Surabaya'],
        ['C00006', 'PT Dinamika Komponen Dalam Negeri', 'Jl. Pemuda No. 88, Surabaya'],
        ['C00007', 'PT Berkah Halal Utama', 'Jl. Darmo Indah Barat Blok I-12, Surabaya'],
        ['C00008', 'PT Petroganik Indonesia Mulia', 'Kawasan Industri Tuban, Tuban'],
        ['C00009', 'PT Baja Utama Sejahtera', 'Jl. Margomulyo No. 22, Surabaya'],
        ['C00010', 'PT Makanan Sehat Keluarga', 'Jl. Raya Pasuruan KM 15, Pasuruan']
    ];
    
    $stmtPerusahaan = $db->prepare("INSERT INTO perusahaan (id, nama, alamat) VALUES (?, ?, ?)");
    foreach ($perusahaans as $p) {
        $stmtPerusahaan->execute($p);
    }
    echo "[✓] 10 Perusahaan seeded\n";
    
    // Nyalakan kembali check foreign key
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "\n=========================================\n";
    echo "[★] DATABASE INITIALIZATION SUCCESSFUL!\n";
    echo "=========================================\n";
    
} catch (Exception $e) {
    echo "\n[ERROR] Setup Gagal: " . $e->getMessage() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
}
