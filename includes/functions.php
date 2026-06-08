<?php
// ============================================================
// includes/functions.php  —  Helper & utilitas umum
// ============================================================

/**
 * Generate ID berurutan dengan format: 1 Huruf + 5 Angka
 * Contoh: K00001, P00023, N00100
 *
 * Prefix per tabel:
 *   A = admins        C = perusahaan (Company)
 *   F = formasi_audit J = jenis_audit
 *   K = kunjungan     N = notifikasi
 *   P = pegawai       R = role
 *   T = penugasan_tim
 */
function generateId(PDO $db, string $table, string $prefix): string
{
    $prefix = strtoupper(substr($prefix, 0, 1));
    $stmt = $db->prepare(
        "SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) FROM `{$table}` WHERE id LIKE ?"
    );
    $stmt->execute([$prefix . '%']);
    $max  = (int) $stmt->fetchColumn();
    $next = $max + 1;
    if ($next > 99999) {
        throw new \OverflowException("ID untuk tabel '{$table}' sudah mencapai batas maksimum (99999).");
    }
    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ── Format Tanggal ─────────────────────────────────────────

/** Format tanggal ke bahasa Indonesia. Contoh: 27 Mei 2026 */
function fmtTanggal(?string $date): string
{
    if (!$date) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    [$y, $m, $d] = explode('-', substr($date, 0, 10));
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}

/** Format rentang tanggal. Contoh: 1–5 Jun 2026 */
function fmtRentang(string $mulai, string $selesai): string
{
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun',
              'Jul','Agt','Sep','Okt','Nov','Des'];
    [$ym, $mm, $dm] = explode('-', $mulai);
    [$ys, $ms, $ds] = explode('-', $selesai);
    if ($ym === $ys && $mm === $ms) {
        return "{$dm}–{$ds} {$bulan[(int)$mm]} {$ym}";
    }
    return "{$dm} {$bulan[(int)$mm]} – {$ds} {$bulan[(int)$ms]} {$ys}";
}

/** Hitung selisih hari antara dua tanggal (inklusif) */
function selisihHari(string $mulai, string $selesai): int
{
    return (int) round((strtotime($selesai) - strtotime($mulai)) / 86400) + 1;
}

// ── Badge & Status ─────────────────────────────────────────

/** Badge HTML status kunjungan */
function badgeStatus(string $status): string
{
    $map = [
        'Aktif'            => 'badge-green',
        'Selesai'          => 'badge-gray',
        'Butuh Intervensi' => 'badge-red',
    ];
    $cls = $map[$status] ?? 'badge-blue';
    return "<span class=\"badge {$cls}\">{$status}</span>";
}

// ── Flash Messages ─────────────────────────────────────────

/** Simpan flash message ke session */
function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** Ambil dan hapus flash message dari session */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Render flash message HTML */
function renderFlash(): string
{
    $f = getFlash();
    if (!$f) return '';
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return "<div class=\"alert {$cls}\">" . htmlspecialchars($f['msg']) . '</div>';
}

// ── Utility ────────────────────────────────────────────────

/** Escape HTML — null-safe */
function e(?string $str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Redirect dengan flash message */
function redirectWith(string $url, string $type, string $msg): never
{
    setFlash($type, $msg);
    header("Location: {$url}");
    exit;
}

/** Cek apakah request adalah POST */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Ambil nilai POST dengan default kosong */
function post(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

// ── Notifikasi Counter ─────────────────────────────────────

/** Jumlah notifikasi belum dibaca untuk admin */
function countNotifAdmin(PDO $db, string $adminId): int
{
    $s = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE tipe_penerima='admin' AND penerima_id=? AND sudah_dibaca=0");
    $s->execute([$adminId]);
    return (int) $s->fetchColumn();
}

/** Jumlah notifikasi belum dibaca untuk pegawai */
function countNotifPegawai(PDO $db, string $pegawaiId): int
{
    $s = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE tipe_penerima='pegawai' AND penerima_id=? AND sudah_dibaca=0");
    $s->execute([$pegawaiId]);
    return (int) $s->fetchColumn();
}

/** Jumlah jadwal aktif saat ini untuk pegawai */
function countJadwalAktif(PDO $db, string $pegawaiId): int
{
    $s = $db->prepare("
        SELECT COUNT(*) FROM penugasan_tim pt
        JOIN kunjungan k ON k.id = pt.kunjungan_id
        WHERE pt.pegawai_id = ? AND k.status = 'Aktif'
    ");
    $s->execute([$pegawaiId]);
    return (int) $s->fetchColumn();
}
