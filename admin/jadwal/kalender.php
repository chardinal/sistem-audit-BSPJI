<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Ambil semua kunjungan untuk kalender
$kunjungans = $db->query("
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status, k.nama_perusahaan AS perusahaan
    FROM kunjungan k
    ORDER BY k.tanggal_mulai ASC
")->fetchAll();

$eventData = array_map(fn($k) => [
    'id'         => $k['id'],
    'perusahaan' => $k['perusahaan'],
    'status'     => $k['status'],
    'tgl_mulai'  => $k['tanggal_mulai'],
    'tgl_selesai'=> $k['tanggal_selesai'],
], $kunjungans);

$pageTitle  = 'Kalender Visual';
$activePage = 'kalender';
include '../_header.php';
?>

<div class="page-header">
  <div><h1>🗓️ Kalender Visual Kunjungan</h1></div>
  <a href="create.php" class="btn btn-primary">+ Buat Kunjungan Baru</a>
</div>

<div class="card">
  <div class="card-body">
    <div id="admin-kalender"></div>
  </div>
</div>

<!-- Legenda -->
<div style="display:flex;gap:12px;margin-top:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:6px;font-size:12px">
    <div style="width:14px;height:14px;background:#1A1F2E;border-radius:3px"></div> Draft
  </div>
  <div style="display:flex;align-items:center;gap:6px;font-size:12px">
    <div style="width:14px;height:14px;background:#10B981;border-radius:3px"></div> Approved
  </div>
  <div style="display:flex;align-items:center;gap:6px;font-size:12px">
    <div style="width:14px;height:14px;background:#EF4444;border-radius:3px"></div> Butuh Intervensi
  </div>
</div>

<!-- Tabel ringkasan -->
<div class="card mt-3">
  <div class="card-header"><h2>Semua Kunjungan (<?= count($kunjungans) ?>)</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Perusahaan</th><th>Tanggal</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($kunjungans as $k): ?>
        <tr>
          <td class="fw-600"><?= e($k['perusahaan']) ?></td>
          <td class="text-sm"><?= fmtRentang($k['tanggal_mulai'],$k['tanggal_selesai']) ?></td>
          <td><?= badgeStatus($k['status']) ?></td>
          <td><a href="detail.php?id=<?= e($k['id']) ?>" class="btn btn-secondary btn-sm">Detail</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
initKalenderAdmin(<?= json_encode(array_values($eventData)) ?>);
</script>

<?php include '../_footer.php'; ?>
