<?php
// pegawai/_header.php
// Variabel yang harus di-set sebelum include ini:
//   $pageTitle  = 'Judul Halaman'
//   $activePage = 'jadwal' | 'kalender' | 'riwayat' | 'notifikasi' | 'profil'
// Session sudah distart di config/database.php

$pgw        = currentPegawai();
$db         = getDB();
$notifCount = countNotifPegawai($db, $pgw['id']);
$jadwalCount = countJadwalAktif($db, $pgw['id']);
$B = BASE_URL;
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $B ?>/assets/icon_kemenperin.png?v=202605281134">
<link rel="shortcut icon" type="image/png" href="<?= $B ?>/assets/icon_kemenperin.png?v=202605281134">
<title><?= e($pageTitle ?? 'Portal Pegawai') ?> - Sistem Manajemen Audit BSPJI</title>
<meta name="description" content="Portal Pegawai Kemenperin - Sistem Manajemen Audit">
<link rel="stylesheet" href="<?= $B ?>/assets/css/pegawai.css?v=<?= time() ?>">
</head>
<body>

<!-- Top Header -->
<header class="pgw-header">
  <div class="pgw-header-brand" style="gap:10px;align-items:center">
    <img src="<?= $B ?>/assets/logo_kemenperin.png" alt="Logo Kemenperin" style="height:28px">
    <img src="<?= $B ?>/assets/logo_bspji.png" alt="Logo BSPJI" style="height:28px">
  </div>
  <div style="flex:1;padding:0 12px">
    <div style="font-size:13px;font-weight:600;color:#374151"><?= e($pgw['nama']) ?></div>
    <div class="pgw-header-name">Portal Pegawai</div>
  </div>
  <div class="pgw-header-actions">
    <a href="<?= $B ?>/pegawai/notifikasi.php" style="position:relative;display:inline-flex;align-items:center;color:var(--text2);text-decoration:none" title="Notifikasi">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;display:block">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span class="pgw-nav-badge" id="notif-badge" style="display:<?= $notifCount>0?'flex':'none' ?>;top:-8px;right:-8px"><?= $notifCount ?></span>
    </a>
  </div>
</header>

<!-- Desktop layout wrapper -->
<div class="pgw-layout">

<!-- Desktop Sidebar -->
<aside class="pgw-sidebar">
  <nav class="pgw-sidebar-nav">
    <a href="<?= $B ?>/pegawai/index.php" class="pgw-sidebar-item <?= ($activePage??'')==='jadwal'?'active':'' ?>">
      Jadwal Saya
      <?php if ($jadwalCount > 0): ?>
      <span class="pgw-sidebar-badge"><?= $jadwalCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $B ?>/pegawai/kalender.php" class="pgw-sidebar-item <?= ($activePage??'')==='kalender'?'active':'' ?>">
      Kalender
    </a>
    <a href="<?= $B ?>/pegawai/riwayat.php" class="pgw-sidebar-item <?= ($activePage??'')==='riwayat'?'active':'' ?>">
      Riwayat
    </a>
    <a href="<?= $B ?>/pegawai/notifikasi.php" class="pgw-sidebar-item <?= ($activePage??'')==='notifikasi'?'active':'' ?>">
      Notifikasi
      <?php if ($notifCount > 0): ?>
      <span class="pgw-sidebar-badge"><?= $notifCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $B ?>/pegawai/profil.php" class="pgw-sidebar-item <?= ($activePage??'')==='profil'?'active':'' ?>">
      Profil
    </a>
    <div style="border-top:1px solid #E5E7EB;margin:12px 0;padding-top:12px">
      <a href="<?= $B ?>/pegawai/logout.php" class="pgw-sidebar-item" style="color:#EF4444">
        Keluar
      </a>
    </div>
  </nav>
</aside>

<main class="pgw-main">
<?= renderFlash() ?>
