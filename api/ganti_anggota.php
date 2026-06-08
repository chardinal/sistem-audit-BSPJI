<?php
// api/ganti_anggota.php
// POST JSON: { action, penugasan_id, kunjungan_id, pegawai_baru_id? }
// action: 'otomatis' → cari kandidat terbaik sesuai rules
//         'manual'   → simpan pegawai_baru_id yang sudah dipilih admin
// Returns: { success, message, pegawai? }

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/algorithm.php';
require_once '../includes/notifikasi.php';
require_once '../services/NotificationService.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Body JSON tidak valid.']); exit;
}

$action       = trim($body['action']         ?? '');
$penugasanId  = trim($body['penugasan_id']   ?? '');
$kunjunganId  = trim($body['kunjungan_id']   ?? '');
$pegawaiBaru  = trim($body['pegawai_baru_id'] ?? '');

if (!$action || !$penugasanId || !$kunjunganId) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']); exit;
}

$db = getDB();

// ── Ambil detail penugasan lama ──────────────────────────────────────────
$sPen = $db->prepare("
    SELECT pt.id, pt.kunjungan_id, pt.pegawai_id, pt.role_id,
           k.tanggal_mulai, k.tanggal_selesai, k.perusahaan_id, k.jenis_audit_id,
           pg.nama AS pegawai_lama_nama
    FROM penugasan_tim pt
    JOIN kunjungan k ON k.id = pt.kunjungan_id
    JOIN pegawai pg  ON pg.id = pt.pegawai_id
    WHERE pt.id = ? AND pt.kunjungan_id = ?
");
$sPen->execute([$penugasanId, $kunjunganId]);
$pen = $sPen->fetch();

if (!$pen) {
    echo json_encode(['success' => false, 'message' => 'Data penugasan tidak ditemukan.']); exit;
}

$oldPegawaiId = $pen['pegawai_id'];

// ── Kumpulkan ID anggota lain (untuk exclude) ────────────────────────────
$sOther = $db->prepare("
    SELECT pegawai_id FROM penugasan_tim
    WHERE kunjungan_id = ? AND id != ?
");
$sOther->execute([$kunjunganId, $penugasanId]);
$otherIds = array_column($sOther->fetchAll(), 'pegawai_id');

// ════════════════════════════════════════════════════════════
// ACTION: OTOMATIS — pilih kandidat beban terkecil sesuai rules
// ════════════════════════════════════════════════════════════
if ($action === 'otomatis') {
    $kandidat = getKandidatPerRole(
        $db,
        $pen['role_id'],
        $pen['tanggal_mulai'],
        $pen['tanggal_selesai'],
        $pen['perusahaan_id'],
        $otherIds,
        $pen['jenis_audit_id']
    );

    if (empty($kandidat)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada kandidat pengganti yang memenuhi aturan.']); exit;
    }

    $pengganti = $kandidat[0];

    // Ganti penugasan
    $db->prepare("UPDATE penugasan_tim SET pegawai_id=?, ditugaskan_pada=NOW() WHERE id=?")
       ->execute([$pengganti['id'], $penugasanId]);

    // Notifikasi
    _kirimNotifGanti($db, $kunjunganId, $oldPegawaiId, $pengganti['id']);

    echo json_encode([
        'success'  => true,
        'message'  => 'Berhasil diganti dengan '.$pengganti['nama'],
        'pegawai'  => [
            'id'         => $pengganti['id'],
            'nama'       => $pengganti['nama'],
            'beban_bulan'=> $pengganti['beban_bulan'],
        ],
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
// ACTION: MANUAL — admin memilih pegawai spesifik
// ════════════════════════════════════════════════════════════
if ($action === 'manual') {
    if (!$pegawaiBaru) {
        echo json_encode(['success' => false, 'message' => 'ID pegawai baru tidak diberikan.']); exit;
    }
    if (in_array($pegawaiBaru, $otherIds)) {
        echo json_encode(['success' => false, 'message' => 'Pegawai sudah ada di dalam tim.']); exit;
    }

    // Ambil info pegawai baru
    $sPeg = $db->prepare("SELECT id, nama FROM pegawai WHERE id=?");
    $sPeg->execute([$pegawaiBaru]);
    $pegObj = $sPeg->fetch();
    if (!$pegObj) {
        echo json_encode(['success' => false, 'message' => 'Pegawai tidak ditemukan atau tidak aktif.']); exit;
    }

    // Ganti penugasan
    $db->prepare("UPDATE penugasan_tim SET pegawai_id=?, ditugaskan_pada=NOW() WHERE id=?")
       ->execute([$pegawaiBaru, $penugasanId]);

    // Notifikasi
    _kirimNotifGanti($db, $kunjunganId, $oldPegawaiId, $pegawaiBaru);

    echo json_encode([
        'success' => true,
        'message' => 'Berhasil diganti dengan '.$pegObj['nama'],
        'pegawai' => ['id' => $pegObj['id'], 'nama' => $pegObj['nama']],
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
// ACTION: KANDIDAT — ambil daftar kandidat untuk dropdown
// ════════════════════════════════════════════════════════════
if ($action === 'kandidat') {
    $kandidat = getKandidatPerRole(
        $db,
        $pen['role_id'],
        $pen['tanggal_mulai'],
        $pen['tanggal_selesai'],
        $pen['perusahaan_id'],
        $otherIds,
        $pen['jenis_audit_id']
    );
    echo json_encode(['success' => true, 'kandidat' => array_values($kandidat)]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenal.']);

// ── Helper: kirim notifikasi ganti anggota ───────────────────────────────
function _kirimNotifGanti(PDO $db, string $kunjunganId, string $oldId, string $newId): void
{
    try {
        $notif = new NotificationService($db);
        if ($notif->isReady()) {
            $notif->kirimGantiAnggota($kunjunganId, $oldId, $newId);
        }
    } catch (Throwable $e) {
        error_log('[ganti_anggota] Notif error: '.$e->getMessage());
    }
}
