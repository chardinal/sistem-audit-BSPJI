<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

// Data perusahaan
$s = $db->prepare("SELECT * FROM perusahaan WHERE id=?");
$s->execute([$id]);
$perusahaan = $s->fetch();
if (!$perusahaan) { header('Location: index.php'); exit; }

// Semua riwayat kunjungan ke perusahaan ini
$riwayat = $db->prepare("
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status, ja.nama AS jenis,
           GROUP_CONCAT(
               CONCAT(pg.nama,' (',r.nama_role,')')
               ORDER BY r.nama_role, pg.nama
               SEPARATOR ' | '
           ) AS tim_label
    FROM kunjungan k
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    LEFT JOIN penugasan_tim pt ON pt.kunjungan_id = k.id
    LEFT JOIN pegawai pg ON pg.id = pt.pegawai_id
    LEFT JOIN role r    ON r.id   = pt.role_id
    WHERE k.perusahaan_id = ?
    GROUP BY k.id
    ORDER BY k.tanggal_mulai DESC
");
$riwayat->execute([$id]);
$kunjunganList = $riwayat->fetchAll();

$pageTitle  = 'Detail Perusahaan';
$activePage = 'perusahaan';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1><?= e($perusahaan['nama']) ?></h1>
    <div class="breadcrumb"><a href="index.php">Perusahaan</a> / <?= e($perusahaan['nama']) ?></div>
  </div>
  <a href="index.php" class="btn btn-secondary">Kembali</a>
</div>

<!-- Info Perusahaan -->
<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="display:flex;gap:40px;flex-wrap:wrap">
    <div>
      <div class="text-muted text-sm">Nama Perusahaan</div>
      <div class="fw-600" style="font-size:15px"><?= e($perusahaan['nama']) ?></div>
    </div>
    <?php if ($perusahaan['alamat']): ?>
    <div>
      <div class="text-muted text-sm">Alamat</div>
      <div style="font-size:13px"><?= e($perusahaan['alamat']) ?></div>
    </div>
    <?php endif; ?>
    <div>
      <div class="text-muted text-sm">Terdaftar</div>
      <div class="text-sm"><?= fmtTanggal(substr($perusahaan['dibuat_pada'],0,10)) ?></div>
    </div>
    <div>
      <div class="text-muted text-sm">Total Kunjungan</div>
      <div class="fw-600"><?= count($kunjunganList) ?></div>
    </div>
  </div>
</div>

<!-- Riwayat Kunjungan -->
<div class="card">
  <div class="card-header">
    <h2>Riwayat Kunjungan Audit</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Jenis Audit</th>
          <th>Tanggal Mulai</th>
          <th>Tanggal Selesai</th>
          <th>Tim Auditor</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($kunjunganList)): ?>
        <tr><td colspan="7" class="text-center" style="padding:40px;color:#9CA3AF">Belum ada kunjungan audit.</td></tr>
        <?php else: ?>
        <?php foreach ($kunjunganList as $i => $kj): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td><?= e($kj['jenis']) ?></td>
          <td class="text-sm"><?= fmtTanggal($kj['tanggal_mulai']) ?></td>
          <td class="text-sm"><?= fmtTanggal($kj['tanggal_selesai']) ?></td>
          <td class="text-sm" style="max-width:280px;white-space:normal">
            <?= $kj['tim_label'] ? e($kj['tim_label']) : '<span class="text-muted">—</span>' ?>
          </td>
          <td><?= badgeStatus($kj['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($kj['id']) ?>" class="btn btn-secondary btn-sm">Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../_footer.php'; ?>
