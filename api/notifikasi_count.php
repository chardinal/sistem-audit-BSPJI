<?php
// api/notifikasi_count.php — Polling badge count
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$portal = $_GET['portal'] ?? '';

if ($portal === 'admin' && !empty($_SESSION['admin_id'])) {
    $count = countNotifAdmin(getDB(), $_SESSION['admin_id']);
    echo json_encode(['count' => $count]);
} elseif ($portal === 'pegawai' && !empty($_SESSION['pegawai_id'])) {
    $count = countNotifPegawai(getDB(), $_SESSION['pegawai_id']);
    echo json_encode(['count' => $count]);
} else {
    echo json_encode(['count' => 0]);
}
