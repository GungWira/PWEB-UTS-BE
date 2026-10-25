# Pemira API (Backend)

REST API untuk sistem E-Voting Pilkades. Stateless, autentikasi JWT, database MongoDB.
Tidak menyajikan HTML apa pun — frontend ada di project `pemira-web`.

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "echo bin2hex(random_bytes(32));"   # tempel hasilnya ke JWT_SECRET
# isi MONGODB_URI dengan connection string Atlas
php artisan migrate      # membuat index unik di MongoDB
php artisan db:seed      # akun admin + 5 contoh DPT
php artisan serve
```

## Konfigurasi

| Variabel | Keterangan |
|---|---|
| `MONGODB_URI` | Connection string Atlas (`mongodb+srv://...`) |
| `MONGODB_DATABASE` | Nama database, default `pemira` |
| `JWT_SECRET` | Kunci penandatangan token, wajib diisi |
| `JWT_TTL` | Masa berlaku token dalam detik, default 7200 |
| `FRONTEND_URL` | Origin yang diizinkan CORS, default `http://localhost:5173` |

## Akun hasil seeder

Admin: `admin` / `admin123` (password disimpan ter-hash).

5 pemilih contoh, login dengan NIK + tanggal lahir, misalnya
`5103010101900001` / `1990-01-01`.

## Endpoint

Semua endpoint berada di bawah prefix `/api/v1`.

### Publik

| Method | Path | Keterangan |
|---|---|---|
| POST | `/auth/voter/login` | `{nik, birth_date}` → token pemilih |
| POST | `/auth/admin/login` | `{username, password}` → token admin |
| GET | `/live-count` | Statistik partisipasi & perolehan suara |

### Perlu token

| Method | Path | Peran |
|---|---|---|
| GET | `/auth/me` | pemilih / admin |
| GET | `/vote/candidates` | pemilih |
| POST | `/vote` | pemilih |
| GET | `/admin/dashboard` | admin |
| POST | `/admin/reset-election` | admin |
| GET | `/admin/voters/template` | admin |
| POST | `/admin/voters/import/preview` | admin |
| POST | `/admin/voters/import/confirm` | admin |
| POST | `/admin/voters` | admin |
| POST | `/admin/voters/{id}/reset` | admin |
| DELETE | `/admin/voters/{id}` | admin |
| POST | `/admin/candidates` | admin |
| POST | `/admin/candidates/{id}` | admin |
| DELETE | `/admin/candidates/{id}` | admin |

Kirim token lewat header `Authorization: Bearer <token>`.

## Koleksi MongoDB

| Koleksi | Isi | Index |
|---|---|---|
| `voters` | DPT: nik, name, birth_date, rt_rw, has_voted | unik `nik`, index `has_voted` |
| `candidates` | Paslon: candidate_number, name, vice_name, vision_mission, photo_url | unik `candidate_number` |
| `votes` | Suara anonim: hanya `candidate_id` | index `candidate_id` |
| `admins` | Akun panitia: username, password (hash) | unik `username` |

`votes` sengaja tidak menyimpan referensi ke pemilih, sehingga pilihan tidak bisa
dilacak ke orangnya. Status memilih dicatat di `voters.has_voted`.

## Impor DPT

Karena JWT bersifat stateless, impor berjalan dua tahap tanpa session:

1. `POST /admin/voters/import/preview` — berkas dibaca dan diverifikasi, hasilnya
   dikembalikan sebagai JSON. Tidak ada data yang disimpan.
2. `POST /admin/voters/import/confirm` — frontend mengirim balik baris yang valid.
   Server **memvalidasi ulang** setiap baris sebelum menyimpan, jadi data dari
   browser tidak dipercaya begitu saja.

Yang diperiksa saat verifikasi: NIK harus 16 digit, NIK notasi ilmiah Excel
(`5.1E+15`), tanggal lahir wajib ada dan dinormalkan dari beberapa format,
NIK ganda di dalam berkas, dan NIK yang sudah terdaftar di DPT.

## Tes

```bash
php artisan test
```

`JwtAuthTest` berjalan tanpa database. Tes lainnya memerlukan MongoDB yang aktif
dan memakai database `pemira_test` (lihat `phpunit.xml`).
