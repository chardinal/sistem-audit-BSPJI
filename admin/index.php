<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();

// ── KPI Kunjungan — 1 query ────────────────────────────────
$kpiK = $db->query("
    SELECT
        COUNT(*)                                                        AS total,
        SUM(status = 'Aktif'
            AND tanggal_mulai <= CURDATE()
            AND tanggal_selesai >= CURDATE())                           AS running,
        SUM(status = 'Aktif')                                          AS aktif,
        SUM(status = 'Selesai')                                        AS selesai,
        SUM(status = 'Butuh Intervensi')                               AS intervensi
    FROM kunjungan
")->fetch();
$kpiTotal      = (int)$kpiK['total'];
$kpiRunning    = (int)$kpiK['running'];
$kpiAktif      = (int)$kpiK['aktif'];
$kpiSelesai    = (int)$kpiK['selesai'];
$kpiIntervensi = (int)$kpiK['intervensi'];

// ── KPI Pegawai — 1 query ─────────────────────────────────
$kpiP = $db->query("
    SELECT
        (SELECT COUNT(*) FROM pegawai)                                  AS total_pegawai,
        (SELECT COUNT(*)  FROM role WHERE aktif = 1)                   AS total_role,
        (SELECT COUNT(DISTINCT pt.pegawai_id)
         FROM penugasan_tim pt
         JOIN kunjungan k ON k.id = pt.kunjungan_id
         WHERE k.status = 'Aktif'
           AND k.tanggal_mulai  <= CURDATE()
           AND k.tanggal_selesai >= CURDATE())                          AS sedang_tugas
")->fetch();
$kpiPegawai     = (int)$kpiP['total_pegawai'];
$kpiRole        = (int)$kpiP['total_role'];
$kpiSedangTugas = (int)$kpiP['sedang_tugas'];

// ── Chart: tren 6 bulan ────────────────────────────────────
$tren = $db->query("
    SELECT DATE_FORMAT(dibuat_pada,'%Y-%m') AS bulan, COUNT(*) AS total
    FROM kunjungan
    WHERE dibuat_pada >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY bulan ORDER BY bulan ASC
")->fetchAll();
$trenLabels = array_column($tren, 'bulan');
$trenData   = array_column($tren, 'total');

// ── Chart: distribusi jenis audit ─────────────────────────
$distribusi = $db->query("
    SELECT ja.nama, COUNT(k.id) AS total
    FROM jenis_audit ja
    LEFT JOIN kunjungan k ON k.jenis_audit_id = ja.id
    GROUP BY ja.id, ja.nama
    HAVING total > 0
    ORDER BY total DESC
")->fetchAll();
$distLabels = array_column($distribusi, 'nama');
$distData   = array_column($distribusi, 'total');

// ── Chart: distribusi role pegawai ─────────────────────────
$distRole = $db->query("
    SELECT r.nama_role AS nama, COUNT(pr.pegawai_id) AS total
    FROM role r
    LEFT JOIN pegawai_role pr ON pr.role_id = r.id
    GROUP BY r.id, r.nama_role
    HAVING total > 0
    ORDER BY total DESC
")->fetchAll();
$distRoleLabels = array_column($distRole, 'nama');
$distRoleData   = array_column($distRole, 'total');

// ── Chart: beban per role (penugasan) ─────────────────────
$bebanRole = $db->query("
    SELECT r.nama_role AS nama, COUNT(pt.id) AS total
    FROM role r
    LEFT JOIN penugasan_tim pt ON pt.role_id = r.id
    GROUP BY r.id, r.nama_role
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();
$bebanLabels = array_column($bebanRole, 'nama');
$bebanData   = array_column($bebanRole, 'total');

// ── Feed 10 aktivitas terbaru ──────────────────────────────
$feed = $db->query("
    SELECT n.pesan, n.dibuat_pada, pr.nama AS perusahaan_nama, n.kunjungan_id
    FROM notifikasi n
    LEFT JOIN kunjungan k ON k.id = n.kunjungan_id
    LEFT JOIN perusahaan pr ON pr.id = k.perusahaan_id
    WHERE n.tipe_penerima = 'admin'
    ORDER BY n.dibuat_pada DESC LIMIT 10
")->fetchAll();

// ── Daftar Intervensi ──────────────────────────────────────
$intervensiList = $db->query("
    SELECT k.id, pr.nama AS perusahaan, ja.nama AS tipe,
           k.tanggal_mulai, k.tanggal_selesai
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE k.status = 'Butuh Intervensi'
    ORDER BY k.dibuat_pada DESC
")->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include '_header.php';
?>

<?php if (!empty($intervensiList)): ?>
<div class="intervensi-panel">
  <h3>Kunjungan Butuh Intervensi Manual (<?= count($intervensiList) ?>)</h3>
  <?php foreach ($intervensiList as $iv): ?>
  <div class="intervensi-item">
    <div>
      <strong><?= e($iv['perusahaan']) ?></strong>
      <span class="text-muted text-sm"> · <?= e($iv['tipe']) ?> · <?= fmtRentang($iv['tanggal_mulai'],$iv['tanggal_selesai']) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($iv['id']) ?>" class="btn btn-danger btn-sm">Tangani</a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── KPI Row 1: Kunjungan ───────────────────────────────── -->
<div style="margin-bottom:10px">
  <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Ringkasan Kunjungan</div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon blue">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div>
        <div class="kpi-label">Total Kunjungan</div>
        <div class="kpi-value"><?= $kpiTotal ?></div>
        <div class="kpi-sub"><?= $kpiTotal > 0 ? 'Sepanjang waktu' : 'Belum ada data' ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon green">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
      </div>
      <div>
        <div class="kpi-label">Sedang Berjalan</div>
        <div class="kpi-value"><?= $kpiRunning ?></div>
        <div class="kpi-sub"><?= $kpiRunning > 0 ? 'Aktif hari ini' : 'Tidak ada hari ini' ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon yellow">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
      </div>
      <div>
        <div class="kpi-label">Terjadwal Aktif</div>
        <div class="kpi-value"><?= $kpiAktif ?></div>
        <div class="kpi-sub"><?= $kpiAktif > 0 ? 'Termasuk upcoming' : 'Belum ada jadwal' ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon gray">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
      </div>
      <div>
        <div class="kpi-label">Selesai</div>
        <div class="kpi-value"><?= $kpiSelesai ?></div>
        <div class="kpi-sub"><?= $kpiSelesai > 0 ? 'Audit tuntas' : 'Belum ada' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ── KPI Row 2: Pegawai ─────────────────────────────────── -->
<div style="margin-bottom:24px">
  <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Ringkasan Pegawai</div>
  <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card">
      <div class="kpi-icon blue">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <div>
        <div class="kpi-label">Total Pegawai</div>
        <div class="kpi-value"><?= $kpiPegawai ?></div>
        <div class="kpi-sub"><?= $kpiPegawai > 0 ? 'Terdaftar di sistem' : 'Belum ada pegawai' ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon green">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 00-2 2v6a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      </div>
      <div>
        <div class="kpi-label">Sedang Bertugas</div>
        <div class="kpi-value"><?= $kpiSedangTugas ?></div>
        <div class="kpi-sub"><?= $kpiSedangTugas > 0 ? 'Hari ini aktif' : 'Tidak ada yang bertugas' ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon" style="background:#EDE9FE;color:#7C3AED">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
      </div>
      <div>
        <div class="kpi-label">Role Aktif</div>
        <div class="kpi-value"><?= $kpiRole ?></div>
        <div class="kpi-sub"><?= $kpiRole > 0 ? 'Jenis kompetensi' : 'Belum ada role' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Charts Row 1: Tren + Distribusi Jenis ─────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">

  <div class="card">
    <div class="card-header"><h2>Tren Kunjungan 6 Bulan Terakhir</h2></div>
    <div class="card-body" style="height:240px;display:flex;align-items:center;justify-content:center;padding:16px">
      <?php if (empty($trenLabels)): ?>
      <div class="empty-state" style="padding:0">
        <div class="empty-icon" style="font-size:32px;color:#CBD5E1;margin-bottom:8px">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
        </div>
        <p>Belum ada data kunjungan<br><small>Data muncul setelah jadwal pertama dibuat</small></p>
      </div>
      <?php else: ?>
      <canvas id="chart-tren" style="width:100%;height:200px"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Distribusi Jenis Audit</h2></div>
    <div class="card-body" style="height:240px;display:flex;align-items:center;justify-content:center;padding:16px">
      <?php if (empty($distLabels)): ?>
      <div class="empty-state" style="padding:0">
        <div class="empty-icon" style="font-size:32px;color:#CBD5E1;margin-bottom:8px">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 118 2.83"/><path d="M22 12A10 10 0 0012 2v10z"/></svg>
        </div>
        <p>Belum ada kunjungan<br><small>Buat jadwal pertama untuk melihat distribusi</small></p>
      </div>
      <?php else: ?>
      <canvas id="chart-dist" style="width:100%;max-height:200px"></canvas>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Charts Row 2: Distribusi Role + Beban + Feed ─────── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px">

  <div class="card">
    <div class="card-header">
      <h2>Distribusi Pegawai per Role</h2>
      <a href="<?= BASE_URL ?>/admin/pegawai/index.php" class="btn btn-secondary btn-sm">Kelola</a>
    </div>
    <div class="card-body" style="height:240px;display:flex;align-items:center;justify-content:center;padding:16px">
      <?php if (empty($distRoleLabels)): ?>
      <div class="empty-state" style="padding:0">
        <div class="empty-icon" style="color:#CBD5E1;margin-bottom:8px">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <p>Belum ada data pegawai<br><small>Import pegawai melalui Setup</small></p>
      </div>
      <?php else: ?>
      <canvas id="chart-role-dist" style="width:100%;max-height:200px"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Beban Penugasan per Role</h2></div>
    <div class="card-body" style="height:240px;display:flex;align-items:center;justify-content:center;padding:16px">
      <?php if (empty($bebanLabels)): ?>
      <div class="empty-state" style="padding:0">
        <div class="empty-icon" style="color:#CBD5E1;margin-bottom:8px">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <p>Belum ada penugasan<br><small>Data muncul setelah tim pertama terbentuk</small></p>
      </div>
      <?php else: ?>
      <canvas id="chart-role" style="width:100%;max-height:200px"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Feed Aktivitas -->
  <div class="card">
    <div class="card-header">
      <h2>Aktivitas Terbaru</h2>
      <a href="<?= BASE_URL ?>/admin/notifikasi/index.php" class="btn btn-secondary btn-sm">Semua</a>
    </div>
    <div class="card-body" style="padding:0;height:240px;overflow-y:auto">
      <?php if (empty($feed)): ?>
      <div class="empty-state" style="padding:32px 20px">
        <div class="empty-icon" style="color:#CBD5E1;margin-bottom:8px">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        </div>
        <p>Belum ada aktivitas</p>
      </div>
      <?php else: ?>
      <?php foreach ($feed as $f): ?>
      <div style="padding:10px 16px;border-bottom:1px solid #F3F4F6;font-size:12.5px">
        <?php if (!empty($f['perusahaan_nama'])): ?>
        <div style="font-weight:600;font-size:11px;color:#059669;margin-bottom:2px"><?= e($f['perusahaan_nama']) ?></div>
        <?php endif; ?>
        <div style="color:#374151"><?= e($f['pesan']) ?></div>
        <div style="color:#9CA3AF;font-size:11px;margin-top:2px"><?= fmtTanggal(substr($f['dibuat_pada'],0,10)) ?> · <?= substr($f['dibuat_pada'],11,5) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>


<script>
<?php if (!empty($trenLabels)): ?>
initLineChart('chart-tren', <?= json_encode($trenLabels) ?>, <?= json_encode($trenData) ?>);
<?php endif; ?>
<?php if (!empty($distLabels)): ?>
initDonutChart('chart-dist', <?= json_encode($distLabels) ?>, <?= json_encode($distData) ?>,
  ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#84CC16','#F97316']);
<?php endif; ?>
<?php if (!empty($distRoleLabels)): ?>
initDonutChart('chart-role-dist', <?= json_encode($distRoleLabels) ?>, <?= json_encode($distRoleData) ?>,
  ['#6366F1','#0EA5E9','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#84CC16','#F97316','#14B8A6','#A855F7','#F43F5E','#22C55E']);
<?php endif; ?>
<?php if (!empty($bebanLabels)): ?>
initBarChart('chart-role', <?= json_encode($bebanLabels) ?>, <?= json_encode($bebanData) ?>);
<?php endif; ?>
</script>

<?php include '_footer.php'; ?>
