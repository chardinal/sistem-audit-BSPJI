<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db    = getDB();
$admin = currentAdmin();
$errors = [];

// Ambil data admin dari DB
$s = $db->prepare("SELECT id, nama, email FROM admins WHERE id=?");
$s->execute([$admin['id']]);
$adminData = $s->fetch();

// POST: Ubah password
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'ubah_password') {
    $passBaru    = post('password_baru');
    $konfirmasi  = post('konfirmasi');
    $passLama    = post('password_lama');

    if (!$passLama)            $errors[] = 'Password lama wajib diisi.';
    if (!$passBaru)            $errors[] = 'Password baru wajib diisi.';
    if (strlen($passBaru) < 6) $errors[] = 'Password baru minimal 6 karakter.';
    if ($passBaru !== $konfirmasi) $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        // Verifikasi password lama
        $chk = $db->prepare("SELECT password_hash FROM admins WHERE id=?");
        $chk->execute([$admin['id']]);
        $hash = $chk->fetchColumn();
        if (!password_verify($passLama, $hash)) {
            $errors[] = 'Password lama tidak sesuai.';
        } else {
            $db->prepare("UPDATE admins SET password_hash=? WHERE id=?")
               ->execute([password_hash($passBaru, PASSWORD_BCRYPT), $admin['id']]);
            redirectWith('profil.php', 'success', 'Password berhasil diubah.');
        }
    }
}

$pageTitle  = 'Profil';
$activePage = 'profil';
include '_header.php';
?>

<div class="page-header">
  <div>
    <h1>Profil Admin</h1>
    <div class="breadcrumb">Informasi akun yang sedang login</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;max-width:800px">

<!-- Info Akun -->
<div class="card">
  <div class="card-header"><h2>Informasi Akun</h2></div>
  <div class="card-body">
    <div style="margin-bottom:16px">
      <div class="text-muted text-sm">Nama</div>
      <div class="fw-600" style="font-size:16px"><?= e($adminData['nama']) ?></div>
    </div>
    <div>
      <div class="text-muted text-sm">Email</div>
      <div class="fw-600"><?= e($adminData['email']) ?></div>
    </div>
    <div class="form-hint mt-3" style="font-size:11px">
      Akun admin tidak dapat diubah melalui antarmuka web. Untuk mengubah nama atau email, hubungi administrator sistem.
    </div>
  </div>
</div>

<!-- Ubah Password -->
<div class="card">
  <div class="card-header"><h2>Ubah Password</h2></div>
  <div class="card-body">
    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error" style="margin-bottom:12px"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="">
      <input type="hidden" name="action" value="ubah_password">
      <div class="form-group">
        <label class="form-label">Password Lama <span class="req">*</span></label>
        <input type="password" name="password_lama" class="form-control" required placeholder="Password saat ini">
      </div>
      <div class="form-group">
        <label class="form-label">Password Baru <span class="req">*</span></label>
        <input type="password" name="password_baru" class="form-control" required placeholder="Minimal 6 karakter">
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password Baru <span class="req">*</span></label>
        <input type="password" name="konfirmasi" class="form-control" required placeholder="Ulangi password baru">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Simpan Password Baru
      </button>
    </form>
  </div>
</div>

</div>

<?php include '_footer.php'; ?>
