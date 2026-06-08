<?php
// ============================================================
// CalendarService — Buat & hapus event Google Calendar
// ============================================================

require_once __DIR__ . '/GoogleClientService.php';

class CalendarService
{
    private Google\Service\Calendar $calendar;

    public function __construct()
    {
        $googleClient = new GoogleClientService();
        $this->calendar = new Google\Service\Calendar($googleClient->getClient());
    }

    /**
     * Buat event audit di Google Calendar pegawai
     *
     * @param string $pegawaiEmail   Email Google pegawai
     * @param string $namaPerusahaan Nama perusahaan yang diaudit
     * @param string $jenisAudit     Jenis audit (misal: LS Pro - Logam)
     * @param string $rolePegawai    Role pegawai dalam audit ini
     * @param string $tanggalMulai   Format Y-m-d
     * @param string $tanggalSelesai Format Y-m-d (batas akhir eksklusif di API Calendar)
     * @param array  $anggotaTim     [['nama'=>..., 'role'=>...], ...]
     * @return string Event ID (simpan ke penugasan_tim.calendar_event_id)
     */
    public function createEvent(
        string $pegawaiEmail,
        string $namaPerusahaan,
        string $jenisAudit,
        string $rolePegawai,
        string $tanggalMulai,
        string $tanggalSelesai,
        array  $anggotaTim = []
    ): string {
        $timList = implode("\n", array_map(
            fn($a) => "- {$a['nama']} ({$a['role']})",
            $anggotaTim
        ));

        // Google Calendar: tanggal selesai all-day event harus +1 hari
        $endDate = date('Y-m-d', strtotime($tanggalSelesai . ' +1 day'));

        $event = new Google\Service\Calendar\Event([
            'summary'     => "Audit: {$namaPerusahaan}",
            'description' => "Jenis Audit : {$jenisAudit}\nRole Anda   : {$rolePegawai}\n\nTim Audit:\n{$timList}",
            'start'       => ['date' => $tanggalMulai],
            'end'         => ['date' => $endDate],
            'attendees'   => [['email' => $pegawaiEmail]],
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email',  'minutes' => 24 * 60],  // H-1 via email
                    ['method' => 'popup',  'minutes' => 60],        // 1 jam sebelum
                ],
            ],
        ]);

        $created = $this->calendar->events->insert('primary', $event, [
            'sendUpdates' => 'all',
        ]);

        return $created->getId();
    }

    /**
     * Hapus event dari kalender (saat penugasan dibatalkan)
     *
     * @param string $calendarEventId ID yang tersimpan di penugasan_tim
     */
    public function deleteEvent(string $calendarEventId): void
    {
        try {
            $this->calendar->events->delete('primary', $calendarEventId);
        } catch (\Exception $e) {
            // Event mungkin sudah dihapus manual — log dan lanjutkan
            error_log('[AMS CalendarService] Gagal hapus event ' . $calendarEventId . ': ' . $e->getMessage());
        }
    }
}
