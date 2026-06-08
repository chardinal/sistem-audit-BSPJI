<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

// Ambil kunjungan Aktif untuk pegawai ini
$jadwalList = $db->prepare("
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, pr.nama AS perusahaan, r.nama_role AS role_nama
    FROM penugasan_tim pt
    JOIN kunjungan k   ON k.id  = pt.kunjungan_id
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN role r        ON r.id  = pt.role_id
    WHERE pt.pegawai_id = ? AND k.status = 'Aktif'
    ORDER BY k.tanggal_mulai ASC
");
$jadwalList->execute([$pgw['id']]);
$jadwals = $jadwalList->fetchAll();

// Data untuk kalender JS
$eventData = array_map(fn($j) => [
    'id'         => $j['id'],
    'perusahaan' => $j['perusahaan'],
    'role'       => $j['role_nama'],
    'tgl_mulai'  => $j['tanggal_mulai'],
    'tgl_selesai'=> $j['tanggal_selesai'],
], $jadwals);

$pageTitle  = 'Kalender';
$activePage = 'kalender';
include '_header.php';
?>

<h2 style="font-size:18px;font-weight:700;color:#1A1F2E;margin-bottom:14px">Kalender Agenda</h2>

<div class="card mb-3">
  <div class="card-body">
    <div id="pgw-kalender"></div>
  </div>
</div>

<!-- Daftar kunjungan bulan ini -->
<?php
$bulanIni = date('Y-m');
$jadwalBulanIni = array_filter($jadwals, fn($j) => substr($j['tanggal_mulai'],0,7)===$bulanIni || substr($j['tanggal_selesai'],0,7)===$bulanIni);
?>
<div class="card">
  <div class="card-header"><h2>Kunjungan Bulan Ini</h2></div>
  <div class="card-body" style="padding:0">
    <?php if (empty($jadwalBulanIni)): ?>
    <div class="empty-state" style="padding:28px"><p>Tidak ada kunjungan bulan ini.</p></div>
    <?php else: ?>
    <?php foreach ($jadwalBulanIni as $j): ?>
    <div class="riwayat-item" style="padding:14px 16px">
      <div>
        <div class="riwayat-title"><?= e($j['perusahaan']) ?></div>
        <div class="riwayat-sub"><?= fmtRentang($j['tanggal_mulai'],$j['tanggal_selesai']) ?> · <span class="badge badge-blue" style="font-size:10px"><?= e($j['role_nama']) ?></span></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
initKalenderPegawai(<?= json_encode(array_values($eventData)) ?>);
</script>

<?php include '_footer.php'; ?>
