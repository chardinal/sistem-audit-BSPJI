-- ============================================================
-- Sistem Audit BSPJI — Audit Management System (AMS)
-- Database Schema v7.3  |  DDL Only (Struktur Tabel)
-- Engine  : MySQL 8.x / MariaDB 10.x
-- Charset : utf8mb4
--
-- ID Format: 1 Huruf + 5 Angka (VARCHAR(7))
--   A = admins        | C = perusahaan
--   F = formasi_audit | J = jenis_audit
--   K = kunjungan     | N = notifikasi
--   P = pegawai       | R = role
--   T = penugasan_tim
-- ============================================================

SET NAMES UTF8MB4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `notifikasi`;
DROP TABLE IF EXISTS `penugasan_tim`;
DROP TABLE IF EXISTS `kunjungan`;
DROP TABLE IF EXISTS `formasi_audit`;
DROP TABLE IF EXISTS `jenis_audit`;
DROP TABLE IF EXISTS `pegawai_role`;
DROP TABLE IF EXISTS `perusahaan`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `pegawai`;
DROP TABLE IF EXISTS `admins`;

CREATE TABLE `admins` (
  `id`            VARCHAR(7)   NOT NULL,
  `nama`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `dibuat_pada`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pegawai` (
  `id`            VARCHAR(7)   NOT NULL,
  `nama`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `foto_profil`   VARCHAR(255) DEFAULT NULL,
  `dibuat_pada`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pegawai_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role` (
  `id`        VARCHAR(7)   NOT NULL,
  `nama_role` VARCHAR(100) NOT NULL,
  `aktif`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pegawai_role` (
  `pegawai_id` VARCHAR(7) NOT NULL,
  `role_id`    VARCHAR(7) NOT NULL,
  PRIMARY KEY (`pegawai_id`, `role_id`),
  CONSTRAINT `fk_pr_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_role`    FOREIGN KEY (`role_id`)    REFERENCES `role` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `perusahaan` (
  `id`          VARCHAR(7)   NOT NULL,
  `nama`        VARCHAR(200) NOT NULL,
  `alamat`      TEXT         DEFAULT NULL,
  `dibuat_pada` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `jenis_audit` (
  `id`          VARCHAR(7)   NOT NULL,
  `nama`        VARCHAR(150) NOT NULL,
  `deskripsi`   TEXT         DEFAULT NULL,
  `aktif`       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `formasi_audit` (
  `id`             VARCHAR(7) NOT NULL,
  `jenis_audit_id` VARCHAR(7) NOT NULL,
  `role_id`        VARCHAR(7) NOT NULL,
  `jumlah_slot`    INT         NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_formasi` (`jenis_audit_id`, `role_id`),
  CONSTRAINT `fk_fa_jenis` FOREIGN KEY (`jenis_audit_id`) REFERENCES `jenis_audit` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fa_role`  FOREIGN KEY (`role_id`)        REFERENCES `role` (`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `kunjungan` (
  `id`              VARCHAR(7)   NOT NULL,
  `perusahaan_id`   VARCHAR(7)   NOT NULL,
  `jenis_audit_id`  VARCHAR(7)   NOT NULL,
  `tanggal_mulai`   DATE         NOT NULL,
  `tanggal_selesai` DATE         NOT NULL,
  `status`          ENUM('Aktif','Selesai','Butuh Intervensi') NOT NULL DEFAULT 'Aktif',
  `catatan`         TEXT         DEFAULT NULL,
  `dibuat_oleh`     VARCHAR(7)   NOT NULL,
  `dibuat_pada`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Index untuk anti-overlap check (Aturan 3)
  KEY `idx_k_status_tanggal`  (`status`, `tanggal_mulai`, `tanggal_selesai`),
  -- Index untuk rotasi siklus (Aturan 4)
  KEY `idx_k_rotasi`          (`perusahaan_id`, `jenis_audit_id`, `tanggal_mulai`),
  CONSTRAINT `fk_k_perusahaan`  FOREIGN KEY (`perusahaan_id`)  REFERENCES `perusahaan` (`id`),
  CONSTRAINT `fk_k_jenis_audit` FOREIGN KEY (`jenis_audit_id`) REFERENCES `jenis_audit` (`id`),
  CONSTRAINT `fk_k_admin`       FOREIGN KEY (`dibuat_oleh`)    REFERENCES `admins` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penugasan_tim` (
  `id`                VARCHAR(7)   NOT NULL,
  `kunjungan_id`      VARCHAR(7)   NOT NULL,
  `pegawai_id`        VARCHAR(7)   NOT NULL,
  `role_id`           VARCHAR(7)   NOT NULL,
  `calendar_event_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID event Google Calendar',
  `ditugaskan_pada`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Index untuk lookup kunjungan dan beban kerja pegawai
  KEY `idx_pt_kunjungan`  (`kunjungan_id`),
  KEY `idx_pt_pegawai`    (`pegawai_id`),
  KEY `idx_pt_role`       (`role_id`),
  CONSTRAINT `fk_pt_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_pegawai`   FOREIGN KEY (`pegawai_id`)   REFERENCES `pegawai` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_pt_role`      FOREIGN KEY (`role_id`)      REFERENCES `role` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifikasi` (
  `id`            VARCHAR(7)   NOT NULL,
  `tipe_penerima` ENUM('admin','pegawai') NOT NULL,
  `penerima_id`   VARCHAR(7)   NOT NULL,
  `pesan`         TEXT         NOT NULL,
  `kunjungan_id`  VARCHAR(7)   DEFAULT NULL,
  `sudah_dibaca`  TINYINT(1)   NOT NULL DEFAULT 0,
  `dibuat_pada`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_penerima` (`tipe_penerima`, `penerima_id`, `sudah_dibaca`),
  CONSTRAINT `fk_notif_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
