<?php
// ============================================================
// includes/algorithm.php  —  Algoritma pembentukan tim audit
// 5 aturan bisnis (README §4) diterapkan berurutan:
//   1. Validasi Rentang Tanggal (di form, bukan di sini)
//   2. Matriks Formasi Kompetensi
//   3. Anti-Overlap (Ketersediaan)
//   4. Rotasi / Cool-off Period
//   5. Pemerataan Beban Kerja (tiebreaker sort)
// ============================================================

/**
 * Ambil formasi (slot per role) untuk sebuah jenis audit.
 */
function getFormasiByJenis(PDO $db, string $jenis_id): array
{
    $s = $db->prepare("
        SELECT fa.role_id, fa.jumlah_slot, r.nama_role AS role_nama
        FROM formasi_audit fa
        JOIN role r ON r.id = fa.role_id
        WHERE fa.jenis_audit_id = ?
          AND r.aktif = 1
        ORDER BY r.nama_role
    ");
    $s->execute([$jenis_id]);
    return $s->fetchAll();
}

/**
 * Aturan 3 + 4 + 5:
 * Kembalikan daftar pegawai yang ELIGIBLE untuk sebuah slot role
 * pada tanggal dan perusahaan tertentu, diurutkan beban terkecil.
 *
 * Aturan 4 (REVISED — Rotasi Berbasis Siklus):
 *   Pegawai yang ikut di kunjungan TERAKHIR (most recent sebelum kunjungan baru)
 *   dengan jenis_audit_id DAN perusahaan_id yang sama → DIBLOKIR.
 *   Boleh kembali setelah minimal 1 siklus audit terlewati (ada kunjungan lain setelahnya).
 *   Scope rotasi: per kombinasi (perusahaan_id × jenis_audit_id).
 *
 * @param string   $jenis_audit_id  Jenis audit kunjungan baru (untuk scope rotasi)
 * @param string[] $exclude_ids     ID pegawai yang sudah dialokasikan di kunjungan ini
 */
function getKandidatPerRole(
    PDO    $db,
    string $role_id,
    string $tgl_mulai,
    string $tgl_selesai,
    string $perusahaan_id,
    array  $exclude_ids = [],
    string $jenis_audit_id = ''
): array {
    $bulan_mulai   = date('Y-m-01', strtotime($tgl_mulai));
    $bulan_selesai = date('Y-m-t',  strtotime($tgl_mulai));

    // Placeholder untuk exclude list
    $excludePlaceholders = count($exclude_ids)
        ? 'AND p.id NOT IN (' . implode(',', array_fill(0, count($exclude_ids), '?')) . ')'
        : '';

    // ── Aturan 4: Rotasi Berbasis Siklus ──────────────────────────────────
    // Sub-query: temukan kunjungan TERAKHIR (most recent, status Aktif/Selesai)
    // dengan (perusahaan_id, jenis_audit_id) yang sama dan tanggal_mulai < tgl_mulai_baru.
    // Pegawai yang berpartisipasi di kunjungan itu → diblokir untuk siklus ini.
    // Jika jenis_audit_id tidak diberikan, lewati rotasi (fallback aman).
    $rotasiClause = '';
    $rotasiParams = [];
    if (!empty($jenis_audit_id)) {
        $rotasiClause = "
          /* Aturan 4: Rotasi Siklus — blokir jika ikut di kunjungan terakhir jenis+perusahaan yg sama */
          AND p.id NOT IN (
              SELECT pt.pegawai_id
              FROM penugasan_tim pt
              JOIN kunjungan k ON k.id = pt.kunjungan_id
              WHERE k.perusahaan_id   = ?
                AND k.jenis_audit_id  = ?
                AND k.status IN ('Aktif','Selesai')
                AND k.tanggal_mulai   < ?
                AND k.id = (
                    /* Kunjungan terakhir dengan jenis+perusahaan yang sama sebelum kunjungan baru */
                    SELECT k2.id
                    FROM kunjungan k2
                    WHERE k2.perusahaan_id  = ?
                      AND k2.jenis_audit_id = ?
                      AND k2.status IN ('Aktif','Selesai')
                      AND k2.tanggal_mulai  < ?
                    ORDER BY k2.tanggal_mulai DESC
                    LIMIT 1
                )
          )
        ";
        // Params: 3 untuk outer WHERE + 3 untuk sub-query
        $rotasiParams = [
            $perusahaan_id, $jenis_audit_id, $tgl_mulai,   // outer
            $perusahaan_id, $jenis_audit_id, $tgl_mulai,   // inner sub-query
        ];
    }

    $sql = "
        SELECT
            p.id,
            p.nama,
            p.email,
            /* Aturan 5: beban kunjungan bulan ini */
            (
                SELECT COUNT(*)
                FROM penugasan_tim pt2
                JOIN kunjungan k2 ON k2.id = pt2.kunjungan_id
                WHERE pt2.pegawai_id = p.id
                  AND k2.status IN ('Aktif','Butuh Intervensi')
                  AND k2.tanggal_mulai BETWEEN ? AND ?
            ) AS beban_bulan
        FROM pegawai p
        JOIN pegawai_role pr ON pr.pegawai_id = p.id
        WHERE pr.role_id = ?
          {$excludePlaceholders}

          /* Aturan 3: Anti-Overlap STRICT — tidak ada jadwal Aktif yang bentrok */
          AND p.id NOT IN (
              SELECT pt.pegawai_id
              FROM penugasan_tim pt
              JOIN kunjungan k ON k.id = pt.kunjungan_id
              WHERE k.status = 'Aktif'
                AND k.tanggal_mulai  <= ?
                AND k.tanggal_selesai >= ?
          )

          {$rotasiClause}

        ORDER BY beban_bulan ASC, p.nama ASC
    ";

    $params = [
        $bulan_mulai,    // beban_bulan range start
        $bulan_selesai,  // beban_bulan range end
        $role_id,
    ];
    foreach ($exclude_ids as $eid) {
        $params[] = $eid;
    }
    $params[] = $tgl_selesai;    // anti-overlap: k.tgl_mulai <=
    $params[] = $tgl_mulai;      // anti-overlap: k.tgl_selesai >=

    // Tambahkan params rotasi siklus (jika ada)
    foreach ($rotasiParams as $rp) {
        $params[] = $rp;
    }

    $s = $db->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}


/**
 * Aturan 2: Bangun preview tim lengkap untuk sebuah kunjungan.
 * Slot yang paling sedikit kandidat diisi terlebih dahulu (prioritas kelangkaan).
 *
 * Return: array indexed by role_id dengan:
 *   - role_id, role_nama, jumlah_slot
 *   - terpilih:   array pegawai terpilih otomatis
 *   - alternatif: kandidat cadangan (untuk override manual Admin)
 */
function buildPreviewTim(
    PDO    $db,
    string $jenis_id,
    string $tgl_mulai,
    string $tgl_selesai,
    string $perusahaan_id
): array {
    $formasi = getFormasiByJenis($db, $jenis_id);
    if (empty($formasi)) return [];

    // Hitung kelangkaan awal untuk setiap slot
    $slotData = [];
    foreach ($formasi as $slot) {
        // Aturan 4: teruskan jenis_id untuk rotasi berbasis siklus
        $kandidat  = getKandidatPerRole($db, $slot['role_id'], $tgl_mulai, $tgl_selesai, $perusahaan_id, [], $jenis_id);
        $slotData[] = array_merge($slot, [
            'kandidat_awal' => $kandidat,
            'kelangkaan'    => count($kandidat) / max($slot['jumlah_slot'], 1),
        ]);
    }

    // Urutkan: slot paling langka (rasio terkecil) diproses pertama
    usort($slotData, fn($a, $b) => $a['kelangkaan'] <=> $b['kelangkaan']);

    $result  = [];
    $usedIds = [];

    foreach ($slotData as $slot) {
        // Ambil ulang kandidat dengan exclude yang sudah terpilih
        // Aturan 4: teruskan jenis_id
        $kandidat = getKandidatPerRole(
            $db, $slot['role_id'], $tgl_mulai, $tgl_selesai, $perusahaan_id, $usedIds, $jenis_id
        );

        $terpilih   = array_slice($kandidat, 0, $slot['jumlah_slot']);
        $alternatif = array_slice($kandidat, $slot['jumlah_slot']);

        foreach ($terpilih as $p) {
            $usedIds[] = $p['id'];
        }

        $result[$slot['role_id']] = [
            'role_id'     => $slot['role_id'],
            'role_nama'   => $slot['role_nama'],
            'jumlah_slot' => $slot['jumlah_slot'],
            'terpilih'    => $terpilih,
            'alternatif'  => $alternatif,
        ];
    }

    // Kembalikan dalam urutan alfabetis role untuk tampilan konsisten
    ksort($result);
    return $result;
}

/**
 * Simpan kunjungan + penugasan_tim ke database (langsung status Aktif).
 * $override: ['role_id' => [pegawai_id, ...]] — pilihan manual Admin.
 * Jika override kosong, gunakan hasil preview otomatis.
 *
 * @return string ID kunjungan yang baru dibuat
 */
function simpanKunjungan(
    PDO    $db,
    string $perusahaan_id,
    string $jenis_id,
    string $tgl_mulai,
    string $tgl_selesai,
    string $catatan,
    string $admin_id,
    array  $override   // ['role_id' => ['pegawai_id1', ...]]
): string {
    // Guard: admin_id tidak boleh kosong (cegah FK violation)
    if (empty($admin_id)) {
        throw new \RuntimeException('Admin ID tidak valid — sesi mungkin sudah kadaluarsa. Silakan login ulang.');
    }

    // Verifikasi admin_id benar-benar ada di tabel admins
    $cek = $db->prepare("SELECT id FROM admins WHERE id = ? LIMIT 1");
    $cek->execute([$admin_id]);
    if (!$cek->fetchColumn()) {
        throw new \RuntimeException("Admin ID '{$admin_id}' tidak ditemukan di database. Sesi tidak valid.");
    }

    $kunjunganId = generateId($db, 'kunjungan', 'K');

    $db->prepare("
        INSERT INTO kunjungan
            (id, perusahaan_id, jenis_audit_id, tanggal_mulai, tanggal_selesai, catatan, dibuat_oleh, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif')
    ")->execute([$kunjunganId, $perusahaan_id, $jenis_id, $tgl_mulai, $tgl_selesai, $catatan, $admin_id]);

    foreach ($override as $roleId => $pegawaiIds) {
        foreach ($pegawaiIds as $pegawaiId) {
            if (empty($pegawaiId)) continue;
            $db->prepare("
                INSERT INTO penugasan_tim (id, kunjungan_id, pegawai_id, role_id)
                VALUES (?, ?, ?, ?)
            ")->execute([generateId($db, 'penugasan_tim', 'T'), $kunjunganId, $pegawaiId, $roleId]);
        }
    }

    return $kunjunganId;
}


/**
 * Auto-Replacement: dipanggil ketika Admin menghapus anggota dari tim.
 * Cari pengganti untuk slot role yang sama, kirim notifikasi.
 * Return: 'replaced' | 'intervensi'
 */
function autoReplacement(PDO $db, string $penugasanId): string
{
    require_once __DIR__ . '/notifikasi.php';

    // Ambil detail penugasan yang dihapus - termasuk jenis_audit_id kunjungan
    $s = $db->prepare("
        SELECT pt.id, pt.kunjungan_id, pt.pegawai_id, pt.role_id,
               k.tanggal_mulai, k.tanggal_selesai, k.perusahaan_id, k.jenis_audit_id
        FROM penugasan_tim pt
        JOIN kunjungan k ON k.id = pt.kunjungan_id
        WHERE pt.id = ?
    ");
    $s->execute([$penugasanId]);
    $pen = $s->fetch();
    if (!$pen) return 'intervensi';

    // Kumpulkan semua anggota tim saat ini (exclude yang dihapus)
    $s2 = $db->prepare("
        SELECT pegawai_id FROM penugasan_tim
        WHERE kunjungan_id = ? AND id != ?
    ");
    $s2->execute([$pen['kunjungan_id'], $penugasanId]);
    $existingIds = array_column($s2->fetchAll(), 'pegawai_id');

    // Hapus penugasan lama
    $db->prepare("DELETE FROM penugasan_tim WHERE id=?")->execute([$penugasanId]);

    // Cari kandidat pengganti — sertakan jenis_audit_id untuk Aturan 4
    $kandidat = getKandidatPerRole(
        $db,
        $pen['role_id'],
        $pen['tanggal_mulai'],
        $pen['tanggal_selesai'],
        $pen['perusahaan_id'],
        $existingIds,
        $pen['jenis_audit_id']   // ← Aturan 4: rotasi berbasis siklus
    );

    if (empty($kandidat)) {
        // Tandai kunjungan Butuh Intervensi
        $db->prepare("UPDATE kunjungan SET status='Butuh Intervensi' WHERE id=?")
           ->execute([$pen['kunjungan_id']]);

        // Notifikasi semua admin
        $admins = $db->query("SELECT id FROM admins")->fetchAll();
        foreach ($admins as $admin) {
            kirimNotifikasi($db, 'admin', $admin['id'],
                'Kunjungan membutuhkan intervensi manual: tidak ada kandidat pengganti tersedia.',
                $pen['kunjungan_id']
            );
        }
        return 'intervensi';
    }

    // Pilih kandidat pertama (beban terkecil)
    $pengganti = $kandidat[0];

    // Buat penugasan baru
    $newPenId = generateId($db, 'penugasan_tim', 'T');
    $db->prepare("
        INSERT INTO penugasan_tim (id, kunjungan_id, pegawai_id, role_id)
        VALUES (?, ?, ?, ?)
    ")->execute([$newPenId, $pen['kunjungan_id'], $pengganti['id'], $pen['role_id']]);

    // Notifikasi pegawai pengganti
    kirimNotifikasi($db, 'pegawai', $pengganti['id'],
        'Anda mendapat penugasan audit baru.',
        $pen['kunjungan_id']
    );

    return 'replaced';
}

/**
 * Kembalikan role nama dari ID (helper tampilan)
 */
function getRoleName(PDO $db, string $roleId): string
{
    $s = $db->prepare("SELECT nama_role FROM role WHERE id=?");
    $s->execute([$roleId]);
    return $s->fetchColumn() ?: '-';
}
