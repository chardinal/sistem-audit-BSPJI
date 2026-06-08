# AMS — Audit Management System
### Sistem Penjadwalan Kunjungan Audit Perusahaan — BSPJI Surabaya

| Versi | Tanggal Rilis | Status |
|-------|---------------|--------|
| 8.1   | 7 Juni 2026   | Aktif  |

---

## Daftar Isi

1. [Gambaran Umum Sistem](#1-gambaran-umum-sistem)
2. [Pengguna Sistem](#2-pengguna-sistem)
3. [Model Role Pegawai](#3-model-role-pegawai)
4. [Algoritma Pembentukan Tim (5 Aturan)](#4-algoritma-pembentukan-tim-5-aturan)
5. [Alur Kerja Lengkap (End-to-End)](#5-alur-kerja-lengkap-end-to-end)
6. [Status Jadwal Kunjungan](#6-status-jadwal-kunjungan)
7. [Dokumentasi Fungsi & Logika Sistem](#7-dokumentasi-fungsi--logika-sistem)
8. [Skema Database & Relasi Tabel](#8-skema-database--relasi-tabel)
9. [Integrasi Google API](#9-integrasi-google-api)
10. [Konfigurasi & Instalasi](#10-konfigurasi--instalasi)
11. [Struktur Direktori](#11-struktur-direktori)
12. [Code Review & Catatan Teknis](#12-code-review--catatan-teknis)

---

## 1. Gambaran Umum Sistem

Audit Management System (AMS) adalah platform internal berbasis web yang memfasilitasi perencanaan, pembentukan tim, dan pengelolaan kunjungan audit lapangan ke perusahaan industri. Sistem mengotomatiskan proses yang sebelumnya dilakukan manual, mulai dari seleksi kandidat auditor hingga distribusi jadwal ke perangkat pribadi pegawai melalui email dan Google Calendar.

### 1.1 Tujuan Sistem

- Mengotomatiskan pembentukan tim auditor berdasarkan kompetensi dan ketersediaan
- Memastikan pembagian beban kerja yang adil dan terukur antar pegawai
- Memberikan transparansi operasional melalui dashboard real-time
- Mengintegrasikan jadwal langsung ke Google Calendar dan email pegawai saat jadwal dikonfirmasi
- Mengurangi potensi konflik jadwal melalui validasi otomatis berlapis

### 1.2 Prinsip Utama

> [!NOTE]
> - Tidak ada hierarki jabatan dalam tim audit — semua anggota berperan setara dengan role teknis masing-masing.
> - Seorang pegawai dapat memiliki lebih dari satu role (multi-role).
> - Formasi tim setiap kunjungan ditentukan oleh kombinasi role yang dibutuhkan, bukan jabatan struktural.
> - Komposisi tim bersifat fleksibel dan dikonfigurasi Admin per jenis audit.

---

## 2. Pengguna Sistem

Sistem menerapkan pemisahan portal login yang mutlak:

| Dimensi | Portal Admin | Portal Pegawai |
|---------|-------------|----------------|
| **Pengguna** | Administrator sistem | Seluruh pegawai terdaftar |
| **Akses Data** | Penuh atas semua data dan konfigurasi | Terbatas pada data penugasan diri sendiri |
| **Fungsi Utama** | Kelola jadwal, pegawai, perusahaan, role, jenis audit | Lihat jadwal pribadi, riwayat, dan profil |
| **Login** | Akun admin khusus (`admins` table) | Email pegawai + password (`pegawai` table) |
| **Platform** | Desktop (layar >= 1024px) | Mobile & Desktop (responsif) |
| **Notifikasi** | Alert intervensi & sistem | Penugasan baru & perubahan status |

---

## 3. Model Role Pegawai

### 3.1 Daftar Role (12 Role Aktif)

| Kategori | Role |
|----------|------|
| **Auditor** | Auditor Logam, Auditor Mamin & Pupuk, Auditor Elektronika, Auditor LSSM, Auditor Industri Hijau, Auditor Luar Negeri |
| **Verifikator** | Verifikator TKDN, Halal |
| **PPC (Petugas Pengambil Contoh)** | PPC Logam, PPC Mamin & Pupuk, PPC Elektronika, PPC Petroganik |

### 3.2 Multi-Role

Setiap pegawai dapat memiliki lebih dari satu role. Seorang pegawai dengan role `Auditor Logam` dan `PPC Logam` dapat dipilih untuk mengisi salah satu slot tersebut dalam satu kunjungan, **namun tidak keduanya** (satu pegawai, satu slot per kunjungan — enforced oleh anti-duplikat di `simpanKunjungan()`).

---

## 4. Algoritma Pembentukan Tim (5 Aturan)

Implementasi: [`includes/algorithm.php`](includes/algorithm.php)

Algoritma dijalankan secara berurutan setiap kali Admin membuat atau mengedit jadwal. Lima aturan diterapkan sebagai filter bertingkat:

### Aturan 1 — Validasi Rentang Tanggal
Dilakukan di form (`create.php` / `edit.php`) sebelum query:
- `tanggal_selesai >= tanggal_mulai` (minimal 1 hari)
- Kedua tanggal wajib diisi

### Aturan 2 — Matriks Formasi Kompetensi
```
getFormasiByJenis(PDO $db, string $jenis_id): array
```
Setiap jenis audit memiliki **formasi wajib** yang dikonfigurasi di tabel `formasi_audit`. Contoh:
- LS Pro - Logam: 1× Auditor Logam + 1× PPC Logam
- LSSM: 2× Auditor LSSM
- Petroganik: 2× PPC Petroganik

Hanya pegawai yang memiliki role yang sesuai (via `pegawai_role`) yang masuk ke pool kandidat.

### Aturan 3 — Anti-Overlap (Ketersediaan Harian)
```sql
AND p.id NOT IN (
    SELECT pt.pegawai_id FROM penugasan_tim pt
    JOIN kunjungan k ON k.id = pt.kunjungan_id
    WHERE k.status = 'Aktif'
      AND k.tanggal_mulai  <= :tgl_selesai_baru
      AND k.tanggal_selesai >= :tgl_mulai_baru
)
```
Pegawai yang sudah memiliki jadwal `Aktif` yang **bersinggungan** dengan rentang tanggal kunjungan baru otomatis dikeluarkan dari pool kandidat. Berlaku STRICT — satu pun hari yang overlap sudah cukup untuk memblokir.

### Aturan 4 — Rotasi Berbasis Siklus (Cool-off)
```sql
AND p.id NOT IN (
    SELECT pt.pegawai_id FROM penugasan_tim pt
    JOIN kunjungan k ON k.id = pt.kunjungan_id
    WHERE k.perusahaan_id  = :perusahaan_id
      AND k.jenis_audit_id = :jenis_id
      AND k.id = (/* kunjungan terakhir dengan kombinasi yang sama */)
)
```
Scope rotasi: per kombinasi **(perusahaan × jenis audit)**. Pegawai yang berpartisipasi di kunjungan **terakhir** (most-recent) dengan jenis dan perusahaan yang sama diblokir untuk kunjungan berikutnya. Mereka baru bisa kembali setelah minimal 1 siklus terlewati.

### Aturan 5 — Pemerataan Beban Kerja (Tiebreaker)
```sql
ORDER BY beban_bulan ASC, p.nama ASC
```
Di antara kandidat yang lolos semua filter, diprioritaskan yang memiliki **beban terkecil** (jumlah penugasan aktif di bulan yang sama). Ini memastikan tidak ada pegawai yang terus-menerus menerima penugasan sementara yang lain idle.

### Strategi Kelangkaan (Scarcity-First)
```php
// buildPreviewTim()
usort($slotData, fn($a, $b) => $a['kelangkaan'] <=> $b['kelangkaan']);
```
Slot yang paling sedikit kandidatnya diproses **lebih dulu**. Ini mencegah situasi di mana slot dengan banyak kandidat "mengambil" orang yang dibutuhkan oleh slot yang langka.

---

## 5. Alur Kerja Lengkap (End-to-End)

### Tahap 1 — Pembuatan Jadwal (Admin)

```
Admin mengisi form Buat Kunjungan:
  → Pilih perusahaan (autocomplete)
  → Pilih jenis audit
  → Isi tanggal mulai & selesai
  → Klik "Lihat Preview Tim"
       ↓
  [AJAX] → api/preview_tim.php
       ↓
  buildPreviewTim() dijalankan:
    → getFormasiByJenis() [Aturan 2]
    → getKandidatPerRole() per slot [Aturan 3, 4, 5]
    → Kelangkaan dihitung → slot langka diproses dahulu
       ↓
  Preview ditampilkan: slot terpilih otomatis + alternatif
  Admin dapat override manual jika diperlukan
       ↓
  Admin klik "Simpan Jadwal"
       ↓
  simpanKunjungan() dipanggil:
    → Validasi admin_id ke DB
    → INSERT ke tabel kunjungan (status = 'Aktif')
    → INSERT ke penugasan_tim per anggota
    → Return kunjungan_id
       ↓
  NotificationService::kirimPenugasanBaru():
    → CalendarService::createEvent() untuk setiap anggota
    → GmailService::send() email penugasan ke setiap anggota
```

### Tahap 2 — Pengelolaan Jadwal Aktif (Admin)

Admin dapat melakukan:
- **Edit Jadwal** — ubah tanggal, catatan, atau komposisi tim
- **Tandai Selesai** — ubah status ke `Selesai` via `api/tandai_selesai.php`
- **Hapus Jadwal** — kirim email pembatalan + hapus calendar + DELETE dari DB

### Tahap 3 — Penggantian Anggota (Auto-Replacement)

```
Admin hapus anggota dari tim (di detail.php)
    ↓
api/ganti_anggota.php dipanggil
    ↓
autoReplacement():
  → Kumpulkan ID anggota yang tersisa (exclude list)
  → DELETE penugasan lama dari penugasan_tim
  → getKandidatPerRole() dijalankan ulang dengan semua 5 aturan
       ↓
  Jika ada kandidat:
    → INSERT penugasan baru (kandidat teratas/beban terkecil)
    → kirimNotifikasi() ke pegawai baru
    → Return 'replaced'
  Jika tidak ada kandidat:
    → UPDATE kunjungan SET status = 'Butuh Intervensi'
    → kirimNotifikasi() ke semua admin
    → Return 'intervensi'
    ↓
NotificationService::kirimGantiAnggota():
  → Hapus Calendar event anggota lama
  → Email pembatalan ke anggota lama
  → Buat Calendar event baru untuk anggota pengganti
  → Email penugasan ke anggota pengganti
  → Email perubahan tim ke semua anggota yang masih aktif
```

### Tahap 4 — Intervensi Manual (Admin)

Jika status `Butuh Intervensi`, Admin masuk ke halaman detail kunjungan dan menambahkan anggota secara manual dari daftar pegawai yang tersedia.

### Tahap 5 — Penyelesaian Kunjungan

Admin menekan **Tandai Selesai** → status berubah ke `Selesai` → kunjungan pindah ke halaman **Riwayat**.

### Tahap 6 — Penghapusan Jadwal

```
Admin klik Hapus → Modal konfirmasi muncul
    ↓
POST ke api/hapus_kunjungan.php
    ↓
[LANGKAH 1] NotificationService::kirimPembatalanKunjungan():
  → Untuk setiap anggota tim (SEBELUM data dihapus):
    → CalendarService::deleteEvent(calendar_event_id)
    → GmailService::send() email "KUNJUNGAN DIBATALKAN"
    ↓
[LANGKAH 2] DELETE dari DB (urutan child → parent):
  → DELETE FROM notifikasi WHERE kunjungan_id = ?
  → DELETE FROM penugasan_tim WHERE kunjungan_id = ?
  → DELETE FROM kunjungan WHERE id = ?
```

> [!IMPORTANT]
> Notifikasi **wajib dipanggil sebelum DELETE** karena membutuhkan data tim (`calendar_event_id`) yang akan ikut terhapus. Jika Google API belum dikonfigurasi, hapus DB tetap berjalan (graceful degradation).

---

## 6. Status Jadwal Kunjungan

| Status | Keterangan | Transisi Selanjutnya |
|--------|------------|---------------------|
| `Aktif` | Jadwal dibuat dan tim terbentuk | → `Selesai` atau → `Butuh Intervensi` |
| `Selesai` | Kunjungan telah dilaksanakan | (final) |
| `Butuh Intervensi` | Tidak ada kandidat pengganti tersedia | → `Aktif` setelah anggota ditambahkan manual |

---

## 7. Dokumentasi Fungsi & Logika Sistem

### 7.1 `includes/functions.php` — Utilitas Umum

| Fungsi | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `generateId($db, $table, $prefix)` | PDO, string, string | string | Generate ID sequential format `[Huruf][5Angka]`. Thread-safe via `MAX(CAST(...))`. Batas 99999. |
| `fmtTanggal($date)` | ?string | string | Format `Y-m-d` → `27 Mei 2026` (bahasa Indonesia) |
| `fmtRentang($mulai, $selesai)` | string, string | string | Format rentang tanggal → `1–5 Jun 2026` |
| `selisihHari($mulai, $selesai)` | string, string | int | Hitung durasi inklusif (mulai s.d. selesai) |
| `badgeStatus($status)` | string | string | Render badge HTML berwarna untuk status kunjungan |
| `setFlash($type, $msg)` | string, string | void | Simpan flash message ke `$_SESSION['flash']` |
| `getFlash()` | — | ?array | Ambil & hapus flash dari session. Key: `type`, `msg` |
| `renderFlash()` | — | string | Render flash sebagai HTML `<div class="alert ...">` |
| `redirectWith($url, $type, $msg)` | string, string, string | never | `setFlash()` + `header(Location)` + `exit` |
| `countNotifAdmin($db, $adminId)` | PDO, string | int | Hitung notifikasi belum dibaca untuk admin |
| `countNotifPegawai($db, $pegawaiId)` | PDO, string | int | Hitung notifikasi belum dibaca untuk pegawai |

> [!NOTE]
> `generateId()` menggunakan `MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED))` untuk menemukan nomor urut tertinggi yang ada. Ini aman terhadap gap ID (misal setelah hapus data). Setiap panggilan harus dilakukan langsung sebelum `INSERT` untuk menghindari race condition pada insert batch.

### 7.2 `includes/algorithm.php` — Inti Algoritma

| Fungsi | Deskripsi |
|--------|-----------|
| `getFormasiByJenis($db, $jenis_id)` | Ambil daftar (role_id, jumlah_slot) dari `formasi_audit` untuk satu jenis audit. Filter `r.aktif = 1`. |
| `getKandidatPerRole($db, $role_id, $tgl_mulai, $tgl_selesai, $perusahaan_id, $exclude_ids, $jenis_audit_id)` | Query utama — terapkan Aturan 3 (anti-overlap) + Aturan 4 (rotasi siklus) + Aturan 5 (beban kerja). Return array pegawai terurut `beban_bulan ASC`. |
| `buildPreviewTim($db, $jenis_id, $tgl_mulai, $tgl_selesai, $perusahaan_id)` | Bangun preview tim lengkap. Hitung kelangkaan tiap slot, urutkan dari paling langka, isi slot secara greedy dengan `$usedIds` accumulator. |
| `simpanKunjungan($db, ...)` | INSERT kunjungan + semua penugasan_tim. Validasi `admin_id` ke DB sebelum INSERT. |
| `autoReplacement($db, $penugasanId)` | Hapus penugasan lama, cari pengganti via `getKandidatPerRole()`, INSERT penugasan baru. Tandai `Butuh Intervensi` jika tidak ada kandidat. |
| `getRoleName($db, $roleId)` | Helper: ambil `nama_role` dari `role.id`. |

### 7.3 `includes/auth.php` — Autentikasi & Sesi

| Fungsi | Deskripsi |
|--------|-----------|
| `requireAdmin()` | Guard halaman admin. Cek session `admin_id`, lalu validasi ke DB. Jika sesi tidak valid (mis. setelah DB reset), destroy session & redirect login dengan `?reason=session_invalid`. |
| `loginAdmin($db, $email, $password)` | Query `admins` by email, verifikasi password via `password_verify()`. Set session jika valid. |
| `logoutAdmin()` | Hapus session, destroy, redirect ke login. |
| `requirePegawai()` | Guard halaman pegawai, cek session `pegawai_id`. |
| `loginPegawai($db, $email, $password)` | Query `pegawai` by email, verifikasi password. |
| `currentAdmin()` | Return `['id', 'nama']` dari session. Redirect jika session kosong. |

### 7.4 `services/NotificationService.php` — Orkestrasi Notifikasi

| Method | Kapan Dipanggil | Yang Dilakukan |
|--------|----------------|----------------|
| `kirimPenugasanBaru($kunjunganId)` | Setelah jadwal baru dibuat | Buat Google Calendar event + kirim email penugasan untuk setiap anggota tim |
| `kirimPembatalanKunjungan($kunjunganId)` | Sebelum hapus kunjungan | Hapus Calendar event + kirim email pembatalan ke semua anggota |
| `kirimGantiAnggota($kvId, $oldId, $newId)` | Setelah anggota diganti | Hapus Calendar lama → email pembatalan ke lama → buat Calendar baru → email penugasan ke baru → email perubahan tim ke semua yang tersisa |
| `kirimPerubahanJadwal($kunjunganId, $perubahanDetail)` | Setelah edit jadwal disimpan | Anggota baru: Calendar + email penugasan. Anggota lama: email perubahan jadwal dengan diff tabel. |

> [!IMPORTANT]
> Semua method bersifat **best-effort**: kegagalan Google API (CalendarService/GmailService) dicatat di `$this->errors[]` dan di-log, namun tidak menghentikan proses utama.

### 7.5 `services/CalendarService.php`

| Method | Deskripsi |
|--------|-----------|
| `createEvent(...)` | Buat all-day event di Google Calendar pegawai. Tanggal selesai dikirim +1 hari (konvensi Google Calendar). Simpan `eventId` ke `penugasan_tim.calendar_event_id`. |
| `deleteEvent($calendarEventId)` | Hapus event dari Calendar. Error diabaikan (event mungkin sudah dihapus manual). |

### 7.6 `templates/EmailTemplates.php`

| Template | Subject | Dipicu Oleh |
|----------|---------|-------------|
| `penugasanBaru()` | `[AMS] Penugasan Audit Baru — {Perusahaan}` | Jadwal baru dibuat / anggota baru ditambahkan |
| `pembatalanPenugasan()` | `[AMS] Pembatalan Penugasan — {Perusahaan}` | Seorang anggota dihapus dari tim |
| `pembatalanKunjungan()` | `[AMS] Kunjungan Dibatalkan — {Perusahaan}` | Seluruh kunjungan dihapus oleh Admin |
| `perubahanTim()` | `[AMS] Perubahan Tim/Jadwal — {Perusahaan}` | Edit jadwal disimpan, dikirim ke anggota yang masih aktif |

### 7.7 `api/` — Endpoint AJAX & Form Action

| File | Method | Deskripsi |
|------|--------|-----------|
| `hapus_kunjungan.php` | POST | Kirim notifikasi pembatalan → hapus DB (notifikasi → penugasan_tim → kunjungan) |
| `tandai_selesai.php` | POST | UPDATE `kunjungan.status = 'Selesai'`, redirect dengan flash |
| `ganti_anggota.php` | POST | Panggil `autoReplacement()` + `NotificationService::kirimGantiAnggota()` |
| `preview_tim.php` | GET | Panggil `buildPreviewTim()`, return JSON preview tim |
| `pegawai_by_role.php` | GET | Return daftar pegawai berdasarkan role (untuk dropdown override manual) |
| `perusahaan_search.php` | GET | Autocomplete pencarian perusahaan |
| `perusahaan_create.php` | POST | Buat perusahaan baru langsung dari form jadwal |
| `notifikasi_count.php` | GET | Return jumlah notifikasi belum dibaca (untuk badge header) |

---

## 8. Skema Database & Relasi Tabel

### 8.1 Diagram Relasi

```
admins ──────────────────────────────────── kunjungan (dibuat_oleh)
                                                 │
perusahaan ──────────────────────────────── kunjungan (perusahaan_id)
                                                 │
jenis_audit ─────────────────────────────── kunjungan (jenis_audit_id)
     │                                           │
formasi_audit ◄──────────────────────────        │
     │ (role_id)                                 │
role ◄──────────────────────────────────── penugasan_tim (role_id)
     │                                           │
pegawai_role ◄──────────────────────────── penugasan_tim (pegawai_id)
     │ (pegawai_id)                              │
pegawai ────────────────────────────────── penugasan_tim (pegawai_id)
                                                 │
                                      notifikasi (kunjungan_id)
```

### 8.2 Tabel & Kolom Utama

| Tabel | PK Format | Kolom Kritis | Catatan |
|-------|-----------|--------------|---------|
| `admins` | `A00001` | `email` UNIQUE | Satu atau lebih admin |
| `pegawai` | `P00001` | `email` UNIQUE | 88 auditor & PPC |
| `role` | `R00001` | `nama_role`, `aktif` | 12 role aktif |
| `pegawai_role` | Composite PK | `pegawai_id`, `role_id` | Multi-role, CASCADE DELETE |
| `perusahaan` | `C00001` | `nama`, `alamat` | Perusahaan yang diaudit |
| `jenis_audit` | `J00001` | `nama`, `aktif` | 9 jenis audit |
| `formasi_audit` | `F00001` | `jenis_audit_id`, `role_id`, `jumlah_slot` | UNIQUE(jenis_audit_id, role_id) |
| `kunjungan` | `K00001` | `status` ENUM, `dibuat_oleh` FK→admins | Status: Aktif/Selesai/Butuh Intervensi |
| `penugasan_tim` | `T00001` | `calendar_event_id` | CASCADE DELETE saat kunjungan dihapus |
| `notifikasi` | `N00001` | `tipe_penerima` ENUM, `sudah_dibaca` | CASCADE DELETE saat kunjungan dihapus |

### 8.3 Foreign Key & Cascade Rules

| Constraint | Behavior |
|------------|----------|
| `pegawai_role → pegawai` | ON DELETE CASCADE |
| `pegawai_role → role` | ON DELETE CASCADE |
| `formasi_audit → jenis_audit` | ON DELETE CASCADE |
| `formasi_audit → role` | ON DELETE CASCADE |
| `penugasan_tim → kunjungan` | ON DELETE CASCADE |
| `penugasan_tim → pegawai` | ON DELETE CASCADE |
| `notifikasi → kunjungan` | ON DELETE CASCADE |
| `kunjungan → perusahaan` | **RESTRICT** (no cascade) — hapus perusahaan diblokir jika ada kunjungan |
| `kunjungan → jenis_audit` | **RESTRICT** — hapus jenis audit diblokir jika ada kunjungan |
| `kunjungan → admins` | **RESTRICT** — hapus admin diblokir jika ada kunjungan |

> [!CAUTION]
> Tabel `kunjungan` **tidak memiliki ON DELETE CASCADE** ke `perusahaan`, `jenis_audit`, atau `admins`. Ini disengaja untuk menjaga integritas referensial — data historis kunjungan harus dipertahankan.

---

## 9. Integrasi Google API

### 9.1 Komponen

| File | Deskripsi |
|------|-----------|
| `services/GoogleClientService.php` | Inisialisasi Google Client, load credentials & token OAuth2 |
| `services/GmailService.php` | Kirim email via Gmail API menggunakan akun Google yang sudah di-OAuth |
| `services/CalendarService.php` | Buat & hapus event Google Calendar |
| `auth/google_callback.php` | Callback OAuth2 — tukarkan authorization code dengan refresh token |

### 9.2 Setup OAuth

```
1. Buka https://console.cloud.google.com
2. Buat project → Enable Gmail API + Google Calendar API
3. Buat OAuth 2.0 credentials (Web Application)
4. Download credentials.json → letakkan di config/credentials.json
5. Akses /admin/email_debug.php → klik "Jalankan OAuth Google"
6. Izinkan akses → token tersimpan di config/token.json
```

### 9.3 Mode Tanpa Google API

Jika `config/credentials.json` tidak ada atau token kadaluarsa, sistem tetap berfungsi penuh — hanya fitur email dan kalender yang tidak aktif. Error dicatat di `NotificationService::$errors[]` dan di PHP error log.

---

## 10. Konfigurasi & Instalasi

### 10.1 Prasyarat

- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Docker & Docker Compose (direkomendasikan)
- Composer

### 10.2 Instalasi via Docker

```bash
# 1. Clone repository
git clone <repo-url> && cd Sistem_Audit_BSPJI

# 2. Salin file konfigurasi (sesuaikan isi)
cp config/config.example.php config/config.php

# 3. Jalankan Docker
docker-compose up -d

# 4. Inisialisasi database & data demo
# Buka browser: http://localhost:8080/setup.php
```

### 10.3 Variabel Konfigurasi (`config/config.php`)

| Key | Deskripsi |
|-----|-----------|
| `DB_HOST` | Host database (default: `db` untuk Docker) |
| `DB_NAME` | Nama database (default: `ams_db`) |
| `DB_USER` | Username database |
| `DB_PASS` | Password database |
| `BASE_URL` | URL dasar sistem (contoh: `http://localhost:8080`) |

### 10.4 Setup & Reset Database

Akses `http://localhost:8080/setup.php` untuk:
- Drop semua tabel yang ada
- Buat ulang skema dari `database/schema.sql`
- Seed: 1 admin, 12 role, 9 jenis audit, 88 pegawai, 10 perusahaan, 10 kunjungan demo

> [!CAUTION]
> `setup.php` menghapus **semua data yang ada**. Gunakan hanya pada environment development atau saat inisialisasi awal.

### 10.5 Kredensial Default (setelah setup.php)

| Akun | Email | Password |
|------|-------|----------|
| Admin | `admin@gmail.com` | `admin123` |
| Pegawai | `p1@bspji.go.id` s.d. `p88@bspji.go.id` | `pegawai123` |

---

## 11. Struktur Direktori

```
Sistem_Audit_BSPJI/
├── admin/                    # Portal Admin
│   ├── _header.php           # Layout header + sidebar (shared)
│   ├── _footer.php           # Layout footer + script Chart.js
│   ├── index.php             # Dashboard KPI & chart
│   ├── login.php / logout.php
│   ├── email_debug.php       # Tool diagnostik Gmail + OAuth
│   ├── jadwal/               # CRUD jadwal kunjungan
│   │   ├── create.php        # Form buat jadwal + preview tim (AJAX)
│   │   ├── edit.php          # Form edit jadwal + ganti anggota
│   │   ├── detail.php        # Detail kunjungan + kelola tim
│   │   └── index.php         # Daftar semua jadwal
│   ├── pegawai/              # CRUD data pegawai
│   ├── perusahaan/           # CRUD data perusahaan
│   ├── role/                 # Manajemen role
│   ├── jenis-audit/          # Manajemen jenis audit + formasi
│   ├── notifikasi/           # Inbox notifikasi admin
│   ├── riwayat/              # Kunjungan selesai
│   └── kinerja/              # Laporan beban kerja pegawai
│
├── api/                      # Endpoint AJAX & form action
│   ├── hapus_kunjungan.php
│   ├── tandai_selesai.php
│   ├── ganti_anggota.php
│   ├── preview_tim.php
│   ├── pegawai_by_role.php
│   ├── perusahaan_search.php
│   ├── perusahaan_create.php
│   └── notifikasi_count.php
│
├── pegawai/                  # Portal Pegawai (responsif)
│   ├── login.php / logout.php
│   ├── index.php             # Dashboard jadwal pribadi
│   ├── riwayat.php           # Riwayat penugasan
│   └── profil.php            # Edit profil & ganti password
│
├── services/                 # Layanan eksternal
│   ├── NotificationService.php
│   ├── GmailService.php
│   ├── CalendarService.php
│   └── GoogleClientService.php
│
├── templates/
│   └── EmailTemplates.php    # Template HTML 4 jenis email
│
├── includes/                 # Core logic
│   ├── algorithm.php         # 5 aturan pembentukan tim
│   ├── auth.php              # Session & autentikasi
│   ├── functions.php         # Helper & utilitas
│   └── notifikasi.php        # Fungsi kirimNotifikasi()
│
├── config/
│   ├── database.php          # PDO connection + session_start
│   ├── config.php            # Konfigurasi (DB, BASE_URL)
│   └── credentials.json      # Google OAuth credentials
│
├── database/
│   └── schema.sql            # DDL lengkap semua tabel
│
├── assets/                   # CSS, JS, gambar
├── auth/                     # Google OAuth callback
├── setup.php                 # Inisialisasi & seeding DB
├── docker-compose.yml
└── Dockerfile
```

---

## 12. Code Review & Catatan Teknis

### 12.1 Temuan Code Review

#### Isu yang Sudah Diperbaiki (v8.1)

| # | Isu | Lokasi | Status |
|---|-----|--------|--------|
| 1 | `$flash['message']` key salah (seharusnya `'msg'`) | `admin/jadwal/index.php` | Diperbaiki |
| 2 | Sesi UUID lama menyebabkan error setelah DB reset | `includes/auth.php` → `requireAdmin()` | Diperbaiki — validasi ke DB ditambahkan |
| 3 | Sisa kode lama terbawa setelah edit (orphan code) | `setup.php`, `admin/jadwal/index.php` | Diperbaiki |
| 4 | Hapus kunjungan tanpa notifikasi & hapus calendar | `api/hapus_kunjungan.php` | Diperbaiki — `kirimPembatalanKunjungan()` ditambahkan |
| 5 | `catch` block pegawai menelan kode seeder perusahaan | `setup.php` baris 263 | Diperbaiki |
| 6 | `generateId()` duplikat jika dipanggil batch sebelum INSERT | `setup.php` | Diperbaiki — interleaved generate-insert |
| 7 | Emoji dekoratif di dashboard mengurangi kesan profesional | `admin/index.php` | Diperbaiki — diganti SVG icon |

#### Potensi Risiko yang Perlu Diperhatikan

| # | Isu | Lokasi | Rekomendasi |
|---|-----|--------|-------------|
| 1 | `generateId()` tidak atomic — race condition pada concurrent INSERT | `includes/functions.php` | Pertimbangkan `SELECT ... FOR UPDATE` atau auto-increment |
| 2 | `badgeValidasi()` mereferensikan status yang sudah dihapus (`Menunggu`, `Accepted`, `Reschedule`) | `includes/functions.php` | Hapus atau update ke status yang berlaku |
| 3 | `uuid()` masih ada di `functions.php` (deprecated) | `includes/functions.php` | Hapus setelah migrasi selesai dikonfirmasi |
| 4 | `$db` global di `requireAdmin()` tidak selalu tersedia | `includes/auth.php` | Pertimbangkan passing `$db` sebagai parameter |
| 5 | Tidak ada rate limiting di endpoint API | `api/*.php` | Tambahkan validasi CSRF token |

### 12.2 Integritas Relasi Tabel

| Relasi | Status | Catatan |
|--------|--------|---------|
| `kunjungan → notifikasi` | **CASCADE DELETE** — aman | Notifikasi orphan tidak mungkin terjadi |
| `kunjungan → penugasan_tim` | **CASCADE DELETE** — aman | Penugasan orphan tidak mungkin terjadi |
| `penugasan_tim → pegawai` | **CASCADE DELETE** — aman | Hapus pegawai otomatis hapus penugasannya |
| `kunjungan → admins` | **RESTRICT** — disengaja | Admin tidak bisa dihapus jika ada kunjungan |
| `kunjungan → perusahaan` | **RESTRICT** — disengaja | Perusahaan tidak bisa dihapus jika ada kunjungan |
| `notifikasi.penerima_id` | **Tidak ada FK** | `penerima_id` bisa merujuk `admins.id` atau `pegawai.id` tergantung `tipe_penerima`. Integritas dijaga secara aplikasi. |

> [!WARNING]
> `notifikasi.penerima_id` tidak memiliki foreign key constraint karena nilai tersebut bisa merujuk ke dua tabel berbeda (`admins` atau `pegawai`). Jika pegawai dihapus, notifikasi yang ditujukan ke pegawai tersebut masih ada di database (orphan data). Pertimbangkan cleanup periodic atau soft-delete untuk pegawai.

### 12.3 Konvensi ID

Format: **`[1 Huruf Besar][5 Angka]`**

| Prefix | Tabel |
|--------|-------|
| `A` | admins |
| `C` | perusahaan (Company) |
| `F` | formasi_audit |
| `J` | jenis_audit |
| `K` | kunjungan |
| `N` | notifikasi |
| `P` | pegawai |
| `R` | role |
| `T` | penugasan_tim |

Contoh: `K00001`, `P00023`, `N00100`

---

*Dokumen ini diperbarui otomatis — terakhir direvisi 7 Juni 2026 (v8.1)*