<?php
// ============================================================
// includes/auth.php  —  Session & autentikasi
// ============================================================

// Session sudah distart di config/database.php (selalu di-include pertama kali)
// Tidak perlu session_start() di sini lagi.

// ── Admin ─────────────────────────────────────────────────

/** Paksa halaman hanya untuk Admin. Redirect ke login jika tidak ada sesi atau sesi tidak valid. */
function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . adminLoginUrl());
        exit;
    }

    // Validasi admin_id masih ada di DB (mencegah error setelah database di-reset)
    try {
        $dbVal = getDB();
        $cek = $dbVal->prepare('SELECT id FROM admins WHERE id = ? LIMIT 1');
        $cek->execute([$_SESSION['admin_id']]);
        if (!$cek->fetchColumn()) {
            // Session mengandung ID yang tidak ada di DB → hapus sesi dan redirect login
            session_unset();
            session_destroy();
            header('Location: ' . adminLoginUrl() . '?reason=session_invalid');
            exit;
        }
    } catch (\Throwable $e) {
        // Jika koneksi DB gagal di sini, biarkan lolos dan error akan ditangani oleh getDB() di halaman utama
    }
}

/** Login admin — set sesi, return true/false */
function loginAdmin(PDO $db, string $email, string $password): bool
{
    $stmt = $db->prepare('SELECT id, nama, password_hash FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_nama'] = $admin['nama'];
        return true;
    }
    return false;
}

/** Logout admin */
function logoutAdmin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_nama']);
    session_destroy();
    header('Location: ' . adminLoginUrl());
    exit;
}

// ── Pegawai ───────────────────────────────────────────────

/** Paksa halaman hanya untuk Pegawai. Redirect ke login jika tidak ada sesi. */
function requirePegawai(): void
{
    if (empty($_SESSION['pegawai_id'])) {
        header('Location: ' . pegawaiLoginUrl());
        exit;
    }
}

/** Login pegawai — return true/false */
function loginPegawai(PDO $db, string $email, string $password): bool
{
    $stmt = $db->prepare('SELECT id, nama, password_hash FROM pegawai WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $pgw = $stmt->fetch();
    if ($pgw && password_verify($password, $pgw['password_hash'])) {
        $_SESSION['pegawai_id']   = $pgw['id'];
        $_SESSION['pegawai_nama'] = $pgw['nama'];
        return true;
    }
    return false;
}

/** Logout pegawai */
function logoutPegawai(): void
{
    unset($_SESSION['pegawai_id'], $_SESSION['pegawai_nama']);
    session_destroy();
    header('Location: ' . pegawaiLoginUrl());
    exit;
}

// ── URL helpers ───────────────────────────────────────────

function adminLoginUrl(): string
{
    return BASE_URL . '/admin/login.php';
}

function pegawaiLoginUrl(): string
{
    return BASE_URL . '/pegawai/login.php';
}

/** Ambil info admin yang sedang login */
function currentAdmin(): array
{
    $id = $_SESSION['admin_id'] ?? '';

    // Jika session sudah tidak valid, paksa logout dan redirect login
    if (empty($id)) {
        session_destroy();
        header('Location: ' . adminLoginUrl());
        exit;
    }

    return [
        'id'   => $id,
        'nama' => $_SESSION['admin_nama'] ?? 'Admin',
    ];
}

/** Ambil info pegawai yang sedang login */
function currentPegawai(): array
{
    return [
        'id'   => $_SESSION['pegawai_id']   ?? '',
        'nama' => $_SESSION['pegawai_nama'] ?? 'Pegawai',
    ];
}
