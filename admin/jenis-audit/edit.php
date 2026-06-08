<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db  = getDB();
$id  = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

$jenis = $db->prepare("SELECT id, nama, deskripsi, aktif FROM jenis_audit WHERE id=?");
$jenis->execute([$id]);
$ja = $jenis->fetch();
if (!$ja) { header('Location: index.php'); exit; }

// Formasi saat ini
$curFormasi = $db->prepare("
    SELECT fa.*, r.nama_role AS role_nama
    FROM formasi_audit fa
    JOIN role r ON r.id = fa.role_id
    WHERE fa.jenis_audit_id=?
");
$curFormasi->execute([$id]);
$curFormasiList = $curFormasi->fetchAll();

$allRoles = $db->query("SELECT id, nama_role AS nama FROM role WHERE aktif=1 ORDER BY nama_role")->fetchAll();
$errors   = [];

if (isPost()) {
    $nama      = post('nama');
    $deskripsi = post('deskripsi');
    $formasi   = $_POST['formasi'] ?? [];

    if (!$nama) $errors[] = 'Nama jenis audit wajib diisi.';

    if (empty($errors)) {
        $db->prepare("UPDATE jenis_audit SET nama=?, deskripsi=? WHERE id=?")->execute([$nama, $deskripsi, $id]);

        // Hapus formasi lama, insert baru
        $db->prepare("DELETE FROM formasi_audit WHERE jenis_audit_id=?")->execute([$id]);
        foreach ($formasi as $f) {
            if (empty($f['role_id']) || (int)($f['jumlah']??0) < 1) continue;
            $db->prepare("INSERT INTO formasi_audit (id, jenis_audit_id, role_id, jumlah_slot) VALUES (?,?,?,?)")
               ->execute([generateId($db, 'formasi_audit', 'F'), $id, $f['role_id'], (int)$f['jumlah']]);
        }

        redirectWith('index.php', 'success', "Jenis audit '{$nama}' berhasil diperbarui.");
    }
}

$rolesJson  = json_encode($allRoles);
$pageTitle  = 'Edit Jenis Audit';
$activePage = 'jenis-audit';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Edit Jenis Audit</h1>
    <div class="breadcrumb"><a href="index.php">Jenis Audit</a> / <?= e($ja['nama']) ?></div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>
<div class="alert alert-warning" style="font-size:13px">
  Perubahan formasi hanya berdampak pada kunjungan yang dibuat setelah perubahan ini. Jadwal existing tidak terpengaruh.
</div>

<div class="card" style="max-width:700px">
  <div class="card-header"><h2>Edit Jenis Audit</h2></div>
  <div class="card-body">
    <form method="POST" action="">

      <div class="form-group">
        <label class="form-label">Nama Jenis Audit <span class="req">*</span></label>
        <input type="text" name="nama" class="form-control" required
               value="<?= e(isPost()?post('nama'):$ja['nama']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="deskripsi" class="form-control" rows="2"><?= e(isPost()?post('deskripsi'):$ja['deskripsi']) ?></textarea>
      </div>

      <div class="divider"></div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <strong>Formasi Tim per Slot Role</strong>
        <button type="button" id="btn-add-slot" class="btn btn-secondary btn-sm"
                data-idx="<?= count($curFormasiList) ?>" data-roles="<?= e($rolesJson) ?>">
          + Tambah Slot
        </button>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>Role</th><th>Jumlah Slot</th><th></th></tr></thead>
          <tbody id="formasi-table-body">
            <?php foreach ($curFormasiList as $i => $f): ?>
            <tr>
              <td>
                <select name="formasi[<?= $i ?>][role_id]" class="form-control">
                  <option value="">-- Pilih Role --</option>
                  <?php foreach ($allRoles as $r): ?>
                  <option value="<?= e($r['id']) ?>" <?= $r['id']===$f['role_id']?'selected':'' ?>><?= e($r['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="formasi[<?= $i ?>][jumlah]" class="form-control" value="<?= (int)$f['jumlah_slot'] ?>" min="1" max="10"></td>
              <td><button type="button" class="btn btn-danger btn-sm btn-remove-slot">✕</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php include '../_footer.php'; ?>
