<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db     = getDB();
$errors = [];

$allRoles = $db->query("SELECT id, nama_role AS nama FROM role WHERE aktif=1 ORDER BY nama_role")->fetchAll();

if (isPost()) {
    $nama   = post('nama');
    $email  = post('email');
    $pass   = post('password');
    $roles  = $_POST['roles'] ?? [];

    if (!$nama)  $errors[] = 'Nama wajib diisi.';
    if (!$email) $errors[] = 'Email wajib diisi.';
    if (!$pass)  $errors[] = 'Password wajib diisi.';
    if (strlen($pass) < 6) $errors[] = 'Password minimal 6 karakter.';
    if (empty($roles)) $errors[] = 'Pilih minimal satu role.';

    // Cek email unik
    if ($email) {
        $chk = $db->prepare('SELECT id FROM pegawai WHERE email=?');
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'Email sudah terdaftar.';
    }

    if (empty($errors)) {
        $pid = generateId($db, 'pegawai', 'P');

        // Upload foto profil (opsional)
        $fotoProfil = null;
        if (!empty($_FILES['foto_profil']['name'])) {
            $ext      = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed)) {
                $dir  = __DIR__ . '/../../assets/foto_profil/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = $pid . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dir . $fname)) {
                    $fotoProfil = '/assets/foto_profil/' . $fname;
                }
            } else {
                $errors[] = 'Format foto tidak didukung (jpg, jpeg, png, webp).';
            }
        }

        if (empty($errors)) {
            $db->prepare("INSERT INTO pegawai (id,nama,email,password_hash,foto_profil) VALUES (?,?,?,?,?)")
               ->execute([$pid, $nama, $email, password_hash($pass, PASSWORD_BCRYPT), $fotoProfil]);

            foreach ($roles as $roleId) {
                $db->prepare("INSERT INTO pegawai_role (pegawai_id,role_id) VALUES (?,?)")->execute([$pid, $roleId]);
            }

            redirectWith('index.php', 'success', "Pegawai '{$nama}' berhasil ditambahkan.");
        }
    }
}

$pageTitle  = 'Tambah Pegawai';
$activePage = 'pegawai';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Tambah Pegawai</h1>
    <div class="breadcrumb"><a href="index.php">Pegawai</a> / Tambah Baru</div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px">
  <div class="card-header"><h2>Data Pegawai</h2></div>
  <div class="card-body">
    <form method="POST" action="" enctype="multipart/form-data">

      <div class="form-group">
        <label class="form-label">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama" class="form-control" value="<?= e(post('nama')) ?>" required placeholder="Nama lengkap pegawai">
      </div>

      <div class="form-group">
        <label class="form-label">Email <span class="req">*</span></label>
        <input type="email" name="email" class="form-control" value="<?= e(post('email')) ?>" required placeholder="email@domain.com">
        <div class="form-hint">Email digunakan untuk login Portal Pegawai.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Password Awal <span class="req">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
        <div class="form-hint">Pegawai disarankan mengganti password setelah login pertama.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Foto Profil <span class="form-hint">(opsional, jpg/png/webp, maks 2MB)</span></label>
        <input type="file" name="foto_profil" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>

      <div class="form-group">
        <label class="form-label">Role Kompetensi <span class="req">*</span> <span class="form-hint">(pilih satu atau lebih)</span></label>
        <div class="checkbox-group">
          <?php foreach ($allRoles as $r): ?>
          <label class="checkbox-item">
            <input type="checkbox" name="roles[]" value="<?= e($r['id']) ?>"
              <?= in_array($r['id'], (array)($_POST['roles']??[]))?'checked':'' ?>>
            <?= e($r['nama']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Simpan Pegawai</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php include '../_footer.php'; ?>
