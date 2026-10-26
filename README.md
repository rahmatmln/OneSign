# OneSign - Sistem Pengesahan Dokumen Digital (E-Signature) & Security Watermarking TIK PNJ

Aplikasi pengelolaan pengesahan dokumen PDF digital terpusat berbasis **PHP Native** murni dengan integrasi **Dynamic Security Watermarking**, **Canvas E-Signature**, dan **Audit Trail**, dibangun untuk Proyek Berbasis Pembelajaran (PBL) Terpadu Jurusan Teknik Informatika dan Komputer Politeknik Negeri Jakarta.

---

## Prasyarat Sistem (Prerequisites)

Pastikan komputer/laptop Anda telah terpasang:
- **Git** ([Download Git](https://git-scm.com/))
- **PHP** versi 8.1 atau lebih tinggi (Diuji pada PHP 8.2 XAMPP)
- **Composer** ([Download Composer](https://getcomposer.org/))
- **MySQL / MariaDB** (melalui XAMPP)

---

## Panduan Instalasi dari Awal (Setup Guide)

Ikuti langkah-langkah berikut secara berurutan untuk menjalankan proyek di komputer lokal Anda:

### 1. Clone Repository
Buka Terminal / Command Prompt / Git Bash, lalu jalankan:
```bash
git clone https://github.com/rahmatmln/onesign.git
cd onesign
```

---

### 2. Install Dependensi Composer
Jalankan perintah ini di dalam direktori proyek untuk mengunduh library yang dibutuhkan (termasuk `setasign/fpdf` dan `setasign/fpdi` untuk pemrosesan PDF & watermarking):
```bash
composer install
```
> Perintah ini akan otomatis membuat folder `vendor/` dan autoloader `vendor/autoload.php`.

---

### 3. Setup Basis Data MySQL
1. Buka aplikasi **XAMPP Control Panel**, lalu klik **Start** pada modul **MySQL** dan **Apache**.
2. Buat database baru bernama:
   ```text
   onesign_db
   ```
3. Import file [**`database.sql`**](database.sql) yang ada di root proyek ini:
   - **Opsi phpMyAdmin**: Buka browser ke `http://localhost/phpmyadmin`, pilih database `onesign_db`, klik menu **Import**, pilih file `database.sql`, lalu klik **Import / Go**.
   - **Opsi Terminal / Command Prompt**:
     ```bash
     mysql -u root -p onesign_db < database.sql
     ```
   File `database.sql` sudah otomatis membuat 5 tabel (`users`, `documents`, `document_signers`, `notifications`, `audit_logs`) lengkap dengan relasi foreign key, timestamps, dan data bawaan (*seeder*).

---

### 4. Sesuaikan Konfigurasi Database (Jika Diperlukan)
Buka file [**`config/database.php`**](config/database.php) dan sesuaikan pengaturan koneksi dengan MySQL di komputer Anda:
```php
$host     = '127.0.0.1';
$port     = '3306'; // Ubah ke 3307 jika MySQL XAMPP Anda menggunakan port 3307
$dbname   = 'onesign_db';
$username = 'root';
$password = '';     // Kosongkan jika default XAMPP tanpa password
```

Dan periksa [**`config/constants.php`**](config/constants.php) untuk memastikan URL dasar sesuai dengan nama folder di server lokal Anda:
```php
define('BASE_URL', 'http://localhost/onesign');
```

---

### 5. Menjalankan Aplikasi

Anda dapat memilih salah satu dari dua cara berikut:

#### Opsi A: Menggunakan Apache XAMPP (`htdocs`) — Direkomendasikan
1. Tempatkan folder proyek ini di dalam folder `htdocs` XAMPP, contoh:
   `C:\xampp\htdocs\onesign`
2. Pastikan servis Apache dan MySQL di XAMPP aktif.
3. Buka browser dan akses:
   ```
   http://localhost/onesign
   ```

#### Opsi B: Menggunakan PHP Built-in Server
Jalankan perintah ini di terminal:
```bash
php -S localhost:8000 index.php
```
> **Catatan:** Jika menggunakan port `8000`, sesuaikan `BASE_URL` pada file `config/constants.php` menjadi `http://localhost:8000`.

Buka browser Anda dan akses:
```
http://localhost:8000
```

---

## Kredensial Akun Bawaan (Default Login)

Gunakan salah satu akun berikut untuk masuk ke sistem:

| Peran (Role) | Email | Password | Keterangan Akses |
| :--- | :--- | :--- | :--- |
| **Administrator** | `rini.maharani@pnj.ac.id` | `password123` | **Full Access**: Pantau statistik dokumen, unduh arsip berkas, dan inspeksi riwayat aktivitas (*Audit Trail*) lengkap dengan filter aksi & waktu. |
| **Penandatangan** | `bagus.wijaya@pnj.ac.id` | `password123` | **Signer Access**: Pratinjau dokumen (*Sign Workspace*), atur ukuran & posisi kotak TTE, bubuhkan tanda tangan kanvas/paraf, atau tolak berkas dengan catatan revisi. |
| **Pemohon** | `rahmat.maulana@stu.pnj.ac.id` | `password123` | **Requester Access**: Ajukan dokumen PDF baru, tentukan dosen penandatangan, pantau status verifikasi berkas, dan unduh PDF yang telah disahkan. |

---

## Struktur Direktori

```
├── actions/            # Handler pemrosesan form (auth, upload, sign, download, preview)
├── config/             # Konfigurasi koneksi basis data & konstanta global
├── database/           # Skrip skema database awal
├── helpers/            # Helper otentikasi session & generator watermark PDF
├── uploads/            # Repositori penyimpanan berkas PDF & citra TTE
│   ├── originals/      # Berkas PDF asli yang diunggah pemohon
│   ├── signatures/     # Citra goresan TTE / paraf
│   └── signed/         # Berkas PDF bertanda tangan & ber-watermark
├── vendor/             # Dependensi Composer (diabaikan oleh Git)
├── views/              # Tampilan antarmuka pengguna (Layout, Pemohon, Penandatangan, Admin)
├── .gitignore          # File pengecualian upload Git
├── composer.json       # Definisi dependensi Composer (FPDF & FPDI)
├── database.sql        # Skrip DDL & DML database siap import
├── index.php           # Front Controller & URL router utama
└── README.md           # Panduan instalasi dan penggunaan
```

---

## Troubleshooting Umum

1. **Error: `Fatal error: Uncaught Error: Class "setasign\Fpdi\Fpdi" not found`**
   - **Solusi**: Jalankan perintah `composer install` di terminal untuk mengunduh library FPDF dan FPDI yang dibutuhkan.
2. **Error: `Connection refused` atau `Access denied for user`**
   - **Solusi**: Periksa kembali nomor port MySQL di `config/database.php` (default XAMPP adalah `3306`, namun beberapa instalasi menggunakan `3307`). Pastikan juga database `onesign_db` sudah dibuat dan diimport.
3. **Peringatan: Gagal Menyimpan Berkas PDF atau Tanda Tangan**
   - **Solusi**: Pastikan folder `uploads/originals/`, `uploads/signatures/`, dan `uploads/signed/` memiliki hak akses tulis (*write permissions*).
