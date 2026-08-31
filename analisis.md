# Analisis Teknis — `opensid/laravel-filemanager`

> Dokumen orientasi untuk developer / agen AI yang memelihara atau
> mengembangkan package ini. Dibuat pada rilis **v1.0.0** (2026-08-31).
> Pasangkan dengan [CHANGELOG.md](CHANGELOG.md) dan [README.md](README.md).

---

## 1. Ringkasan

Package ini adalah **penulisan ulang penuh Responsive Filemanager (RFM)
v9.x** — pengelola berkas/gambar prosedural yang dulu diletakkan di
direktori `rfm/` pada root OpenSID — menjadi package Laravel yang:

- berjalan **di dalam** lifecycle Laravel (route → middleware → controller),
- menyimpan berkas lewat **`Storage`/Flysystem** (bukan akses `fopen`/FTP
  langsung), sehingga disk apa pun bisa dipakai (`local`, `s3`, `ftp`,
  `sftp`),
- mengotorisasi lewat **`Gate`**, bukan cookie terenkripsi buatan sendiri.

Konsumen utamanya adalah editor **TinyMCE** di OpenSID (Artikel Web,
Pengaturan/Cetak Surat, Lampiran, QR Code), tetapi package tidak bergantung
pada OpenSID — integrasi dilakukan lewat titik ekstensi (lihat §8).

**Yang TIDAK ditulis ulang:** bundle JavaScript frontend (`resources/dist/js/
include.js` dan kawan-kawan). Itu tetap file RFM asli yang di-*minify*, hanya
di-*patch* untuk memanggil URL `/filemanager/...`. Konsekuensi kontraknya
dijelaskan di §7 — **ini bagian paling mudah bikin salah**.

---

## 2. Prinsip desain (kenapa begini)

| Keputusan | Alasan |
|---|---|
| Semua I/O lewat `Storage::disk()` | Menghapus dua jalur kode (lokal + FTP) RFM; dukungan S3/SFTP gratis; path selalu relatif terhadap root disk. |
| Otorisasi lewat `Gate`, konteks dari **session** | RFM lama pakai cookie `rfm_access` terenkripsi + HMAC `fm_key` → rentan PHP Object Injection saat `decrypt()` (premium #6976, #6985). Sekarang tidak ada `decrypt()` sama sekali. |
| Frontend bundle **tidak disentuh** | Menulis ulang UI RFM = proyek besar tersendiri. Selama backend meniru kontрак lama persis, UI jalan apa adanya. |
| Fitur berisiko **dibuang**, bukan diamankan | `extract` (zip/tar), `cad_preview`, `media_preview`, upload-via-URL, `chmod`, TUI image editor. Lebih sedikit permukaan serang > lebih banyak fitur. |
| Validasi konten berkas terpisah dari validasi nama | Nama (`shell.jpg`) gampang dipalsukan; `FileContentValidator` mengecek *byte* aktual. |
| `.htaccess` ditulis di setiap `makeDirectory()` | Pertahanan terakhir bila sebuah berkas berbahaya lolos validasi. |

---

## 3. Peta direktori

```
src/
├── LaravelFilemanagerServiceProvider.php   Registrasi package (spatie/laravel-package-tools)
│                                            → config, view, route, command, Gate default
├── helpers.php                              filemanager_authorize(), filemanager_base_url(), filemanager_asset()
│
├── Http/
│   ├── Controllers/
│   │   ├── DialogController.php     GET /filemanager        → render UI browser/picker
│   │   ├── AjaxController.php       GET|POST /filemanager/ajax    → view/sort/filter/copy-cut/get_file
│   │   ├── ExecuteController.php    POST /filemanager/execute     → create/rename/delete/duplicate/crop/paste/save-text
│   │   ├── UploadController.php     GET|POST /filemanager/upload  → unggah (single + chunked blueimp)
│   │   └── DownloadController.php   POST /filemanager/download    → Storage::download()
│   └── Middleware/
│       └── EnsureFilemanagerAccess.php   abort_unless(Gate::allows('filemanager.access'))
│
├── Services/
│   ├── FilesystemManager.php     Pembungkus Storage: list/exists/move/copy/put/delete + protectDirectory() + hardenAllDirectories()
│   ├── PathGuard.php             Guard path-traversal (nilai mentah + rawurldecode berulang, null byte, ADS, base folder)
│   ├── FileContentValidator.php  Magic-byte + scan polyglot + sanitasi SVG (dipanggil saat upload & crop)
│   ├── FilenameSanitizer.php     Bersihkan nama berkas/folder (tag, kutip, slash, transliterasi, spasi, titik akhir)
│   ├── ThumbnailGenerator.php    spatie/image, via file temp (disk-agnostik)
│   ├── ClipboardManager.php      Copy/cut/paste state di session
│   └── MimeTypeResolver.php      Pembungkus league/mime-type-detection
│
├── Support/
│   ├── FilemanagerConfig.php           Semua akses config('filemanager.*') terpusat di sini
│   └── ResolvesFilemanagerContext.php  Trait: baca slug modul dari session (untuk Gate)
│
├── Console/
│   └── HardenUploadsCommand.php   php artisan filemanager:harden
│
└── Exceptions/
    └── UnsafePathException.php

config/filemanager.php     Konfigurasi (dipublish ke config/ aplikasi host)
routes/filemanager.php     Definisi route (prefix + middleware dari config)
resources/
├── views/dialog.blade.php + partials/   UI (grid, toolbar, upload-panel)
├── lang/{id,en_EN}/filemanager.php       Terjemahan (namespace "filemanager::")
└── dist/                                 Aset frontend RFM asli (di-minify) — dipublish ke webroot
tests/                                    Feature (route) + Unit (service)
```

---

## 4. Alur request

### Buka dialog (browsing/picker)

```
Browser (iframe / popup / TinyMCE)
  → GET /filemanager?type=1&field_id=...&editor=tinymce
    → middleware: web, auth, EnsureFilemanagerAccess (Gate filemanager.access)
      → DialogController::show()
        - resolveSubdir()  : ambil ?fldr / session, lewat PathGuard, fallback ke base_folder
        - files->list()    : satu pass listContents, buang hidden + ekstensi tak diizinkan
        - sortEntries()
        - hitung apply function (apply_img/apply_link/apply_video/apply_multiple/apply)
      → view('filemanager::dialog')  — memuat resources/dist/js/include.js
```

### Aksi berkas (dari include.js)

```
include.js  → POST /filemanager/execute  {action, path, name, ...}
  → ExecuteController::handle()  match($action)
    - Gate::allows('filemanager.upload' | 'filemanager.delete', context)
    - safePath()  : '' → base_folder, PathGuard::assertSafe + assertInsideBaseFolder
    - FilenameSanitizer::sanitize()
    - cek isExtensionAllowed / isFilenameSafe / isHiddenFile / isHiddenExtension
    - FilesystemManager melakukan operasi (mirror ke thumbs disk bila perlu)
  → response BODY KOSONG = sukses ; BODY berisi teks = pesan error (di-alert)
```

### Upload

```
include.js (blueimp jQuery-File-Upload, maxChunkSize 2 MB)
  → POST /filemanager/upload   (file > 2 MB → beberapa request Content-Range)
    → UploadController::store()
      - Gate::authorize('filemanager.upload', context)
      - normalisasi folder, PathGuard
      - per berkas: handleUpload()
        - sanitasi nama, cek ekstensi (allowedExtensionsForType + blacklist + isFilenameSafe)
        - chunk?  → tulis ke storage_path('app/filemanager-chunks/<sha1>')
        - chunk terakhir / single  → finalize()
          - FileContentValidator::isValidUpload(localPath, ext)   ← satu-satunya titik cek byte
          - uniquePath() (anti-overwrite), disk->put(stream)
          - ThumbnailGenerator::make() bila gambar
      - balas JSON blueimp: {"files":[{name,size,url,thumbnailUrl,error?}]}
```

---

## 5. Komponen inti — tanggung jawab & catatan

### `Support\FilemanagerConfig`
Titik tunggal akses `config('filemanager.*')`. **Selalu tambahkan pembacaan
config baru sebagai method di sini**, jangan panggil `config()` tersebar.
Logika non-sepele: `baseFolder()` menyelaraskan `base_folder` config dengan
`root` disk (menangani kasus root disk sudah menunjuk ke sebagian path).

### `Services\PathGuard` (statis)
- `assertSafe($path)` — tolak `..`, `./`, null byte, karakter kontrol,
  Windows ADS (`::$DATA`), path absolut Windows. Dicek juga terhadap hasil
  `rawurldecode()` berulang (maks 10x) → `%2e%2e` tidak lolos.
- `assertInsideBaseFolder($path, $base)` — pastikan masih di dalam
  `base_folder`.
- **Dipanggil di setiap controller** sebelum menyentuh path. Jangan pernah
  operasikan path request tanpa lewat sini.

### `Services\FileContentValidator`
- `isValidUpload($localPath, $extension)` dipanggil dari
  `UploadController::finalize()` dan `ExecuteController::cropImage()`.
- Gambar raster → `getimagesize()` + scan polyglot (`<?php`, `<?`, `<script`,
  `<html>`, `<!doctype html>`) atas **seluruh** isi berkas.
- SVG → daftar-hitam pola XSS berbasis regex. **Catatan:** `svg` sudah
  dikeluarkan dari whitelist default; method ini tetap ada sebagai
  jaring pengaman kalau host menambahkannya kembali.
- Non-gambar → **wajib cocok `KNOWN_SIGNATURES`** (magic-byte). Ekstensi
  yang diizinkan tapi tidak punya entri signature → **selalu ditolak**.
  Menambah ekstensi baru ke `config` tanpa menambah signature di sini =
  ekstensi itu tidak akan pernah bisa diunggah.

### `Services\FilesystemManager`
- Semua operasi disk + mirror otomatis ke `thumbs_disk` (thumbnail ikut
  di-rename/hapus/pindah).
- `protectDirectory($path, $isThumbDisk)` — tulis `.htaccess` keras +
  `index.html`. Dipanggil dari `makeDirectory()`.
- `hardenAllDirectories()` — retrofit seluruh pohon; dipakai command
  `filemanager:harden`.
- **`.htaccess` hanya berlaku di Apache/LiteSpeed/OLS**. Nginx & OLS native
  butuh konfigurasi server (README §Keamanan Web Server).

### `Services\ThumbnailGenerator`
- `spatie/image`, driver **di-pin** lewat `config('filemanager.image_driver')`
  (default `gd`). Sengaja tidak membiarkan `spatie/image` auto-pilih Imagick
  — berkas unggahan tak boleh masuk ke coder/delegate ImageMagick.
- SVG/ICO **tidak** dibuatkan thumbnail (GD tak bisa rasterisasi) —
  `isImage()` mengembalikan `false`.
- Kegagalan thumbnail **tidak pernah** menggagalkan operasi induk
  (upload/rename sudah sukses saat thumbnail dibuat).

### `LaravelFilemanagerServiceProvider`
Gate default digerakkan sepenuhnya oleh `config('filemanager.permissions')`:
memanggil helper global `can($ability, $context)` milik OpenSID. Host bisa
menimpa `Gate::define('filemanager.access'|'upload'|'delete', ...)` sendiri
(last-wins) untuk sistem izin lain.

---

## 6. Model keamanan (berlapis)

| Lapis | Mekanisme | Berkas |
|---|---|---|
| Akses masuk | middleware `auth` + `EnsureFilemanagerAccess` (`Gate filemanager.access`) | `routes/filemanager.php`, `Middleware/` |
| Otorisasi aksi | `Gate filemanager.upload` (buat/ubah/rename/duplikasi/crop/teks), `Gate filemanager.delete` (hapus/copy-cut/paste) | controller |
| Konteks modul | slug modul di-*stamp* server-side lewat `filemanager_authorize()` dari Blade halaman pemanggil — **tidak pernah** dari input request | `helpers.php`, `Support/ResolvesFilemanagerContext` |
| Path traversal | `PathGuard` (raw + decode berulang, null byte, ADS) + batas `base_folder` | `Services/PathGuard` |
| Nama berkas | `FilenameSanitizer` + `isFilenameSafe()` (tolak ekstensi blacklist di segmen mana pun: `shell.php.jpg`) | `FilenameSanitizer`, `FilemanagerConfig` |
| Ekstensi | whitelist per kategori + `blacklisted_extensions` + `hidden_extensions` | `config/filemanager.php` |
| Isi berkas | `FileContentValidator` (magic-byte + polyglot + SVG) | `FileContentValidator` |
| Eksekusi skrip di folder unggah | `.htaccess` keras per folder (Apache/LS/OLS) + dok. Nginx/OLS + `filemanager:harden` | `FilesystemManager::protectDirectory()` |
| CSRF | route `execute`/`download` POST di bawah middleware `web` | Laravel |

**Prinsip:** tidak ada satu lapis pun yang cukup sendirian. Saat menambah
fitur, tanyakan lapis mana yang relevan dan pastikan semuanya kena.

---

## 7. Kontrak dengan frontend legacy (WAJIB DIPAHAMI)

`resources/dist/js/*` = kode RFM asli di-*minify*. **Jangan diedit** kecuali
terpaksa; kalau perlu logika baru, lebih baik di Blade/`<script>` inline pada
`dialog.blade.php`.

1. **URL di-hardcode `/filemanager/...`.** `include.js` dan plugin TinyMCE
   `responsivefilemanager` memanggil path absolut. Kalau
   `config('filemanager.route_prefix')` diubah, kedua berkas itu **harus
   di-patch ulang**. Lihat docblock `routes/filemanager.php`.

2. **Kontrak respons `ajax` & `execute`:**
   - **body kosong + HTTP 200** = sukses.
   - **body berisi teks + HTTP 200** = pesan error → di-`alert()` ke user.
   - **Jangan** balas 4xx/5xx untuk error yang seharusnya tampil ke user —
     `.done()` jQuery tidak akan jalan dan pesannya hilang diam-diam.
   - Helper `ok(string $body = '')` di controller sudah menerapkan ini.

3. **Kontrak respons `upload`:** JSON bentuk blueimp
   `{"files":[{name,size,url,thumbnailUrl,error?}]}`. Widget
   `jquery.fileupload-ui.js` merender apa adanya.

4. **Upload chunked:** bundle di-set `maxChunkSize: 2 MB`. Berkas > 2 MB
   datang sebagai deretan request `Content-Range`. `UploadController`
   menyusun ulang di `storage/app/filemanager-chunks/`. Validasi byte
   (`FileContentValidator`) **hanya bisa** di chunk terakhir — itulah kenapa
   `handleUpload()` cek nama dulu, `finalize()` cek isi.

5. **Endpoint mati yang masih dipanggil:** `include.js` masih punya
   `POST /filemanager/ajax?action=extract` (fitur extract dibuang). Backend
   membalas "wrong action" (body non-kosong) → user lihat alert, tidak ada
   kerusakan. Aman diabaikan.

6. **`ext_img` diekspos ke JS** di `dialog.blade.php` (`var ext_img = ...`)
   untuk filter preview gambar. `var image_editor = false` sengaja
   dipertahankan supaya guard di `include.js` tidak error.

---

## 8. Integrasi dengan aplikasi host

### 8.1 Disk

Host mendefinisikan disk di `config/filesystems.php`, lalu:

```php
// config/filemanager.php
'disk'        => 'filemanager',          // disk berkas
'thumbs_disk' => 'filemanager_thumbs',   // disk thumbnail (layout path dicermin)
'base_folder' => 'desa/upload/media',    // batas atas; '' = seluruh root disk
```

### 8.2 Izin

```php
// config/filemanager.php
'permissions' => [
    'access' => null,   // null = semua user terautentikasi boleh browse
    'upload' => 'u',     // dilempar ke can('u', $modulSlug) milik OpenSID
    'delete' => 'h',
],
```

Atau timpa sendiri di `AuthServiceProvider` host:
`Gate::define('filemanager.upload', fn ($user, $context) => ...)`.

### 8.3 Konteks modul (per-modul RBAC)

Panggil dari Blade halaman yang meng-*embed* picker — **bukan** dari
sesuatu yang digerakkan input request:

```blade
@php filemanager_authorize('surat') @endphp
```

Slug ini masuk session dan dipakai semua Gate berikutnya. Karena Blade itu
hanya jalan setelah kontrol akses halaman induk lolos, slug tidak bisa
dipalsukan lewat URL filemanager.

### 8.4 TinyMCE / CKEditor

Lihat README §Penggunaan. Intinya arahkan `file_picker_callback` /
`filebrowser*Url` ke `route('filemanager.dialog')` (`filemanager_base_url()`)
dengan query `type` (0 = file, 1 = image, 2 = link, 3 = media).

---

## 9. Konfigurasi — referensi cepat

| Kunci | Arti |
|---|---|
| `route_prefix` | Segmen URL (default `filemanager`). Ubah = patch ulang JS (§7.1). |
| `middleware` | Stack route, ditambah `EnsureFilemanagerAccess` otomatis. |
| `permissions.{access,upload,delete}` | Level `can()` atau `null` (selalu izinkan). |
| `disk`, `thumbs_disk` | Nama disk Flysystem. |
| `base_folder` | Batas direktori (relatif root disk). |
| `max_upload_size` | MB per berkas. |
| `extensions.{image,document,video,audio,archive}` | Whitelist. Tambah entri → **wajib** tambah signature di `FileContentValidator` (kecuali gambar). |
| `blacklisted_extensions` | Ditolak walau ada di whitelist; dicek di setiap segmen nama. |
| `hidden_files/folders/extensions` | Disembunyikan dari listing (`.htaccess`, `index.html`, dll). |
| `editable_text_extensions` + `text_editing_enabled` | Editor teks in-app (default mati). |
| `transliterate`, `convert_spaces`, `replace_with`, `lower_case` | Perilaku `FilenameSanitizer`. |
| `assets_path`, `assets_url_path` | Lokasi publish + URL aset frontend. |

---

## 10. Testing

```bash
composer install
vendor/bin/phpunit
```

- **`orchestra/testbench`** — tidak butuh aplikasi Laravel lengkap.
- `tests/TestCase.php` — set disk `filemanager`/`filemanager_thumbs` ke
  folder temp; `actingAsUser()` (stub `Authenticatable`, tanpa tabel users);
  `allowAllFilemanagerAbilities()` untuk test yang bukan tentang izin.
- **Unit** (`tests/Unit/`) — `PathGuard`, `FilenameSanitizer`,
  `FileContentValidator`, `FilemanagerConfig`, `ThumbnailGenerator`.
- **Feature** (`tests/Feature/`) — satu berkas per route + command harden.
- Saat menambah service, tambahkan unit test yang menembak kelasnya
  langsung; saat menambah action controller, tambahkan feature test yang
  memverifikasi **respons kontrak** (§7.2) dan efek disk (`Storage::fake`).

---

## 11. Playbook — menambah / mengubah fitur

### Menambah action `execute` baru
1. Tambah `case` di `ExecuteController::handle()`.
2. Panggil `Gate::allows()` yang sesuai (`upload` untuk tulis, `delete`
   untuk hapus/pindah).
3. `safePath()` untuk setiap path dari request.
4. `FilenameSanitizer::sanitize()` untuk setiap nama.
5. Validasi ekstensi (`isExtensionAllowed` + `isFilenameSafe` +
   `isHidden*`).
6. Kembalikan `$this->ok()` (sukses) / `$this->ok('pesan')` (error).
7. Feature test: kontrak respons + efek `Storage::fake`.
8. Kalau `include.js` belum memanggil action itu → tambah `<script>` di
   `dialog.blade.php` (jangan edit bundle minified).

### Menambah tipe berkas yang diizinkan
1. Tambah ke `config('filemanager.extensions.<kategori>')`.
2. **Kalau bukan gambar:** tambah entri di
   `FileContentValidator::KNOWN_SIGNATURES` — kalau tidak, upload-nya selalu
   ditolak. Kalau format itu tidak punya magic-byte andal, **jangan
   diizinkan**.
3. Pastikan tidak bentrok `blacklisted_extensions` / `hidden_extensions`.
4. Unit test di `FileContentValidatorTest`.

### Mengganti storage / menambah disk remote
Cukup ubah `disk`/`thumbs_disk` config. `FilesystemManager` sudah
disk-agnostik; `protectDirectory()` menelan error diam-diam pada disk
read-only/S3 (`.htaccess` tidak relevan di sana).

### Mengubah UI
Blade + `<style>`/`<script>` inline di `resources/views/`. Bundle JS di
`resources/dist/` diperlakukan sebagai vendor: ganti hanya saat upgrade
menyeluruh RFM (dan siap patch ulang URL).

---

## 12. Utang teknis & keterbatasan yang diketahui

| Hal | Dampak | Ide perbaikan |
|---|---|---|
| Bundle JS RFM di-minify & di-patch manual | Sulit di-*maintain*; URL hardcoded | Tulis ulang UI (Blade + Alpine/Vue) — proyek terpisah |
| Sanitasi SVG berbasis regex (bila di-*enable* lagi) | Bisa dilewati | Integrasikan `enshrined/svg-sanitize` + header CSP/attachment |
| `.htaccess` tidak berlaku di Nginx/OLS native | Butuh langkah manual admin | Generator config server, atau sajikan berkas non-gambar lewat controller |
| Validasi byte hanya di `finalize()` upload & crop | `create_file`/`save_text_file` hanya cek ekstensi teks | Cukup untuk saat ini (teks + `text_editing_enabled` default mati) |
| Gambar disimpan apa adanya (tidak di-*re-encode*) | Payload polyglot/EXIF hanya dijaring regex, bukan dihancurkan | Opsi config untuk re-encode raster lewat GD saat unggah |
| Chunk staging tidak dibersihkan otomatis kalau chunk terakhir tak pernah dikirim | Berkas ≤ `max_upload_size` per (folder,nama) menumpuk di `storage/app/filemanager-chunks/` | Command/schedule pembersih berkas staging tua |
| `directoryStats()` / `humanFileSize()` iterasi `allFiles` | Lambat di direktori sangat besar pada disk remote | Cache, atau batasi kedalaman |
| Field `version` di `composer.json` | Composer menyarankan pakai tag git saja | Hapus saat mulai rilis via tag Packagist |
| `hidden_files` menyertakan `index.htm`/`index.html` | Berkas HTML sah bernama itu ikut tersembunyi | Terima (trade-off kecil) |

---

## 13. Pemetaan ke isu OpenSID `premium`

| Isu | Judul | Status di package |
|---|---|---|
| #6937 | Migrasi RFM ke ekosistem Laravel | ✅ Package ini |
| #6976 | PHP Object Injection via `decrypt()` | ✅ Tidak ada `decrypt()` cookie |
| #6985 | APP_KEY bocor → RCE (16 lokasi) | ✅ bagian RFM; 15 lokasi lain = aplikasi host |
| #7008 | Proteksi eksekusi PHP folder unggah lemah | ✅ `.htaccess` keras + `filemanager:harden` + dok Nginx/OLS |
| #7011 | SVG → stored XSS | ✅ `svg` dihapus dari whitelist + di-blacklist |
| #7012 | `isPHP()` cakupan terbatas | ✅ magic-byte semua tipe + short tag `<?` + scan penuh |
| #7013 | Bug `$conf` ekstraksi TAR | ✅ fitur extract dibuang |
| #7014 | Decompression bomb `.gz`/`.tar` | ✅ fitur extract dibuang |
| #7016 | Reflected XSS `ajax_calls.php` | ✅ `cad_preview`/`media_preview`/gdocs/rawgit dibuang |
| #5724 | Blind SSRF | ✅ upload-via-URL tidak diimplementasikan |
| SEC-007 | ImageMagick command injection | ✅ `spatie/image`, tanpa shell |
| SEC-018 | Path traversal | ✅ `PathGuard` |

Detail per isu: lihat riwayat commit dan [CHANGELOG.md](CHANGELOG.md).
