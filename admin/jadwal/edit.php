<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/algorithm.php';
require_once '../../includes/notifikasi.php';
require_once '../../services/NotificationService.php';

requireAdmin();
$db    = getDB();
$admin = currentAdmin();

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: index.php'); exit; }

// ── POST: Hapus anggota dari tim → auto-replacement sesuai rules ──────────
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'hapus_anggota') {
    $penId = post('penugasan_id');
    if ($penId) {
        $sOld = $db->prepare("SELECT pegawai_id FROM penugasan_tim WHERE id=?");
        $sOld->execute([$penId]);
        $oldPegawaiId = $sOld->fetchColumn();

        $result = autoReplacement($db, $penId);

        if ($result === 'replaced') {
            $sNew = $db->prepare("
                SELECT pegawai_id FROM penugasan_tim
                WHERE kunjungan_id = ?
                  AND pegawai_id != ?
                ORDER BY ditugaskan_pada DESC LIMIT 1
            ");
            $sNew->execute([$id, $oldPegawaiId]);
            $newPegawaiId = $sNew->fetchColumn();

            if ($oldPegawaiId && $newPegawaiId) {
                $notif = new NotificationService($db);
                if ($notif->isReady()) {
                    $notif->kirimGantiAnggota($id, $oldPegawaiId, $newPegawaiId);
                    foreach ($notif->errors as $errMsg) error_log('[AMS Email] ' . $errMsg);
                }
            }
            redirectWith(BASE_URL . '/admin/jadwal/edit.php?id='.$id, 'success', 'Anggota dihapus. Pengganti otomatis berhasil ditemukan dan dinotifikasi.');
        } else {
            redirectWith(BASE_URL . '/admin/jadwal/edit.php?id='.$id, 'warning', 'Anggota dihapus. Tidak ada kandidat pengganti — jadwal ditandai Butuh Intervensi.');
        }
    }
}

// Load data kunjungan
$stmt = $db->prepare("
    SELECT k.*, pr.nama AS perusahaan, pr.alamat, ja.nama AS jenis, ja.id AS jenis_id
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE k.id = ?
");
$stmt->execute([$id]);
$k = $stmt->fetch();
if (!$k) { header('Location: index.php'); exit; }

// Anggota tim saat ini
$anggotaStmt = $db->prepare("
    SELECT pt.id AS pen_id,
           pg.id AS pegawai_id,
           pg.nama AS pegawai_nama, pg.email,
           r.id AS role_id,
           r.nama_role AS role_nama
    FROM penugasan_tim pt
    JOIN pegawai pg ON pg.id = pt.pegawai_id
    JOIN role r    ON r.id   = pt.role_id
    WHERE pt.kunjungan_id = ?
    ORDER BY r.nama_role, pg.nama
");
$anggotaStmt->execute([$id]);
$tim = $anggotaStmt->fetchAll();

$errors = [];

// ── POST: Simpan perubahan tanggal/catatan ────────────────────────────────
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $tglMulai   = post('tanggal_mulai');
    $tglSelesai = post('tanggal_selesai');
    $catatan    = post('catatan');

    if (!$tglMulai)   $errors[] = 'Tanggal mulai wajib diisi.';
    if (!$tglSelesai) $errors[] = 'Tanggal selesai wajib diisi.';
    if ($tglMulai && $tglSelesai && $tglSelesai < $tglMulai) {
        $errors[] = 'Tanggal selesai tidak boleh mendahului tanggal mulai.';
    }

    if (empty($errors)) {
        // ── Snapshot nilai LAMA dari DB sebelum diubah ────────
        $tglMulaiBefore   = $k['tanggal_mulai'];
        $tglSelesaiBefore = $k['tanggal_selesai'];
        $catatanBefore    = $k['catatan'];

        // ── Simpan perubahan ke DB ────────────────────────────
        $db->prepare("UPDATE kunjungan SET tanggal_mulai=?, tanggal_selesai=?, catatan=? WHERE id=?")
           ->execute([$tglMulai, $tglSelesai, $catatan, $id]);

        // ── Kirim notifikasi perubahan jadwal ─────────────────
        try {
            // Deteksi apa yang berubah
            $perubahanDetail = [];
            if ($tglMulai !== $tglMulaiBefore || $tglSelesai !== $tglSelesaiBefore) {
                $perubahanDetail[] = [
                    'label' => 'Tanggal Kunjungan',
                    'lama'  => $tglMulaiBefore . ' s/d ' . $tglSelesaiBefore,
                    'baru'  => $tglMulai . ' s/d ' . $tglSelesai,
                ];
            }
            if (trim((string)$catatan) !== trim((string)$catatanBefore)) {
                $perubahanDetail[] = [
                    'label' => 'Catatan',
                    'lama'  => $catatanBefore ?: '(kosong)',
                    'baru'  => $catatan      ?: '(kosong)',
                ];
            }

            $notif = new NotificationService($db);
            if ($notif->isReady()) {
                $notif->kirimPerubahanJadwal($id, $perubahanDetail);
                foreach ($notif->errors as $errMsg) error_log('[AMS Email] ' . $errMsg);
            }
        } catch (Throwable $e) {
            error_log('[AMS Notif] kirimPerubahanJadwal: ' . $e->getMessage());
        }

        redirectWith(BASE_URL . '/admin/jadwal/detail.php?id=' . $id, 'success', 'Jadwal berhasil diperbarui. Notifikasi dikirim ke tim.');
    }

    $k['tanggal_mulai']   = $tglMulai;
    $k['tanggal_selesai'] = $tglSelesai;
    $k['catatan']         = $catatan;
}

$pageTitle  = 'Edit Jadwal';
$activePage = 'jadwal';
include '../_header.php';
?>

<style>
/* ── Tombol Aksi Tim ──────────────────────────────────────── */
.btn-tim {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 0;
  width: 110px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s, border-color .15s;
  white-space: nowrap;
  border: 1px solid transparent;
}
.btn-tim-auto {
  background: #EFF6FF;
  color: #2563EB;
  border-color: #BFDBFE;
}
.btn-tim-auto:hover { background: #DBEAFE; border-color: #93C5FD; }

.btn-tim-manual {
  background: #F5F3FF;
  color: #6D28D9;
  border-color: #DDD6FE;
}
.btn-tim-manual:hover { background: #EDE9FE; border-color: #C4B5FD; }

.btn-tim-hapus {
  background: #FEF2F2;
  color: #DC2626;
  border-color: #FECACA;
}
.btn-tim-hapus:hover { background: #FEE2E2; border-color: #FCA5A5; }

.btn-loading { opacity: .55; pointer-events: none; }

/* ── Dropdown Pilih Manual ───────────────────────────────── */
#manual-dropdown {
  display: none;
  position: fixed;
  z-index: 400;
  width: 340px;
  max-height: 420px;
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(0,0,0,.14);
  flex-direction: column;
  overflow: hidden;
}
#manual-dropdown-header {
  padding: 12px 14px 10px;
  border-bottom: 1px solid #F1F5F9;
  background: #FAFAFA;
}
#manual-dropdown-title {
  font-size: 11px;
  font-weight: 700;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: .6px;
  margin-bottom: 8px;
}
#manual-search {
  width: 100%;
  padding: 7px 10px;
  border: 1.5px solid #E2E8F0;
  border-radius: 6px;
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
  transition: border-color .15s;
}
#manual-search:focus { border-color: #6D28D9; }

#manual-list {
  overflow-y: auto;
  max-height: 330px;
  padding: 4px 0;
}
.manual-item {
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #F8FAFC;
  transition: background .1s;
  display: flex;
  align-items: center;
  gap: 10px;
}
.manual-item:hover { background: #F5F3FF; }
.manual-item:last-child { border-bottom: none; }
.manual-item-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #EDE9FE;
  color: #6D28D9;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.manual-item-info { flex: 1; min-width: 0; }
.manual-item-nama { font-size: 13px; font-weight: 600; color: #1E293B; }
.manual-item-beban { font-size: 11px; color: #64748B; margin-top: 1px; }
.manual-item-none  { padding: 24px; text-align: center; color: #94A3B8; font-size: 13px; }

/* ── Toast ───────────────────────────────────────────────── */
#toast {
  display: none;
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 500;
  padding: 12px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 4px 20px rgba(0,0,0,.14);
  max-width: 360px;
  line-height: 1.5;
}
.toast-success { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
.toast-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
.toast-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }

/* ── Tombol Simpan & Batal (paling bawah) ────────────────── */
.edit-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #E5E7EB;
}
.edit-actions .btn { flex: 1; justify-content: center; }
</style>

<div class="page-header">
  <div>
    <h1>Edit Jadwal Kunjungan</h1>
    <div class="breadcrumb">
      <a href="index.php">Jadwal</a> /
      <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($id) ?>"><?= e($k['perusahaan']) ?></a> /
      Edit
    </div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?= renderFlash() ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

<!-- ── Kolom Kiri: Form + Formasi Tim ─────────────────────── -->
<div>

  <form method="POST" action="" id="form-edit-jadwal">
  <input type="hidden" name="action" value="edit">

  <!-- Card: Data Kunjungan -->
  <div class="card">
    <div class="card-header"><h2>Data Kunjungan</h2></div>
    <div class="card-body">

      <div class="alert alert-info" style="margin-bottom:16px;font-size:13px">
        Perusahaan dan Jenis Audit tidak dapat diubah. Untuk mengganti, hapus jadwal ini dan buat jadwal baru.
      </div>

      <!-- Info read-only -->
      <div class="form-row" style="margin-bottom:16px">
        <div class="form-group">
          <label class="form-label">Perusahaan</label>
          <input type="text" class="form-control" value="<?= e($k['perusahaan']) ?>"
                 readonly style="background:#F9FAFB;color:#6B7280;cursor:default">
        </div>
        <div class="form-group">
          <label class="form-label">Jenis Audit</label>
          <input type="text" class="form-control" value="<?= e($k['jenis']) ?>"
                 readonly style="background:#F9FAFB;color:#6B7280;cursor:default">
        </div>
      </div>

      <!-- Tanggal -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="tanggal_mulai">Tanggal Mulai <span class="req">*</span></label>
          <input id="tanggal_mulai" type="date" name="tanggal_mulai" class="form-control"
                 value="<?= e($k['tanggal_mulai']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="tanggal_selesai">Tanggal Selesai <span class="req">*</span></label>
          <input id="tanggal_selesai" type="date" name="tanggal_selesai" class="form-control"
                 value="<?= e($k['tanggal_selesai']) ?>" required>
        </div>
      </div>

      <!-- Catatan -->
      <div class="form-group" style="margin-top:12px">
        <label class="form-label" for="catatan">Catatan / Instruksi Khusus</label>
        <textarea id="catatan" name="catatan" class="form-control" rows="3"
                  placeholder="Opsional..."><?= e($k['catatan']) ?></textarea>
      </div>
    </div>
  </div>

  <!-- Card: Kelola Formasi Tim -->
  <div class="card" style="margin-top:20px">
    <div class="card-header">
      <h2>Kelola Formasi Tim</h2>
      <span class="badge badge-blue"><?= count($tim) ?> Anggota</span>
    </div>

    <?php if (empty($tim)): ?>
    <div class="card-body" style="text-align:center;color:#9CA3AF;padding:32px">
      Belum ada anggota tim yang ditugaskan.
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pegawai</th>
            <th>Role</th>
            <th style="width:260px;text-align:center">Aksi</th>
          </tr>
        </thead>
        <tbody id="tabel-tim-body">
          <?php foreach ($tim as $t): ?>
          <tr id="row-<?= e($t['pen_id']) ?>">
            <td>
              <div class="fw-600"><?= e($t['pegawai_nama']) ?></div>
              <div class="text-muted text-sm"><?= e($t['email']) ?></div>
            </td>
            <td><span class="badge badge-blue"><?= e($t['role_nama']) ?></span></td>
            <td>
              <div style="display:flex;gap:6px;justify-content:center;align-items:center">
                <button type="button" class="btn-tim btn-tim-auto"
                  onclick="gantiOtomatis('<?= e($t['pen_id']) ?>', '<?= e($t['pegawai_nama']) ?>', this)">
                  Ganti Otomatis
                </button>
                <button type="button" class="btn-tim btn-tim-manual"
                  onclick="bukaDropdownManual('<?= e($t['pen_id']) ?>', '<?= e($t['role_id']) ?>', '<?= e($t['pegawai_nama']) ?>', this)">
                  Pilih Manual
                </button>
                <button type="button" class="btn-tim btn-tim-hapus"
                  onclick="hapusAnggota('<?= e($t['pen_id']) ?>', '<?= e(addslashes($t['pegawai_nama'])) ?>')">
                  Hapus
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tombol Simpan & Batal — paling bawah -->
  <div class="edit-actions">
    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($id) ?>"
       class="btn btn-secondary">Batal</a>
  </div>

  </form>

  <!-- Form hapus tersembunyi (submit via JS agar tidak dalam form-edit) -->
  <form id="form-hapus-anggota" method="POST" action="" style="display:none">
    <input type="hidden" name="action" value="hapus_anggota">
    <input type="hidden" name="penugasan_id" id="hapus-penugasan-id">
  </form>

</div><!-- end kolom kiri -->

<!-- ── Kolom Kanan: Info Ringkas ──────────────────────────── -->
<div>
  <div class="card">
    <div class="card-header"><h2>Info Saat Ini</h2></div>
    <div class="card-body">
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Perusahaan</div>
        <div class="fw-600"><?= e($k['perusahaan']) ?></div>
        <?php if ($k['alamat']): ?>
        <div class="text-muted text-sm" style="margin-top:2px"><?= e($k['alamat']) ?></div>
        <?php endif; ?>
      </div>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Jenis Audit</div>
        <div class="fw-600"><?= e($k['jenis']) ?></div>
      </div>
      <div style="margin-bottom:12px">
        <div class="text-muted text-sm">Tanggal Saat Ini</div>
        <div class="fw-600"><?= fmtRentang($k['tanggal_mulai'], $k['tanggal_selesai']) ?></div>
        <div class="text-muted text-sm"><?= selisihHari($k['tanggal_mulai'], $k['tanggal_selesai']) ?> hari</div>
      </div>
      <div>
        <div class="text-muted text-sm">Status</div>
        <?= badgeStatus($k['status']) ?>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <div class="card-body">
      <a href="<?= BASE_URL ?>/admin/jadwal/detail.php?id=<?= e($id) ?>"
         class="btn btn-secondary" style="width:100%;justify-content:center">
        Lihat Detail
      </a>
    </div>
  </div>
</div>

</div><!-- end grid -->

<!-- ── Overlay ───────────────────────────────────────────── -->
<div id="manual-overlay"
     style="display:none;position:fixed;inset:0;z-index:399;background:rgba(15,23,42,.2)"
     onclick="tutupDropdownManual()"></div>

<!-- ── Dropdown Pilih Manual ─────────────────────────────── -->
<div id="manual-dropdown">
  <div id="manual-dropdown-header">
    <div id="manual-dropdown-title">Pilih Pengganti</div>
    <input id="manual-search" type="text" placeholder="Cari nama pegawai...">
  </div>
  <div id="manual-list"></div>
</div>

<!-- ── Toast Notifikasi ───────────────────────────────────── -->
<div id="toast"></div>

<script>
const BASE_URL      = '<?= BASE_URL ?>';
const KUNJUNGAN_ID  = '<?= e($id) ?>';

let _currentPenId  = null;
let _currentRoleId = null;
let _allKandidat   = [];
const _kandidatMap = new Map();

// ═══════════════════════════════════════════════════════════
// GANTI OTOMATIS
// ═══════════════════════════════════════════════════════════
function gantiOtomatis(penugasanId, namaLama, btn) {
  if (!confirm('Ganti "' + namaLama + '" secara otomatis?\n\nSistem akan memilih pengganti terbaik berdasarkan aturan rotasi dan beban kerja.')) return;

  setLoading(btn, true, 'Mencari...');

  fetch(BASE_URL + '/api/ganti_anggota.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'otomatis', penugasan_id: penugasanId, kunjungan_id: KUNJUNGAN_ID }),
  })
  .then(r => r.json())
  .then(res => {
    setLoading(btn, false, 'Ganti Otomatis');
    if (res.success) {
      tampilToast('success', 'Berhasil: ' + res.message);
      setTimeout(() => location.reload(), 1300);
    } else {
      tampilToast('error', res.message || 'Gagal mengganti anggota.');
    }
  })
  .catch(() => {
    setLoading(btn, false, 'Ganti Otomatis');
    tampilToast('error', 'Gagal terhubung ke server.');
  });
}

// ═══════════════════════════════════════════════════════════
// HAPUS — submit form tersembunyi
// ═══════════════════════════════════════════════════════════
function hapusAnggota(penugasanId, namaPegawai) {
  if (!confirm('Hapus ' + namaPegawai + ' dari tim?\n\nSistem akan mencari pengganti otomatis sesuai aturan rotasi.')) return;
  document.getElementById('hapus-penugasan-id').value = penugasanId;
  document.getElementById('form-hapus-anggota').submit();
}

// ═══════════════════════════════════════════════════════════
// PILIH MANUAL — Buka dropdown kandidat
// ═══════════════════════════════════════════════════════════
function bukaDropdownManual(penugasanId, roleId, namaLama, btn) {
  tutupDropdownManual();
  _currentPenId  = penugasanId;
  _currentRoleId = roleId;
  _allKandidat   = [];
  _kandidatMap.clear();

  const dropdown = document.getElementById('manual-dropdown');
  const overlay  = document.getElementById('manual-overlay');
  const list     = document.getElementById('manual-list');
  const title    = document.getElementById('manual-dropdown-title');

  title.textContent = 'Pengganti: ' + namaLama;

  // Posisi dropdown — usahakan tampil di bawah tombol, jangan keluar viewport
  const rect     = btn.getBoundingClientRect();
  const scrollY  = window.scrollY;
  const scrollX  = window.scrollX;
  const ddW      = 340;
  const ddH      = 420;
  let top  = rect.bottom + scrollY + 6;
  let left = rect.left + scrollX;

  if (left + ddW > window.innerWidth - 12) left = window.innerWidth - ddW - 12;
  if (rect.bottom + ddH > window.innerHeight) top = rect.top + scrollY - ddH - 6;

  dropdown.style.top  = top + 'px';
  dropdown.style.left = left + 'px';

  list.innerHTML = '<div class="manual-item-none">Memuat kandidat...</div>';
  dropdown.style.display = 'flex';
  overlay.style.display  = 'block';

  // Reset + fokus ke search
  const searchOld = document.getElementById('manual-search');
  const searchNew = searchOld.cloneNode(true);
  searchOld.parentNode.replaceChild(searchNew, searchOld);
  searchNew.value = '';
  searchNew.focus();
  searchNew.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    renderManualList(_allKandidat.filter(k => k.nama.toLowerCase().includes(q)));
  });

  // Ambil kandidat via API
  fetch(BASE_URL + '/api/ganti_anggota.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'kandidat', penugasan_id: penugasanId, kunjungan_id: KUNJUNGAN_ID }),
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      _allKandidat = res.kandidat || [];
      renderManualList(_allKandidat);
    } else {
      list.innerHTML = '<div class="manual-item-none">' + escHtml(res.message || 'Gagal memuat.') + '</div>';
    }
  })
  .catch(() => {
    list.innerHTML = '<div class="manual-item-none">Gagal terhubung ke server.</div>';
  });
}

function renderManualList(kandidat) {
  const list = document.getElementById('manual-list');
  if (!kandidat.length) {
    list.innerHTML = '<div class="manual-item-none">Tidak ada kandidat yang memenuhi aturan.</div>';
    return;
  }

  _kandidatMap.clear();
  kandidat.forEach((k, i) => _kandidatMap.set(String(i), k));

  list.innerHTML = kandidat.map((k, i) => {
    const inisial = k.nama.trim().split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
    return '<div class="manual-item" data-kidx="' + i + '">'
      + '<div class="manual-item-avatar">' + escHtml(inisial) + '</div>'
      + '<div class="manual-item-info">'
      + '<div class="manual-item-nama">' + escHtml(k.nama) + '</div>'
      + '<div class="manual-item-beban">Beban bulan ini: ' + escHtml(String(k.beban_bulan)) + ' penugasan</div>'
      + '</div></div>';
  }).join('');

  list.onclick = function (e) {
    const item = e.target.closest('.manual-item');
    if (!item) return;
    const peg = _kandidatMap.get(item.dataset.kidx);
    if (!peg) return;
    konfirmasiManual(peg);
  };
}

function konfirmasiManual(peg) {
  tutupDropdownManual();
  if (!confirm('Pilih "' + peg.nama + '" sebagai anggota tim?')) return;

  fetch(BASE_URL + '/api/ganti_anggota.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:          'manual',
      penugasan_id:    _currentPenId,
      kunjungan_id:    KUNJUNGAN_ID,
      pegawai_baru_id: peg.id,
    }),
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      tampilToast('success', 'Berhasil: ' + res.message);
      setTimeout(() => location.reload(), 1300);
    } else {
      tampilToast('error', res.message || 'Gagal menyimpan pilihan.');
    }
  })
  .catch(() => tampilToast('error', 'Gagal terhubung ke server.'));
}

function tutupDropdownManual() {
  document.getElementById('manual-dropdown').style.display = 'none';
  document.getElementById('manual-overlay').style.display  = 'none';
  _currentPenId  = null;
  _currentRoleId = null;
}

// ═══════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════
function setLoading(btn, loading, text) {
  btn.textContent = text;
  btn.classList.toggle('btn-loading', loading);
}

let _toastTimer = null;
function tampilToast(type, msg) {
  const t = document.getElementById('toast');
  t.className = 'toast-' + type;
  t.textContent = msg;
  t.style.display = 'block';
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => { t.style.display = 'none'; }, 4500);
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Tutup dropdown saat tekan Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupDropdownManual(); });
</script>

<?php include '../_footer.php'; ?>
