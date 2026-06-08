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

$errors = [];

// ── POST: Simpan kunjungan ───────────────────────────────
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'simpan') {
    $perusahaanId = post('perusahaan_id');
    $jenisId      = post('jenis_audit_id');
    $tglMulai     = post('tanggal_mulai');
    $tglSelesai   = post('tanggal_selesai');
    $catatan      = post('catatan');

    if (!$perusahaanId) $errors[] = 'Perusahaan wajib dipilih dari daftar.';
    if (!$jenisId)      $errors[] = 'Jenis audit wajib dipilih.';
    if (!$tglMulai)     $errors[] = 'Tanggal mulai wajib diisi.';
    if (!$tglSelesai)   $errors[] = 'Tanggal selesai wajib diisi.';
    if ($tglMulai && $tglSelesai && $tglSelesai < $tglMulai) {
        $errors[] = 'Tanggal selesai tidak boleh mendahului tanggal mulai.';
    }

    if (empty($errors)) {
        $override = [];
        if (!empty($_POST['anggota']) && is_array($_POST['anggota'])) {
            foreach ($_POST['anggota'] as $roleId => $pegIds) {
                $filtered = array_filter(array_map('trim', (array)$pegIds));
                if (!empty($filtered)) {
                    $override[$roleId] = array_values($filtered);
                }
            }
        }

        // Validasi server-side: semua slot harus terisi
        $formasi = getFormasiByJenis($db, $jenisId);
        $slotError = false;
        foreach ($formasi as $slot) {
            $filled = count($override[$slot['role_id']] ?? []);
            if ($filled < $slot['jumlah_slot']) {
                $errors[] = "Slot '{$slot['role_nama']}' belum lengkap ({$filled}/{$slot['jumlah_slot']}).";
                $slotError = true;
            }
        }

        if (!$slotError) {
            // Jika override kosong total, fallback ke otomatis
            if (empty($override)) {
                $preview = buildPreviewTim($db, $jenisId, $tglMulai, $tglSelesai, $perusahaanId);
                foreach ($preview as $roleId => $slot) {
                    $override[$roleId] = array_column($slot['terpilih'], 'id');
                }
            }

            try {
                $kunjunganId = simpanKunjungan($db, $perusahaanId, $jenisId, $tglMulai, $tglSelesai, $catatan, $admin['id'], $override);
            } catch (\RuntimeException $e) {
                // Guard admin_id gagal → sesi tidak valid, arahkan ke login
                redirectWith(BASE_URL . '/admin/login.php', 'error', $e->getMessage());
            }

            $anggota = $db->prepare("SELECT DISTINCT pegawai_id FROM penugasan_tim WHERE kunjungan_id=?");
            $anggota->execute([$kunjunganId]);
            foreach ($anggota->fetchAll() as $a) {
                kirimNotifikasi($db, 'pegawai', $a['pegawai_id'],
                    "Anda mendapat penugasan audit baru. Jadwal telah aktif.", $kunjunganId);
            }

            $notif = new NotificationService($db);
            if ($notif->isReady()) {
                $notif->kirimPenugasanBaru($kunjunganId);
            }

            redirectWith(BASE_URL . '/admin/jadwal/detail.php?id=' . $kunjunganId, 'success', 'Kunjungan berhasil dibuat dan tim telah dibentuk!');
        }

    }
}

$jenisList = $db->query("SELECT id, nama FROM jenis_audit WHERE aktif=1 ORDER BY nama")->fetchAll();

$pageTitle  = 'Buat Kunjungan Baru';
$activePage = 'jadwal';
include '../_header.php';
?>

<div class="page-header">
  <div>
    <h1>Buat Kunjungan Baru</h1>
    <div class="breadcrumb"><a href="index.php">Jadwal</a> / Buat Baru</div>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="POST" action="" id="form-kunjungan">
<input type="hidden" name="action" value="simpan">

<div style="display:grid;grid-template-columns:1fr 420px;gap:20px;align-items:start">

<!-- ── Kolom Kiri: Form Detail ────────────────────────────── -->
<div>
  <div class="card">
    <div class="card-header"><h2>Detail Kunjungan</h2></div>
    <div class="card-body">

      <!-- Perusahaan Live Search -->
      <div class="form-group" id="group-perusahaan">
        <label class="form-label" for="perusahaan_nama">Nama Perusahaan Klien <span class="req">*</span></label>
        <div style="position:relative">
          <input id="perusahaan_nama" type="text" class="form-control"
                 placeholder="Ketik minimal 2 karakter..." autocomplete="off"
                 value="<?= e(post('perusahaan_nama')) ?>">
          <input type="hidden" id="perusahaan_id" name="perusahaan_id" value="<?= e(post('perusahaan_id')) ?>">
          <div id="dropdown-perusahaan" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;background:#fff;border:1px solid #E5E7EB;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.10);overflow:hidden;margin-top:3px"></div>
        </div>
        <div id="msg-not-found" style="display:none;margin-top:8px;padding:10px 12px;background:#FEF9C3;border:1px solid #FDE68A;border-radius:8px;font-size:13px;color:#92400E">
          Perusahaan "<span id="txt-not-found"></span>" belum terdaftar.
          <br><button type="button" id="btn-tambah-perusahaan" class="btn btn-sm" style="margin-top:6px;background:#F59E0B;color:#fff;border:none">+ Tambahkan Perusahaan</button>
        </div>
        <div id="form-inline-perusahaan" style="display:none;margin-top:8px;padding:14px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px">
          <div style="font-size:12px;font-weight:600;color:#15803D;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px">Daftarkan Perusahaan Baru</div>
          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:12px">Nama Perusahaan</label>
            <input id="new_perusahaan_nama" type="text" class="form-control" style="font-size:13px" placeholder="Nama perusahaan...">
          </div>
          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:12px">Alamat <span class="req">*</span></label>
            <input id="new_perusahaan_alamat" type="text" class="form-control" style="font-size:13px" placeholder="Alamat lengkap...">
          </div>
          <div style="display:flex;gap:8px">
            <button type="button" id="btn-simpan-perusahaan" class="btn btn-success btn-sm">Simpan &amp; Gunakan</button>
            <button type="button" id="btn-batal-perusahaan" class="btn btn-secondary btn-sm">Batal</button>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="perusahaan_alamat">Alamat Perusahaan</label>
        <input id="perusahaan_alamat" type="text" class="form-control"
               placeholder="Terisi otomatis setelah perusahaan dipilih"
               readonly style="background:#F9FAFB;color:#6B7280">
      </div>

      <div class="form-group">
        <label class="form-label" for="jenis_audit_id">Jenis Audit <span class="req">*</span></label>
        <select id="jenis_audit_id" name="jenis_audit_id" class="form-control" required>
          <option value="">-- Pilih Jenis Audit --</option>
          <?php foreach ($jenisList as $j): ?>
          <option value="<?= e($j['id']) ?>" <?= post('jenis_audit_id')===$j['id']?'selected':'' ?>><?= e($j['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="tanggal_mulai">Tanggal Mulai <span class="req">*</span></label>
          <input id="tanggal_mulai" type="date" name="tanggal_mulai" class="form-control"
                 value="<?= e(post('tanggal_mulai')) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="tanggal_selesai">Tanggal Selesai <span class="req">*</span></label>
          <input id="tanggal_selesai" type="date" name="tanggal_selesai" class="form-control"
                 value="<?= e(post('tanggal_selesai')) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="catatan">Catatan / Instruksi Khusus</label>
        <textarea id="catatan" name="catatan" class="form-control" placeholder="Opsional..."><?= e(post('catatan')) ?></textarea>
      </div>

      <button type="button" id="btn-preview" class="btn btn-navy" style="width:100%;justify-content:center">
        Preview Tim Otomatis
      </button>
    </div>
  </div>
</div>

<!-- ── Kolom Kanan: Preview Formasi Tim ───────────────────── -->
<div>
  <!-- Status bar validasi -->
  <div id="status-bar" style="display:none;margin-bottom:12px;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px"></div>

  <div class="card">
    <div class="card-header" style="justify-content:space-between">
      <h2>Formasi Tim</h2>
      <button type="button" id="btn-acak-ulang" class="btn btn-secondary btn-sm" style="display:none">Acak Ulang</button>
    </div>
    <div class="card-body" style="padding:0">
      <div id="preview-tim-box" style="padding:32px 20px;color:#9CA3AF;text-align:center">
        <p>Lengkapi form di sebelah kiri<br>lalu klik <strong>Preview Tim Otomatis</strong></p>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <button type="submit" id="btn-simpan" class="btn btn-success" style="width:100%;justify-content:center;padding:12px;font-size:15px" disabled>
        Simpan &amp; Aktifkan Jadwal
      </button>
      <div id="simpan-hint" style="font-size:11px;color:#9CA3AF;text-align:center;margin-top:6px">Lengkapi semua slot formasi terlebih dahulu</div>
      <a href="index.php" class="btn btn-secondary mt-3" style="width:100%;justify-content:center">Batal</a>
    </div>
  </div>
</div>

</div><!-- end grid -->
</form>

<!-- Dropdown pilih manual removed -->

<<style>
/* ── Formasi Slot Cards ──────────────────────────────────── */
.formasi-slot {
  padding: 14px 16px;
  border-bottom: 1px solid #F3F4F6;
}
.formasi-slot:last-child { border-bottom: none; }
.formasi-slot-label {
  font-size: 11px; font-weight: 700;
  color: #64748B; text-transform: uppercase; letter-spacing: .6px;
  margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.slot-badge {
  background: #E0E7FF; color: #3730A3;
  padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 700;
}
.slot-badge.full  { background: #D1FAE5; color: #065F46; }
.slot-badge.empty { background: #FEE2E2; color: #991B1B; }

.anggota-card {
  display: flex; flex-direction: column; gap: 8px;
  background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 8px;
  padding: 12px; margin-bottom: 8px;
  transition: border-color .15s;
}
.anggota-card.valid { border-color: #10B981; background: #F0FDF4; }
.anggota-card.empty-slot {
  border: 2.5px dashed #FCA5A5; background: #FFF5F5;
  color: #EF4444; font-size: 13px; padding: 16px;
  border-radius: 8px; margin-bottom: 8px; text-align: center;
}
.anggota-top {
  display: flex; align-items: center; gap: 10px;
}
.anggota-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: #0F172A; color: #fff; font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.anggota-info { flex: 1; min-width: 0; }
.anggota-nama { font-size: 13px; font-weight: 600; color: #1E293B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.anggota-beban { font-size: 11px; color: #64748B; margin-top: 1px; }
.anggota-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-ganti { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .15s; }
.btn-ganti:hover { background: #DBEAFE; }
.btn-hapus { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
.btn-hapus:hover { background: #FEE2E2; }

/* Status bar */
.status-ok   { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
.status-err  { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
.status-load { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
</style>

<script>
const BASE_URL = '<?= BASE_URL ?>';

// ═══════════════════════════════════════════════════════════
// State global
// ═══════════════════════════════════════════════════════════
let formasiState = {};
// formasiState[roleId] = {
//   role_id, role_nama, jumlah_slot,
//   anggota: [ {id, nama, beban_bulan} | null, ... ]  // null = slot kosong
// }
let currentManualTarget = null; // { roleId, slotIdx }

// ═══════════════════════════════════════════════════════════
// Live Search Perusahaan
// ═══════════════════════════════════════════════════════════
(function() {
  const inputNama   = document.getElementById('perusahaan_nama');
  const inputId     = document.getElementById('perusahaan_id');
  const inputAlamat = document.getElementById('perusahaan_alamat');
  const dropdown    = document.getElementById('dropdown-perusahaan');
  const msgNF       = document.getElementById('msg-not-found');
  const txtNF       = document.getElementById('txt-not-found');
  const btnTambah   = document.getElementById('btn-tambah-perusahaan');
  const formInline  = document.getElementById('form-inline-perusahaan');
  const newNama     = document.getElementById('new_perusahaan_nama');
  const newAlamat   = document.getElementById('new_perusahaan_alamat');
  const btnSimpan   = document.getElementById('btn-simpan-perusahaan');
  const btnBatal    = document.getElementById('btn-batal-perusahaan');
  let timer = null;
  let dipilih = false;

  function pilih(id, nama, alamat) {
    inputId.value = id; inputNama.value = nama; inputAlamat.value = alamat || '';
    inputAlamat.readOnly = true; inputAlamat.style.background = '#F9FAFB';
    dipilih = true; tutupDD(); msgNF.style.display = 'none'; formInline.style.display = 'none';
  }
  function tutupDD() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }
  function reset() { inputId.value = ''; inputAlamat.value = ''; inputAlamat.readOnly = false; inputAlamat.style.background = ''; dipilih = false; }

  function render(results) {
    tutupDD(); if (!results.length) return;
    results.forEach(p => {
      const d = document.createElement('div');
      d.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid #F3F4F6;transition:background .15s';
      d.onmouseenter = function() { this.style.background='#F0F9FF'; };
      d.onmouseleave = function() { this.style.background=''; };
      const a = p.alamat && p.alamat.length > 60 ? p.alamat.substring(0,60)+'…' : (p.alamat||'');
      d.innerHTML = '<div style="font-weight:600;font-size:13px;color:#111827">'+esc(p.nama)+'</div>'+(a?'<div style="font-size:11px;color:#6B7280;margin-top:2px">'+esc(a)+'</div>':'');
      d.addEventListener('click', () => pilih(p.id, p.nama, p.alamat));
      dropdown.appendChild(d);
    });
    dropdown.style.display = 'block';
  }

  function doSearch(q) {
    if (q.length < 2) { tutupDD(); msgNF.style.display='none'; return; }
    fetch(BASE_URL+'/api/perusahaan_search.php?q='+encodeURIComponent(q))
      .then(r=>r.json()).then(res => {
        if (res.length) { render(res); msgNF.style.display='none'; formInline.style.display='none'; }
        else { tutupDD(); txtNF.textContent=q; msgNF.style.display='block'; formInline.style.display='none'; }
      }).catch(()=>tutupDD());
  }

  inputNama.addEventListener('input', function() {
    reset(); clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 2) { tutupDD(); msgNF.style.display='none'; formInline.style.display='none'; return; }
    timer = setTimeout(()=>doSearch(q), 250);
  });
  inputNama.addEventListener('change', function() {
    if (!this.value.trim()) { reset(); tutupDD(); msgNF.style.display='none'; formInline.style.display='none'; }
  });
  document.addEventListener('click', e => { if (!e.target.closest('#group-perusahaan')) tutupDD(); });
  btnTambah.addEventListener('click', () => { newNama.value=inputNama.value.trim(); newAlamat.value=''; formInline.style.display='block'; msgNF.style.display='none'; newAlamat.focus(); });
  btnSimpan.addEventListener('click', function() {
    const nama=newNama.value.trim(), alamat=newAlamat.value.trim();
    if(!nama){alert('Nama wajib diisi.');return;} if(!alamat){alert('Alamat wajib diisi.');return;}
    this.disabled=true; this.textContent='Menyimpan...';
    fetch(BASE_URL+'/api/perusahaan_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nama,alamat})})
      .then(r=>r.json()).then(res=>{ if(res.success){pilih(res.id,res.nama,res.alamat);formInline.style.display='none';msgNF.style.display='none';} else alert(res.message||'Gagal.'); })
      .catch(()=>alert('Kesalahan jaringan.'))
      .finally(()=>{this.disabled=false;this.textContent='Simpan & Gunakan';});
  });
  btnBatal.addEventListener('click', () => { formInline.style.display='none'; inputNama.value=''; inputId.value=''; reset(); });
})();

// ═══════════════════════════════════════════════════════════
// PREVIEW TIM — Load & Render
// ═══════════════════════════════════════════════════════════
function getFormValues() {
  return {
    perusahaanId: document.getElementById('perusahaan_id').value,
    jenisId:      document.getElementById('jenis_audit_id').value,
    tglMulai:     document.getElementById('tanggal_mulai').value,
    tglSelesai:   document.getElementById('tanggal_selesai').value,
  };
}

document.getElementById('btn-preview').addEventListener('click', loadPreview);
document.getElementById('btn-acak-ulang').addEventListener('click', loadPreview);

function loadPreview() {
  const f = getFormValues();
  if (!f.perusahaanId || !f.jenisId || !f.tglMulai || !f.tglSelesai) {
    tampilStatusBar('err', 'Lengkapi semua field dan pilih perusahaan dari daftar terlebih dahulu.');
    return;
  }
  if (f.tglSelesai < f.tglMulai) {
    tampilStatusBar('err', 'Tanggal selesai tidak boleh mendahului tanggal mulai.');
    return;
  }

  tampilStatusBar('load', 'Memuat formasi tim...');
  document.getElementById('preview-tim-box').innerHTML = '<div style="padding:32px;text-align:center;color:#64748B"><p>Membangun formasi tim...</p></div>';
  document.getElementById('btn-simpan').disabled = true;

  const url = BASE_URL+'/api/preview_tim.php'
    +'?perusahaan_id='+encodeURIComponent(f.perusahaanId)
    +'&jenis_id='+encodeURIComponent(f.jenisId)
    +'&tgl_mulai='+encodeURIComponent(f.tglMulai)
    +'&tgl_selesai='+encodeURIComponent(f.tglSelesai);

  fetch(url).then(r=>r.json()).then(data => {
    if (!data || data.error) {
      document.getElementById('preview-tim-box').innerHTML = '<div style="padding:24px;text-align:center;color:#EF4444">' + esc(data.error || 'Gagal memuat preview.') + '</div>';
      tampilStatusBar('err', (data.error || 'Gagal memuat preview.'));
      return;
    }
    // Bangun formasiState dari response
    formasiState = {};
    (Array.isArray(data)?data:Object.values(data)).forEach(slot => {
      const anggota = Array.from({length: slot.jumlah_slot}, (_, i) => slot.terpilih[i] || null);
      formasiState[slot.role_id] = {
        role_id:     slot.role_id,
        role_nama:   slot.role_nama,
        jumlah_slot: slot.jumlah_slot,
        anggota:     anggota,
      };
    });
    renderFormasiPanel();
    document.getElementById('btn-acak-ulang').style.display = '';
  }).catch(() => {
    document.getElementById('preview-tim-box').innerHTML = '<div style="padding:24px;color:#EF4444;text-align:center">Gagal terhubung ke server.</div>';
    tampilStatusBar('err', 'Gagal terhubung ke server.');
  });
}

// ═══════════════════════════════════════════════════════════
// Render Panel Formasi — pakai event delegation, bukan onclick inline
// ═══════════════════════════════════════════════════════════
function renderFormasiPanel() {
  const box = document.getElementById('preview-tim-box');
  let html = '';

  Object.values(formasiState).forEach(slot => {
    const terisi = slot.anggota.filter(a=>a!==null).length;
    const badgeClass = terisi === slot.jumlah_slot ? 'full' : 'empty';
    const badgeTxt   = terisi+'/'+slot.jumlah_slot;

    html += '<div class="formasi-slot" data-role-id="'+esc(slot.role_id)+'">';
    html += '<div class="formasi-slot-label">'+esc(slot.role_nama)+' <span class="slot-badge '+badgeClass+'">'+badgeTxt+'</span></div>';

    slot.anggota.forEach((anggota, idx) => {
      if (anggota) {
        const inisial = anggota.nama.split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase();
        html += '<div class="anggota-card valid">';
        html += '  <input type="hidden" name="anggota['+esc(slot.role_id)+'][]" value="'+esc(anggota.id)+'">';
        html += '  <div class="anggota-top">';
        html += '    <div class="anggota-avatar">'+esc(inisial)+'</div>';
        html += '    <div class="anggota-info">';
        html += '      <div class="anggota-nama">'+esc(anggota.nama)+'</div>';
        html += '      <div class="anggota-beban">Beban bulan ini: '+String(anggota.beban_bulan)+' penugasan</div>';
        html += '    </div>';
        html += '  </div>';
        html += '  <div class="anggota-actions">';
        html += '    <button type="button" class="btn-ganti" data-role="'+esc(slot.role_id)+'" data-idx="'+idx+'">Ganti Otomatis</button>';
        html += '    <button type="button" class="btn-hapus" data-role="'+esc(slot.role_id)+'" data-idx="'+idx+'">Hapus</button>';
        html += '  </div>';
        html += '</div>';
      } else {
        html += '<div class="anggota-card empty-slot">';
        html += '  <div style="color:#EF4444;font-size:13px;text-align:center;margin-bottom:6px">Slot kosong</div>';
        html += '  <button type="button" class="btn-ganti" style="width:100%;text-align:center" data-role="'+esc(slot.role_id)+'" data-idx="'+idx+'">Pilih Otomatis</button>';
        html += '</div>';
      }
    });

    html += '</div>';
  });

  box.innerHTML = html || '<div style="padding:24px;text-align:center;color:#9CA3AF">Tidak ada formasi untuk jenis audit ini.</div>';

  updateValidasi();
}

// Event delegation — dipasang satu kali saja di luar renderFormasiPanel
document.getElementById('preview-tim-box').addEventListener('click', handleFormasiClick);

function handleFormasiClick(e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  const roleId = btn.dataset.role;
  const idx    = btn.dataset.idx !== undefined ? parseInt(btn.dataset.idx) : -1;
  if (!roleId) return;

  if (btn.classList.contains('btn-ganti')) {
    gantiAnggotaOtomatis(roleId, idx);
  } else if (btn.classList.contains('btn-hapus')) {
    hapusAnggota(roleId, idx);
  }
}


// ═══════════════════════════════════════════════════════════
// Ganti Anggota Otomatis (beban terkecil)
// ═══════════════════════════════════════════════════════════
function gantiAnggotaOtomatis(roleId, slotIdx) {
  const f = getFormValues();
  const slot = formasiState[roleId];
  if (!slot) return;

  const excludeIds = getAllSelectedIds(roleId, slotIdx);

  const url = BASE_URL+'/api/pegawai_by_role.php'
    +'?role_id='+encodeURIComponent(roleId)
    +'&tgl_mulai='+encodeURIComponent(f.tglMulai)
    +'&tgl_selesai='+encodeURIComponent(f.tglSelesai)
    +'&perusahaan_id='+encodeURIComponent(f.perusahaanId)
    +'&jenis_id='+encodeURIComponent(f.jenisId)
    +'&exclude_ids='+encodeURIComponent(JSON.stringify(excludeIds));

  tampilStatusBar('load', 'Mencari pengganti...');
  fetch(url).then(r=>r.json()).then(kandidat => {
    if (!kandidat || kandidat.error || kandidat.length === 0) {
      formasiState[roleId].anggota[slotIdx] = null;
      renderFormasiPanel();
      tampilStatusBar('err', 'Tidak ada kandidat pengganti untuk slot '+slot.role_nama+'.');
      return;
    }
    formasiState[roleId].anggota[slotIdx] = kandidat[0];
    renderFormasiPanel();
  }).catch(() => tampilStatusBar('err', 'Gagal terhubung ke server.'));
}


// ═══════════════════════════════════════════════════════════
// Hapus Anggota
// ═══════════════════════════════════════════════════════════
function hapusAnggota(roleId, slotIdx) {
  formasiState[roleId].anggota[slotIdx] = null;
  renderFormasiPanel();
}

// ═══════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════

/** Kumpulkan semua ID yang sudah dipilih (exclude roleId+slotIdx tertentu) */
function getAllSelectedIds(skipRoleId = null, skipSlotIdx = -1) {
  const ids = [];
  Object.values(formasiState).forEach(slot => {
    slot.anggota.forEach((a, idx) => {
      if (!a) return;
      if (slot.role_id === skipRoleId && idx === skipSlotIdx) return;
      ids.push(a.id);
    });
  });
  return ids;
}

/** Update status bar dan tombol simpan */
function updateValidasi() {
  let totalSlot = 0, terisiSlot = 0;
  const missingRoles = [];

  Object.values(formasiState).forEach(slot => {
    slot.anggota.forEach(a => {
      totalSlot++;
      if (a) terisiSlot++;
    });
    const kosong = slot.anggota.filter(a=>!a).length;
    if (kosong > 0) missingRoles.push(slot.role_nama + ' ('+kosong+' kosong)');
  });

  const semua = terisiSlot === totalSlot && totalSlot > 0;
  const btnSimpan = document.getElementById('btn-simpan');
  const hint = document.getElementById('simpan-hint');

  if (totalSlot === 0) {
    tampilStatusBar('load', 'Membangun formasi...');
    btnSimpan.disabled = true;
    hint.textContent = 'Menunggu data formasi...';
    return;
  }

  if (semua) {
    tampilStatusBar('ok', 'Formasi Lengkap — '+terisiSlot+'/'+totalSlot+' slot terisi. Siap disimpan.');
    btnSimpan.disabled = false;
    hint.textContent = 'Klik simpan untuk mengaktifkan jadwal.';
  } else {
    tampilStatusBar('err', missingRoles.join(', ') + ' — Lengkapi sebelum menyimpan.');
    btnSimpan.disabled = true;
    hint.textContent = (totalSlot - terisiSlot)+' slot belum terisi.';
  }
}

function tampilStatusBar(type, msg) {
  const bar = document.getElementById('status-bar');
  bar.style.display = 'flex';
  bar.className = 'status-'+type;
  bar.textContent = msg;
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>

<?php include '../_footer.php'; ?>
