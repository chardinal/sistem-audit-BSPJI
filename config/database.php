<?php
// ============================================================
// AMS — Database Configuration
// Sesuaikan DB_HOST, DB_USER, DB_PASS jika perlu.
// Database 'ams_db' akan dibuat OTOMATIS jika belum ada.
// ============================================================

// ── Konfigurasi Session (HARUS sebelum session_start) ────────
// Dipusatkan di sini agar semua halaman pakai setting yang sama.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime',  28800);   // 8 jam (detik)
    ini_set('session.cookie_lifetime', 0);        // cookie hilang saat browser ditutup (bukan per waktu)
    ini_set('session.cookie_path',     '/');      // berlaku untuk semua URL di domain ini
    ini_set('session.cookie_httponly', '1');      // tidak bisa diakses JavaScript (anti-XSS)
    ini_set('session.use_strict_mode', '1');      // tolak session ID yang tidak dikenal (anti-fixation)
    ini_set('session.cookie_samesite', 'Lax');    // kompatibel navigasi normal, blokir CSRF lintas-situs
    ini_set('session.use_only_cookies', '1');     // jangan pakai session ID di URL
    session_start();
}

// Baca dari environment variable (Docker/Vercel) — fallback ke nilai default (Laragon/XAMPP)
$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'ams_db';
$dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$dbPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: '';

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'AMS — Audit Management System');
define('APP_VERSION', '7.0');

// Base URL sistem (tanpa trailing slash)
// Docker: app berjalan di root domain, gunakan ''
// Laragon/XAMPP di subfolder: gunakan '/sistem-audit'
define('BASE_URL', '');

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// ── Global Exception Handler ──────────────────────────────────
// Mencegah stack trace PHP muncul langsung di browser (production-safe)
// Uncaught exception → tampilkan halaman error yang bersih
set_exception_handler(function (Throwable $e): void {
    // Hanya log detail error di server (tidak ke browser)
    error_log('[AMS Error] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }
    $isAdmin = !empty($_SESSION['admin_id']);
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Terjadi Kesalahan — AMS</title>
        <style>
            body { font-family: Inter, Arial, sans-serif; background: #F8FAFC;
                   display: flex; align-items: center; justify-content: center;
                   min-height: 100vh; margin: 0; }
            .err { background: #fff; border: 1.5px solid #FECACA; border-radius: 14px;
                   padding: 36px 40px; max-width: 500px; text-align: center; }
            .err h1 { font-size: 22px; color: #991B1B; margin: 0 0 10px; }
            .err p  { color: #4B5563; font-size: 14px; line-height: 1.6; }
            .err a  { display: inline-block; margin-top: 18px; padding: 10px 24px;
                      background: #2563EB; color: #fff; border-radius: 8px;
                      text-decoration: none; font-weight: 600; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="err">
            <h1>⚠️ Terjadi Kesalahan</h1>
            <p>Sistem mengalami masalah saat memproses permintaan ini.<br>
               Tim teknis telah dicatat. Silakan coba beberapa saat lagi.</p>
            <?php if (defined('APP_VERSION') && APP_VERSION !== 'prod'): ?>
            <p style="font-size:12px;color:#9CA3AF;margin-top:14px">
                <code><?= htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) ?></code>
            </p>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($baseUrl . ($isAdmin ? '/admin/index.php' : '/pegawai/index.php')) ?>">
                Kembali ke Beranda
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
});


/**
 * Mengembalikan instance PDO (singleton).
 * Otomatis membuat database jika belum ada.
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        // ── Tahap 1: Koneksi tanpa nama DB dulu ──────────────
        $dsnNoDB = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
        $tmp = new PDO($dsnNoDB, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Buat database jika belum ada
        $tmp->exec(
            "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
             CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $tmp = null; // tutup koneksi sementara

        // ── Tahap 2: Koneksi ke database yang sudah pasti ada ─
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        die('
        <div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:60px auto;
                    padding:28px;background:#FEF2F2;border:1.5px solid #FECACA;
                    border-radius:12px;color:#991B1B">
            <div style="font-size:22px;font-weight:700;margin-bottom:10px">Koneksi Database Gagal</div>
            <p style="margin:0 0 14px">' . htmlspecialchars($e->getMessage()) . '</p>
            <hr style="border:none;border-top:1px solid #FECACA;margin:14px 0">
            <p style="margin:0;font-size:13px;color:#B91C1C">
                Pastikan MySQL sedang berjalan di Laragon,<br>
                lalu cek konfigurasi di <code>config/database.php</code>:<br><br>
                DB_HOST = <strong>' . DB_HOST . '</strong><br>
                DB_USER = <strong>' . DB_USER . '</strong><br>
                DB_PASS = <strong>(kosong jika tidak ada password)</strong>
            </p>
        </div>');
    }

    return $pdo;
}
