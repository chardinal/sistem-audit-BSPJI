<?php
// Root index — redirect ke portal yang sesuai
require_once __DIR__ . '/config/database.php'; // session_start() ada di sini

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/index.php'); exit;
}
if (!empty($_SESSION['pegawai_id'])) {
    header('Location: ' . BASE_URL . '/pegawai/index.php'); exit;
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icon_kemenperin.png?v=202605281134">
<link rel="shortcut icon" type="image/png" href="assets/icon_kemenperin.png?v=202605281134">
<title>Sistem Manajemen Audit - Kemenperin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center}
.hero{text-align:center;color:#fff;padding:40px 20px}
.hero-logo-img{height:100px;margin-bottom:16px}
.hero-title{font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;margin-bottom:6px}
.hero-sub{font-size:14px;color:rgba(255,255,255,.5);margin-bottom:48px}
.portal-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:440px;margin:0 auto}
.portal-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:28px 20px;text-align:center;text-decoration:none;transition:all .2s;cursor:pointer}
.portal-card:hover{background:rgba(255,255,255,.1);transform:translateY(-3px);border-color:rgba(255,255,255,.25);text-decoration:none}
.portal-name{font-size:15px;font-weight:700;color:#fff;margin-bottom:4px}
.portal-desc{font-size:12px;color:rgba(255,255,255,.45)}
.version{margin-top:40px;font-size:11px;color:rgba(255,255,255,.25)}
</style>
</head>
<body>
<div class="hero">
  <div style="display:flex; justify-content:center; align-items:center; gap:20px; margin-bottom:20px">
    <img src="assets/logo_kemenperin.png" style="height:60px" alt="Logo Kemenperin">
    <img src="assets/logo_bspji.png" style="height:60px" alt="Logo BSPJI">
  </div>
  <div class="hero-title">Sistem Manajemen Audit</div>
  <div class="hero-sub" style="margin-bottom: 40px">Penjadwalan Kunjungan · BSPJI</div>

  <div class="portal-grid">
    <a class="portal-card" href="admin/login.php">
      <div class="portal-name">Portal Admin</div>
      <div class="portal-desc">Kelola jadwal, pegawai, dan konfigurasi sistem</div>
    </a>
    <a class="portal-card" href="pegawai/login.php">
      <div class="portal-name">Portal Pegawai</div>
      <div class="portal-desc">Validasi penugasan dan lihat jadwal pribadi</div>
    </a>
  </div>

  <div class="version">Kementerian Perindustrian · BSPJI Surabaya</div>
</div>
</body>
</html>
