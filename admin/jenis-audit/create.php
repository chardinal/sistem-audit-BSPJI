<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db     = getDB();
$errors = [];

$allRoles = $db->query("SELECT id, nama_role AS nama FROM role WHERE aktif=1 ORDER BY nama_role")->fetchAll();

if (isPost()) {
    $nama      = post('nama');
    $deskripsi = post('deskripsi');
    $formasi   = $_POST['formasi'] ?? [];

    if (!$nama) $errors[] = 'Nama jenis audit wajib diisi.';
    if (empty($formasi)) $errors[] = 'Tambahkan minimal satu slot formasi.';

    if (empty($errors)) {
        $jenisId = generateId($db, 'jenis_audit', 'J');
        $db->prepare("INSERT INTO jenis_audit (id, nama, deskripsi, aktif) VALUES (?,?,?,1)")
           ->execute([$jenisId, $nama, $deskripsi]);

        foreach ($formasi as $f) {
            if (empty($f['role_id']) || (int)($f['jumlah']??0) < 1) continue;
            $db->prepare("INSERT INTO formasi_audit (id, jenis_audit_id, role_id, jumlah_slot) VALUES (?,?,?,?)")
               ->execute([generateId($db, 'formasi_audit', 'F'), $jenisId, $f['role_id'], (int)$f['jumlah']]);
        }

        redirectWith('index.php', 'success', "Jenis audit '{$nama}' berhasil ditambahkan.");
    }
}

$rolesJson  = json_encode($allRoles);
$pageTitle  = 'Tambah Jenis Audit';
$activePage = 'jenis-audit';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Tambah Jenis Audit</h1>
    <div class="breadcrumb"><a href="index.php">Jenis Audit</a> / Tambah Baru</div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:700px">
  <div class="card-header"><h2>Detail Jenis Audit</h2></div>
  <div class="card-body">
    <form method="POST" action="">

      <div class="form-group">
        <label class="form-label">Nama Jenis Audit <span class="req">*</span></label>
        <input type="text" name="nama" class="form-control" required
               value="<?= e(post('nama')) ?>" placeholder="cth: Industri Besar">
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi <span class="form-hint">(opsional)</span></label>
        <textarea name="deskripsi" class="form-control" rows="2"><?= e(post('deskripsi')) ?></textarea>
      </div>

      <div class="divider"></div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <strong>Formasi Tim per Slot Role</strong>
        <button type="button" id="btn-add-slot" class="btn btn-secondary btn-sm"
                data-idx="1" data-roles="<?= e($rolesJson) ?>">
          + Tambah Slot
        </button>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>Role</th><th>Jumlah Slot</th><th></th></tr></thead>
          <tbody id="formasi-table-body">
            <tr>
              <td>
                <select name="formasi[0][role_id]" class="form-control" required>
                  <option value="">-- Pilih Role --</option>
                  <?php foreach ($allRoles as $r): ?>
                  <option value="<?= e($r['id']) ?>"><?= e($r['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="formasi[0][jumlah]" class="form-control" value="1" min="1" max="10" required></td>
              <td><button type="button" class="btn btn-danger btn-sm btn-remove-slot">✕</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Simpan Jenis Audit</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php include '../_footer.php'; ?>
