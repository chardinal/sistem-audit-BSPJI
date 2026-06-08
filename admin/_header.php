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
  <div class="sidebar-brand" style="text-align:center;padding:20px 16px;border-bottom:1px solid rgba(255,255,255,0.08)">
    <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:10px">
      <img src="<?= $B ?>/assets/logo_kemenperin.png" alt="Logo Kemenperin" style="height:36px">
      <img src="<?= $B ?>/assets/logo_bspji.png" alt="Logo BSPJI" style="height:36px">
    </div>
    <div class="brand-sub" style="font-size:10px;font-weight:600;color:#fff;text-transform:uppercase;letter-spacing:0.8px">Sistem Manajemen Audit</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Menu Utama</div>
    <a href="<?= $B ?>/admin/index.php"
       class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="<?= $B ?>/admin/jadwal/index.php"
       class="nav-item <?= ($activePage??'')==='jadwal'?'active':'' ?>">Jadwal</a>
    <a href="<?= $B ?>/admin/riwayat/index.php"
       class="nav-item <?= ($activePage??'')==='riwayat'?'active':'' ?>">Riwayat</a>

    <div class="nav-section-label" style="margin-top:12px">Data Master</div>
    <a href="<?= $B ?>/admin/pegawai/index.php"
       class="nav-item <?= ($activePage??'')==='pegawai'?'active':'' ?>">Pegawai</a>
    <a href="<?= $B ?>/admin/perusahaan/index.php"
       class="nav-item <?= ($activePage??'')==='perusahaan'?'active':'' ?>">Perusahaan</a>

    <div class="nav-section-label" style="margin-top:12px">Konfigurasi</div>
    <a href="<?= $B ?>/admin/role/index.php"
       class="nav-item <?= ($activePage??'')==='role'?'active':'' ?>">Role</a>
    <a href="<?= $B ?>/admin/jenis-audit/index.php"
       class="nav-item <?= ($activePage??'')==='jenis-audit'?'active':'' ?>">Jenis Audit</a>

    <div class="nav-section-label" style="margin-top:12px">Akun</div>
    <a href="<?= $B ?>/admin/profil.php"
       class="nav-item <?= ($activePage??'')==='profil'?'active':'' ?>">Profil</a>
    <a href="<?= $B ?>/admin/logout.php" class="nav-item">Logout</a>
  </nav>
</aside>

<!-- Main Content -->
<div class="main-content">
<div class="page-body">
<?= renderFlash() ?>
