<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/notifikasi.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

// Get the pre-selected kunjungan_id from query parameter
$selectedKunjunganId = $_GET['kunjungan_id'] ?? '';

$errors = [];
if (isPost()) {
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
                redirectWith('index.php?tab=notifikasi', 'success', 'Pernyataan tidak bersedia berhasil dikirim. Jadwal sedang ditinjau oleh Admin.');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Terjadi kesalahan sistem saat menyimpan penolakan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil daftar jadwal aktif pegawai untuk dropdown
$stmt = $db->prepare("
    SELECT k.id, pr.nama AS perusahaan, ja.nama AS jenis, k.tanggal_mulai, k.tanggal_selesai
    FROM penugasan_tim pt
    JOIN kunjungan k ON k.id = pt.kunjungan_id
    JOIN perusahaan pr ON pr.id = k.perusahaan_id
    JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
    WHERE pt.pegawai_id = ? AND k.status = 'Aktif'
    ORDER BY k.tanggal_mulai ASC
");
$stmt->execute([$pgw['id']]);
$activeSchedules = $stmt->fetchAll();

$pageTitle = 'Pernyataan Tidak Bersedia';
$activePage = 'notifikasi'; // Highlight notifikasi tab
include '_header.php';
?>

<div style="max-width: 600px; margin: 20px auto;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
    <h2 style="font-size:20px;font-weight:700;color:#1A1F2E">Formulir Pernyataan Tidak Bersedia</h2>
    <a href="index.php?tab=notifikasi" class="btn btn-secondary btn-sm">&larr; Kembali</a>
  </div>

  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error" style="margin-bottom:14px"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="card" style="padding: 24px; border: 1.5px solid #FCA5A5; background: #FFF5F5; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1)">
    <p style="font-size: 14px; color: #7F1D1D; line-height: 1.6; margin-bottom: 20px;">
      Silakan gunakan formulir ini untuk menyatakan ketidaksediaan Anda dalam penugasan kunjungan audit aktif. Setelah dikirim, status kunjungan akan berubah menjadi <strong>Butuh Intervensi</strong> dan Administrator akan segera meninjau atau mengganti penugasan Anda.
    </p>

    <form method="POST">
      <div class="form-group" style="margin-bottom: 18px;">
        <label class="form-label" style="font-weight: 600; color: #7F1D1D; display:block; margin-bottom: 6px;">Pilih Jadwal Kunjungan Aktif</label>
        <select name="kunjungan_id" class="form-control" style="width: 100%; border-color: #FCA5A5; background: #fff;" required>
          <option value="">-- Pilih Jadwal Kunjungan --</option>
          <?php foreach ($activeSchedules as $sch): ?>
          <option value="<?= e($sch['id']) ?>" <?= ($selectedKunjunganId === $sch['id']) ? 'selected' : '' ?>>
            <?= e($sch['perusahaan']) ?> — <?= e($sch['jenis']) ?> (<?= fmtRentang($sch['tanggal_mulai'], $sch['tanggal_selesai']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label class="form-label" style="font-weight: 600; color: #7F1D1D; display:block; margin-bottom: 6px;">Alasan Tidak Bersedia</label>
        <textarea name="alasan" class="form-control" rows="5" placeholder="Tuliskan alasan profesional Anda tidak dapat menghadiri tugas ini..." style="width: 100%; border-color: #FCA5A5; background: #fff;" required><?= e($_POST['alasan'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 10px;">
        <button type="submit" class="btn btn-danger" style="flex: 1; padding: 10px 0; font-weight: 600;">Kirim Penolakan</button>
        <a href="index.php?tab=notifikasi" class="btn btn-secondary" style="flex: 1; text-align: center; padding: 10px 0;">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php include '_footer.php'; ?>
