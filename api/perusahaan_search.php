<?php
// api/perusahaan_search.php
// Live search perusahaan — mengembalikan JSON [{id, nama, alamat}]
// Min 2 karakter, maks 5 hasil, case-insensitive partial match (§7.3.1)
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) { echo json_encode([]); exit; }

$q  = trim($_GET['q'] ?? '');
$db = getDB();

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$s = $db->prepare("
    SELECT id, nama, COALESCE(alamat,'') AS alamat
    FROM perusahaan
    WHERE nama LIKE ?
    ORDER BY nama ASC
    LIMIT 5
");
$s->execute(["%{$q}%"]);
$rows = $s->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);
