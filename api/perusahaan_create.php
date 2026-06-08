<?php
// api/perusahaan_create.php
// Simpan perusahaan baru dari form inline di halaman Tambah Jadwal (§7.3.1)
// POST: {nama, alamat} → return JSON {success, id, nama, alamat}
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nama  = trim($input['nama']   ?? '');
$alamat = trim($input['alamat'] ?? '');

if (!$nama) {
    echo json_encode(['success' => false, 'message' => 'Nama perusahaan wajib diisi.']);
    exit;
}

$db = getDB();

// Cek apakah sudah ada (case-insensitive)
$cek = $db->prepare("SELECT id, nama, COALESCE(alamat,'') AS alamat FROM perusahaan WHERE LOWER(nama) = LOWER(?) LIMIT 1");
$cek->execute([$nama]);
$existing = $cek->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    // Sudah ada — kembalikan data yang ada
    echo json_encode(['success' => true, 'id' => $existing['id'], 'nama' => $existing['nama'], 'alamat' => $existing['alamat']]);
    exit;
}

$id = generateId($db, 'perusahaan', 'C');
$db->prepare("INSERT INTO perusahaan (id, nama, alamat) VALUES (?, ?, ?)")
   ->execute([$id, $nama, $alamat ?: null]);

echo json_encode(['success' => true, 'id' => $id, 'nama' => $nama, 'alamat' => $alamat]);
