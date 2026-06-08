<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifikasi.php';

requireAdmin();
$db    = getDB();
$admin = currentAdmin();

// Tandai semua dibaca
if (isset($_GET['baca_semua'])) {
    bacaSemuaNotifAdmin($db, $admin['id']);
    redirectWith('index.php', 'success', 'Semua notifikasi ditandai sudah dibaca.');
}

$notifs = getNotifikasi($db, 'admin', $admin['id'], 50);

$pageTitle  = 'Notifikasi';
$activePage = 'notifikasi';
include '../_header.php';
?>

<div class="page-header">
  <div><h1>Notifikasi Admin</h1></div>
  <a href="?baca_semua=1" class="btn btn-secondary btn-sm">Tandai Semua Dibaca</a>
</div>

<div class="card">
  <?php if (empty($notifs)): ?>
  <div class="empty-state" style="padding:48px"><div class="empty-icon"></div><p>Tidak ada notifikasi.</p></div>
  <?php else: ?>
  <?php foreach ($notifs as $n): ?>
  <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6;display:flex;gap:12px;align-items:flex-start;background:<?= $n['sudah_dibaca']?'#fff':'#EFF6FF' ?>">
    <div style="width:8px;height:8px;border-radius:50%;background:<?= $n['sudah_dibaca']?'#E5E7EB':'#3B82F6' ?>;flex-shrink:0;margin-top:5px"></div>
    <div style="flex:1">
      <?php if ($n['perusahaan_nama']): ?>
      <div style="font-weight:600;font-size:13px;color:#1A1F2E;margin-bottom:2px"><?= e($n['perusahaan_nama']) ?></div>
      <?php endif; ?>
      <div style="font-size:13px;color:#374151"><?= e($n['pesan']) ?></div>
      <div style="font-size:11px;color:#9CA3AF;margin-top:4px"><?= fmtTanggal(substr($n['dibuat_pada'],0,10)) ?> · <?= substr($n['dibuat_pada'],11,5) ?></div>
    </div>
    <?php if ($n['kunjungan_id']): ?>
    <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($n['kunjungan_id']) ?>" class="btn btn-secondary btn-sm" style="flex-shrink:0">Detail →</a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include '../_footer.php'; ?>
