<?php
// api/preview_tim.php — AJAX endpoint untuk preview tim otomatis (GET)
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/algorithm.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$jenis_id      = trim($_GET['jenis_id']      ?? '');
$tgl_mulai     = trim($_GET['tgl_mulai']     ?? '');
$tgl_selesai   = trim($_GET['tgl_selesai']   ?? '');
$perusahaan_id = trim($_GET['perusahaan_id'] ?? '');

if (!$jenis_id || !$tgl_mulai || !$tgl_selesai || !$perusahaan_id) {
    echo json_encode(['error' => 'Parameter tidak lengkap.']); exit;
}
if ($tgl_selesai < $tgl_mulai) {
    echo json_encode(['error' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.']); exit;
}

$db      = getDB();
$preview = buildPreviewTim($db, $jenis_id, $tgl_mulai, $tgl_selesai, $perusahaan_id);

if (empty($preview)) {
    echo json_encode(['error' => 'Tidak ada formasi untuk jenis audit ini. Cek konfigurasi Jenis Audit.']);
    exit;
}

echo json_encode(array_values($preview));
