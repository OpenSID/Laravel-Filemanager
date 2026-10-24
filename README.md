# OpenSID Laravel Filemanager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/opensid/laravel-filemanager.svg?style=flat-square)](https://packagist.org/packages/opensid/laravel-filemanager)
[![Total Downloads](https://img.shields.io/packagist/dt/opensid/laravel-filemanager.svg?style=flat-square)](https://packagist.org/packages/opensid/laravel-filemanager)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg?style=flat-square)](LICENSE)

Paket Laravel File Manager berbasis Laravel Storage / Flysystem yang diadaptasi dari Responsive Filemanager dengan antarmuka yang modern, cepat, responsif, dan terintegrasi mulus dengan ekosistem OpenSID serta Laravel 11/12+.

---

## 🌟 Fitur Utama

- 📁 **Integrasi Flysystem Penuh**: Menggunakan disk storage bawaan Laravel (`local`, `public`, `s3`, dll.).
- 🚀 **Navigasi Cepat & Ringan**: Pemuatan berkas instan dengan lookup hash map $O(1)$ dan script defer.
- 📄 **Pratinjau Dokumen PDF**: Mendukung pratinjau (*preview*) dokumen PDF langsung di dalam antarmuka dengan modal lightbox Featherlight.
- 🖼️ **Pengelolaan Gambar & Thumbnail**: Pratinjau gambar responsif, potong/edit gambar, dan pembuatan thumbnail otomatis menggunakan `spatie/image`.
- 🎨 **Antarmuka Modern & Rapi**: Tampilan slate clean, sudut rounded presisi (5px), floating tooltip bebas overflow, dan breadcrumb navigasi yang selaras.
- 🗂️ **Fitur Manajemen Berkas Lengkap**: Unggah berkas, buat folder, ubah nama, duplikasi berkas, salin/potong (*copy/cut/paste*), pengurutan (*sorting*), serta pencarian berkas secara realtime.
- 🔗 **Integrasi Rich Text Editor**: Kompatibel dengan TinyMCE, CKEditor, Summernote, maupun pemanggilan via popup mandiri (*standalone modal/iframe*).

---

## 📥 Instalasi

Pasang paket melalui Composer:

```bash
composer require opensid/laravel-filemanager
```

Publikasikan file konfigurasi dan aset (opsional/jika diperlukan):

```bash
# Publikasikan konfigurasi
php artisan vendor:publish --tag=filemanager-config

# Publikasikan asset frontend (CSS, JS, Icons)
php artisan vendor:publish --tag=filemanager-assets

# Publikasikan view template
php artisan vendor:publish --tag=filemanager-views
```

---

## ⚙️ Konfigurasi

Setelah mempublikasikan file konfigurasi, Anda dapat menyesuaikan pengaturan pada `config/filemanager.php`. Cuplikan di bawah memakai **nilai bawaan yang sebenarnya** dari paket (lihat berkas konfigurasinya untuk daftar lengkap):

```php
return [
    // URL dialog = /{route_prefix}, mis. /filemanager
    'route_prefix' => env('FILEMANAGER_ROUTE_PREFIX', 'filemanager'),
    'middleware' => ['web', 'auth'],

    // Level akses yang dicek lewat helper global can($akses, $modul) milik
    // aplikasi induk; null = selalu diizinkan.
    'permissions' => [
        'access' => null,
        'upload' => 'u',
        'delete' => 'h',
    ],

    // Disk Laravel (config/filesystems.php) untuk berkas & thumbnail.
    'disk' => env('FILEMANAGER_DISK', 'filemanager'),
    'thumbs_disk' => env('FILEMANAGER_THUMBS_DISK', 'filemanager_thumbs'),

    // Folder dasar di dalam disk. Kosong ('') = SELURUH root disk dapat
    // dijelajahi dan dikelola — sebaiknya isi, mis. 'desa/upload'.
    'base_folder' => env('FILEMANAGER_BASE_FOLDER', ''),

    'max_upload_size' => 8,        // MB per berkas (juga berlaku untuk unggahan chunk)
    'max_total_size' => false,     // false = tanpa batas

    'extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp'],
        'document' => ['doc', 'docx', 'pdf', 'xls', 'xlsx'],
        'video' => ['mp4', 'm4v', 'mov', 'webm', 'avi'],
        'audio' => ['mp3', 'm4a', 'ogg', 'wav'],
    ],

    'editable_text_extensions' => ['txt', 'log', 'md', 'csv'],
    'text_editing_enabled' => false,
];
```

| Kunci | Bawaan | Keterangan |
|---|---|---|
| `route_prefix` | `filemanager` | Prefix semua route. Dialog ada di `/{prefix}` (route `filemanager.dialog`), bukan `/{prefix}/dialog`. |
| `permissions` | `access => null`, `upload => 'u'`, `delete => 'h'` | Level akses untuk Gate `filemanager.access`/`upload`/`delete`, dicek dengan `can($level, $modul)` milik aplikasi induk terhadap slug modul yang dicap `filemanager_authorize('slug')`. `null` = selalu diizinkan. `access` juga diizinkan bila belum ada slug modul. Bila fungsi `can()` tidak ada, izin yang bernilai non-`null` ditolak. Ganti seluruhnya dengan `Gate::define('filemanager.*', ...)` sendiri bila perlu. |
| `disk` / `thumbs_disk` | `filemanager` / `filemanager_thumbs` | Nama disk di `config/filesystems.php`; **wajib** didefinisikan oleh aplikasi induk. |
| `base_folder` | `''` | Relatif terhadap root disk. Kosong berarti seluruh disk terbuka untuk dijelajah, diubah nama, dan dihapus. |
| `max_upload_size` | `8` | Batas MB per berkas; pada unggahan chunk, nilai `total` di header `Content-Range` juga diperiksa. |
| `extensions` | lihat di atas | Whitelist per kategori. Ekstensi non-gambar baru harus punya tanda tangan di `FileContentValidator`, kalau tidak unggahan ditolak. |
| `text_editing_enabled` | `false` | Mengaktifkan buat/ubah berkas teks (`create_file`, `save_text_file`), terbatas pada `editable_text_extensions`. |

---

## 🚀 Penggunaan

Route dialog bernama `filemanager.dialog` dan beralamat **`/filemanager`** (mengikuti `route_prefix`). Dari Blade gunakan `route('filemanager.dialog', [...])` atau helper `filemanager_base_url()`; jangan menulis `/filemanager/dialog` (404).

Parameter query yang dikenali: `type` (`1` gambar, `2` berkas/tautan, `3` video; `0` tanpa `field_id` hanya untuk menjelajah), `field_id`, `popup=1` (dibuka lewat `window.open`), `crossdomain=1`, `editor`, `multiple`, `relative_url`, `callback`.

Panggil `filemanager_authorize('slug-modul')` di view yang menyematkan filemanager agar pemeriksaan izin `upload`/`delete` memakai modul tersebut.

### 1. Membuka Filemanager Standalone / Popup
Saat berkas dipilih, URL-nya diisikan ke `<input id="target_input">` di jendela pembuka (`popup=1`) atau di halaman induk iframe (tanpa `popup`):

```blade
@php filemanager_authorize('artikel'); @endphp

<input type="text" id="target_input">
<a href="{{ route('filemanager.dialog', ['type' => 1, 'field_id' => 'target_input', 'popup' => 1]) }}"
   onclick="window.open(this.href, 'filemanager', 'width=900,height=600'); return false;"
   class="btn btn-primary">
    Buka File Manager
</a>
```

### 2. Integrasi dengan TinyMCE 5 / 6 / 7
Dialog mengirim hasil pilihan lewat `window.parent.postMessage({sender: 'responsivefilemanager', url: '...', field_id: null}, origin)` lalu menutup jendela TinyMCE sendiri. Pesan ini tidak memakai format `mceAction`, jadi tangkap dengan listener `message` di `window` (bukan `onMessage` milik `openUrl`) dan baca **`url`** — bukan `content`. Selalu periksa `origin` dan `sender`.

```blade
<script>
tinymce.init({
    selector: '#editor',
    plugins: 'image link media',
    file_picker_callback: function (callback, value, meta) {
        var type = 2; // berkas/tautan
        if (meta.filetype === 'image') type = 1;
        if (meta.filetype === 'media') type = 3;

        var cmsURL = @json(filemanager_base_url()) + '?type=' + type + '&editor=tinymce';

        function onFilemanagerMessage(event) {
            if (event.origin !== window.location.origin) return;
            if (!event.data || event.data.sender !== 'responsivefilemanager') return;

            window.removeEventListener('message', onFilemanagerMessage);
            callback(event.data.url);
        }

        window.addEventListener('message', onFilemanagerMessage);

        tinymce.activeEditor.windowManager.openUrl({
            url: cmsURL,
            title: 'File Manager',
            width: Math.round(window.innerWidth * 0.8),
            height: Math.round(window.innerHeight * 0.8),
            onClose: function () {
                // juga terpanggil saat filemanager menutup dialog sendiri;
                // ditunda agar pesan pilihan sempat diterima lebih dulu
                setTimeout(function () {
                    window.removeEventListener('message', onFilemanagerMessage);
                }, 0);
            }
        });
    }
});
</script>
```

Alternatifnya, plugin TinyMCE `responsivefilemanager` bawaan aplikasi dapat diarahkan ke dialog dengan `external_filemanager_path: @json(filemanager_base_url())`.

### 3. Integrasi dengan CKEditor 4
CKEditor 4 membuka dialog sebagai popup dan menambahkan `CKEditorFuncNum`; dengan `editor=ckeditor` filemanager mengembalikan URL lewat `window.opener.CKEDITOR.tools.callFunction()`.

```blade
<script>
CKEDITOR.replace('editor', {
    filebrowserBrowseUrl: @json(route('filemanager.dialog', ['type' => 2, 'editor' => 'ckeditor'])),
    filebrowserImageBrowseUrl: @json(route('filemanager.dialog', ['type' => 1, 'editor' => 'ckeditor']))
});
</script>
```

> **Keterbatasan:** jangan isi `filebrowserUploadUrl` dengan `/filemanager/upload`. Endpoint itu membalas JSON gaya blueimp (`{"files": [...]}`), bukan format yang diharapkan tab *Upload* CKEditor 4, serta membutuhkan token CSRF dan parameter `fldr`. Unggah berkas dari dalam dialog filemanager; bila tab *Upload* CKEditor dibutuhkan, buat endpoint adaptor sendiri di aplikasi induk.

---

## 🛡️ Keamanan Web Server (Apache, OpenLiteSpeed, LiteSpeed, Nginx)

Setiap pembuatan folder baru di filemanager secara otomatis menyertakan berkas `.htaccess` dan `index.html` yang telah diperkuat (*hardened*) untuk memblokir eksekusi skrip PHP, CGI, ASP, shell, HTML/SVG, dan indeks direktori. Berkas `.htaccess` **hanya satu lapis** — Nginx & OpenLiteSpeed mode native mengabaikannya, jadi konfigurasi server tetap wajib (lihat di bawah).

### 1. Instalasi lama / folder yang dibuat di luar package

`.htaccess` hanya ditulis saat folder dibuat **oleh package ini**. Untuk instalasi hasil migrasi dari `rfm/` lama, atau folder yang dibuat manual, jalankan sekali:

```bash
php artisan filemanager:harden
```

Perintah ini menimpa `.htaccess` + `index.html` di root disk dan **seluruh** sub-folder (disk utama & disk thumbnail). Aman diulang — selalu menimpa, tidak menambah.

### 2. Apache & OpenLiteSpeed / LiteSpeed
Konfigurasi `.htaccess` otomatis aktif dengan aturan:
- `<FilesMatch>` case-insensitive `(?i)` mencakup `php`, `php[0-9]*`, `phtml`, `phtm`, `phps`, `pht`, `phar`, `inc`, `module`, `htm(l)`, `svg`, shell/CGI — dengan `Require all denied` & `Deny from all`
- `RewriteRule` penolakan instan `[F,L]`
- `RemoveHandler` + `RemoveType` + `AddType text/plain`, `SetHandler None`, `ForceType text/plain`
- `Options -ExecCGI -Indexes`
- `php_flag engine off` (mod_php 5/7/8)
- `X-Content-Type-Options: nosniff` untuk semua berkas; `Content-Disposition: attachment` + `Content-Security-Policy` untuk `.svg`/`.xml`/`.html`

### 3. Nginx
Nginx tidak membaca `.htaccess`. Tambahkan pada blok `server` situs Anda, dan pastikan `location ~ \.php$` **hanya** meng-*cover* root aplikasi, bukan folder unggahan:

```nginx
# Tolak eksekusi & sniffing skrip di seluruh pohon unggahan
location ~* ^/(desa/upload|assets|storage)/ {
    add_header X-Content-Type-Options "nosniff" always;

    location ~* \.(php|php[0-9]*|phtml|phtm|phps|pht|phar|inc|module|pl|py|cgi|sh|bash|asp|aspx|jsp|exe|bat|cmd|env|htaccess|htpasswd)$ {
        deny all;
        return 403;
    }

    # SVG/HTML: paksa unduh, jangan render inline
    location ~* \.(svg|svgz|xml|x?html?)$ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Content-Disposition "attachment" always;
        add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; sandbox" always;
    }
}
```

### 4. OpenLiteSpeed (panel / mode native)
Selain `.htaccess`, di **Virtual Host → Context** tambahkan Static Context `/desa/upload/` dengan *Accessible: Yes* lalu **Rewrite** rule:

```
RewriteRule (?i)\.(php|php[0-9]*|phtml|phtm|phps|pht|phar|inc|module|pl|py|cgi|sh|asp|jsp|exe|bat|env)$ - [F,L]
```

### 5. Ekstensi berkas & SVG

Whitelist `extensions` di `config/filemanager.php` sengaja dipangkas ke format yang dipakai desa **dan** yang tanda-tangan (*magic byte*)-nya bisa diverifikasi oleh `FileContentValidator`. Menambah ekstensi non-gambar yang tidak punya *signature* akan otomatis **ditolak** saat unggah.

`svg` **tidak** ada di whitelist bawaan (risiko *stored XSS* — SVG adalah dokumen XML yang bisa memuat `<script>`/`<style>`/*event handler*). Jika benar-benar butuh SVG, tambahkan pustaka sanitasi khusus (`enshrined/svg-sanitize`) dan pastikan header `nosniff` + `Content-Disposition: attachment` aktif untuk folder media.

---

## 🤝 Berkontribusi

Kontribusi selalu terbuka untuk perbaikan bug, peningkatan performa, dan fitur baru!

1. Fork repositori ini
2. Buat branch fitur Anda (`git checkout -b feature/fitur-baru`)
3. Commit perubahan Anda (`git commit -m 'feat: tambah fitur baru'`)
4. Push ke branch Anda (`git push origin feature/fitur-baru`)
5. Ajukan Pull Request

---

## 📜 Lisensi

Paket ini dirilis di bawah lisensi [GPL-3.0-or-later](LICENSE). Hak Cipta &copy; Perkumpulan Desa Digital Terbuka (OpenDesa).

