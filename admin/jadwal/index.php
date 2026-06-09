<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireAdmin();
$db = getDB();

// Flash message dari redirect
$flash = getFlash();

// Filter
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');

// Build where clause for Active
$whereActive = ['1=1'];
$paramsActive = [];
if ($filterSearch) {
    $whereActive[] = '(pr.nama LIKE ? OR ja.nama LIKE ?)';
    $paramsActive[] = "%$filterSearch%"; $paramsActive[] = "%$filterSearch%";
}
if ($filterStatus) {
    if ($filterStatus === 'Selesai') {
        $whereActive[] = '1=0';
    } else {
        $whereActive[] = 'k.status = ?';
        $paramsActive[] = $filterStatus;
    }
} else {
    $whereActive[] = "k.status IN ('Aktif', 'Butuh Intervensi')";
}

// Build where clause for Selesai
$whereSelesai = ['1=1'];
$paramsSelesai = [];
if ($filterSearch) {
    $whereSelesai[] = '(pr.nama LIKE ? OR ja.nama LIKE ?)';
    $paramsSelesai[] = "%$filterSearch%"; $paramsSelesai[] = "%$filterSearch%";
}
if ($filterStatus) {
    if ($filterStatus !== 'Selesai') {
        $whereSelesai[] = '1=0';
    } else {
        $whereSelesai[] = "k.status = 'Selesai'";
    }
} else {
    $whereSelesai[] = "k.status = 'Selesai'";
}

// Active query: nearest to furthest (k.tanggal_mulai ASC)
$sqlActive = "
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status, k.dibuat_pada,
           pr.nama AS perusahaan, ja.nama AS jenis,
           (SELECT COUNT(*) FROM penugasan_tim WHERE kunjungan_id=k.id) AS jml_anggota
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE " . implode(' AND ', $whereActive) . "
    ORDER BY k.tanggal_mulai ASC
";
$sActive = $db->prepare($sqlActive);
$sActive->execute($paramsActive);
$kunjungansActive = $sActive->fetchAll();

// Selesai query: newest to oldest (k.tanggal_mulai DESC)
$sqlSelesai = "
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, k.status, k.dibuat_pada,
           pr.nama AS perusahaan, ja.nama AS jenis,
           (SELECT COUNT(*) FROM penugasan_tim WHERE kunjungan_id=k.id) AS jml_anggota
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE " . implode(' AND ', $whereSelesai) . "
    ORDER BY k.tanggal_mulai DESC
";
$sSelesai = $db->prepare($sqlSelesai);
$sSelesai->execute($paramsSelesai);
$kunjungansSelesai = $sSelesai->fetchAll();

$showActive = ($filterStatus === '' || $filterStatus === 'Aktif' || $filterStatus === 'Butuh Intervensi');
$showSelesai = ($filterStatus === '' || $filterStatus === 'Selesai');

$pageTitle  = 'Jadwal';
$activePage = 'jadwal';
include '../_header.php';
?>

<style>
/* ── Tombol aksi seragam ────────────────────────────── */
.aksi-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 76px;
  height: 32px;
  padding: 0 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
  font-family: inherit;
  transition: filter .15s;
}
.aksi-btn:hover { filter: brightness(1.1); text-decoration: none; }
.aksi-btn.secondary { background: #F1F5F9; color: #334155; border: 1px solid #E2E8F0; }
.aksi-btn.primary   { background: #3B82F6; color: #fff; }
.aksi-btn.success   { background: #10B981; color: #fff; }
.aksi-btn.danger    { background: #EF4444; color: #fff; }

/* ── Modal Konfirmasi Hapus ─────────────────────────── */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 9999;
  align-items: center;
  justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal-box {
  background: #fff;
  border-radius: 12px;
  padding: 28px 32px;
  max-width: 440px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
  animation: modalIn .2s ease;
}
@keyframes modalIn {
  from { transform: scale(.92); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
.modal-icon { font-size: 40px; text-align: center; margin-bottom: 12px; }
.modal-title { font-size: 17px; font-weight: 700; color: #1A1F2E; text-align: center; margin-bottom: 8px; }
.modal-body  { font-size: 13.5px; color: #64748B; text-align: center; margin-bottom: 24px; line-height: 1.6; }
.modal-body strong { color: #EF4444; }
.modal-actions { display: flex; gap: 10px; justify-content: center; }
</style>

<div class="page-header">
  <div>
    <h1>Jadwal Kunjungan</h1>
    <div class="breadcrumb">Kelola dan pantau semua jadwal kunjungan audit</div>
  </div>
  <a href="<?= BASE_URL ?>/admin/jadwal/create.php" class="btn btn-primary">+ Buat Kunjungan Baru</a>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom:16px">
  <?= $flash['type'] === 'success' ? '✓' : '✗' ?> <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar card" style="padding:12px 16px;border-radius:10px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center">
    <input type="text" name="q" class="form-control" placeholder="Cari perusahaan / jenis audit..."
           value="<?= e($filterSearch) ?>" style="max-width:250px">
    <select name="status" class="form-control" style="max-width:200px">
      <option value="">Semua Status</option>
      <?php foreach (['Aktif','Selesai','Butuh Intervensi'] as $s): ?>
      <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div>

<?php if ($showActive): ?>
<div class="card mt-3">
  <div class="card-header">
    <h2>Jadwal Kunjungan Aktif (<?= count($kunjungansActive) ?>)</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Perusahaan</th>
          <th>Jenis Audit</th>
          <th>Tanggal</th>
          <th>Tim</th>
          <th>Status</th>
          <th style="min-width:280px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($kunjungansActive)): ?>
        <tr><td colspan="7" class="text-center" style="padding:32px;color:#9CA3AF">Tidak ada data jadwal kunjungan aktif.</td></tr>
        <?php else: ?>
        <?php foreach ($kunjungansActive as $i => $k): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($k['perusahaan']) ?></div>
          </td>
          <td><span class="text-sm"><?= e($k['jenis']) ?></span></td>
          <td>
            <span class="text-sm"><?= fmtRentang($k['tanggal_mulai'],$k['tanggal_selesai']) ?></span>
            <div class="text-muted" style="font-size:10px"><?= selisihHari($k['tanggal_mulai'],$k['tanggal_selesai']) ?> hari</div>
          </td>
          <td>
            <span class="badge badge-blue"><?= $k['jml_anggota'] ?> anggota</span>
          </td>
          <td><?= badgeStatus($k['status']) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:nowrap;align-items:center">
              <!-- Detail -->
              <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($k['id']) ?>"
                 class="aksi-btn secondary">Detail</a>

              <!-- Edit -->
              <a href="<?= BASE_URL ?>/admin/jadwal/edit.php?id=<?= e($k['id']) ?>"
                 class="aksi-btn primary">Edit</a>

              <!-- Hapus -->
              <button type="button" class="aksi-btn danger"
                      onclick="bukaModalHapus('<?= e($k['id']) ?>','<?= e(addslashes($k['perusahaan'])) ?>','<?= e(addslashes($k['jenis'])) ?>')">
                Hapus
              </button>

              <!-- Selesai (hanya jika Aktif) -->
              <?php if ($k['status'] === 'Aktif'): ?>
              <form method="POST" action="<?= BASE_URL ?>/api/tandai_selesai.php" style="margin:0">
                <input type="hidden" name="kunjungan_id" value="<?= e($k['id']) ?>">
                <input type="hidden" name="_redirect" value="<?= BASE_URL ?>/admin/jadwal/index.php">
                <button type="submit" class="aksi-btn success"
                        onclick="return confirm('Tandai kunjungan ini sebagai Selesai?')">
                  ✓ Selesai
                </button>
              </form>
              <?php else: ?>
              <!-- Placeholder agar lebar kolom konsisten -->
              <span style="min-width:76px;display:inline-block"></span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($showSelesai): ?>
<div class="card mt-3">
  <div class="card-header">
    <h2>Jadwal Kunjungan Selesai (<?= count($kunjungansSelesai) ?>)</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Perusahaan</th>
          <th>Jenis Audit</th>
          <th>Tanggal</th>
          <th>Tim</th>
          <th>Status</th>
          <th style="min-width:280px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($kunjungansSelesai)): ?>
        <tr><td colspan="7" class="text-center" style="padding:32px;color:#9CA3AF">Tidak ada data jadwal kunjungan selesai.</td></tr>
        <?php else: ?>
        <?php foreach ($kunjungansSelesai as $i => $k): ?>
        <tr>
          <td class="text-muted text-sm"><?= $i+1 ?></td>
          <td>
            <div class="fw-600"><?= e($k['perusahaan']) ?></div>
          </td>
          <td><span class="text-sm"><?= e($k['jenis']) ?></span></td>
          <td>
            <span class="text-sm"><?= fmtRentang($k['tanggal_mulai'],$k['tanggal_selesai']) ?></span>
            <div class="text-muted" style="font-size:10px"><?= selisihHari($k['tanggal_mulai'],$k['tanggal_selesai']) ?> hari</div>
          </td>
          <td>
            <span class="badge badge-blue"><?= $k['jml_anggota'] ?> anggota</span>
          </td>
          <td><?= badgeStatus($k['status']) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:nowrap;align-items:center">
              <!-- Detail -->
              <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($k['id']) ?>"
                 class="aksi-btn secondary">Detail</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Modal Konfirmasi Hapus ──────────────────────────── -->
<div class="modal-overlay" id="modal-hapus">
  <div class="modal-box">
    <div class="modal-icon" style="color: #ef4444">&#9888;</div>
    <div class="modal-title">Hapus Kunjungan?</div>
    <div class="modal-body" id="modal-hapus-body">
      Data kunjungan ini akan dihapus <strong>permanen</strong> beserta seluruh tim dan notifikasinya.<br>
      Tindakan ini <strong>tidak dapat dibatalkan</strong>.
    </div>
    <form method="POST" action="<?= BASE_URL ?>/api/hapus_kunjungan.php" id="form-hapus">
      <input type="hidden" name="kunjungan_id" id="modal-kunjungan-id">
      <input type="hidden" name="_redirect" value="<?= BASE_URL ?>/admin/jadwal/index.php">
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="tutupModal()">Batal</button>
        <button type="submit" class="btn btn-danger">Ya, Hapus Sekarang</button>
      </div>
    </form>
  </div>
</div>

<script>
function bukaModalHapus(id, perusahaan, jenis) {
  document.getElementById('modal-kunjungan-id').value = id;
  document.getElementById('modal-hapus-body').innerHTML =
    'Anda akan menghapus kunjungan <strong>' + perusahaan + '</strong> (' + jenis + ').<br>' +
    'Semua data tim &amp; notifikasi akan ikut terhapus <strong>permanen</strong>.<br>' +
    'Tindakan ini <strong style="color:#EF4444">tidak dapat dibatalkan</strong>.';
  document.getElementById('modal-hapus').classList.add('show');
}
function tutupModal() {
  document.getElementById('modal-hapus').classList.remove('show');
}
// Tutup modal jika klik di luar box
document.getElementById('modal-hapus').addEventListener('click', function(e) {
  if (e.target === this) tutupModal();
});
</script>

<?php include '../_footer.php'; ?>

