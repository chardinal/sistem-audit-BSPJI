<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Nonaktifkan / Aktifkan jenis audit
if (isset($_GET['nonaktifkan'])) {
    $db->prepare("UPDATE jenis_audit SET aktif=0 WHERE id=?")->execute([$_GET['nonaktifkan']]);
    redirectWith('index.php', 'success', 'Jenis audit berhasil dinonaktifkan.');
}
if (isset($_GET['aktifkan'])) {
    $db->prepare("UPDATE jenis_audit SET aktif=1 WHERE id=?")->execute([$_GET['aktifkan']]);
    redirectWith('index.php', 'success', 'Jenis audit berhasil diaktifkan kembali.');
}

$jenisList = $db->query("
    SELECT ja.id, ja.nama, ja.deskripsi, ja.aktif,
           COUNT(DISTINCT k.id) AS jml_kunjungan,
           GROUP_CONCAT(CONCAT(r.nama_role,':',fa.jumlah_slot) ORDER BY r.nama_role SEPARATOR '|') AS formasi
    FROM jenis_audit ja
    LEFT JOIN kunjungan k ON k.jenis_audit_id = ja.id
    LEFT JOIN formasi_audit fa ON fa.jenis_audit_id = ja.id
    LEFT JOIN role r ON r.id = fa.role_id
    GROUP BY ja.id ORDER BY ja.aktif DESC, ja.nama ASC
")->fetchAll();

$pageTitle  = 'Jenis Audit';
$activePage = 'jenis-audit';
include '../_header.php';
?>

<div class="page-header">
  <div><h1>Jenis Audit</h1>
  <div class="breadcrumb">Konfigurasi tipe kunjungan audit beserta formasi tim</div>
  </div>
  <a href="create.php" class="btn btn-primary">+ Tambah Jenis Audit</a>
</div>

<div class="card">
  <div class="card-header"><h2>Daftar Jenis Audit (<?= count($jenisList) ?>)</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Nama Jenis Audit</th><th>Formasi Tim</th><th>Kunjungan</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($jenisList)): ?>
        <tr><td colspan="6" class="text-center" style="padding:32px;color:#9CA3AF">Belum ada jenis audit.</td></tr>
        <?php else: ?>
        <?php foreach ($jenisList as $i => $ja): ?>
        <?php
          $formasiItems = $ja['formasi'] ? explode('|', $ja['formasi']) : [];
          $totalSlot = 0;
          foreach ($formasiItems as $fi) {
            $parts = explode(':', $fi);
            $totalSlot += (int)($parts[1] ?? 0);
          }
        ?>
        <tr <?= !$ja['aktif'] ? 'style="opacity:0.55"' : '' ?>>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($ja['nama']) ?></div>
            <?php if ($ja['deskripsi']): ?><div class="text-muted text-sm"><?= e($ja['deskripsi']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php foreach ($formasiItems as $fi): ?>
            <?php $parts = explode(':', $fi); if (!$parts[0]) continue; ?>
            <span class="badge badge-gray" style="margin:1px"><?= e($parts[0]) ?>: <?= (int)($parts[1]??0) ?></span>
            <?php endforeach; ?>
            <?php if ($totalSlot): ?><div class="text-sm text-muted mt-1">Total: <?= $totalSlot ?> anggota</div><?php endif; ?>
          </td>
          <td><span class="badge badge-blue"><?= $ja['jml_kunjungan'] ?></span></td>
          <td>
            <?= $ja['aktif']
              ? '<span class="badge badge-green">Aktif</span>'
              : '<span class="badge badge-gray">Nonaktif</span>' ?>
          </td>
          <td>
            <a href="edit.php?id=<?= e($ja['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
            <?php if ($ja['aktif']): ?>
            <a href="?nonaktifkan=<?= e($ja['id']) ?>"
               onclick="return confirm('Nonaktifkan jenis audit ini? Hanya berlaku pada jadwal baru.')"
               class="btn btn-secondary btn-sm">Nonaktifkan</a>
            <?php else: ?>
            <a href="?aktifkan=<?= e($ja['id']) ?>" class="btn btn-success btn-sm">Aktifkan</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../_footer.php'; ?>
