<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

$stmt = $db->prepare("SELECT COUNT(*) FROM penugasan_tim pt JOIN kunjungan k ON k.id=pt.kunjungan_id WHERE pt.pegawai_id=? AND k.status='Selesai'");
$stmt->execute([$pgw['id']]);
$totalSelesai = (int)$stmt->fetchColumn();

$stmt2 = $db->prepare("SELECT COUNT(*) FROM penugasan_tim pt JOIN kunjungan k ON k.id=pt.kunjungan_id WHERE pt.pegawai_id=? AND k.status IN ('Aktif','Selesai') AND k.tanggal_mulai >= DATE_FORMAT(NOW(),'%Y-%m-01')");
$stmt2->execute([$pgw['id']]);
$bulanIni = (int)$stmt2->fetchColumn();

// Role pegawai
$roles = $db->prepare("SELECT r.nama_role AS nama FROM pegawai_role pr JOIN role r ON r.id=pr.role_id WHERE pr.pegawai_id=? ORDER BY r.nama_role");
$roles->execute([$pgw['id']]);
$roleList = array_column($roles->fetchAll(), 'nama');

$pegawai = $db->prepare("SELECT * FROM pegawai WHERE id=?");
$pegawai->execute([$pgw['id']]);
$p = $pegawai->fetch();

$pageTitle  = 'Profil';
$activePage = 'profil';
include '_header.php';
?>

<div class="profil-grid">
  <div class="profil-left">
    <!-- Profil Header -->
    <div class="profil-header card" style="border-radius:12px;margin-bottom:14px">
      <!-- Avatar / Foto Profil -->
      <div class="profil-avatar-wrap">
        <?php if (!empty($p['foto_profil']) && file_exists(ROOT_PATH . '/' . $p['foto_profil'])): ?>
          <img src="<?= BASE_URL ?>/<?= e($p['foto_profil']) ?>?v=<?= filemtime(ROOT_PATH . '/' . $p['foto_profil']) ?>"
               alt="Foto Profil" class="profil-avatar profil-avatar-img">
        <?php else: ?>
          <div class="profil-avatar"><?= strtoupper(mb_substr($p['nama'], 0, 1)) ?></div>
        <?php endif; ?>
      </div>

      <div class="profil-name"><?= e($p['nama']) ?></div>
      <div class="profil-email"><?= e($p['email']) ?></div>

      <!-- Tombol Edit Profil -->
      <button type="button" class="btn btn-edit-profil" id="btn-edit-profil"
              onclick="openEditProfil()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" style="width:14px;height:14px">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
        </svg>
        Edit Profil
      </button>
    </div>

    <!-- Logout -->
    <a href="<?= BASE_URL ?>/pegawai/logout.php" class="btn btn-danger btn-block" style="margin-bottom:14px">Keluar dari Akun</a>
  </div>

  <div class="profil-right">
    <!-- Statistik -->
    <div class="stat-row" style="margin-top:0">
      <div class="stat-box">
        <div class="stat-num"><?= $totalSelesai ?></div>
        <div class="stat-label">Total Kunjungan Selesai</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $bulanIni ?></div>
        <div class="stat-label">Kunjungan Bulan Ini</div>
      </div>
    </div>

    <!-- Role -->
    <div class="card mb-3">
      <div class="card-header"><h2>Role Kompetensi</h2></div>
      <div class="card-body">
        <?php if (empty($roleList)): ?>
        <p class="text-muted">Belum ada role yang ditetapkan.</p>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach ($roleList as $r): ?>
          <span class="badge badge-green" style="font-size:13px;padding:6px 14px"><?= e($r) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL EDIT PROFIL
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-profil" style="display:none" onclick="closeEditIfBackdrop(event)">
  <div class="modal modal-edit">
    <div class="modal-header-bar">
      <div class="modal-title">Edit Profil</div>
      <button type="button" class="modal-close-btn" onclick="closeEditProfil()" aria-label="Tutup">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" style="width:18px;height:18px">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <form action="<?= BASE_URL ?>/pegawai/update_profil.php" method="post" enctype="multipart/form-data" id="form-edit-profil">

      <!-- Preview Foto -->
      <div class="foto-upload-area">
        <div class="foto-preview-wrap" id="foto-preview-wrap">
          <?php if (!empty($p['foto_profil']) && file_exists(ROOT_PATH . '/' . $p['foto_profil'])): ?>
            <img src="<?= BASE_URL ?>/<?= e($p['foto_profil']) ?>" alt="Foto" id="foto-preview-img" class="foto-preview-img">
          <?php else: ?>
            <div class="foto-preview-initial" id="foto-preview-initial">
              <?= strtoupper(mb_substr($p['nama'], 0, 1)) ?>
            </div>
            <img src="" alt="Foto" id="foto-preview-img" class="foto-preview-img" style="display:none">
          <?php endif; ?>

          <!-- Overlay kamera -->
          <label for="foto-input" class="foto-camera-overlay" title="Ganti Foto">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2" style="width:20px;height:20px">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </label>
        </div>
        <input type="file" id="foto-input" name="foto" accept="image/*" style="display:none" onchange="previewFoto(this)">
        <p class="foto-hint">Ketuk foto untuk mengganti<br><small>JPG, PNG, WebP — maks. 2 MB</small></p>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label" for="edit-email">Email</label>
        <input type="email" id="edit-email" name="email" class="form-control"
               value="<?= e($p['email']) ?>" placeholder="nama@contoh.com" required>
      </div>

      <!-- Info nama (read-only) -->
      <div class="form-group">
        <label class="form-label">Nama</label>
        <div class="form-static"><?= e($p['nama']) ?></div>
        <p class="form-hint">Nama hanya dapat diubah oleh Admin.</p>
      </div>

      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-secondary" style="flex:1" onclick="closeEditProfil()">Batal</button>
        <button type="submit" class="btn btn-primary" style="flex:1" id="btn-simpan-profil">Simpan</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ── Tambahan style khusus halaman profil ── */

/* Wrap avatar agar bisa tumpuk kamera */
.profil-avatar-wrap {
  position: relative;
  width: 80px; height: 80px;
  margin: 0 auto 12px;
}
.profil-avatar-img {
  width: 80px; height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255,255,255,.35);
  display: block;
}
.profil-avatar {
  width: 80px; height: 80px;
  border-radius: 50%;
  background: var(--green);
  display: flex; align-items: center; justify-content: center;
  font-size: 30px; font-weight: 700; color: #fff;
  border: 3px solid rgba(255,255,255,.3);
  margin: 0;
}

/* Tombol edit kecil di card profil */
.btn-edit-profil {
  margin-top: 14px;
  background: rgba(255,255,255,.18);
  border: 1.5px solid rgba(255,255,255,.35);
  color: #fff;
  padding: 7px 18px;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s;
}
.btn-edit-profil:hover {
  background: rgba(255,255,255,.28);
  text-decoration: none;
  filter: none;
}

/* ── Modal edit profil ─────────────────────── */
.modal-edit {
  max-width: 420px;
}
.modal-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.modal-close-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--gray);
  padding: 4px;
  border-radius: 6px;
  display: flex;
  transition: background .15s;
}
.modal-close-btn:hover { background: var(--gray-l); }

/* ── Foto upload area ──────────────────────── */
.foto-upload-area {
  text-align: center;
  margin-bottom: 20px;
}
.foto-preview-wrap {
  position: relative;
  width: 90px; height: 90px;
  margin: 0 auto 8px;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
}
.foto-preview-initial {
  width: 90px; height: 90px;
  border-radius: 50%;
  background: var(--green);
  color: #fff;
  font-size: 32px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.foto-preview-img {
  width: 90px; height: 90px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}
.foto-camera-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.42);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  opacity: 0;
  transition: opacity .2s;
  cursor: pointer;
  border-radius: 50%;
}
.foto-preview-wrap:hover .foto-camera-overlay { opacity: 1; }
.foto-hint { font-size: 12px; color: var(--gray); line-height: 1.5; }
.foto-hint small { font-size: 11px; }

/* ── Form static (read-only info) ──────────── */
.form-static {
  padding: 9px 13px;
  background: var(--gray-l);
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-size: 14px;
  color: var(--text);
  margin-bottom: 4px;
}
.form-hint { font-size: 11.5px; color: var(--gray); margin-top: 4px; }
</style>

<script>
function openEditProfil() {
  document.getElementById('modal-edit-profil').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeEditProfil() {
  document.getElementById('modal-edit-profil').style.display = 'none';
  document.body.style.overflow = '';
}

function closeEditIfBackdrop(e) {
  if (e.target === document.getElementById('modal-edit-profil')) {
    closeEditProfil();
  }
}

function previewFoto(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (!file.type.startsWith('image/')) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('foto-preview-img');
    const initial = document.getElementById('foto-preview-initial');

    img.src = e.target.result;
    img.style.display = 'block';
    if (initial) initial.style.display = 'none';
  };
  reader.readAsDataURL(file);
}

// Loading state saat submit
document.getElementById('form-edit-profil').addEventListener('submit', function() {
  const btn = document.getElementById('btn-simpan-profil');
  btn.disabled = true;
  btn.textContent = 'Menyimpan…';
});
</script>

<?php include '_footer.php'; ?>
