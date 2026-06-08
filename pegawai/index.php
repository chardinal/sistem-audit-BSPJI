<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

// ── SINGLE JOIN QUERY — eliminasi N+1 ────────────────────────
// Sebelumnya: 1 query jadwal + N query tim (satu per jadwal)
// Sekarang:   1 query yang sekaligus ambil jadwal + seluruh anggota tim
$stmt = $db->prepare("
    SELECT
        /* Data jadwal pegawai yang login */
        pt_me.id          AS pen_id,
        pt_me.role_id,
        k.id              AS kunjungan_id,
        k.tanggal_mulai,
        k.tanggal_selesai,
        k.catatan,
        k.status,
        pr.nama           AS perusahaan,
        pr.alamat,
        ja.nama           AS jenis,
        r_me.nama_role    AS role_nama,
        /* Data anggota tim (semua, termasuk pegawai lain) */
        pt_all.pegawai_id AS tim_pegawai_id,
        pg_all.nama       AS tim_nama,
        r_all.nama_role   AS tim_role
    FROM penugasan_tim pt_me
    JOIN kunjungan     k      ON k.id      = pt_me.kunjungan_id
    JOIN perusahaan    pr     ON pr.id     = k.perusahaan_id
    JOIN jenis_audit   ja     ON ja.id     = k.jenis_audit_id
    JOIN role          r_me   ON r_me.id   = pt_me.role_id
    /* Ambil semua anggota tim di kunjungan yang sama */
    LEFT JOIN penugasan_tim pt_all ON pt_all.kunjungan_id = k.id
    LEFT JOIN pegawai       pg_all ON pg_all.id            = pt_all.pegawai_id
    LEFT JOIN role          r_all  ON r_all.id             = pt_all.role_id
    WHERE pt_me.pegawai_id = ? AND k.status = 'Aktif'
    ORDER BY k.tanggal_mulai ASC, r_all.nama_role, pg_all.nama
");
$stmt->execute([$pgw['id']]);
$rows = $stmt->fetchAll();

// ── Group hasil per kunjungan ────────────────────────────────
$jadwals = [];
$timMap  = [];
$seen    = [];

foreach ($rows as $row) {
    $kid = $row['kunjungan_id'];

    // Jadwal (hanya tambah sekali per kunjungan)
    if (!isset($seen[$kid])) {
        $seen[$kid] = true;
        $jadwals[] = [
            'pen_id'        => $row['pen_id'],
            'role_id'       => $row['role_id'],
            'kunjungan_id'  => $kid,
            'tanggal_mulai' => $row['tanggal_mulai'],
            'tanggal_selesai' => $row['tanggal_selesai'],
            'catatan'       => $row['catatan'],
            'status'        => $row['status'],
            'perusahaan'    => $row['perusahaan'],
            'alamat'        => $row['alamat'],
            'jenis'         => $row['jenis'],
            'role_nama'     => $row['role_nama'],
        ];
        $timMap[$kid] = [];
    }

    // Anggota tim
    if ($row['tim_pegawai_id']) {
        $timMap[$kid][] = [
            'pegawai_id' => $row['tim_pegawai_id'],
            'nama'       => $row['tim_nama'],
            'role_nama'  => $row['tim_role'],
        ];
    }
}

$pageTitle  = 'Jadwal Saya';
$activePage = 'jadwal';
include '_header.php';
?>


<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <h2 style="font-size:18px;font-weight:700;color:#1A1F2E">Jadwal Aktif Saya</h2>
  <?php if (!empty($jadwals)): ?>
  <span class="badge badge-blue"><?= count($jadwals) ?> jadwal</span>
  <?php endif; ?>
</div>

<div id="jadwal-container">
<?php if (empty($jadwals)): ?>
<div class="empty-state">
  <p class="fw-600" style="color:#065F46">Tidak ada jadwal aktif saat ini</p>
  <p class="text-muted">Jadwal akan muncul di sini saat Admin menugaskan Anda ke kunjungan audit.</p>
</div>
<?php else: ?>

<?php foreach ($jadwals as $j): ?>
<div class="aksi-card">
  <div class="aksi-card-header">
    <div class="aksi-card-company"><?= e($j['perusahaan']) ?></div>
    <div class="aksi-card-type"><?= e($j['jenis']) ?></div>
  </div>
  <div class="aksi-card-body">
    <div class="aksi-info-row">
      <div>
        <div class="aksi-info-label">Tanggal</div>
        <div class="aksi-info-value"><?= fmtRentang($j['tanggal_mulai'],$j['tanggal_selesai']) ?> (<?= selisihHari($j['tanggal_mulai'],$j['tanggal_selesai']) ?> hari)</div>
      </div>
    </div>
    <div class="aksi-info-row">
      <div>
        <div class="aksi-info-label">Role Anda</div>
        <div class="aksi-info-value"><span class="badge badge-blue"><?= e($j['role_nama']) ?></span></div>
      </div>
    </div>
    <?php if ($j['alamat']): ?>
    <div class="aksi-info-row">
      <div>
        <div class="aksi-info-label">Alamat</div>
        <div class="aksi-info-value" style="font-size:12px;color:#6B7280"><?= e($j['alamat']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tim -->
    <div style="margin-top:10px">
      <div class="aksi-info-label">Anggota Tim</div>
      <div class="aksi-team">
        <?php foreach ($timMap[$j['kunjungan_id']] as $anggota): ?>
        <span class="aksi-team-chip <?= $anggota['pegawai_id']===$pgw['id']?'me':'' ?>">
          <?= e($anggota['nama']) ?> <span style="opacity:.7;font-size:10px">(<?= e($anggota['role_nama']) ?>)</span>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($j['catatan']): ?>
    <div style="margin-top:10px;font-size:12px;color:#6B7280;background:#F9FAFB;border-radius:6px;padding:8px">
      <?= e($j['catatan']) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php include '_footer.php'; ?>
