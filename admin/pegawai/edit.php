<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db  = getDB();
$id  = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

$pgw = $db->prepare("SELECT * FROM pegawai WHERE id=?");
$pgw->execute([$id]);
$p = $pgw->fetch();
if (!$p) { header('Location: index.php'); exit; }

// Roles saat ini
$curRoles = $db->prepare("SELECT role_id FROM pegawai_role WHERE pegawai_id=?");
$curRoles->execute([$id]);
$curRoleIds = array_column($curRoles->fetchAll(), 'role_id');

$allRoles = $db->query("SELECT id, nama_role AS nama FROM role WHERE aktif=1 ORDER BY nama_role")->fetchAll();
$errors   = [];

if (isPost()) {
    $nama  = post('nama');
    $email = post('email');
    $pass  = post('password');
    $roles = $_POST['roles'] ?? [];

    if (!$nama)  $errors[] = 'Nama wajib diisi.';
    if (!$email) $errors[] = 'Email wajib diisi.';
    if (empty($roles)) $errors[] = 'Pilih minimal satu role.';

    // Cek email unik (kecuali diri sendiri)
    if ($email) {
        $chk = $db->prepare('SELECT id FROM pegawai WHERE email=? AND id!=?');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) $errors[] = 'Email sudah digunakan pegawai lain.';
    }

    if (empty($errors)) {
        // Upload foto baru (opsional)
        $fotoProfil = $p['foto_profil'];
        if (!empty($_FILES['foto_profil']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $dir  = __DIR__ . '/../../assets/foto_profil/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = $id . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dir . $fname)) {
                    $fotoProfil = '/assets/foto_profil/' . $fname;
                }
            }
        }

        if ($pass) {
            $db->prepare("UPDATE pegawai SET nama=?,email=?,password_hash=?,foto_profil=? WHERE id=?")
               ->execute([$nama, $email, password_hash($pass, PASSWORD_BCRYPT), $fotoProfil, $id]);
        } else {
            $db->prepare("UPDATE pegawai SET nama=?,email=?,foto_profil=? WHERE id=?")
               ->execute([$nama, $email, $fotoProfil, $id]);
        }

        // Update roles
        $db->prepare("DELETE FROM pegawai_role WHERE pegawai_id=?")->execute([$id]);
        foreach ($roles as $roleId) {
            $db->prepare("INSERT INTO pegawai_role (pegawai_id,role_id) VALUES (?,?)")->execute([$id, $roleId]);
        }

        redirectWith('index.php', 'success', "Data pegawai '{$nama}' berhasil diperbarui.");
    }

    $curRoleIds = $roles;
}

$pageTitle  = 'Edit Pegawai';
$activePage = 'pegawai';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Edit Pegawai</h1>
    <div class="breadcrumb"><a href="index.php">Pegawai</a> / <?= e($p['nama']) ?></div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px">
  <div class="card-header"><h2>Edit Data Pegawai</h2></div>
  <div class="card-body">
    <form method="POST" action="" enctype="multipart/form-data">

      <div class="form-group">
        <label class="form-label">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama" class="form-control" value="<?= e(isPost()?post('nama'):$p['nama']) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email <span class="req">*</span></label>
        <input type="email" name="email" class="form-control" value="<?= e(isPost()?post('email'):$p['email']) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
      </div>

      <div class="form-group">
        <label class="form-label">Foto Profil <span class="form-hint">(opsional, kosongkan untuk tetap menggunakan foto lama)</span></label>
        <?php if ($p['foto_profil']): ?>
        <div style="margin-bottom:8px"><img src="<?= e($p['foto_profil']) ?>" style="height:48px;width:48px;border-radius:50%;object-fit:cover"> <span class="text-muted text-sm">Foto saat ini</span></div>
        <?php endif; ?>
        <input type="file" name="foto_profil" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>

      <div class="form-group">
        <label class="form-label">Role Kompetensi <span class="req">*</span></label>
        <div class="checkbox-group">
          <?php foreach ($allRoles as $r): ?>
          <label class="checkbox-item">
            <input type="checkbox" name="roles[]" value="<?= e($r['id']) ?>"
              <?= in_array($r['id'], $curRoleIds)?'checked':'' ?>>
            <?= e($r['nama']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php include '../_footer.php'; ?>
