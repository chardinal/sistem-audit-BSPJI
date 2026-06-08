<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// ── Kinerja pegawai — query dengan tabel & kolom yang benar ──
// Menggunakan: role (bukan roles), penugasan_tim (bukan penugasan)
$kinerja = $db->query("
    SELECT
        p.id,
        p.nama,
        p.email,
        /* Semua role yang dimiliki pegawai */
        GROUP_CONCAT(DISTINCT r.nama_role ORDER BY r.nama_role SEPARATOR ', ') AS semua_role,

        /* Total kunjungan yang sudah Selesai */
        COUNT(DISTINCT CASE WHEN k.status = 'Selesai' THEN pt.id END) AS total_selesai,

        /* Kunjungan bulan ini (Aktif + Selesai) */
        COUNT(DISTINCT CASE
            WHEN k.status IN ('Aktif','Selesai')
             AND k.tanggal_mulai >= DATE_FORMAT(NOW(),'%Y-%m-01')
            THEN pt.id
        END) AS bulan_ini,

        /* Kunjungan aktif saat ini */
        COUNT(DISTINCT CASE WHEN k.status = 'Aktif' THEN pt.id END) AS beban_aktif,

        /* Tanggal kunjungan terakhir yang selesai */
        MAX(CASE WHEN k.status = 'Selesai' THEN k.tanggal_selesai END) AS tgl_terakhir
    FROM pegawai p
    LEFT JOIN pegawai_role   pr ON pr.pegawai_id = p.id
    LEFT JOIN role           r  ON r.id           = pr.role_id
    LEFT JOIN penugasan_tim  pt ON pt.pegawai_id  = p.id
    LEFT JOIN kunjungan      k  ON k.id           = pt.kunjungan_id
    GROUP BY p.id, p.nama, p.email
    ORDER BY total_selesai DESC, bulan_ini DESC, p.nama
")->fetchAll();

$pageTitle  = 'Kinerja Pegawai';
$activePage = 'kinerja';
include '../_header.php';
?>


<div class="page-header">
  <div><h1>Kinerja Pegawai</h1></div>
</div>

<div class="card">
  <div class="card-header"><h2>Ringkasan Kinerja (<?= count($kinerja) ?> pegawai)</h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Pegawai</th>
          <th>Role Dimiliki</th>
          <th>Total Selesai</th>
          <th>Aktif Saat Ini</th>
          <th>Bulan Ini</th>
          <th>Terakhir</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($kinerja)): ?>
        <tr><td colspan="7" class="text-center" style="padding:32px;color:#9CA3AF">Belum ada data kinerja.</td></tr>
        <?php else: ?>
        <?php foreach ($kinerja as $i=>$pg): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($pg['nama']) ?></div>
            <div class="text-muted text-sm"><?= e($pg['email']) ?></div>
          </td>
          <td>
            <?php foreach (explode(', ', $pg['semua_role'] ?? '') as $r): ?>
            <?php if (trim($r)): ?><span class="badge badge-blue" style="margin:1px;font-size:10px"><?= e(trim($r)) ?></span><?php endif; ?>
            <?php endforeach; ?>
          </td>
          <td>
            <span style="font-size:20px;font-weight:700;color:#1A1F2E"><?= (int)$pg['total_selesai'] ?></span>
            <span class="text-muted text-sm"> selesai</span>
          </td>
          <td>
            <?php $ba = (int)$pg['beban_aktif']; ?>
            <span class="badge <?= $ba>0?'badge-green':'badge-gray' ?>"><?= $ba ?> aktif</span>
          </td>
          <td>
            <?php $bi = (int)$pg['bulan_ini']; ?>
            <span class="badge <?= $bi>0?'badge-blue':'badge-gray' ?>"><?= $bi ?></span>
          </td>
          <td class="text-sm text-muted"><?= $pg['tgl_terakhir'] ? fmtTanggal($pg['tgl_terakhir']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../_footer.php'; ?>
