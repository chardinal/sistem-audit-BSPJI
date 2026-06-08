<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/notifikasi.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

// Tandai semua dibaca
if (isset($_GET['baca_semua'])) {
    bacaSemuaNotifPegawai($db, $pgw['id']);
    redirectWith('notifikasi.php', 'success', 'Semua notifikasi sudah dibaca.');
}

$notifs = getNotifikasi($db, 'pegawai', $pgw['id'], 50);

$pageTitle  = 'Notifikasi';
$activePage = 'notifikasi';
include '_header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <h2 style="font-size:18px;font-weight:700;color:#1A1F2E">Notifikasi</h2>
  <a href="?baca_semua=1" class="btn btn-secondary btn-sm">Baca Semua</a>
</div>

<div class="card">
  <?php if (empty($notifs)): ?>
  <div class="empty-state" style="padding:40px">
    <p>Tidak ada notifikasi.</p>
  </div>
  <?php else: ?>
  <?php foreach ($notifs as $n): ?>
  <div class="notif-item <?= !$n['sudah_dibaca']?'unread':'' ?>">
    <div class="notif-dot <?= $n['sudah_dibaca']?'read':'' ?>"></div>
    <div style="flex:1">
      <?php if ($n['perusahaan_nama']): ?>
      <div style="font-weight:700;font-size:12px;color:#059669;margin-bottom:2px"><?= e($n['perusahaan_nama']) ?></div>
      <?php endif; ?>
      <div class="notif-msg"><?= e($n['pesan']) ?></div>
      <div class="notif-time"><?= fmtTanggal(substr($n['dibuat_pada'],0,10)) ?> · <?= substr($n['dibuat_pada'],11,5) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include '_footer.php'; ?>
