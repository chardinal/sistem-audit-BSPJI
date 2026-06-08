<?php
// ============================================================
// includes/notifikasi.php  —  In-app notification system
// ============================================================

/**
 * Kirim notifikasi in-app ke penerima (admin atau pegawai).
 *
 * @param string $tipe_penerima  'admin' | 'pegawai'
 * @param string $penerima_id    ID admin atau pegawai
 * @param string $pesan          Teks notifikasi
 * @param string|null $kunjungan_id  (opsional) konteks kunjungan
 */
function kirimNotifikasi(
    PDO     $db,
    string  $tipe_penerima,
    string  $penerima_id,
    string  $pesan,
    ?string $kunjungan_id = null
): void {
    require_once __DIR__ . '/functions.php';

    $db->prepare("
        INSERT INTO notifikasi (id, tipe_penerima, penerima_id, pesan, kunjungan_id)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([generateId($db, 'notifikasi', 'N'), $tipe_penerima, $penerima_id, $pesan, $kunjungan_id]);
}

/**
 * Kirim notifikasi ke semua admin.
 */
function kirimNotifikasiAllAdmin(PDO $db, string $pesan, ?string $kunjungan_id = null): void
{
    $admins = $db->query("SELECT id FROM admins")->fetchAll();
    foreach ($admins as $a) {
        kirimNotifikasi($db, 'admin', $a['id'], $pesan, $kunjungan_id);
    }
}

/**
 * Kirim notifikasi ke semua anggota tim kunjungan.
 */
function kirimNotifikasiTim(PDO $db, string $kunjungan_id, string $pesan): void
{
    $s = $db->prepare("SELECT DISTINCT pegawai_id FROM penugasan_tim WHERE kunjungan_id=?");
    $s->execute([$kunjungan_id]);
    foreach ($s->fetchAll() as $row) {
        kirimNotifikasi($db, 'pegawai', $row['pegawai_id'], $pesan, $kunjungan_id);
    }
}

/**
 * Tandai semua notifikasi pegawai sebagai sudah dibaca.
 */
function bacaSemuaNotifPegawai(PDO $db, string $pegawai_id): void
{
    $db->prepare("
        UPDATE notifikasi SET sudah_dibaca=1
        WHERE tipe_penerima='pegawai' AND penerima_id=?
    ")->execute([$pegawai_id]);
}

/**
 * Tandai semua notifikasi admin sebagai sudah dibaca.
 */
function bacaSemuaNotifAdmin(PDO $db, string $admin_id): void
{
    $db->prepare("
        UPDATE notifikasi SET sudah_dibaca=1
        WHERE tipe_penerima='admin' AND penerima_id=?
    ")->execute([$admin_id]);
}

/**
 * Ambil notifikasi terbaru untuk penerima (20 terakhir).
 */
function getNotifikasi(PDO $db, string $tipe, string $penerima_id, int $limit = 20): array
{
    $s = $db->prepare("
        SELECT n.id, n.tipe_penerima, n.penerima_id, n.pesan, n.kunjungan_id, n.sudah_dibaca,
               n.dibuat_pada,
               pr.nama AS perusahaan_nama,
               k.status AS kunjungan_status,
               (SELECT COUNT(*) FROM penugasan_tim WHERE kunjungan_id = n.kunjungan_id AND pegawai_id = n.penerima_id) AS is_assigned
        FROM notifikasi n
        LEFT JOIN kunjungan k  ON k.id  = n.kunjungan_id
        LEFT JOIN perusahaan pr ON pr.id = k.perusahaan_id
        WHERE n.tipe_penerima = ? AND n.penerima_id = ?
        ORDER BY n.dibuat_pada DESC
        LIMIT ?
    ");
    $s->execute([$tipe, $penerima_id, $limit]);
    return $s->fetchAll();
}
