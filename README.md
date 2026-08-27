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

Setiap pembuatan folder baru di filemanager secara otomatis menyertakan berkas `.htaccess` dan `index.html` yang telah diperkuat (*hardened*) untuk memblokir eksekusi skrip PHP, CGI, ASP, shell, dan indeks direktori.

### 1. Apache & OpenLiteSpeed / LiteSpeed
Konfigurasi `.htaccess` otomatis aktif pada Apache dan OpenLiteSpeed (OLS) dengan aturan:
- `<FilesMatch>` dengan `Require all denied` & `Deny from all`
- `RewriteRule` penolakan instan `[F,L]`
- `RemoveHandler` dan `SetHandler None`
- `Options -ExecCGI -Indexes`
- `php_flag engine off`

### 2. Nginx
Pada Nginx (karena Nginx tidak membaca `.htaccess`), tambahkan blok aturan berikut pada konfigurasi `server` Nginx Anda untuk memblokir eksekusi skrip di folder berkas unggahan:

```nginx
# Blokir eksekusi skrip berbahaya di direktori kelola_file / storage
location ~* /(kelola_file|storage)/.*\.(php|phtml|php[0-9]|phar|sh|cgi|pl|py|exe|bat|cmd|env)$ {
    deny all;
    return 403;
}
```

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

