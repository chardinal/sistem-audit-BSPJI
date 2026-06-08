<?php
// api/tandai_selesai.php — Admin: tandai kunjungan selesai
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/notifikasi.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();

$kunjunganId = trim($_POST['kunjungan_id'] ?? '');
$redirect    = trim($_POST['_redirect']    ?? '/admin/jadwal/index.php');

if (!$kunjunganId) {
    redirectWith($redirect, 'error', 'ID kunjungan tidak valid.');
}

$db = getDB();

$k = $db->prepare("SELECT status FROM kunjungan WHERE id=?");
$k->execute([$kunjunganId]);
$kRow = $k->fetch();

if (!$kRow) {
    redirectWith($redirect, 'error', 'Kunjungan tidak ditemukan.');
}
if ($kRow['status'] !== 'Aktif') {
    redirectWith($redirect, 'error', 'Hanya kunjungan berstatus Aktif yang dapat ditandai Selesai.');
}

$db->prepare("UPDATE kunjungan SET status='Selesai' WHERE id=?")->execute([$kunjunganId]);

// Notifikasi tim
kirimNotifikasiTim($db, $kunjunganId, 'Kunjungan audit telah ditandai SELESAI oleh Admin. Terima kasih atas partisipasi Anda.');

// [PLACEHOLDER] Google Calendar — hapus/update event
// updateGoogleCalendarEvent($kunjunganId, 'completed');

redirectWith($redirect, 'success', 'Kunjungan berhasil ditandai Selesai.');
