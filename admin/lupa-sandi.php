<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$error = '';
$success = '';
$step = 1; // Step 1: Input email, Step 2: Input new password
$email = '';
$user_id = '';
$user_nama = '';

if (isPost()) {
    $action = post('action');
    if ($action === 'check_email') {
        $email = trim(post('email'));
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nama FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $step = 2;
            $user_id = $user['id'];
            $user_nama = $user['nama'];
        } else {
            $error = 'Email tidak terdaftar sebagai Administrator.';
        }
    } elseif ($action === 'reset_password') {
        $email = post('email');
        $user_id = post('user_id');
        $pass = post('new_password');
        $pass_confirm = post('confirm_password');
        
        if (strlen($pass) < 8) {
            $error = 'Kata sandi baru minimal 8 karakter.';
            $step = 2;
            $db = getDB();
            $stmt = $db->prepare('SELECT nama FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $user_nama = $stmt->fetchColumn() ?: '';
        } elseif ($pass !== $pass_confirm) {
            $error = 'Konfirmasi kata sandi tidak cocok.';
            $step = 2;
            $db = getDB();
            $stmt = $db->prepare('SELECT nama FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $user_nama = $stmt->fetchColumn() ?: '';
        } else {
            $db = getDB();
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user_id]);
            $success = 'Kata sandi berhasil disetel ulang. Silakan kembali login.';
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/icon_kemenperin.png?v=202605281134">
  <link rel="shortcut icon" type="image/png" href="../assets/icon_kemenperin.png?v=202605281134">
  <title>Lupa Sandi Admin - Sistem Manajemen Audit</title>
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
        Pemulihan Akun
      </div>
    </div>

    <h1 class="card-title">Lupa Sandi Admin</h1>
    <p class="card-subtitle" style="margin-bottom: 24px;">Temukan akun Anda dan atur kata sandi baru</p>

    <?php if ($error): ?>
    <div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px; border-radius:10px; font-size:13px; margin-bottom:20px; text-align:center; font-weight:500;">
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#D1FAE5; border:1px solid #10B981; color:#065F46; padding:12px; border-radius:10px; font-size:13px; margin-bottom:20px; text-align:center; font-weight:500;">
      <?= e($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="POST" action="">
      <input type="hidden" name="action" value="check_email">
      
      <div class="field">
        <div class="field-header">
          <label for="email">Masukkan Email Admin</label>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="2" y="4" width="20" height="16" rx="3"/>
            <polyline points="2,4 12,13 22,4"/>
          </svg>
          <input type="email" id="email" name="email" placeholder="admin@kemenperin.go.id" value="<?= e($email) ?>" required autofocus autocomplete="email"/>
        </div>
      </div>

      <button class="btn-submit" type="submit">
        <span class="btn-inner">
          Cari Akun
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </span>
      </button>
      
      <div style="text-align:center; margin-top: 16px;">
        <a href="<?= BASE_URL ?>/admin/login.php" class="forgot">Kembali ke Login</a>
      </div>
    </form>

    <?php elseif ($step === 2): ?>
    <form method="POST" action="">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="email" value="<?= e($email) ?>">
      <input type="hidden" name="user_id" value="<?= e($user_id) ?>">
      
      <div style="background:#F1F5F9; padding:12px; border-radius:10px; margin-bottom:20px; font-size:13px; color:#334155; border:1px solid #E2E8F0; text-align:center">
        Administrator ditemukan: <br><strong><?= e($user_nama) ?></strong> (<?= e($email) ?>)
      </div>

      <div class="field">
        <div class="field-header">
          <label for="new_password">Kata Sandi Baru</label>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          <input id="new_password" type="password" name="new_password" placeholder="Minimal 8 karakter" required autofocus autocomplete="new-password"/>
          <button class="show-toggle" type="button" onclick="togglePass('new_password', 'eye-icon-new')" aria-label="Tampilkan sandi">
            <svg id="eye-icon-new" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="field">
        <div class="field-header">
          <label for="confirm_password">Konfirmasi Kata Sandi Baru</label>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          <input id="confirm_password" type="password" name="confirm_password" placeholder="Ulangi kata sandi baru" required autocomplete="new-password"/>
          <button class="show-toggle" type="button" onclick="togglePass('confirm_password', 'eye-icon-confirm')" aria-label="Tampilkan sandi">
            <svg id="eye-icon-confirm" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button class="btn-submit" type="submit">
        <span class="btn-inner">
          Setel Ulang Kata Sandi
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </span>
      </button>
      
      <div style="text-align:center; margin-top: 16px;">
        <a href="<?= BASE_URL ?>/admin/login.php" class="forgot">Batal</a>
      </div>
    </form>

    <?php elseif ($step === 3): ?>
    <div style="text-align:center; margin-top:10px">
      <a href="<?= BASE_URL ?>/admin/login.php" class="btn-submit" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none">
        <span class="btn-inner">
          Kembali Login
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </span>
      </a>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="card-footer">
      <span class="footer-note">© 2026 BSPJI Surabaya</span>
    </div>

  </main>

</div>

<script>
  function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
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
