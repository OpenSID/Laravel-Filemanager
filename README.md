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

Setelah mempublikasikan file konfigurasi, Anda dapat menyesuaikan pengaturan pada `config/filemanager.php`:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    | Tentukan disk storage Laravel yang akan digunakan sebagai root filemanager.
    */
    'disk' => env('FILEMANAGER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Base Folder
    |--------------------------------------------------------------------------
    | Folder dasar di dalam disk tempat file akan dikelola.
    */
    'base_folder' => env('FILEMANAGER_BASE_FOLDER', 'desa/upload'),

    /*
    |--------------------------------------------------------------------------
    | Routes & Middleware
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'filemanager',
    'middleware' => ['web', 'auth'],
];
```

---

## 🚀 Penggunaan

### 1. Membuka Filemanager Standalone / Popup
Akses route dialog filemanager melalui iframe atau popup browser:

```html
<a href="/filemanager/dialog?type=0&field_id=target_input" class="btn btn-primary" target="_blank">
    Buka File Manager
</a>
```

### 2. Integrasi dengan TinyMCE 4 / 5 / 6
```javascript
tinymce.init({
    selector: '#editor',
    plugins: 'image link media',
    file_picker_callback: function (callback, value, meta) {
        var x = window.innerWidth || document.documentElement.clientWidth || document.getElementsByTagName('body')[0].clientWidth;
        var y = window.innerHeight || document.documentElement.clientHeight || document.getElementsByTagName('body')[0].clientHeight;

        var type = 0;
        if (meta.filetype === 'image') type = 1;
        if (meta.filetype === 'media') type = 3;

        var cmsURL = '/filemanager/dialog?type=' + type + '&editor=tinymce';

        tinymce.activeEditor.windowManager.openUrl({
            url: cmsURL,
            title: 'File Manager',
            width: x * 0.8,
            height: y * 0.8,
            onMessage: function (api, message) {
                callback(message.content);
            }
        });
    }
});
```

### 3. Integrasi dengan CKEditor 4
```javascript
CKEDITOR.replace('editor', {
    filebrowserBrowseUrl: '/filemanager/dialog?type=2&editor=ckeditor',
    filebrowserImageBrowseUrl: '/filemanager/dialog?type=1&editor=ckeditor',
    filebrowserUploadUrl: '/filemanager/upload'
});
```

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

#### Syarat `AllowOverride` (Apache)

`.htaccess` di folder unggahan memakai direktif `Options`, `FilesMatch`/`Require`, `RewriteRule`, `AddType`/`RemoveHandler`, `Header`, dan `php_flag`. Virtual host harus mengizinkannya, misalnya:

```apache
<Directory /var/www/opensid/desa/upload>
    AllowOverride FileInfo AuthConfig Limit Options=ExecCGI,Indexes
</Directory>
```

Bila `AllowOverride` **tidak** memuat `Options` (mis. hanya `AllowOverride FileInfo`), Apache menjawab **HTTP 500 untuk semua berkas** di folder tersebut, dan baris `Options` tidak bisa dibungkus `<IfModule>`. Pada server seperti itu matikan baris tersebut lalu tulis ulang `.htaccess`:

```dotenv
FILEMANAGER_HTACCESS_OPTIONS=false
```

```bash
php artisan filemanager:harden
```

Setelah itu nonaktifkan CGI & indeks direktori di level vhost (`Options -ExecCGI -Indexes` di blok `<Directory>`).

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

### 6. URL berkas & Host header

Untuk disk lokal, URL berkas dibangun ulang dari **host request saat ini** (`url()`), agar mengikuti domain/port yang dipakai admin. Host tersebut berasal dari header `Host` kiriman klien: tanpa middleware `TrustHosts`, Host yang dimanipulasi bisa membuat URL yang disisipkan TinyMCE ke artikel mengarah ke domain lain. Pilih salah satu:

- Aktifkan `TrustHosts` di aplikasi host (Laravel 11+: `->withMiddleware(fn ($m) => $m->trustHosts())` di `bootstrap/app.php`; Laravel 10: aktifkan `\App\Http\Middleware\TrustHosts::class` di `Kernel`), **atau**
- Pakai `APP_URL` sebagai sumber skema/host/port URL berkas:

```dotenv
APP_URL=https://desa-anda.id
FILEMANAGER_USE_APP_URL=true
```

### 7. Aset frontend sebagai symlink

`vendor:publish --tag=filemanager-assets` **menyalin** aset. Bila aset justru di-*symlink* ke `vendor/opensid/laravel-filemanager/resources/dist` (mis. lewat `ln -s` atau perintah publikasi aplikasi host yang membuat tautan):

- Apache harus mengizinkan `Options FollowSymLinks` (atau `SymLinksIfOwnerMatch` dengan pemilik berkas yang sama) pada folder aset — sekaligus berarti `AllowOverride`/vhost harus mengizinkan `Options` tersebut.
- `open_basedir` PHP (bila diset) harus mencakup direktori `vendor/` maupun direktori aset, agar PHP dapat membaca target tautan.
- Nginx: `disable_symlinks` harus `off` (bawaan) atau `if_not_owner` dengan pemilik yang sama.

Bila salah satu syarat tidak terpenuhi, gunakan penyalinan biasa (`vendor:publish`).

---

## 🧾 Jejak Audit & Ketahanan Storage

Setiap aksi yang mengubah berkas — unggah, hapus, ubah nama, tempel (*paste*), buat folder/berkas, duplikasi, crop, simpan teks — memicu event `OpenSID\LaravelFilemanager\Events\FilemanagerActionPerformed` (`action`, `paths`, `userId`, `ip`) dan menulis satu baris `Log::info('filemanager.<aksi>', ...)`. Aplikasi host dapat mendengarkan event tersebut untuk log aktivitasnya sendiri.

```dotenv
FILEMANAGER_AUDIT_LOG=true            # false = hanya event, tanpa Log::info
FILEMANAGER_AUDIT_LOG_CHANNEL=daily   # kosong = channel log bawaan aplikasi
```

Pada disk yang dikonfigurasi `'throw' => true` (S3, SFTP, dll.), kegagalan storage tidak lagi menjadi HTTP 500: galat dilaporkan lewat `report()` (log aplikasi) dan pengguna menerima pesan generik `storage_error` dengan HTTP 200, sesuai kontrak frontend lama.

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

