<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['pegawai_id'])) { header('Location: ' . BASE_URL . '/pegawai/index.php'); exit; }

$error = '';
if (isPost()) {
    $db = getDB();
    if (loginPegawai($db, post('email'), post('password'))) {
        header('Location: ' . BASE_URL . '/pegawai/index.php'); exit;
    } else {
        $error = 'Email atau password salah.';
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/icon_kemenperin.png?v=202605281134">
  <link rel="shortcut icon" type="image/png" href="../assets/icon_kemenperin.png?v=202605281134">
  <title>Login Pegawai - Sistem Manajemen Audit</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/login.css?v=<?= time() ?>"/>
</head>
<body>
<div class="page">

  <main class="card" role="main">

    <!-- Logo -->
    <div class="logo-strip">
      <div class="logo-placeholder">
        <img src="../assets/logo_kemenperin.png" alt="Logo Kementerian Perindustrian" />
      </div>
      <div class="logo-sep"></div>
      <div class="logo-placeholder">
        <img src="../assets/logo_bspji.png" alt="Logo BSPJI Surabaya" />
      </div>
    </div>

    <div class="system-badge">
      <span class="system-badge-inner">Sistem Manajemen Audit</span>
    </div>

    <div class="portal-tab-wrap">
      <div class="portal-tab">
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
          <circle cx="5" cy="3" r="2" fill="white"/>
          <path d="M1 9c0-2.2 1.8-4 4-4s4 1.8 4 4" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
        </svg>
        Portal Pegawai
      </div>
    </div>

    <h1 class="card-title">Selamat Datang</h1>
    <p class="card-subtitle">Gunakan akun kredensial resmi Pegawai</p>

    <?php if ($error): ?>
    <div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px; border-radius:10px; font-size:13px; margin-bottom:20px; text-align:center; font-weight:500;">
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="">
      <div class="field">
        <div class="field-header">
          <label for="email">Alamat Email Pegawai</label>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="2" y="4" width="20" height="16" rx="3"/>
            <polyline points="2,4 12,13 22,4"/>
          </svg>
          <input type="email" id="email" name="email" placeholder="email@gmail.com" value="<?= e(post('email')) ?>" required autofocus autocomplete="email"/>
        </div>
      </div>

      <div class="field">
        <div class="field-header">
          <label for="password">Kata Sandi</label>
          <a href="<?= BASE_URL ?>/pegawai/lupa-sandi.php" class="forgot">Lupa sandi?</a>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password"/>
          <button class="show-toggle" type="button" onclick="togglePass()" aria-label="Tampilkan sandi">
            <svg id="eye-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button class="btn-submit" type="submit">
        <span class="btn-inner">
          Masuk
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </span>
      </button>
    </form>

    <!-- Footer -->
    <div class="card-footer">
      <span class="footer-note">© 2026 BSPJI Surabaya</span>
    </div>

  </main>

</div>

<script>
  function togglePass() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
    } else {
      input.type = 'password';
      icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }
  }

  document.querySelectorAll('input').forEach(inp => {
    inp.addEventListener('focus', function() {
      this.parentElement.style.transform = 'scale(1.005)';
      this.parentElement.style.transition = 'transform 0.2s';
    });
    inp.addEventListener('blur', function() {
      this.parentElement.style.transform = 'scale(1)';
    });
  });
</script>
</body>
</html>
