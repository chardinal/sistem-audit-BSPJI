<?php
// ============================================================
// EmailTemplates — Template HTML email notifikasi AMS
// 3 jenis: penugasanBaru, pembatalanPenugasan, perubahanTim
// ============================================================

class EmailTemplates
{
    // ─────────────────────────────────────────────────────────
    // TEMPLATE 1: Penugasan Baru
    // Pemicu: Jadwal baru dikonfirmasi oleh Admin
    // ─────────────────────────────────────────────────────────
    public static function penugasanBaru(
        string $namaPegawai,
        string $rolePegawai,
        string $namaPerusahaan,
        string $alamatPerusahaan,
        string $jenisAudit,
        string $tanggalMulai,
        string $tanggalSelesai,
        array  $anggotaTim = []
    ): array {
        $subject = "[AMS] Penugasan Audit Baru - {$namaPerusahaan}";
        $timHtml = self::buildTimHtml($anggotaTim);

        $body = self::wrapper("
            <div class='header-badge'>PENUGASAN BARU</div>
            <h1>Halo, {$namaPegawai}!</h1>
            <p>Anda telah ditugaskan dalam kunjungan audit berikut.</p>

            <div class='info-box'>
                <div class='info-row'>
                    <span class='label'>Perusahaan</span>
                    <span class='value'><strong>{$namaPerusahaan}</strong></span>
                </div>
                <div class='info-row'>
                    <span class='label'>Alamat</span>
                    <span class='value'>{$alamatPerusahaan}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Jenis Audit</span>
                    <span class='value'>{$jenisAudit}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Tanggal Mulai</span>
                    <span class='value'>{$tanggalMulai}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Tanggal Selesai</span>
                    <span class='value'>{$tanggalSelesai}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Role Anda</span>
                    <span class='value'><span class='role-badge'>{$rolePegawai}</span></span>
                </div>
            </div>

            <h3>Tim Audit</h3>
            {$timHtml}

            <div class='note'>
                Event Google Calendar telah ditambahkan otomatis ke kalender Anda.
            </div>
        ", '#1a6b3c');

        return ['subject' => $subject, 'body' => $body];
    }


    // ─────────────────────────────────────────────────────────
    // TEMPLATE 2: Pembatalan Penugasan (individual — anggota dihapus dari tim)
    // Pemicu: Admin menghapus satu pegawai dari tim
    // ─────────────────────────────────────────────────────────
    public static function pembatalanPenugasan(
        string $namaPegawai,
        string $namaPerusahaan,
        string $alamatPerusahaan,
        string $jenisAudit,
        string $tanggalMulai,
        string $tanggalSelesai
    ): array {
        $subject = "[AMS] Pembatalan Penugasan - {$namaPerusahaan}";

        $body = self::wrapper("
            <div class='header-badge' style='background:#b91c1c'>PEMBATALAN PENUGASAN</div>
            <h1>Halo, {$namaPegawai}.</h1>
            <p>Penugasan audit berikut telah <strong>dibatalkan</strong> untuk Anda.</p>

            <div class='info-box' style='background:#fff5f5;border-color:#fecaca'>
                <div class='info-row'>
                    <span class='label'>Perusahaan</span>
                    <span class='value'><strong>{$namaPerusahaan}</strong></span>
                </div>
                <div class='info-row'>
                    <span class='label'>Alamat</span>
                    <span class='value'>{$alamatPerusahaan}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Jenis Audit</span>
                    <span class='value'>{$jenisAudit}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Tanggal</span>
                    <span class='value'>{$tanggalMulai} s/d {$tanggalSelesai}</span>
                </div>
            </div>

            <div class='note' style='background:#fff7ed;border-color:#f97316;color:#9a3412'>
                Undangan Google Calendar untuk kunjungan ini telah dihapus dari kalender Anda.
                Jika Anda memiliki pertanyaan, hubungi Admin.
            </div>
        ", '#b91c1c');

        return ['subject' => $subject, 'body' => $body];
    }


    // ─────────────────────────────────────────────────────────
    // TEMPLATE 2b: Pembatalan Kunjungan (seluruh jadwal dihapus)
    // Pemicu: Admin menghapus SELURUH kunjungan dari sistem
    // Dikirim ke semua anggota tim yang terdaftar
    // ─────────────────────────────────────────────────────────
    public static function pembatalanKunjungan(
        string $namaPegawai,
        string $namaPerusahaan,
        string $alamatPerusahaan,
        string $jenisAudit,
        string $tanggalMulai,
        string $tanggalSelesai
    ): array {
        $subject = "[AMS] ⚠️ Kunjungan Dibatalkan - {$namaPerusahaan}";
        $tanggal = date('d M Y', strtotime($tanggalMulai)) . ' – ' . date('d M Y', strtotime($tanggalSelesai));

        $body = self::wrapper("
            <div class='header-badge' style='background:#7f1d1d'>KUNJUNGAN DIBATALKAN</div>
            <h1>Halo, {$namaPegawai}.</h1>
            <p>Kami ingin memberitahukan bahwa kunjungan audit berikut telah
               <strong style='color:#b91c1c'>dibatalkan seluruhnya</strong> oleh Admin.</p>

            <div class='info-box' style='background:#fff5f5;border-color:#fca5a5'>
                <div class='info-row'>
                    <span class='label'>Perusahaan</span>
                    <span class='value'><strong>{$namaPerusahaan}</strong></span>
                </div>
                <div class='info-row'>
                    <span class='label'>Alamat</span>
                    <span class='value'>{$alamatPerusahaan}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Jenis Audit</span>
                    <span class='value'>{$jenisAudit}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Tanggal</span>
                    <span class='value'>{$tanggal}</span>
                </div>
            </div>

            <div class='note' style='background:#fef2f2;border-color:#f87171;color:#7f1d1d'>
                <strong>Tindakan yang sudah dilakukan secara otomatis:</strong><br>
                ✓ Event Google Calendar untuk kunjungan ini <strong>telah dihapus</strong> dari kalender Anda.<br>
                ✓ Anda tidak perlu melakukan tindakan apapun.<br><br>
                Jika Anda memiliki pertanyaan mengenai pembatalan ini,
                silakan hubungi Administrator AMS BSPJI.
            </div>
        ", '#7f1d1d');

        return ['subject' => $subject, 'body' => $body];
    }


    // ─────────────────────────────────────────────────────────
    // TEMPLATE 3: Perubahan Komposisi Tim / Jadwal
    // Pemicu: Admin menyimpan perubahan jadwal (Simpan Perubahan)
    //         atau anggota tim diganti.
    // Dikirim ke seluruh anggota yang MASIH AKTIF.
    //
    // $perubahanDetail: array of ['label', 'lama', 'baru']
    //   Kosong  → hanya perubahan anggota, tidak ada perubahan jadwal
    //   Berisi  → tampilkan tabel diff perubahan di dalam email
    // ─────────────────────────────────────────────────────────
    public static function perubahanTim(
        string $namaPegawai,
        string $namaPerusahaan,
        string $alamatPerusahaan,
        string $jenisAudit,
        string $tanggalMulai,
        string $tanggalSelesai,
        array  $anggotaBaru     = [],
        array  $perubahanDetail = []
    ): array {
        // Subject lebih informatif jika ada perubahan jadwal
        $subject = empty($perubahanDetail)
            ? "[AMS] Perubahan Tim Audit - {$namaPerusahaan}"
            : "[AMS] Perubahan Jadwal & Tim - {$namaPerusahaan}";

        $timHtml = self::buildTimHtml($anggotaBaru);

        // Blok detail perubahan (tanggal/catatan) — hanya muncul jika ada
        $detailHtml = '';
        if (!empty($perubahanDetail)) {
            $rows = '';
            foreach ($perubahanDetail as $d) {
                $label  = htmlspecialchars($d['label'], ENT_QUOTES);
                $lama   = htmlspecialchars($d['lama'],  ENT_QUOTES);
                $baru   = htmlspecialchars($d['baru'],  ENT_QUOTES);
                $rows  .= "
                    <tr>
                        <td style='padding:8px 12px;color:#6b7280;font-size:12px;white-space:nowrap;
                                   border-bottom:1px solid #f3f4f6;vertical-align:top'>{$label}</td>
                        <td style='padding:8px 12px;color:#9a3412;font-size:12px;
                                   border-bottom:1px solid #f3f4f6;vertical-align:top;
                                   text-decoration:line-through'>{$lama}</td>
                        <td style='padding:8px 12px;color:#166534;font-size:12px;font-weight:600;
                                   border-bottom:1px solid #f3f4f6;vertical-align:top'>{$baru}</td>
                    </tr>";
            }
            $detailHtml = "
            <h3>Detail Perubahan</h3>
            <table style='width:100%;border-collapse:collapse;font-size:12px;background:#fafafa;
                          border:1px solid #e5e7eb;border-radius:6px;overflow:hidden'>
                <thead>
                    <tr style='background:#b45309'>
                        <th style='padding:8px 12px;text-align:left;color:#fff;font-weight:600;font-size:11px'>Item</th>
                        <th style='padding:8px 12px;text-align:left;color:#fff;font-weight:600;font-size:11px'>Sebelumnya</th>
                        <th style='padding:8px 12px;text-align:left;color:#fff;font-weight:600;font-size:11px'>Sesudah</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>";
        }

        $body = self::wrapper("
            <div class='header-badge' style='background:#b45309'>PERUBAHAN JADWAL</div>
            <h1>Halo, {$namaPegawai}.</h1>
            <p>Ada perubahan pada jadwal atau komposisi tim untuk kunjungan audit berikut.</p>

            <div class='info-box'>
                <div class='info-row'>
                    <span class='label'>Perusahaan</span>
                    <span class='value'><strong>{$namaPerusahaan}</strong></span>
                </div>
                <div class='info-row'>
                    <span class='label'>Alamat</span>
                    <span class='value'>{$alamatPerusahaan}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Jenis Audit</span>
                    <span class='value'>{$jenisAudit}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Tanggal</span>
                    <span class='value'>{$tanggalMulai} s/d {$tanggalSelesai}</span>
                </div>
            </div>

            {$detailHtml}

            <h3>Susunan Tim Terbaru</h3>
            {$timHtml}

            <div class='note'>
                Undangan Google Calendar Anda telah diperbarui secara otomatis.
                Jika ada pertanyaan, hubungi Admin.
            </div>
        ", '#b45309');

        return ['subject' => $subject, 'body' => $body];
    }



    // ─────────────────────────────────────────────────────────
    // HELPER: Tabel daftar anggota tim
    // ─────────────────────────────────────────────────────────
    private static function buildTimHtml(array $anggota): string
    {
        if (empty($anggota)) return '';

        $rows = '';
        foreach ($anggota as $a) {
            $rows .= "<tr>
                <td>{$a['nama']}</td>
                <td><span class='role-badge-sm'>{$a['role']}</span></td>
            </tr>";
        }

        return "<table class='tim-table'>
            <thead><tr><th>Nama</th><th>Role</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>";
    }


    // ─────────────────────────────────────────────────────────
    // HELPER: HTML wrapper — layout & style email
    // ─────────────────────────────────────────────────────────
    private static function wrapper(string $content, string $accentColor): string
    {
        $year = date('Y');
        return "<!DOCTYPE html>
<html lang='id'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
  body        { margin:0; padding:0; background:#f4f4f5; font-family:'Segoe UI',Arial,sans-serif; color:#1a1a2e; }
  .wrapper    { max-width:560px; margin:32px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
  .top-bar    { background:{$accentColor}; height:6px; }
  .body-content { padding:32px 36px; }
  .header-badge { display:inline-block; background:{$accentColor}; color:#fff; font-size:11px; font-weight:700; letter-spacing:1.5px; padding:4px 12px; border-radius:20px; margin-bottom:16px; }
  h1  { font-size:20px; font-weight:700; margin:0 0 8px; color:#111827; }
  h3  { font-size:13px; font-weight:700; color:#374151; margin:24px 0 10px; text-transform:uppercase; letter-spacing:.5px; }
  p   { font-size:14px; color:#4b5563; line-height:1.6; margin:0 0 16px; }
  .info-box   { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; margin:20px 0; }
  .info-row   { display:table; width:100%; padding:7px 0; border-bottom:1px solid #f3f4f6; font-size:13px; }
  .info-row:last-child { border-bottom:none; }
  .label      { display:table-cell; color:#6b7280; width:145px; padding-right:12px; vertical-align:top; white-space:nowrap; }
  .value      { display:table-cell; color:#111827; font-weight:500; vertical-align:top; }
  .role-badge    { background:{$accentColor}; color:#fff; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }
  .role-badge-sm { background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; }
  .tim-table  { width:100%; border-collapse:collapse; font-size:13px; }
  .tim-table thead tr { background:{$accentColor}; color:#fff; }
  .tim-table th { padding:8px 12px; text-align:left; font-weight:600; }
  .tim-table td { padding:8px 12px; border-bottom:1px solid #f3f4f6; color:#374151; }
  .tim-table tbody tr:last-child td { border-bottom:none; }
  .note   { background:#f0fdf4; border-left:3px solid #16a34a; border-radius:4px; padding:10px 14px; font-size:12px; color:#166534; margin-top:20px; }
  .footer { background:#f9fafb; padding:16px 36px; font-size:11px; color:#9ca3af; text-align:center; border-top:1px solid #e5e7eb; }
</style>
</head>
<body>
  <div class='wrapper'>
    <div class='top-bar'></div>
    <div class='body-content'>{$content}</div>
    <div class='footer'>
      Email ini dikirim otomatis oleh sistem AMS BSPJI. Jangan membalas email ini.<br>
      Audit Management System &copy; {$year} &mdash; Balai Standardisasi dan Pelayanan Jasa Industri
    </div>
  </div>
</body>
</html>";
    }
}
