-- ============================================================
-- Migration: Tambah composite INDEX untuk optimalisasi query
-- Sistem Audit BSPJI — AMS v7.2
-- Jalankan sekali di MySQL/phpMyAdmin atau via command line:
--   mysql -u root ams_db < database/migration_indexes.sql
-- ============================================================

-- Index pada tabel kunjungan (Aturan 3: anti-overlap + Aturan 4: rotasi)
ALTER TABLE `kunjungan`
    ADD INDEX IF NOT EXISTS `idx_k_status_tanggal` (`status`, `tanggal_mulai`, `tanggal_selesai`),
    ADD INDEX IF NOT EXISTS `idx_k_rotasi`          (`perusahaan_id`, `jenis_audit_id`, `tanggal_mulai`);

-- Index pada tabel penugasan_tim (lookup beban kerja + kunjungan)
ALTER TABLE `penugasan_tim`
    ADD INDEX IF NOT EXISTS `idx_pt_kunjungan` (`kunjungan_id`),
    ADD INDEX IF NOT EXISTS `idx_pt_pegawai`   (`pegawai_id`),
    ADD INDEX IF NOT EXISTS `idx_pt_role`      (`role_id`);

-- Verifikasi index yang dibuat
SHOW INDEX FROM `kunjungan`;
SHOW INDEX FROM `penugasan_tim`;
