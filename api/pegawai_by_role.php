<?php
// api/pegawai_by_role.php
// GET: kandidat pegawai eligible untuk 1 role (digunakan di dropdown pilih manual)
// Params: role_id, tgl_mulai, tgl_selesai, perusahaan_id, jenis_audit_id, exclude_ids (JSON array)
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/algorithm.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$role_id       = trim($_GET['role_id']       ?? '');
$tgl_mulai     = trim($_GET['tgl_mulai']     ?? '');
$tgl_selesai   = trim($_GET['tgl_selesai']   ?? '');
$perusahaan_id = trim($_GET['perusahaan_id'] ?? '');
$jenis_id      = trim($_GET['jenis_id']      ?? '');

// exclude_ids: JSON array dari ID pegawai yang sudah dipilih di slot lain
$excludeRaw = trim($_GET['exclude_ids'] ?? '[]');
$excludeIds = json_decode($excludeRaw, true);
if (!is_array($excludeIds)) $excludeIds = [];
$excludeIds = array_filter(array_map('trim', $excludeIds));

if (!$role_id || !$tgl_mulai || !$tgl_selesai || !$perusahaan_id) {
    echo json_encode(['error' => 'Parameter tidak lengkap.']); exit;
}

$db = getDB();
$kandidat = getKandidatPerRole(
    $db,
    $role_id,
    $tgl_mulai,
    $tgl_selesai,
    $perusahaan_id,
    array_values($excludeIds),
    $jenis_id
);

echo json_encode(array_values($kandidat));
