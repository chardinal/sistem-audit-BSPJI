<?php
// admin/email_debug.php — Halaman diagnosa email Google API
// Akses: http://localhost:8080/admin/email_debug.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();
$db = getDB();

$checks  = [];
$canSend = false;

// ── Check 1: vendor/autoload.php ────────────────────────
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) {
    $checks[] = ['ok', 'vendor/autoload.php ditemukan ✓'];
    require_once $vendorPath;
} else {
    $checks[] = ['fail', 'vendor/autoload.php TIDAK ADA — jalankan: docker compose up -d --build'];
}

// ── Check 2: google_credentials.json ────────────────────
$credPath = __DIR__ . '/../config/google_credentials.json';
if (!file_exists($credPath)) {
    $checks[] = ['fail', 'config/google_credentials.json TIDAK ADA'];
} else {
    $cred = json_decode(file_get_contents($credPath), true);
    $clientId = $cred['web']['client_id'] ?? '';
    if (str_contains($clientId, 'GANTI_INI')) {
        $checks[] = ['fail', 'client_id masih placeholder — isi dengan Client ID dari Google Cloud Console'];
    } elseif (empty($clientId)) {
        $checks[] = ['fail', 'client_id kosong di google_credentials.json'];
    } else {
        $checks[] = ['ok', 'google_credentials.json valid (client_id: …' . substr($clientId, -20) . ')'];
    }
}

// ── Check 3: google_token.json ───────────────────────────
$tokenPath = __DIR__ . '/../config/google_token.json';
if (!file_exists($tokenPath)) {
    $checks[] = ['fail', 'config/google_token.json TIDAK ADA — OAuth belum dijalankan!'];
    $checks[] = ['info', 'Klik tombol "Jalankan OAuth" di bawah untuk mendapatkan token.'];
} else {
    $token = json_decode(file_get_contents($tokenPath), true);
    if (empty($token['access_token'])) {
        $checks[] = ['fail', 'google_token.json ada tapi access_token kosong — ulangi OAuth'];
    } else {
        $hasRefresh = !empty($token['refresh_token']);
        $expired    = isset($token['created'], $token['expires_in'])
            ? (time() > ($token['created'] + $token['expires_in']))
            : false;

        $checks[] = ['ok', 'google_token.json ditemukan ✓'];
        $checks[] = $hasRefresh
            ? ['ok', 'refresh_token tersedia (token bisa auto-refresh) ✓']
            : ['warn', 'refresh_token TIDAK ADA — jika token expired, harus OAuth ulang'];

        if ($expired) {
            $checks[] = ['warn', 'access_token sudah EXPIRED (akan dicoba refresh otomatis)'];
        } else {
            $expIn = isset($token['created'], $token['expires_in'])
                ? max(0, (int)(($token['created'] + $token['expires_in'] - time()) / 60))
                : '?';
            $checks[] = ['ok', "access_token masih valid (expires in ±{$expIn} menit) ✓"];
        }
        $canSend = true;
    }
}

// ── Check 4: Google Client init ───────────────────────────
if ($canSend && file_exists($vendorPath)) {
    try {
        require_once __DIR__ . '/../config/google_config.php';
        require_once __DIR__ . '/../services/GoogleClientService.php';
        $gc = new GoogleClientService();
        $checks[] = ['ok', 'GoogleClientService berhasil diinisialisasi ✓'];
    } catch (\Exception $e) {
        $checks[] = ['fail', 'GoogleClientService error: ' . $e->getMessage()];
        $canSend  = false;
    }
}

// ── Check 5: Gmail API test (send test email) ─────────────
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email']) && $canSend) {
    $to = trim($_POST['to_email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $testResult = ['fail', 'Alamat email tidak valid.'];
    } else {
        try {
            require_once __DIR__ . '/../services/GmailService.php';
            $gmail = new GmailService();
            $subject = 'Test Email AMS BSPJI — ' . date('d/m/Y H:i');
            $body = '
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#f8fafc">
  <div style="background:#0f172a;border-radius:12px;padding:24px;text-align:center;margin-bottom:20px">
    <h1 style="color:#fff;margin:0;font-size:20px">Test Email Berhasil!</h1>
  </div>
  <div style="background:#fff;border-radius:8px;padding:20px;border:1px solid #e2e8f0">
    <p style="margin:0 0 12px;color:#334155">Email ini dikirim dari <strong>AMS BSPJI</strong> sebagai konfirmasi bahwa konfigurasi Gmail API sudah benar.</p>
    <p style="margin:0;color:#64748b;font-size:13px">Waktu: '.date('d/m/Y H:i:s').'</p>
  </div>
</div>';
            $gmail->send($to, $subject, $body);
            $testResult = ['ok', "Email berhasil dikirim ke {$to} ✓ Cek inbox (dan folder Spam)."];
        } catch (\Exception $e) {
            $testResult = ['fail', 'Gagal kirim email: ' . $e->getMessage()];
        }
    }
}

$pageTitle  = 'Diagnosa Email';
$activePage = 'dashboard';
include '_header.php';
?>

<div class="page-header">
  <div>
    <h1>Diagnosa Email & Google API</h1>
    <div class="breadcrumb"><a href="index.php">Dashboard</a> / Diagnosa Email</div>
  </div>
</div>

<!-- Status Checklist -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2>Status Komponen</h2></div>
  <div class="card-body" style="padding:0">
    <?php foreach ($checks as [$type, $msg]): ?>
    <?php
      $bg  = ['ok'=>'#F0FDF4','fail'=>'#FEF2F2','warn'=>'#FFFBEB','info'=>'#EFF6FF'][$type] ?? '#fff';
      $clr = ['ok'=>'#15803D','fail'=>'#DC2626','warn'=>'#D97706','info'=>'#2563EB'][$type] ?? '#374151';
      $icon = ['ok'=>'✓','fail'=>'✗','warn'=>'!','info'=>'i'][$type] ?? '•';
    ?>
    <div style="padding:12px 20px;border-bottom:1px solid #F1F5F9;background:<?= $bg ?>;display:flex;gap:10px;align-items:flex-start">
      <span style="font-size:16px;flex-shrink:0"><?= $icon ?></span>
      <span style="color:<?= $clr ?>;font-size:13.5px"><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- OAuth Button -->
<?php if (!$canSend): ?>
<div class="card" style="margin-bottom:20px;border:2px solid #FCA5A5">
  <div class="card-header" style="background:#FEF2F2"><h2 style="color:#DC2626">🔑 Token Belum Ada — Jalankan OAuth</h2></div>
  <div class="card-body">
    <p style="margin-bottom:16px;font-size:14px;color:#374151">
      Kamu perlu menjalankan proses OAuth Google <strong>satu kali</strong> untuk mendapatkan token. Ini menghubungkan sistem ke akun Google kamu agar bisa kirim email dan buat Calendar event.
    </p>
    <ol style="font-size:13.5px;color:#374151;margin-bottom:20px;padding-left:18px;line-height:2">
      <li>Pastikan akun Google kamu sudah ditambahkan sebagai <strong>Test User</strong> di Google Cloud Console → OAuth Consent Screen</li>
      <li>Klik tombol di bawah → login dengan akun Google</li>
      <li>Izinkan akses Gmail dan Calendar → redirect kembali ke sini</li>
      <li>Token tersimpan otomatis di <code>config/google_token.json</code></li>
    </ol>
    <a href="<?= BASE_URL ?>/auth/google_auth.php" class="btn btn-success" style="font-size:15px;padding:12px 28px">
      🔑 Jalankan OAuth Google
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Test Send Email -->
<?php if ($canSend): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2>📧 Test Kirim Email</h2></div>
  <div class="card-body">
    <?php if ($testResult): ?>
    <div class="alert alert-<?= $testResult[0]==='ok'?'success':'error' ?>" style="margin-bottom:16px">
      <?= $testResult[0]==='ok'?'✓':'✗' ?> <?= htmlspecialchars($testResult[1]) ?>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Tujuan Test</label>
        <input type="email" name="to_email" class="form-control" placeholder="contoh@gmail.com"
               value="<?= htmlspecialchars($_POST['to_email'] ?? '') ?>" required style="max-width:380px">
        <div class="form-hint">Masukkan email yang ingin menerima test email. Bisa email kamu sendiri.</div>
      </div>
      <button type="submit" name="test_email" value="1" class="btn btn-primary">
        📤 Kirim Test Email
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Cara Reset Token -->
<div class="card">
  <div class="card-header"><h2>🔄 Troubleshooting</h2></div>
  <div class="card-body">
    <table style="font-size:13px;width:100%;border-collapse:collapse">
      <tr style="background:#F8FAFC">
        <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;width:35%">Masalah</th>
        <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0">Solusi</th>
      </tr>
      <tr><td style="padding:10px;border-bottom:1px solid #F1F5F9">Token tidak ada</td><td style="padding:10px;border-bottom:1px solid #F1F5F9">Klik "Jalankan OAuth Google" di atas</td></tr>
      <tr><td style="padding:10px;border-bottom:1px solid #F1F5F9">redirect_uri_mismatch</td><td style="padding:10px;border-bottom:1px solid #F1F5F9">Tambahkan <code>http://localhost:8080/auth/google_auth.php</code> di Google Cloud Console → Credentials → Edit</td></tr>
      <tr><td style="padding:10px;border-bottom:1px solid #F1F5F9">access_denied</td><td style="padding:10px;border-bottom:1px solid #F1F5F9">Tambahkan email kamu sebagai Test User di OAuth Consent Screen</td></tr>
      <tr><td style="padding:10px;border-bottom:1px solid #F1F5F9">Token expired, tidak bisa refresh</td><td style="padding:10px;border-bottom:1px solid #F1F5F9">Hapus <code>config/google_token.json</code> via Docker, lalu OAuth ulang</td></tr>
      <tr><td style="padding:10px;border-bottom:1px solid #F1F5F9">Email tidak masuk (bukan error)</td><td style="padding:10px;border-bottom:1px solid #F1F5F9">Cek folder <strong>Spam</strong>. Pastikan email pengirim sudah diverifikasi di Google.</td></tr>
      <tr><td style="padding:10px">vendor/ tidak ada</td><td style="padding:10px">Jalankan: <code>docker compose down -v && docker compose up -d --build</code></td></tr>
    </table>

    <div style="margin-top:16px;padding:12px 16px;background:#F8FAFC;border-radius:8px;font-size:13px;color:#64748B">
      <strong>Reset token via Docker:</strong><br>
      <code>docker exec ams_app rm -f /var/www/html/config/google_token.json</code><br>
      Lalu akses kembali: <a href="<?= BASE_URL ?>/auth/google_auth.php"><?= BASE_URL ?>/auth/google_auth.php</a>
    </div>
  </div>
</div>

<!-- ── Test Notifikasi Penugasan ─────────────────────────── -->
<?php
$kunjungansTest = $db->query("
    SELECT k.id, pr.nama AS perusahaan, ja.nama AS jenis, k.tanggal_mulai, k.tanggal_selesai
    FROM kunjungan k
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    ORDER BY k.dibuat_pada DESC LIMIT 20
")->fetchAll();

$notifResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_notif_penugasan'])) {
    $kunjunganIdTest = trim($_POST['kunjungan_id_test'] ?? '');
    if (!$kunjunganIdTest) {
        $notifResult = ['fail', 'Pilih kunjungan terlebih dahulu.'];
    } elseif (!$canSend) {
        $notifResult = ['fail', 'Google API belum siap. Selesaikan OAuth terlebih dahulu.'];
    } else {
        try {
            require_once '../services/NotificationService.php';
            $notif = new NotificationService($db);
            if ($notif->isReady()) {
                $notif->kirimPenugasanBaru($kunjunganIdTest);
                if (!empty($notif->errors)) {
                    $notifResult = ['warn', 'Sebagian gagal: ' . implode('; ', $notif->errors)];
                } else {
                    $notifResult = ['ok', 'Notifikasi penugasan berhasil dikirim ke semua anggota tim!'];
                }
            } else {
                $notifResult = ['fail', 'NotificationService tidak siap: ' . implode('; ', $notif->errors)];
            }
        } catch (\Exception $e) {
            $notifResult = ['fail', 'Error: ' . $e->getMessage()];
        }
    }
}
?>

<div class="card" style="margin-top:20px;border:2px solid #BFDBFE">
  <div class="card-header" style="background:#EFF6FF">
    <h2 style="color:#1D4ED8">Test Trigger Notifikasi Penugasan</h2>
  </div>
  <div class="card-body">
    <p style="font-size:13.5px;color:#374151;margin-bottom:16px">
      Pilih kunjungan dan klik tombol untuk men-trigger pengiriman email notifikasi penugasan
      ke semua anggota tim kunjungan tersebut. Berguna untuk memverifikasi bahwa
      <strong>NotificationService</strong> berfungsi dengan benar.
    </p>

    <?php if ($notifResult): ?>
    <?php
      $nBg  = ['ok'=>'#F0FDF4','fail'=>'#FEF2F2','warn'=>'#FFFBEB'][$notifResult[0]] ?? '#fff';
      $nClr = ['ok'=>'#15803D','fail'=>'#DC2626','warn'=>'#D97706'][$notifResult[0]] ?? '#374151';
    ?>
    <div style="padding:12px 16px;border-radius:8px;background:<?= $nBg ?>;color:<?= $nClr ?>;font-size:13.5px;margin-bottom:16px">
      <?= htmlspecialchars($notifResult[1]) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($kunjungansTest)): ?>
    <div style="padding:16px;background:#FEF9C3;border-radius:8px;font-size:13px;color:#92400E">
      Belum ada data kunjungan. Jalankan <a href="<?= BASE_URL ?>/setup.php"><code>setup.php</code></a> untuk membuat data demo terlebih dahulu.
    </div>
    <?php else: ?>
    <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:300px">
        <label class="form-label">Pilih Kunjungan</label>
        <select name="kunjungan_id_test" class="form-control" required>
          <option value="">-- Pilih kunjungan --</option>
          <?php foreach ($kunjungansTest as $kv): ?>
          <option value="<?= e($kv['id']) ?>" <?= (($_POST['kunjungan_id_test'] ?? '') === $kv['id']) ? 'selected' : '' ?>>
            [<?= e($kv['id']) ?>] <?= e($kv['perusahaan']) ?> — <?= e($kv['jenis']) ?>
            (<?= fmtRentang($kv['tanggal_mulai'], $kv['tanggal_selesai']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Email akan dikirim ke semua pegawai anggota tim kunjungan ini.</div>
      </div>
      <div style="margin-bottom:0">
        <button type="submit" name="test_notif_penugasan" value="1"
                class="btn btn-primary" <?= !$canSend ? 'disabled title="Google API belum siap, selesaikan OAuth dulu"' : '' ?>>
          📨 Kirim Notifikasi Test
        </button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include '_footer.php'; ?>
