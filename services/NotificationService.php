<?php
// ============================================================
// NotificationService — Orkestrasi Gmail + Calendar + DB
// Dipanggil dari halaman admin saat jadwal dibuat/diubah
// Methods:
//   kirimPenugasanBaru($kunjunganId)          → jadwal baru dibuat
//   kirimGantiAnggota($kvId,$oldId,$newId)    → ganti anggota via Kelola Tim
//   kirimPerubahanJadwal($kvId,$perubahan)    → Simpan Perubahan di edit.php
// ============================================================

require_once __DIR__ . '/GmailService.php';
require_once __DIR__ . '/CalendarService.php';
require_once __DIR__ . '/../templates/EmailTemplates.php';
require_once __DIR__ . '/../vendor/autoload.php';

class NotificationService
{
    private ?GmailService    $gmail    = null;
    private ?CalendarService $calendar = null;
    private PDO              $db;

    /** @var string[] Error messages dari proses pengiriman */
    public array $errors = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        try {
            $this->gmail    = new GmailService();
            $this->calendar = new CalendarService();
        } catch (\Exception $e) {
            $this->errors[] = 'Google API belum terkonfigurasi: ' . $e->getMessage();
        }
    }

    public function isReady(): bool
    {
        return $this->gmail !== null && empty($this->errors);
    }

    // ─────────────────────────────────────────────────────────
    // Kirim notifikasi penugasan baru ke seluruh anggota tim
    // Dipanggil setelah jadwal kunjungan baru dibuat
    // ─────────────────────────────────────────────────────────
    public function kirimPenugasanBaru(string $kunjunganId): void
    {
        if (!$this->isReady()) return;

        $kunjungan = $this->getKunjungan($kunjunganId);
        $tim       = $this->getTim($kunjunganId);
        if (!$kunjungan || empty($tim)) return;

        foreach ($tim as $anggota) {
            if (empty($anggota['email'])) continue;

            // 1. Buat event Google Calendar
            $eventId = null;
            try {
                $eventId = $this->calendar->createEvent(
                    pegawaiEmail:    $anggota['email'],
                    namaPerusahaan:  $kunjungan['perusahaan'],
                    jenisAudit:      $kunjungan['jenis_audit'],
                    rolePegawai:     $anggota['role'],
                    tanggalMulai:    $kunjungan['tanggal_mulai'],
                    tanggalSelesai:  $kunjungan['tanggal_selesai'],
                    anggotaTim:      $tim
                );
                $this->simpanCalendarEventId($kunjunganId, $anggota['pegawai_id'], $eventId);
            } catch (\Exception $e) {
                $this->errors[] = "Calendar '{$anggota['nama']}': " . $e->getMessage();
            }

            // 2. Kirim email notifikasi
            try {
                ['subject' => $sub, 'body' => $bod] = EmailTemplates::penugasanBaru(
                    namaPegawai:      $anggota['nama'],
                    rolePegawai:      $anggota['role'],
                    namaPerusahaan:   $kunjungan['perusahaan'],
                    alamatPerusahaan: $kunjungan['alamat'],
                    jenisAudit:       $kunjungan['jenis_audit'],
                    tanggalMulai:     $kunjungan['tanggal_mulai'],
                    tanggalSelesai:   $kunjungan['tanggal_selesai'],
                    anggotaTim:       $tim
                );
                $this->gmail->send($anggota['email'], $sub, $bod);
            } catch (\Exception $e) {
                $this->errors[] = "Email '{$anggota['nama']}': " . $e->getMessage();
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // Kirim notifikasi PEMBATALAN kunjungan ke seluruh anggota
    // + hapus Google Calendar event masing-masing anggota
    //
    // ⚠️  WAJIB dipanggil SEBELUM data dihapus dari DB,
    //     karena membutuhkan data tim & calendar_event_id.
    // ─────────────────────────────────────────────────────────
    public function kirimPembatalanKunjungan(string $kunjunganId): void
    {
        if (!$this->isReady()) return;

        $kunjungan = $this->getKunjungan($kunjunganId);
        $tim       = $this->getTim($kunjunganId);
        if (!$kunjungan || empty($tim)) return;

        foreach ($tim as $anggota) {
            // 1. Hapus Google Calendar event pegawai ini
            if (!empty($anggota['calendar_event_id'])) {
                try {
                    $this->calendar->deleteEvent($anggota['calendar_event_id']);
                } catch (\Exception $e) {
                    $this->errors[] = "Hapus Calendar '{$anggota['nama']}': " . $e->getMessage();
                }
            }

            // 2. Kirim email pembatalan (skip jika tidak ada email)
            if (empty($anggota['email'])) continue;
            try {
                ['subject' => $sub, 'body' => $bod] = EmailTemplates::pembatalanKunjungan(
                    namaPegawai:      $anggota['nama'],
                    namaPerusahaan:   $kunjungan['perusahaan'],
                    alamatPerusahaan: $kunjungan['alamat'],
                    jenisAudit:       $kunjungan['jenis_audit'],
                    tanggalMulai:     $kunjungan['tanggal_mulai'],
                    tanggalSelesai:   $kunjungan['tanggal_selesai']
                );
                $this->gmail->send($anggota['email'], $sub, $bod);
            } catch (\Exception $e) {
                $this->errors[] = "Email pembatalan '{$anggota['nama']}': " . $e->getMessage();
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // Kirim notifikasi saat anggota tim diganti
    // ─────────────────────────────────────────────────────────
    public function kirimGantiAnggota(
        string $kunjunganId,
        string $pegawaiDihapusId,
        string $pegawaiBaruId
    ): void {
        if (!$this->isReady()) return;

        $kunjungan      = $this->getKunjungan($kunjunganId);
        $anggotaDihapus = $this->getPegawaiById($pegawaiDihapusId);
        $anggotaBaru    = $this->getPegawaiById($pegawaiBaruId);

        if (!$kunjungan || !$anggotaDihapus || !$anggotaBaru) return;

        // 1. Hapus calendar event anggota lama
        $calEventId = $this->getCalendarEventId($kunjunganId, $pegawaiDihapusId);
        if ($calEventId) {
            try {
                $this->calendar->deleteEvent($calEventId);
            } catch (\Exception $e) {
                $this->errors[] = "Hapus Calendar '{$anggotaDihapus['nama']}': " . $e->getMessage();
            }
        }

        // 2. Email pembatalan ke anggota lama
        try {
            ['subject' => $sub, 'body' => $bod] = EmailTemplates::pembatalanPenugasan(
                namaPegawai:   $anggotaDihapus['nama'],
                namaPerusahaan:$kunjungan['perusahaan'],
                alamatPerusahaan:$kunjungan['alamat'],
                jenisAudit:    $kunjungan['jenis_audit'],
                tanggalMulai:  $kunjungan['tanggal_mulai'],
                tanggalSelesai:$kunjungan['tanggal_selesai']
            );
            $this->gmail->send($anggotaDihapus['email'], $sub, $bod);
        } catch (\Exception $e) {
            $this->errors[] = "Email pembatalan: " . $e->getMessage();
        }

        // 3. Buat Calendar event ke anggota baru
        try {
            $roleAnggotaBaru = $this->getRoleDiKunjungan($kunjunganId, $pegawaiBaruId);
            
            // Dapatkan tim saat ini untuk pembuatan calendar event
            $timSaatIni = $this->getTim($kunjunganId);
            
            $eventId = $this->calendar->createEvent(
                pegawaiEmail:   $anggotaBaru['email'],
                namaPerusahaan: $kunjungan['perusahaan'],
                jenisAudit:     $kunjungan['jenis_audit'],
                rolePegawai:    $roleAnggotaBaru,
                tanggalMulai:   $kunjungan['tanggal_mulai'],
                tanggalSelesai: $kunjungan['tanggal_selesai'],
                anggotaTim:     $timSaatIni
            );
            $this->simpanCalendarEventId($kunjunganId, $pegawaiBaruId, $eventId);
        } catch (\Exception $e) {
            $this->errors[] = "Calendar anggota baru: " . $e->getMessage();
        }

        // Ambil tim terbaru setelah event ID disimpan
        $timBaru = $this->getTim($kunjunganId);

        // 4. Email perubahan jadwal ke seluruh anggota tim terbaru (termasuk pengganti baru)
        foreach ($timBaru as $anggota) {
            if (empty($anggota['email'])) continue;
            try {
                ['subject' => $sub, 'body' => $bod] = EmailTemplates::perubahanTim(
                    namaPegawai:     $anggota['nama'],
                    namaPerusahaan:  $kunjungan['perusahaan'],
                    alamatPerusahaan:$kunjungan['alamat'],
                    jenisAudit:      $kunjungan['jenis_audit'],
                    tanggalMulai:    $kunjungan['tanggal_mulai'],
                    tanggalSelesai:  $kunjungan['tanggal_selesai'],
                    anggotaBaru:     $timBaru
                );
                $this->gmail->send($anggota['email'], $sub, $bod);
            } catch (\Exception $e) {
                $this->errors[] = "Email perubahan tim '{$anggota['nama']}': " . $e->getMessage();
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // Kirim notifikasi saat Admin menyimpan perubahan jadwal
    // (tombol "Simpan Perubahan" di edit.php)
    //
    // Logika:
    //   - Pegawai yang baru masuk tim (calendar_event_id IS NULL)
    //     → Email "Penugasan Baru" + buat Google Calendar event
    //   - Pegawai yang sudah ada sebelumnya
    //     → Email "Perubahan Jadwal" beserta rincian apa yang berubah
    //
    // $perubahanDetail: array of ['label', 'lama', 'baru']
    //   Kosong = hanya perubahan tim, tidak ada perubahan tanggal/catatan
    // ─────────────────────────────────────────────────────────
    public function kirimPerubahanJadwal(string $kunjunganId, array $perubahanDetail = []): void
    {
        if (!$this->isReady()) return;

        $kunjungan = $this->getKunjungan($kunjunganId);
        $tim       = $this->getTim($kunjunganId);
        if (!$kunjungan || empty($tim)) return;

        foreach ($tim as $anggota) {
            if (empty($anggota['email'])) continue;

            $isAnggotaBaru = empty($anggota['calendar_event_id']);

            if ($isAnggotaBaru) {
                // ── Pegawai baru: buat Calendar + email Penugasan Baru ────
                try {
                    $eventId = $this->calendar->createEvent(
                        pegawaiEmail:   $anggota['email'],
                        namaPerusahaan: $kunjungan['perusahaan'],
                        jenisAudit:     $kunjungan['jenis_audit'],
                        rolePegawai:    $anggota['role'],
                        tanggalMulai:   $kunjungan['tanggal_mulai'],
                        tanggalSelesai: $kunjungan['tanggal_selesai'],
                        anggotaTim:     $tim
                    );
                    $this->simpanCalendarEventId($kunjunganId, $anggota['pegawai_id'], $eventId);
                } catch (\Exception $e) {
                    $this->errors[] = "Calendar baru '{$anggota['nama']}': " . $e->getMessage();
                }

                try {
                    ['subject' => $sub, 'body' => $bod] = EmailTemplates::penugasanBaru(
                        namaPegawai:      $anggota['nama'],
                        rolePegawai:      $anggota['role'],
                        namaPerusahaan:   $kunjungan['perusahaan'],
                        alamatPerusahaan: $kunjungan['alamat'],
                        jenisAudit:       $kunjungan['jenis_audit'],
                        tanggalMulai:     $kunjungan['tanggal_mulai'],
                        tanggalSelesai:   $kunjungan['tanggal_selesai'],
                        anggotaTim:       $tim
                    );
                    $this->gmail->send($anggota['email'], $sub, $bod);
                } catch (\Exception $e) {
                    $this->errors[] = "Email penugasan baru '{$anggota['nama']}': " . $e->getMessage();
                }

            } else {
                // ── Pegawai lama: email Perubahan Jadwal ────────────
                try {
                    ['subject' => $sub, 'body' => $bod] = EmailTemplates::perubahanTim(
                        namaPegawai:      $anggota['nama'],
                        namaPerusahaan:   $kunjungan['perusahaan'],
                        alamatPerusahaan: $kunjungan['alamat'],
                        jenisAudit:       $kunjungan['jenis_audit'],
                        tanggalMulai:     $kunjungan['tanggal_mulai'],
                        tanggalSelesai:   $kunjungan['tanggal_selesai'],
                        anggotaBaru:      $tim,
                        perubahanDetail:  $perubahanDetail
                    );
                    $this->gmail->send($anggota['email'], $sub, $bod);
                } catch (\Exception $e) {
                    $this->errors[] = "Email perubahan '{$anggota['nama']}': " . $e->getMessage();
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // Helper — Query DB
    // ─────────────────────────────────────────────────────────

    private function getKunjungan(string $id): ?array
    {
        $s = $this->db->prepare("
            SELECT k.id, pr.nama AS perusahaan, pr.alamat, ja.nama AS jenis_audit,
                   k.tanggal_mulai, k.tanggal_selesai, k.status
            FROM kunjungan k
            JOIN perusahaan pr ON pr.id = k.perusahaan_id
            JOIN jenis_audit ja ON ja.id = k.jenis_audit_id
            WHERE k.id = ?
        ");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    private function getTim(string $kunjunganId): array
    {
        $s = $this->db->prepare("
            SELECT pt.pegawai_id, pt.calendar_event_id, p.nama, p.email, r.nama_role AS role
            FROM penugasan_tim pt
            JOIN pegawai p ON p.id = pt.pegawai_id
            JOIN role r    ON r.id = pt.role_id
            WHERE pt.kunjungan_id = ?
        ");
        $s->execute([$kunjunganId]);
        return $s->fetchAll();
    }

    private function getPegawaiById(string $id): ?array
    {
        $s = $this->db->prepare("SELECT id, nama, email FROM pegawai WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    private function getRoleDiKunjungan(string $kunjunganId, string $pegawaiId): string
    {
        $s = $this->db->prepare("
            SELECT r.nama_role FROM penugasan_tim pt
            JOIN role r ON r.id = pt.role_id
            WHERE pt.kunjungan_id = ? AND pt.pegawai_id = ?
        ");
        $s->execute([$kunjunganId, $pegawaiId]);
        return $s->fetchColumn() ?: '-';
    }

    private function getCalendarEventId(string $kunjunganId, string $pegawaiId): ?string
    {
        $s = $this->db->prepare("
            SELECT calendar_event_id FROM penugasan_tim
            WHERE kunjungan_id = ? AND pegawai_id = ?
        ");
        $s->execute([$kunjunganId, $pegawaiId]);
        return $s->fetchColumn() ?: null;
    }

    private function simpanCalendarEventId(string $kunjunganId, string $pegawaiId, string $eventId): void
    {
        $this->db->prepare("
            UPDATE penugasan_tim SET calendar_event_id = ?
            WHERE kunjungan_id = ? AND pegawai_id = ?
        ")->execute([$eventId, $kunjunganId, $pegawaiId]);
    }
}
