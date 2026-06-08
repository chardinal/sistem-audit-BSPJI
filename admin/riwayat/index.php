<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Filter
$filterBulan    = $_GET['bulan']     ?? '';
$filterTahun    = $_GET['tahun']     ?? '';
$filterJenis    = $_GET['jenis']     ?? '';
$filterCari     = trim($_GET['q']    ?? '');

$where  = ["k.status = 'Selesai'"];
$params = [];

if ($filterBulan && $filterTahun) {
    $where[]  = 'MONTH(k.tanggal_mulai) = ? AND YEAR(k.tanggal_mulai) = ?';
    $params[] = $filterBulan;
    $params[] = $filterTahun;
} elseif ($filterTahun) {
    $where[]  = 'YEAR(k.tanggal_mulai) = ?';
    $params[] = $filterTahun;
}
if ($filterJenis) {
    $where[]  = 'k.jenis_audit_id = ?';
    $params[] = $filterJenis;
}
if ($filterCari) {
    $where[]  = '(pr.nama LIKE ? OR ja.nama LIKE ?)';
    $params[] = "%$filterCari%"; $params[] = "%$filterCari%";
}

$sql = "
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status, k.dibuat_pada,
           pr.nama AS perusahaan, ja.nama AS jenis,
           (SELECT COUNT(*) FROM penugasan_tim WHERE kunjungan_id=k.id) AS jml_anggota
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY k.tanggal_mulai DESC
";
$s = $db->prepare($sql);
$s->execute($params);
$riwayatList = $s->fetchAll();

// Data filter dropdown
$jenisList   = $db->query("SELECT id, nama FROM jenis_audit ORDER BY nama")->fetchAll();
$tahunList   = $db->query("SELECT DISTINCT YEAR(tanggal_mulai) AS tahun FROM kunjungan WHERE status='Selesai' ORDER BY tahun DESC")->fetchAll();

$pageTitle  = 'Riwayat Kunjungan';
$activePage = 'riwayat';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Riwayat Kunjungan</h1>
    <div class="breadcrumb">Arsip kunjungan audit yang telah selesai dilaksanakan</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar card" style="padding:12px 16px;border-radius:10px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center">
    <input type="text" name="q" class="form-control" placeholder="Cari perusahaan / jenis..."
           value="<?= e($filterCari) ?>" style="max-width:220px">
    <select name="bulan" class="form-control" style="max-width:130px">
      <option value="">Semua Bulan</option>
      <?php
      $bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
      for ($m=1;$m<=12;$m++):
      ?>
      <option value="<?= $m ?>" <?= $filterBulan==(string)$m?'selected':'' ?>><?= $bln[$m] ?></option>
      <?php endfor; ?>
    </select>
    <select name="tahun" class="form-control" style="max-width:110px">
      <option value="">Semua Tahun</option>
      <?php foreach ($tahunList as $t): ?>
      <option value="<?= $t['tahun'] ?>" <?= $filterTahun===$t['tahun']?'selected':'' ?>><?= $t['tahun'] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="jenis" class="form-control" style="max-width:200px">
      <option value="">Semua Jenis Audit</option>
      <?php foreach ($jenisList as $j): ?>
      <option value="<?= e($j['id']) ?>" <?= $filterJenis===$j['id']?'selected':'' ?>><?= e($j['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <h2>Total: <?= count($riwayatList) ?> kunjungan selesai</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Perusahaan</th>
          <th>Jenis Audit</th>
          <th>Tanggal Pelaksanaan</th>
          <th>Tim</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($riwayatList)): ?>
        <tr><td colspan="6" class="text-center" style="padding:40px;color:#9CA3AF">Belum ada riwayat kunjungan selesai.</td></tr>
        <?php else: ?>
        <?php foreach ($riwayatList as $i => $r): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($r['perusahaan']) ?></div>
          </td>
          <td><span class="text-sm"><?= e($r['jenis']) ?></span></td>
          <td>
            <span class="text-sm"><?= fmtRentang($r['tanggal_mulai'],$r['tanggal_selesai']) ?></span>
            <div class="text-muted" style="font-size:10px"><?= selisihHari($r['tanggal_mulai'],$r['tanggal_selesai']) ?> hari</div>
          </td>
          <td><span class="badge badge-gray"><?= $r['jml_anggota'] ?> anggota</span></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($r['id']) ?>" class="btn btn-secondary btn-sm">Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../_footer.php'; ?>
