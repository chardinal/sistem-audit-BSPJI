<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

// Ambil data kunjungan
$kunjungan = $db->prepare("
    SELECT k.*,
           pr.nama AS perusahaan, pr.alamat,
           ja.nama AS jenis, ja.id AS jenis_id,
           a.nama  AS dibuat_oleh_nama
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    JOIN admins a ON a.id = k.dibuat_oleh
    WHERE k.id = ?
");
$kunjungan->execute([$id]);
$k = $kunjungan->fetch();
if (!$k) { header('Location: index.php'); exit; }

// Anggota tim
$anggota = $db->prepare("
    SELECT pt.id AS pen_id,
           pg.id AS pegawai_id,
           pg.nama AS pegawai_nama, pg.email,
           r.id AS role_id,
           r.nama_role AS role_nama
    FROM penugasan_tim pt
    JOIN pegawai pg ON pg.id = pt.pegawai_id
    JOIN role r    ON r.id   = pt.role_id
    WHERE pt.kunjungan_id = ?
    ORDER BY r.nama_role, pg.nama
");
$anggota->execute([$id]);
$tim = $anggota->fetchAll();

$pageTitle  = 'Detail Kunjungan';
$activePage = 'jadwal';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Detail Kunjungan</h1>
    <div class="breadcrumb"><a href="index.php">Jadwal</a> / <?= e($k['perusahaan']) ?></div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($k['status'] === 'Aktif'): ?>
    <a href="<?= BASE_URL ?>/admin/jadwal/edit.php?id=<?= e($id) ?>"
       class="btn btn-primary" style="min-width:130px;justify-content:center">Edit Jadwal</a>
    <form method="POST" action="<?= BASE_URL ?>/api/tandai_selesai.php"
          onsubmit="return confirm('Tandai kunjungan ini sebagai Selesai?')"
          style="display:contents">
      <input type="hidden" name="kunjungan_id" value="<?= e($id) ?>">
      <input type="hidden" name="_redirect" value="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($id) ?>">
      <button type="submit" class="btn btn-success"
              style="min-width:130px;justify-content:center">Tandai Selesai</button>
    </form>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/admin/jadwal/edit.php?id=<?= e($id) ?>"
       class="btn btn-primary" style="min-width:130px;justify-content:center">Edit Jadwal</a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-secondary"
       style="min-width:130px;justify-content:center">Kembali</a>
  </div>
</div>

<?= renderFlash() ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<!-- ── Tim Audit (read-only) ──────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2>Tim Audit</h2>
    <?= badgeStatus($k['status']) ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Role</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tim)): ?>
        <tr><td colspan="2" class="text-center" style="padding:24px;color:#9CA3AF">Belum ada anggota tim.</td></tr>
        <?php else: ?>
        <?php foreach ($tim as $t): ?>
        <tr>
          <td>
            <div class="fw-600"><?= e($t['pegawai_nama']) ?></div>
            <div class="text-muted text-sm"><?= e($t['email']) ?></div>
          </td>
          <td><span class="badge badge-blue"><?= e($t['role_nama']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Info Kunjungan ───────────────────────────────────────── -->
<div>
  <div class="card">
    <div class="card-header"><h2>Informasi Kunjungan</h2></div>
    <div class="card-body">
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Perusahaan</div>
        <div class="fw-600"><?= e($k['perusahaan']) ?></div>
        <?php if ($k['alamat']): ?>
        <div class="text-muted text-sm"><?= e($k['alamat']) ?></div>
        <?php endif; ?>
      </div>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Jenis Audit</div>
        <div class="fw-600"><?= e($k['jenis']) ?></div>
      </div>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Tanggal</div>
        <div class="fw-600"><?= fmtRentang($k['tanggal_mulai'],$k['tanggal_selesai']) ?></div>
        <div class="text-muted text-sm"><?= selisihHari($k['tanggal_mulai'],$k['tanggal_selesai']) ?> hari</div>
      </div>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Status</div>
        <?= badgeStatus($k['status']) ?>
      </div>
      <?php if ($k['catatan']): ?>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Catatan</div>
        <div style="font-size:13px"><?= e($k['catatan']) ?></div>
      </div>
      <?php endif; ?>
      <div>
        <div class="text-muted text-sm">Dibuat Oleh</div>
        <div class="fw-600"><?= e($k['dibuat_oleh_nama']) ?></div>
        <div class="text-muted text-sm"><?= fmtTanggal(substr($k['dibuat_pada'],0,10)) ?></div>
      </div>
    </div>
  </div>

  <!-- Ringkasan Tim -->
  <div class="card mt-3">
    <div class="card-header"><h2>Ringkasan Tim</h2></div>
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="text-sm">Total Anggota</span>
        <span class="fw-600 badge badge-blue"><?= count($tim) ?> orang</span>
      </div>
    </div>
  </div>
</div>

</div><!-- end grid -->

<?php include '../_footer.php'; ?>
