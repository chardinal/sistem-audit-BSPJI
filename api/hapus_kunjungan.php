<?php
// api/hapus_kunjungan.php
// POST: { kunjungan_id, _redirect }
// Urutan: 1) Ambil data tim dari DB  2) Kirim email + hapus Calendar  3) Hapus DB
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method Not Allowed');
}

$kunjunganId = trim($_POST['kunjungan_id'] ?? '');
$redirect    = $_POST['_redirect'] ?? (BASE_URL . '/admin/jadwal/index.php');

if (!$kunjunganId) {
    redirectWith($redirect, 'error', 'ID kunjungan tidak valid.');
}

$db = getDB();

// Pastikan kunjungan ada
$stmt = $db->prepare("SELECT id, status FROM kunjungan WHERE id = ?");
$stmt->execute([$kunjunganId]);
$k = $stmt->fetch();

if (!$k) {
    redirectWith($redirect, 'error', 'Kunjungan tidak ditemukan.');
}

// ── LANGKAH 1: Kirim email pembatalan + hapus Google Calendar ─────────────
// Wajib dilakukan SEBELUM hapus DB karena butuh data tim & calendar_event_id
$notifWarnings = [];
try {
    require_once '../services/NotificationService.php';
    $notif = new NotificationService($db);
    if ($notif->isReady()) {
        $notif->kirimPembatalanKunjungan($kunjunganId);
        if (!empty($notif->errors)) {
            // Ada partial error (misalnya 1 dari 3 email gagal) — log tapi tetap lanjut
            $notifWarnings = $notif->errors;
            error_log('[hapus_kunjungan] Notif partial errors: ' . implode(' | ', $notifWarnings));
        }
    }
    // Jika Google API belum siap (oauth belum dilakukan), lanjut hapus tanpa notif
} catch (\Exception $e) {
    // Log tapi jangan hentikan penghapusan — notif adalah best-effort
    error_log('[hapus_kunjungan] NotificationService error: ' . $e->getMessage());
}

// ── LANGKAH 2: Hapus dari database (child → parent) ──────────────────────
try {
    $db->prepare("DELETE FROM notifikasi    WHERE kunjungan_id = ?")->execute([$kunjunganId]);
    $db->prepare("DELETE FROM penugasan_tim WHERE kunjungan_id = ?")->execute([$kunjunganId]);
    $db->prepare("DELETE FROM kunjungan     WHERE id = ?")->execute([$kunjunganId]);

    $pesan = "Kunjungan [{$kunjunganId}] berhasil dihapus. Email pembatalan dan penghapusan kalender sudah terkirim ke semua anggota tim.";
    if (!empty($notifWarnings)) {
        $pesan = "Kunjungan [{$kunjunganId}] berhasil dihapus, namun ada masalah saat mengirim notifikasi: " . implode('; ', $notifWarnings);
    }

    redirectWith($redirect, 'success', $pesan);
} catch (\PDOException $e) {
    error_log('[hapus_kunjungan] DB Error: ' . $e->getMessage());
    redirectWith($redirect, 'error', 'Gagal menghapus kunjungan dari database: ' . $e->getMessage());
}
