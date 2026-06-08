<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

// Ambil tab aktif dari URL
$tab = $_GET['tab'] ?? 'jadwal';

// ── 1. PROSES FORM PENOLAKAN INLINE (Tab Notifikasi) ───────────
$errors = [];
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'tolak_jadwal') {
    $kunjunganId = post('kunjungan_id');
    $alasan      = post('alasan');

    if (!$kunjunganId) {
        $errors[] = 'Pilih jadwal aktif Anda.';
    }
    if (!$alasan) {
        $errors[] = 'Alasan tidak bersedia wajib diisi.';
    }

    if (empty($errors)) {
        // Validasi keanggotaan dan keaktifan kunjungan
        $chk = $db->prepare("
            SELECT k.id, pr.nama AS perusahaan, ja.nama AS jenis, k.tanggal_mulai, k.tanggal_selesai
            FROM penugasan_tim pt
            JOIN kunjungan k ON k.id = pt.kunjungan_id
            JOIN perusahaan pr ON pr.id = k.perusahaan_id
            JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
            WHERE pt.pegawai_id = ? AND k.id = ? AND k.status = 'Aktif'
        ");
        $chk->execute([$pgw['id'], $kunjunganId]);
        $k = $chk->fetch();

        if (!$k) {
            $errors[] = 'Jadwal tidak valid atau Anda sudah tidak terdaftar pada jadwal ini.';
        } else {
            try {
                $db->beginTransaction();

                // Update kunjungan status ke 'Butuh Intervensi'
                $db->prepare("UPDATE kunjungan SET status = 'Butuh Intervensi' WHERE id = ?")
                   ->execute([$kunjunganId]);

                // Kirim notifikasi ke seluruh admin
                $pesan = "Pegawai {$pgw['nama']} menyatakan tidak bersedia untuk kunjungan {$k['perusahaan']} ({$k['jenis']}, " . fmtRentang($k['tanggal_mulai'], $k['tanggal_selesai']) . "). Alasan: {$alasan}";
                kirimNotifikasiAllAdmin($db, $pesan, $kunjunganId);

                $db->commit();
                redirectWith('index.php?tab=notifikasi', 'success', 'Form penolakan berhasil dikirim. Jadwal Anda sedang ditinjau oleh Admin.');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Terjadi kesalahan sistem saat menyimpan penolakan: ' . $e->getMessage();
            }
        }
    }
}

// ── 2. PROSES MARK ALL AS READ (Tab Notifikasi) ───────────────
if (isset($_GET['baca_semua'])) {
    require_once '../includes/notifikasi.php';
    bacaSemuaNotifPegawai($db, $pgw['id']);
    redirectWith('index.php?tab=notifikasi', 'success', 'Semua notifikasi sudah dibaca.');
}

// ── 3. DATA LOADING UNTUK SELURUH TAB ─────────────────────────

// A. Tab Jadwal (Jadwal Aktif)
$stmt = $db->prepare("
    SELECT
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
        pt_all.pegawai_id AS tim_pegawai_id,
        pg_all.nama       AS tim_nama,
        r_all.nama_role   AS tim_role
    FROM penugasan_tim pt_me
    JOIN kunjungan     k      ON k.id      = pt_me.kunjungan_id
    JOIN perusahaan    pr     ON pr.id     = k.perusahaan_id
    JOIN jenis_audit   ja     ON ja.id     = k.jenis_audit_id
    JOIN role          r_me   ON r_me.id   = pt_me.role_id
    LEFT JOIN penugasan_tim pt_all ON pt_all.kunjungan_id = k.id
    LEFT JOIN pegawai       pg_all ON pg_all.id            = pt_all.pegawai_id
    LEFT JOIN role          r_all  ON r_all.id             = pt_all.role_id
    WHERE pt_me.pegawai_id = ? AND k.status = 'Aktif'
    ORDER BY k.tanggal_mulai ASC, r_all.nama_role, pg_all.nama
");
$stmt->execute([$pgw['id']]);
$rows = $stmt->fetchAll();

$jadwals = [];
$timMap  = [];
$seen    = [];
foreach ($rows as $row) {
    $kid = $row['kunjungan_id'];
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
    if ($row['tim_pegawai_id']) {
        $timMap[$kid][] = [
            'pegawai_id' => $row['tim_pegawai_id'],
            'nama'       => $row['tim_nama'],
            'role_nama'  => $row['tim_role'],
        ];
    }
}

// B. Tab Kalender
$jadwalList = $db->prepare("
    SELECT k.id, k.tanggal_mulai, k.tanggal_selesai, pr.nama AS perusahaan, r.nama_role AS role_nama
    FROM penugasan_tim pt
    JOIN kunjungan k   ON k.id  = pt.kunjungan_id
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN role r        ON r.id  = pt.role_id
    WHERE pt.pegawai_id = ? AND k.status = 'Aktif'
    ORDER BY k.tanggal_mulai ASC
");
$jadwalList->execute([$pgw['id']]);
$jadwalsKalender = $jadwalList->fetchAll();
$eventData = array_map(fn($j) => [
    'id'         => $j['id'],
    'perusahaan' => $j['perusahaan'],
    'role'       => $j['role_nama'],
    'tgl_mulai'  => $j['tanggal_mulai'],
    'tgl_selesai'=> $j['tanggal_selesai'],
], $jadwalsKalender);

// C. Tab Riwayat
$riwayatQuery = $db->prepare("
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
$riwayatQuery->execute([$pgw['id']]);
$riwayats = $riwayatQuery->fetchAll();
$tahunList = array_unique(array_map(fn($r) => substr($r['tanggal_mulai'],0,4), $riwayats));
rsort($tahunList);

// D. Tab Notifikasi
require_once '../includes/notifikasi.php';
$notifs = getNotifikasi($db, 'pegawai', $pgw['id'], 50);

// E. Tab Profil
$stmtP1 = $db->prepare("SELECT COUNT(*) FROM penugasan_tim pt JOIN kunjungan k ON k.id=pt.kunjungan_id WHERE pt.pegawai_id=? AND k.status='Selesai'");
$stmtP1->execute([$pgw['id']]);
$totalSelesai = (int)$stmtP1->fetchColumn();

$stmtP2 = $db->prepare("SELECT COUNT(*) FROM penugasan_tim pt JOIN kunjungan k ON k.id=pt.kunjungan_id WHERE pt.pegawai_id=? AND k.status IN ('Aktif','Selesai') AND k.tanggal_mulai >= DATE_FORMAT(NOW(),'%Y-%m-01')");
$stmtP2->execute([$pgw['id']]);
$bulanIni = (int)$stmtP2->fetchColumn();

$rolesQuery = $db->prepare("SELECT r.nama_role AS nama FROM pegawai_role pr JOIN role r ON r.id=pr.role_id WHERE pr.pegawai_id=? ORDER BY r.nama_role");
$rolesQuery->execute([$pgw['id']]);
$roleList = array_column($rolesQuery->fetchAll(), 'nama');

$pegawai = $db->prepare("SELECT * FROM pegawai WHERE id=?");
$pegawai->execute([$pgw['id']]);
$p = $pegawai->fetch();


// Judul halaman dinamis berdasarkan tab
$titles = [
    'jadwal' => 'Jadwal Saya',
    'kalender' => 'Kalender Agenda',
    'riwayat' => 'Riwayat Kunjungan',
    'notifikasi' => 'Notifikasi',
    'profil' => 'Profil Saya'
];
$pageTitle  = $titles[$tab] ?? 'Portal Pegawai';
$activePage = $tab;
include '_header.php';
?>

<!-- ERROR ALERT BAR UNTUK FORM PENOLAKAN -->
<?php foreach ($errors as $err): ?>
<div class="alert alert-error" style="margin-bottom:14px"><?= e($err) ?></div>
<?php endforeach; ?>

<!-- ── TAB 1: JADWAL SAYA ──────────────────────────────────────── -->
<?php if ($tab === 'jadwal'): ?>
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

<!-- ── TAB 2: KALENDER AGENDA ─────────────────────────────────── -->
<?php elseif ($tab === 'kalender'): ?>
<h2 style="font-size:18px;font-weight:700;color:#1A1F2E;margin-bottom:14px">Kalender Agenda</h2>

<div class="card mb-3">
  <div class="card-body">
    <div id="pgw-kalender"></div>
  </div>
</div>

<?php
$bulanIniFormat = date('Y-m');
$jadwalBulanIni = array_filter($jadwalsKalender, fn($j) => substr($j['tanggal_mulai'],0,7)===$bulanIniFormat || substr($j['tanggal_selesai'],0,7)===$bulanIniFormat);
?>
<div class="card">
  <div class="card-header"><h2>Kunjungan Bulan Ini</h2></div>
  <div class="card-body" style="padding:0">
    <?php if (empty($jadwalBulanIni)): ?>
    <div class="empty-state" style="padding:28px"><p>Tidak ada kunjungan bulan ini.</p></div>
    <?php else: ?>
    <?php foreach ($jadwalBulanIni as $j): ?>
    <div class="riwayat-item" style="padding:14px 16px">
      <div>
        <div class="riwayat-title"><?= e($j['perusahaan']) ?></div>
        <div class="riwayat-sub"><?= fmtRentang($j['tanggal_mulai'],$j['tanggal_selesai']) ?> · <span class="badge badge-blue" style="font-size:10px"><?= e($j['role_nama']) ?></span></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ── TAB 3: RIWAYAT KUNJUNGAN ────────────────────────────────── -->
<?php elseif ($tab === 'riwayat'): ?>
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

<!-- ── TAB 4: NOTIFIKASI & INLINE REFUSAL FORM ────────────────── -->
<?php elseif ($tab === 'notifikasi'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <h2 style="font-size:18px;font-weight:700;color:#1A1F2E">Notifikasi</h2>
  <a href="?baca_semua=1" class="btn btn-secondary btn-sm">Baca Semua</a>
</div>

<div class="card">
  <?php if (empty($notifs)): ?>
  <div class="empty-state" style="padding:40px">
    <p>Tidak ada notifikasi.</p>
  </div>
  <?php else: ?>
  <?php foreach ($notifs as $n): ?>
  <div class="notif-item <?= !$n['sudah_dibaca']?'unread':'' ?>">
    <div class="notif-dot <?= $n['sudah_dibaca']?'read':'' ?>"></div>
    <div style="flex:1">
      <?php if ($n['perusahaan_nama']): ?>
      <div style="font-weight:700;font-size:12px;color:#059669;margin-bottom:2px"><?= e($n['perusahaan_nama']) ?></div>
      <?php endif; ?>
      <div class="notif-msg"><?= e($n['pesan']) ?></div>
      <div class="notif-time"><?= fmtTanggal(substr($n['dibuat_pada'],0,10)) ?> · <?= substr($n['dibuat_pada'],11,5) ?></div>
      
      <?php if ($n['kunjungan_id'] && isset($n['kunjungan_status']) && $n['kunjungan_status'] === 'Aktif' && (int)$n['is_assigned'] > 0): ?>
      <div style="margin-top:8px">
        <button type="button" class="btn btn-danger btn-sm" style="display:inline-flex;align-items:center;gap:4px" onclick="toggleRefusalForm('<?= e($n['kunjungan_id']) ?>')">
          Tidak Bersedia
        </button>
      </div>

      <!-- INLINE REFUSAL CARD FORM -->
      <div id="form-penolakan-<?= e($n['kunjungan_id']) ?>" class="form-penolakan-container" style="display:none; margin-top:12px; padding:16px; border:1.5px solid #FCA5A5; background:#FEF2F2; border-radius:10px; max-width:500px">
        <form method="POST" action="index.php?tab=notifikasi">
          <input type="hidden" name="action" value="tolak_jadwal">
          <input type="hidden" name="kunjungan_id" value="<?= e($n['kunjungan_id']) ?>">
          <div class="form-group">
            <label class="form-label" style="color:#991B1B; font-weight:700; font-size:13px; margin-bottom:6px">Alasan Tidak Bersedia</label>
            <textarea name="alasan" class="form-control" rows="3" placeholder="Tuliskan alasan profesional Anda tidak dapat menghadiri tugas ini..." required style="width:100%; font-size:13px; border-color:#FCA5A5; background:#fff"></textarea>
          </div>
          <div style="display:flex; gap:8px; margin-top:10px">
            <button type="submit" class="btn btn-danger btn-sm">Kirim Penolakan</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRefusalForm('<?= e($n['kunjungan_id']) ?>')">Batal</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── TAB 5: PROFIL & EDIT MODAL ────────────────────────────── -->
<?php elseif ($tab === 'profil'): ?>
<div class="profil-grid">
  <div class="profil-left">
    <div class="profil-header card" style="border-radius:12px;margin-bottom:14px">
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

    <a href="<?= BASE_URL ?>/pegawai/logout.php" class="btn btn-danger btn-block" style="margin-bottom:14px">Keluar dari Akun</a>
  </div>

  <div class="profil-right">
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

<!-- MODAL EDIT PROFIL -->
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

      <div class="form-group">
        <label class="form-label" for="edit-email">Email</label>
        <input type="email" id="edit-email" name="email" class="form-control"
               value="<?= e($p['email']) ?>" placeholder="nama@contoh.com" required>
      </div>

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
.modal-edit { max-width: 420px; }
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
.foto-upload-area { text-align: center; margin-bottom: 20px; }
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
document.getElementById('form-edit-profil').addEventListener('submit', function() {
  const btn = document.getElementById('btn-simpan-profil');
  btn.disabled = true;
  btn.textContent = 'Menyimpan…';
});
</script>
<?php endif; ?>

<!-- ── INITIALIZE INTERACTIVE COMPONENTS ───────────────────────── -->
<script>
// Toggle refusal card form in Tab Notifikasi
function toggleRefusalForm(kunjunganId) {
  const form = document.getElementById('form-penolakan-' + kunjunganId);
  if (form) {
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }
}

// Initialize calendars or filters depending on tab
document.addEventListener('DOMContentLoaded', () => {
  <?php if ($tab === 'kalender'): ?>
  if (typeof initKalenderPegawai === 'function') {
    initKalenderPegawai(<?= json_encode(array_values($eventData)) ?>);
  }
  <?php elseif ($tab === 'riwayat'): ?>
  const yrFilter = document.getElementById('filter-tahun');
  const moFilter = document.getElementById('filter-bulan');
  const items    = document.querySelectorAll('.riwayat-item');

  function filter() {
    const yr = yrFilter.value;
    const mo = moFilter.value;
    items.forEach(item => {
      const dateStr = item.getAttribute('data-riwayat-date');
      const itemYr  = dateStr.substring(0, 4);
      const itemMo  = dateStr.substring(5, 7);

      const yrMatch = !yr || itemYr === yr;
      const moMatch = !mo || itemMo === mo;

      item.style.display = (yrMatch && moMatch) ? 'flex' : 'none';
    });
  }

  yrFilter?.addEventListener('change', filter);
  moFilter?.addEventListener('change', filter);
  <?php endif; ?>
});
</script>

<?php include '_footer.php'; ?>
