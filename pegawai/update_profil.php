<?php
// pegawai/update_profil.php — Handler update email & foto profil
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requirePegawai();
$db  = getDB();
$pgw = currentPegawai();

if (!isPost()) {
    header('Location: profil.php');
    exit;
}

$errors = [];

// ── 1. Ambil & validasi email ────────────────────────────────
$email = trim($_POST['email'] ?? '');
if ($email === '') {
    $errors[] = 'Email tidak boleh kosong.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format email tidak valid.';
} else {
    // Cek duplikat email (kecuali milik pegawai ini sendiri)
    $chk = $db->prepare("SELECT id FROM pegawai WHERE email = ? AND id != ?");
    $chk->execute([$email, $pgw['id']]);
    if ($chk->fetch()) {
        $errors[] = 'Email sudah digunakan oleh pegawai lain.';
    }
}

// ── 2. Proses foto profil (opsional) ─────────────────────────
$fotoPath = null; // null = tidak berubah
$uploadDir = ROOT_PATH . '/assets/uploads/profil/';

if (!empty($_FILES['foto']['name'])) {
    $file     = $_FILES['foto'];
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize  = 2 * 1024 * 1024; // 2 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Gagal mengunggah foto. Coba lagi.';
    } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) {
        $errors[] = 'Format foto tidak didukung. Gunakan JPG, PNG, WebP, atau GIF.';
    } elseif ($file['size'] > $maxSize) {
        $errors[] = 'Ukuran foto maksimal 2 MB.';
    } else {
        // Buat direktori jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'profil_' . $pgw['id'] . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $fotoPath = 'assets/uploads/profil/' . $filename;

            // Hapus foto lama jika ada
            $old = $db->prepare("SELECT foto_profil FROM pegawai WHERE id = ?");
            $old->execute([$pgw['id']]);
            $oldFoto = $old->fetchColumn();
            if ($oldFoto && file_exists(ROOT_PATH . '/' . $oldFoto)) {
                @unlink(ROOT_PATH . '/' . $oldFoto);
            }
        } else {
            $errors[] = 'Gagal menyimpan foto ke server.';
        }
    }
}

// ── 3. Jika ada error, kembali dengan pesan ─────────────────
if ($errors) {
    redirectWith(BASE_URL . '/pegawai/profil.php', 'error', implode(' ', $errors));
}

// ── 4. Update database ───────────────────────────────────────
if ($fotoPath !== null) {
    $stmt = $db->prepare("UPDATE pegawai SET email = ?, foto_profil = ? WHERE id = ?");
    $stmt->execute([$email, $fotoPath, $pgw['id']]);
} else {
    $stmt = $db->prepare("UPDATE pegawai SET email = ? WHERE id = ?");
    $stmt->execute([$email, $pgw['id']]);
}

// ── 5. Refresh data session agar header langsung terupdate ───
$fresh = $db->prepare("SELECT id, nama, email FROM pegawai WHERE id = ?");
$fresh->execute([$pgw['id']]);
$freshData = $fresh->fetch();
if ($freshData) {
    $_SESSION['pegawai_id']   = $freshData['id'];
    $_SESSION['pegawai_nama'] = $freshData['nama'];
}

redirectWith(BASE_URL . '/pegawai/profil.php', 'success', 'Profil berhasil diperbarui.');

