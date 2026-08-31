# Changelog

Semua perubahan penting pada `opensid/laravel-filemanager` didokumentasikan di berkas ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
dan proyek ini memakai [Semantic Versioning](https://semver.org/lang/id/).

## [1.0.0] - 2026-08-31

Rilis stabil pertama. Penulisan ulang penuh Responsive Filemanager (RFM) v9.x
menjadi paket Laravel berbasis Storage/Flysystem — menutup rangkaian isu keamanan
RFM di OpenSID (`OpenSID/premium` #6937, #6976, #6985, #7008, #7011, #7012,
#7013, #7014, #7016).

### Ditambahkan

- Arsitektur paket Laravel: route + controller + middleware, seluruh operasi
  berkas lewat `Storage::disk()` (local, s3, ftp, sftp).
- Otorisasi berbasis `Gate` per-modul (`filemanager.access` / `.upload` /
  `.delete`), konteks modul di-*stamp* server-side via `filemanager_authorize()`
  — tidak ada lagi cookie terenkripsi / HMAC / `decrypt()` (menutup #6976, dan
  bagian RFM dari #6985).
- `FileContentValidator`: verifikasi *magic-byte* untuk **semua** tipe non-gambar
  yang diizinkan (pdf, doc/docx, xls/xlsx, zip, rar, gz, tar, mp3, ogg, wav, avi,
  webm, mp4, mov). Ekstensi tanpa tanda tangan yang bisa diverifikasi otomatis
  ditolak (#7012).
- Pemindaian polyglot menangkap PHP short tag polos `<?`, `<?php`, `<?=`,
  `<script`, `<!doctype html>`, `<html>`, `<body>` pada seluruh isi berkas
  (tanpa batas 4 MB) (#7012).
- Pengerasan direktori otomatis: `.htaccess` + `index.html` ditulis pada setiap
  pembuatan folder — pola `(?i)` mencakup `php[0-9]*`, `phtml`, `phtm`, `phps`,
  `pht`, `phar`, `inc`, `module`, `htm(l)`, `shtml`, shell/CGI; `RemoveHandler` +
  `RemoveType` + `AddType text/plain`, `SetHandler None` + `ForceType text/plain`,
  `Options -ExecCGI -Indexes`, `php_flag engine off`, header
  `X-Content-Type-Options: nosniff`, serta `Content-Disposition: attachment` +
  `Content-Security-Policy` untuk `.svg`/`.xml`/`.html` (#7008, #7011).
- Perintah `php artisan filemanager:harden` — menimpa `.htaccess`/`index.html`
  pengaman di root disk dan seluruh sub-folder untuk instalasi hasil migrasi
  dari `rfm/` lama (#7008).
- Dokumentasi konfigurasi tingkat web server untuk Nginx dan OpenLiteSpeed
  (mode native), yang mengabaikan `.htaccess` (#7008).
- `ThumbnailGenerator` berbasis `spatie/image` (GD/Imagick ext), disk-agnostik,
  tanpa `exec`/shell (menutup SEC-007 ImageMagick command injection).
- `PathGuard` — proteksi *path traversal* atas nilai mentah dan bentuk
  `rawurldecode()` berulang, null byte, karakter kontrol, Windows ADS
  (SEC-018).
- Fitur crop gambar interaktif (Cropper.js), preview PDF/gambar via lightbox
  Featherlight, tampilan grid/list, copy/cut/paste, sort & filter realtime.
- Dukungan PHP 8.1 – 8.4 dan Laravel 10 – 13.
- `laravel/pint` + `pint.json` (preset `laravel`) dan skrip composer
  `composer lint` / `composer lint:test` / `composer test`.

### Diubah

- Whitelist `extensions` dipangkas ke format yang dipakai desa **dan** yang
  tanda tangannya dapat diverifikasi:
  - image: `jpg, jpeg, png, gif, bmp, ico, webp`
  - document: `doc, docx, pdf, xls, xlsx`
  - video: `mp4, m4v, mov, webm, avi`
  - audio: `mp3, m4a, ogg, wav`
  - archive: **kosong** (dulu `zip, rar, gz, tar`) — aktifkan manual bila perlu.
- `FilenameSanitizer` kini hanya memangkas titik/spasi di **akhir** nama
  (`shell.php.` → `shell.php`), titik di depan dipertahankan untuk nama folder.

### Dihapus

- `svg` dari whitelist `extensions.image` — SVG adalah dokumen XML yang dapat
  memuat `<script>`/`<style>`/*event handler* dan sanitasi berbasis regex mudah
  dilewati. Tambahkan `enshrined/svg-sanitize` + header `nosniff`/`attachment`
  bila SVG benar-benar dibutuhkan (#7011).
- Fitur ekstraksi arsip (`zip`/`tar`/`gz`) — menutup *decompression bomb* dan
  bug validasi ekstensi TAR sekaligus (#7013, #7014).
- `cad_preview`, `media_preview` (jPlayer), preview Google Docs via `<iframe>`,
  dan dependensi eksternal `rawgit.com` — menutup *reflected XSS* di
  `ajax_calls.php` (#7016).
- `chmod` lewat filemanager.
- Upload via URL (tidak diimplementasikan) — menutup vektor SSRF (#5724).

- `image_driver` — driver `spatie/image` yang dipakai untuk thumbnail &
  crop (`gd` default / `imagick` / `vips`).

### Keamanan

- **Upload chunked**: potongan (*chunk*) kini wajib berurutan & kontigu
  (offset chunk = ukuran berkas staging saat ini), dan total byte staging
  dibatasi `min(total, max_upload_size)`. Sebelumnya klien bisa
  membanjiri `storage/app/filemanager-chunks/` jauh melebihi ukuran yang
  dideklarasikan → disk penuh / DoS.
- **Thumbnail & crop di-*pin* ke driver GD** (`config('filemanager.
  image_driver')`). `spatie/image` sebelumnya otomatis memilih Imagick
  bila `ext-imagick` ada — menjalankan berkas unggahan yang baru lolos
  validasi longgar lewat *coder/delegate* ImageMagick adalah permukaan di
  balik ImageTragick & RCE delegate Ghostscript.
- `crop_image`: `base64_decode()` kini mode *strict* (tolak karakter non-
  base64) dan menolak hasil kosong.
- Validasi konten `.bmp` kini lewat `getimagesize()` (sebelumnya hanya
  pemindaian polyglot).
- **Unduhan** kini juga menolak ekstensi di `blacklisted_extensions`, bukan
  hanya `hidden_extensions` — berkas warisan seperti `legacy.phar` tidak
  bisa diunduh.
- `editable_text_extensions` default dipangkas ke `txt, log, md, csv`.
  `html`/`htm`/`js`/`xml` dihilangkan: membuat berkas seperti itu di folder
  yang dilayani web (saat `text_editing_enabled`) = *stored XSS*.
- `archive` (`zip`/`rar`/`gz`/`tar`) **tidak lagi** ada di whitelist
  `extensions` default — tak berguna di pemilih media, hanya menambah
  permukaan serang (tetap bisa diaktifkan manual).
- `blacklisted_extensions` & `hidden_extensions` diperluas (`phtm`, `phps`,
  `pht`, `phpt`, `inc`, `module`, `hphp`, `svgz`, `xhtml`, `shtml`, `com`,
  `scr`, `bash`, `jsp`, `asp(x)`, `ini`, dll).
- `FilenameSanitizer` membatasi panjang nama ≤ 255 byte (ekstensi
  dipertahankan) agar nama sangat panjang ditolak rapi, bukan jadi exception
  penulisan filesystem.
- `EnsureFilemanagerAccess` kini menolak permintaan tanpa autentikasi (`401`)
  walau `auth` dilepas dari `config('filemanager.middleware')` — filemanager
  tidak pernah menjadi permukaan publik.

### Diperbaiki

- Parse error `return [` yang hilang di `tests/Unit/PathGuardTest.php` yang
  membuat seluruh test suite gagal dijalankan.
- `FilenameSanitizer` ikut menghapus titik di depan nama folder (`.git` →
  `git`) dan mengubah spasi di akhir menjadi `_` sebelum sempat dipangkas.

[1.0.0]: https://github.com/OpenSID/Laravel-Filemanager/releases/tag/v1.0.0
