<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

$filterCari = trim($_GET['q'] ?? '');
$params = [];

if ($filterCari) {
    $sql    = "SELECT p.id, p.nama, p.alamat,
                      COUNT(k.id) AS jml_kunjungan,
                      MAX(k.tanggal_mulai) AS kunjungan_terakhir
               FROM perusahaan p
               LEFT JOIN kunjungan k ON k.perusahaan_id = p.id
               WHERE p.nama LIKE ?
               GROUP BY p.id
               ORDER BY kunjungan_terakhir DESC";
    $params[] = "%$filterCari%";
} else {
    // 10 perusahaan dengan kunjungan terbaru
    $sql = "SELECT p.id, p.nama, p.alamat,
                   COUNT(k.id) AS jml_kunjungan,
                   MAX(k.tanggal_mulai) AS kunjungan_terakhir
            FROM perusahaan p
            LEFT JOIN kunjungan k ON k.perusahaan_id = p.id
            GROUP BY p.id
            ORDER BY kunjungan_terakhir DESC
            LIMIT 10";
}
$s = $db->prepare($sql);
$s->execute($params);
$perusahaanList = $s->fetchAll();

$pageTitle  = 'Perusahaan';
$activePage = 'perusahaan';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Perusahaan</h1>
    <div class="breadcrumb">Data perusahaan klien yang telah diaudit</div>
  </div>
</div>

<!-- Pencarian -->
<div class="filter-bar card" style="padding:12px 16px;border-radius:10px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;align-items:center;width:100%">
    <input type="text" name="q" class="form-control" placeholder="Cari nama perusahaan..."
           value="<?= e($filterCari) ?>" style="max-width:300px" id="input-cari-perusahaan">
    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    <?php if ($filterCari): ?>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <h2><?= $filterCari ? 'Hasil Pencarian' : '10 Perusahaan Terkini' ?></h2>
    <span class="text-muted text-sm"><?= count($perusahaanList) ?> perusahaan</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama Perusahaan</th>
          <th>Alamat</th>
          <th>Jumlah Kunjungan</th>
          <th>Kunjungan Terakhir</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($perusahaanList)): ?>
        <tr><td colspan="6" class="text-center" style="padding:40px;color:#9CA3AF">Tidak ada data perusahaan.</td></tr>
        <?php else: ?>
        <?php foreach ($perusahaanList as $i => $p): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td class="fw-600"><?= e($p['nama']) ?></td>
          <td class="text-muted text-sm"><?= $p['alamat'] ? e(mb_strimwidth($p['alamat'],0,60,'…')) : '—' ?></td>
          <td><span class="badge badge-blue"><?= $p['jml_kunjungan'] ?></span></td>
          <td class="text-sm"><?= $p['kunjungan_terakhir'] ? fmtTanggal($p['kunjungan_terakhir']) : '—' ?></td>
          <td>
            <a href="detail.php?id=<?= e($p['id']) ?>" class="btn btn-secondary btn-sm">Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../_footer.php'; ?>
