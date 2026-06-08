<?php
// admin/_header.php
// Variabel yang harus diset sebelum include ini:
//   $pageTitle  = 'Judul Halaman'
//   $activePage = 'dashboard' | 'jadwal' | 'riwayat' | 'pegawai' | 'perusahaan'
//                 | 'role' | 'jenis-audit' | 'profil'
// Session sudah distart di config/database.php
$admin      = currentAdmin();
$notifCount = countNotifAdmin(getDB(), $admin['id']);
$B = BASE_URL;
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $B ?>/assets/icon_kemenperin.png?v=202605281134">
<link rel="shortcut icon" type="image/png" href="<?= $B ?>/assets/icon_kemenperin.png?v=202605281134">
<title><?= e($pageTitle ?? 'Admin') ?> - Sistem Manajemen Audit BSPJI</title>
<meta name="description" content="Admin Portal AMS BSPJI - Sistem Manajemen Audit">
<link rel="stylesheet" href="<?= $B ?>/assets/css/admin.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="ams-layout">

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logos">
      <img src="<?= $B ?>/assets/logo_kemenperin.png" alt="Logo Kemenperin" class="brand-logo-img">
      <img src="<?= $B ?>/assets/logo_bspji.png" alt="Logo BSPJI" class="brand-logo-img">
    </div>
    <div class="brand-sub">Sistem Manajemen Audit</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Menu Utama</div>
    <a href="<?= $B ?>/admin/index.php"
       class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
       Dashboard
    </a>
    <a href="<?= $B ?>/admin/jadwal/index.php"
       class="nav-item <?= ($activePage??'')==='jadwal'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
       Jadwal
    </a>
    <a href="<?= $B ?>/admin/riwayat/index.php"
       class="nav-item <?= ($activePage??'')==='riwayat'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
       Riwayat
    </a>

    <div class="nav-section-label" style="margin-top:12px">Data Master</div>
    <a href="<?= $B ?>/admin/pegawai/index.php"
       class="nav-item <?= ($activePage??'')==='pegawai'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
       Pegawai
    </a>
    <a href="<?= $B ?>/admin/perusahaan/index.php"
       class="nav-item <?= ($activePage??'')==='perusahaan'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
       Perusahaan
    </a>

    <div class="nav-section-label" style="margin-top:12px">Konfigurasi</div>
    <a href="<?= $B ?>/admin/role/index.php"
       class="nav-item <?= ($activePage??'')==='role'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
       Role
    </a>
    <a href="<?= $B ?>/admin/jenis-audit/index.php"
       class="nav-item <?= ($activePage??'')==='jenis-audit'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
       Jenis Audit
    </a>

    <div class="nav-section-label" style="margin-top:12px">Akun</div>
    <a href="<?= $B ?>/admin/profil.php"
       class="nav-item <?= ($activePage??'')==='profil'?'active':'' ?>">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
       Profil
    </a>
    <a href="<?= $B ?>/admin/logout.php" class="nav-item">
       <svg class="nav-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
       Logout
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-profile-card">
      <div class="admin-avatar">
        <?= strtoupper(substr($admin['nama'], 0, 1)) ?>
      </div>
      <div class="admin-details">
        <span class="admin-name" title="<?= e($admin['nama']) ?>"><?= e($admin['nama']) ?></span>
        <span class="admin-role">Administrator</span>
      </div>
    </div>
  </div>
</aside>

<!-- Main Content -->
<div class="main-content">
  <header class="main-header">
    <div class="header-left">
      <span class="header-greeting">Halo, <strong><?= e(explode(' ', $admin['nama'])[0]) ?></strong></span>
    </div>
    <div class="header-right">
      <a href="<?= $B ?>/admin/notifikasi/index.php" class="header-notif-bell" title="Notifikasi">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-count-badge" id="notif-badge" style="display: <?= $notifCount > 0 ? 'flex' : 'none' ?>"><?= $notifCount ?></span>
      </a>
      <div class="header-divider"></div>
      <a href="<?= $B ?>/admin/profil.php" class="header-user-btn">
        <div class="user-btn-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
        <span class="user-btn-name"><?= e(explode(' ', $admin['nama'])[0]) ?></span>
      </a>
    </div>
  </header>

  <div class="page-body">
  <?= renderFlash() ?>
