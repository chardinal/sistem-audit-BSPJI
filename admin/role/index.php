<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Tambah role
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nama = post('nama_role');
    if ($nama) {
        $db->prepare("INSERT INTO role (id, nama_role, aktif) VALUES (?,?,1)")->execute([generateId($db, 'role', 'R'), $nama]);
        redirectWith('index.php', 'success', "Role '{$nama}' berhasil ditambahkan.");
    }
}

// Nonaktifkan / Aktifkan role (soft delete via flag aktif)
if (isset($_GET['nonaktifkan'])) {
    $db->prepare("UPDATE role SET aktif=0 WHERE id=?")->execute([$_GET['nonaktifkan']]);
    redirectWith('index.php', 'success', 'Role berhasil dinonaktifkan. Histori data tetap terjaga.');
}
if (isset($_GET['aktifkan'])) {
    $db->prepare("UPDATE role SET aktif=1 WHERE id=?")->execute([$_GET['aktifkan']]);
    redirectWith('index.php', 'success', 'Role berhasil diaktifkan kembali.');
}

$roles = $db->query("
    SELECT r.id, r.nama_role, r.aktif, COUNT(pr.pegawai_id) AS jml_pegawai
    FROM role r
    LEFT JOIN pegawai_role pr ON pr.role_id = r.id
    GROUP BY r.id ORDER BY r.aktif DESC, r.nama_role ASC
")->fetchAll();

$pageTitle  = 'Role';
$activePage = 'role';
include '../_header.php';
?>

<div class="page-header">
  <div><h1>Role</h1>
  <div class="breadcrumb">Konfigurasi kompetensi teknis pegawai</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<div class="card">
  <div class="card-header"><h2>Daftar Role (<?= count($roles) ?>)</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Nama Role</th><th>Pegawai</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($roles)): ?>
        <tr><td colspan="5" class="text-center" style="padding:32px;color:#9CA3AF">Belum ada role.</td></tr>
        <?php else: ?>
        <?php foreach ($roles as $i => $r): ?>
        <tr <?= !$r['aktif'] ? 'style="opacity:0.5"' : '' ?>>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td class="fw-600"><?= e($r['nama_role']) ?></td>
          <td><span class="badge badge-blue"><?= $r['jml_pegawai'] ?> pegawai</span></td>
          <td>
            <?php if ($r['aktif']): ?>
            <span class="badge badge-green">Aktif</span>
            <?php else: ?>
            <span class="badge badge-gray">Nonaktif</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($r['aktif']): ?>
            <a href="?nonaktifkan=<?= e($r['id']) ?>"
               onclick="return confirm('Nonaktifkan role ini? Pegawai yang memiliki role ini tidak akan muncul sebagai kandidat jika role ini satu-satunya.')"
               class="btn btn-secondary btn-sm">Nonaktifkan</a>
            <?php else: ?>
            <a href="?aktifkan=<?= e($r['id']) ?>" class="btn btn-success btn-sm">Aktifkan</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Form Tambah Role -->
<div class="card">
  <div class="card-header"><h2>Tambah Role Baru</h2></div>
  <div class="card-body">
    <form method="POST" action="">
      <input type="hidden" name="action" value="tambah">
      <div class="form-group">
        <label class="form-label">Nama Role <span class="req">*</span></label>
        <input type="text" name="nama_role" class="form-control" required placeholder="cth: Auditor Lingkungan">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Tambah Role</button>
    </form>
    <div class="form-hint mt-2" style="font-size:11px;color:#92400E;background:#FEF9C3;padding:8px;border-radius:6px;margin-top:12px">
      Menonaktifkan role yang masih digunakan pegawai aktif atau menjadi bagian formasi jenis audit dapat mempengaruhi algoritma. Lakukan review terlebih dahulu.
    </div>
  </div>
</div>

</div>

<?php include '../_footer.php'; ?>
