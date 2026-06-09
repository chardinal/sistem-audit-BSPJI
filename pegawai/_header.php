<?php
// pegawai/_header.php
// Session sudah distart di config/database.php

$pgw        = currentPegawai();
$db         = getDB();
$notifCount = countNotifPegawai($db, $pgw['id']);
$jadwalCount = countJadwalAktif($db, $pgw['id']);
$B = BASE_URL;

// Ambil tab aktif dari URL
$tab = $_GET['tab'] ?? 'jadwal';
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
  <div class="pgw-header-brand">
    <img src="<?= $B ?>/assets/logo_bspji.png" alt="Logo BSPJI" class="pgw-header-logo-img">
    <div class="pgw-header-brand-title">
      <span class="pgw-header-app-name">Sistem Manajemen Audit</span>
      <span class="pgw-header-portal-label">Portal Pegawai</span>
    </div>
  </div>
  <div class="pgw-header-actions">
    <a href="<?= $B ?>/pegawai/index.php?tab=notifikasi" class="pgw-header-notif-btn" title="Notifikasi">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span class="pgw-nav-badge" id="notif-badge" style="display:<?= $notifCount>0?'flex':'none' ?>"><?= $notifCount ?></span>
    </a>
    <a href="<?= $B ?>/pegawai/index.php?tab=profil" class="pgw-header-avatar-btn" title="Profil Saya">
      <?php if (!empty($pgw['foto_profil']) && file_exists(ROOT_PATH . '/' . $pgw['foto_profil'])): ?>
        <img src="<?= $B ?>/<?= e($pgw['foto_profil']) ?>" alt="Avatar" class="pgw-header-avatar-img">
      <?php else: ?>
        <div class="pgw-header-avatar-initial"><?= strtoupper(mb_substr($pgw['nama'], 0, 1)) ?></div>
      <?php endif; ?>
    </a>
  </div>
</header>

<!-- Desktop layout wrapper -->
<div class="pgw-layout">

<!-- Desktop Sidebar -->
<aside class="pgw-sidebar">
  <nav class="pgw-sidebar-nav">
    <a href="<?= $B ?>/pegawai/index.php?tab=jadwal" class="pgw-sidebar-item <?= $tab==='jadwal'?'active':'' ?>">
      Jadwal Saya
      <?php if ($jadwalCount > 0): ?>
      <span class="pgw-sidebar-badge"><?= $jadwalCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $B ?>/pegawai/index.php?tab=kalender" class="pgw-sidebar-item <?= $tab==='kalender'?'active':'' ?>">
      Kalender
    </a>
    <a href="<?= $B ?>/pegawai/index.php?tab=riwayat" class="pgw-sidebar-item <?= $tab==='riwayat'?'active':'' ?>">
      Riwayat
    </a>
    <a href="<?= $B ?>/pegawai/index.php?tab=notifikasi" class="pgw-sidebar-item <?= $tab==='notifikasi'?'active':'' ?>">
      Notifikasi
      <?php if ($notifCount > 0): ?>
      <span class="pgw-sidebar-badge"><?= $notifCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $B ?>/pegawai/index.php?tab=profil" class="pgw-sidebar-item <?= $tab==='profil'?'active':'' ?>">
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
