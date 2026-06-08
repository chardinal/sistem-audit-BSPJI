<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Filter
$filterRole = $_GET['role'] ?? '';
$filterCari = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filterCari) {
    $where[]  = '(p.nama LIKE ? OR p.email LIKE ?)';
    $params[] = "%$filterCari%"; $params[] = "%$filterCari%";
}

$sql = "
    SELECT p.id, p.nama, p.email, p.foto_profil, p.dibuat_pada,
           GROUP_CONCAT(DISTINCT r.nama_role ORDER BY r.nama_role SEPARATOR ', ') AS roles,
           MAX(CASE WHEN k.status='Selesai' THEN k.tanggal_selesai END) AS audit_terakhir,
           COUNT(DISTINCT CASE WHEN k.status='Selesai' THEN pt.kunjungan_id END) AS jml_audit_selesai
    FROM pegawai p
    LEFT JOIN pegawai_role pr ON pr.pegawai_id = p.id
    LEFT JOIN role r          ON r.id = pr.role_id
    LEFT JOIN penugasan_tim pt ON pt.pegawai_id = p.id
    LEFT JOIN kunjungan k      ON k.id = pt.kunjungan_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY p.nama
";
$s = $db->prepare($sql);
$s->execute($params);
$pegawaiList = $s->fetchAll();

// Jika ada filter role, saring di PHP (sederhana)
if ($filterRole) {
    $pegawaiList = array_filter($pegawaiList, function($p) use ($filterRole) {
        $roles = array_map('trim', explode(',', $p['roles'] ?? ''));
        return in_array($filterRole, $roles);
    });
    $pegawaiList = array_values($pegawaiList);
}

$roleList = $db->query("SELECT DISTINCT nama_role FROM role WHERE aktif=1 ORDER BY nama_role")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Pegawai';
$activePage = 'pegawai';
include '../_header.php';
?>

<div class="page-header">
  <div><h1>Manajemen Pegawai</h1></div>
  <a href="create.php" class="btn btn-primary">+ Tambah Pegawai</a>
</div>

<!-- Filter -->
<div class="filter-bar card" style="padding:12px 16px;border-radius:10px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center">
    <input type="text" name="q" class="form-control" placeholder="Cari nama / email..."
           value="<?= e($filterCari) ?>" style="max-width:220px">
    <select name="role" class="form-control" style="max-width:200px">
      <option value="">Semua Role</option>
      <?php foreach ($roleList as $r): ?>
      <option value="<?= e($r) ?>" <?= $filterRole===$r?'selected':'' ?>><?= e($r) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>Total: <?= count($pegawaiList) ?> pegawai</h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Tanggal Audit Terakhir</th>
          <th>Jumlah Audit Selesai</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pegawaiList)): ?>
        <tr><td colspan="7" class="text-center" style="padding:32px;color:#9CA3AF">Belum ada pegawai.</td></tr>
        <?php else: ?>
        <?php foreach ($pegawaiList as $i => $p): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td class="fw-600"><?= e($p['nama']) ?></td>
          <td class="text-muted text-sm"><?= e($p['email']) ?></td>
          <td>
            <?php foreach (array_filter(array_map('trim', explode(',', $p['roles'] ?? ''))) as $r): ?>
            <span class="badge badge-blue" style="margin:1px"><?= e($r) ?></span>
            <?php endforeach; ?>
          </td>
          <td class="text-sm"><?= $p['audit_terakhir'] ? fmtTanggal($p['audit_terakhir']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <span class="badge <?= $p['jml_audit_selesai'] > 0 ? 'badge-green' : 'badge-gray' ?>">
              <?= (int)$p['jml_audit_selesai'] ?>
            </span>
          </td>
          <td>
            <a href="edit.php?id=<?= e($p['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
            <a href="?hapus=<?= e($p['id']) ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Hapus pegawai <?= e(addslashes($p['nama'])) ?>?')">Hapus</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Handle hapus
if (isset($_GET['hapus']) && $_GET['hapus']) {
    $db->prepare("DELETE FROM pegawai WHERE id=?")->execute([$_GET['hapus']]);
    redirectWith('index.php', 'success', 'Pegawai berhasil dihapus.');
}
?>

<?php include '../_footer.php'; ?>
