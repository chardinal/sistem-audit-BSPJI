<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

$riwayat = $db->prepare("
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status,
           pr.nama AS perusahaan, ja.nama AS jenis, r.nama_role AS role_nama
    FROM penugasan_tim pt
    JOIN kunjungan k   ON k.id  = pt.kunjungan_id
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    JOIN role r        ON r.id  = pt.role_id
    WHERE pt.pegawai_id = ? AND k.status = 'Selesai'
    ORDER BY k.tanggal_mulai DESC
");
$riwayat->execute([$pgw['id']]);
$riwayats = $riwayat->fetchAll();

// Ambil daftar tahun untuk filter
$tahunList = array_unique(array_map(fn($r) => substr($r['tanggal_mulai'],0,4), $riwayats));
rsort($tahunList);

$pageTitle  = 'Riwayat Kunjungan';
$activePage = 'riwayat';
include '_header.php';
?>

<h2 style="font-size:18px;font-weight:700;color:#1A1F2E;margin-bottom:14px">Riwayat Kunjungan Saya</h2>

<div class="filter-bar">
  <select id="filter-tahun" class="form-control">
    <option value="">Semua Tahun</option>
    <?php foreach ($tahunList as $yr): ?>
    <option value="<?= $yr ?>"><?= $yr ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filter-bulan" class="form-control">
    <option value="">Semua Bulan</option>
    <?php foreach (['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'] as $v=>$n): ?>
    <option value="<?= $v ?>"><?= $n ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="card" style="border:none;background:transparent;box-shadow:none">
  <div class="card-body riwayat-container" style="padding:0">
    <?php if (empty($riwayats)): ?>
    <div class="empty-state" style="padding:36px">
      <p>Belum ada riwayat kunjungan selesai.</p>
    </div>
    <?php else: ?>
    <?php foreach ($riwayats as $r): ?>
    <div class="riwayat-item" data-riwayat-date="<?= e($r['tanggal_mulai']) ?>" style="padding:14px 16px">
      <div style="flex:1">
        <div class="riwayat-title"><?= e($r['perusahaan']) ?></div>
        <div class="riwayat-sub"><?= fmtRentang($r['tanggal_mulai'],$r['tanggal_selesai']) ?></div>
        <div style="display:flex;gap:6px;margin-top:5px;flex-wrap:wrap">
          <span class="badge badge-blue" style="font-size:10px"><?= e($r['role_nama']) ?></span>
          <span class="badge badge-gray" style="font-size:10px"><?= e($r['jenis']) ?></span>
          <?= badgeStatus($r['status']) ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include '_footer.php'; ?>
