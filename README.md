# MCP PostgreSQL Chatbot & ERP Integration

Chatbot cerdas yang terintegrasi dengan database PostgreSQL dan Dokumentasi ERP. Chatbot ini dirancang untuk membantu analisis data bisnis dan memberikan panduan operasional ERP secara interaktif.

## 📋 Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
  - [Windows](#windows)
  - [Linux/Ubuntu](#linuxubuntu)
- [Konfigurasi Database](#konfigurasi-database)
- [User Default (Seeder)](#user-default-seeder)
- [Perintah Artisan Khusus](#perintah-artisan-khusus)
- [MCP Server](#mcp-server)
- [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
- [Admin Panel](#admin-panel)
- [Troubleshooting](#troubleshooting)

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| **Analisis Data Bisnis** | Terhubung langsung ke database untuk menjawab pertanyaan seputar penjualan, produk terlaris, retensi pelanggan, dll. |
| **Panduan ERP (ERP Guidance)** | Mengambil data dari [erp-guidance.online](https://erp-guidance.online) dan memberikan instruksi langkah demi langkah. |
| **Premium UI** | Tampilan modern dengan tema Glassmorphism. |
| **User Authentication** | Sistem Login dan Register untuk keamanan akses. |
| **Role-Based Access Control (RBAC)** | Pembatasan akses tabel database berdasarkan role user. |
| **Admin Dashboard** | Manajemen user, role, dan permissions melalui UI drag & drop. |
| **MCP Server Integration** | Integrasi dengan Model Context Protocol untuk AI assistance. |

---

## 💻 Persyaratan Sistem

- **PHP**: ^8.2
- **Database**: PostgreSQL 12+
- **Composer**: Latest version
- **Node.js**: ^18.x atau ^20.x
- **NPM**: Latest version

---

## 🚀 Instalasi

### Windows

#### 1. Clone Repository

```bash
cd "D:\MCP Versi Web"
git clone <repository-url> mcp-postgresql
cd mcp-postgresql
```

#### 2. Instalasi Dependensi

```bash
composer install
npm install
```

#### 3. Konfigurasi Environment

```bash
copy .env.example .env
php artisan key:generate
```

#### 4. Edit file `.env`

Sesuaikan konfigurasi database:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_penjualan
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

#### 5. Migrasi Database

```bash
php artisan migrate
```

#### 6. Seed Data Default

```bash
php artisan db:seed
```

#### 7. Build Frontend

```bash
npm run build
```

#### 8. Jalankan Development Server

```bash
php artisan serve
```

Akses aplikasi di: **http://localhost:8000**

---

### Linux/Ubuntu

#### 1. Instalasi Dependensi Sistem

```bash
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd unzip git curl nodejs npm -y
```

#### 2. Instalasi Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### 3. Clone Repository

```bash
cd /var/www
git clone <repository-url> mcp-postgresql
cd mcp-postgresql
```

#### 4. Instalasi Dependensi

```bash
composer install
npm install
npm run build
```

#### 5. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

#### 6. Edit file `.env`

Sesuaikan konfigurasi database dan API keys.

#### 7. Migrasi & Seed

```bash
php artisan migrate --seed
```

#### 8. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/mcp-postgresql
sudo chmod -R 755 /var/www/mcp-postgresql/storage
```

#### 9. Jalankan Server

**Development:**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Production (dengan Supervisor & Nginx/Apache):**
```bash
# Setup queue worker
sudo nano /etc/supervisor/conf.d/mcp-worker.conf
```

```ini
[program:mcp-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mcp-postgresql/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasuser=false
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mcp-postgresql/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mcp-worker:*
```

---

## 🗄️ Konfigurasi Database

### 1. Buat Database PostgreSQL

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE db_penjualan;
CREATE USER postgres WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE db_penjualan TO postgres;
\q
```

### 2. Jalankan Migrasi

```bash
php artisan migrate
```

### 3. Seed Data Default

```bash
php artisan db:seed
```

---

## 👤 User Default (Seeder)

Setelah menjalankan `php artisan db:seed`, berikut adalah user yang tersedia:

| Email | Password | Role | Deskripsi | Akses Tabel |
|-------|----------|------|-----------|-------------|
| `role1@example.com` | `password` | Data Entry (ID: 1) | Akses transaksi & pembeli | `transaksi`, `pembeli` |
| `role2@example.com` | `password` | Produk Analyst (ID: 2) | Akses transaksi & produk | `transaksi`, `produk` |
| `role3@example.com` | `password` | Super Admin (ID: 3) | Akses seluruh tabel | **Semua tabel** |

> ⚠️ **PENTING**: Segera ubah password default setelah instalasi pertama kali!

---

## 🛠️ Perintah Artisan Khusus

### ERP Documentation Crawler

Perintah ini digunakan untuk mengambil dokumentasi dari website ERP Guidance.

#### 1. Crawl Documentation

Mengambil semua konten dokumentasi dari [erp-guidance.online](https://erp-guidance.online):

```bash
php artisan app:crawl-documentation
```

**Proses yang dilakukan:**
1. Login ke ERP Guidance (menggunakan credentials yang dikonfigurasi)
2. Mengambil daftar semua halaman dokumentasi dari sitemap
3. Meng-crawl setiap halaman dan menyimpan konten (teks, gambar, video) ke database

**Output:**
- Data tersimpan di tabel `documentation`
- Progress ditampilkan di console

#### 2. Enrich Documentation

Menambahkan detail field formulir ke dokumentasi yang sudah di-crawl:

```bash
php artisan app:enrich-documentation
```

**Proses yang dilakukan:**
- Menambahkan informasi detail field formulir untuk halaman:
  - Order Pembelian
  - Permintaan Pembelian
  - Klaim Barang
- Informasi enrichment membantu AI memahami struktur formulir ERP

**Dokumentasi yang di-enrich:**
| Judul | Konten Tambahan |
|-------|-----------------|
| Order Pembelian | Field header, detail barang, ringkasan total |
| Permintaan Pembelian | Field header, tabel detail barang, tombol aksi |
| Klaim Barang | Field header, detail barang/TTB, faktur pembelian, nilai klaim |

#### 3. Setup ERP Guidance (PENTING untuk Server Baru)

Setelah deploy di server baru, **WAJIB** menjalankan:

```bash
# 1. Crawl data dari website
php artisan app:crawl-documentation

# 2. Enrich dengan detail formulir
php artisan app:enrich-documentation
```

> 📝 **Catatan**: Data dokumentasi ERP tidak disimpan di Git. Setiap server baru harus menjalankan crawler ini.

---

## 🔌 MCP Server

Project ini mengintegrasikan **Model Context Protocol (MCP)** untuk AI assistance.

### Konfigurasi API Keys

Edit file `.env` dan tambahkan API keys:

```env
# NVIDIA API Key
NVIDIA_API_KEY=nvapi-your-api-key-here

# OpenRouter API Key
OPENROUTER_API_KEY=sk-or-v1-your-api-key-here
```

### Endpoint MCP

MCP server tersedia di endpoint:

```
GET /mcp
```

### Konfigurasi MCP di Laravel

File konfigurasi MCP terdapat di `config/mcp.php` (jika ada) atau melalui service provider.

---

## 🔐 Role-Based Access Control (RBAC)

Sistem membatasi akses tabel database berdasarkan role user:

| Role ID | Nama Role | Deskripsi | Akses Tabel |
|---------|-----------|-----------|-------------|
| 1 | Data Entry | Akses transaksi & pembeli | `transaksi`, `pembeli` |
| 2 | Produk Analyst | Akses transaksi & produk | `transaksi`, `produk` |
| 3 | Super Admin | Akses seluruh tabel | **Semua tabel** |

### Cara Kerja RBAC

1. User login dengan credentials
2. Sistem mengecek role user
3. Chatbot hanya bisa mengakses tabel yang diizinkan untuk role tersebut
4. Admin dapat mengubah permissions melalui `/admin/roles`

---

## 👨‍💼 Admin Panel

Admin panel tersedia untuk manajemen user, role, dan permissions.

### Akses Admin Panel

1. Login dengan user Super Admin (`role3@example.com`)
2. Navigasi ke `/admin` atau klik menu **Admin**

### Fitur Admin Panel

#### Dashboard (`/admin`)
- Statistik users
- Jumlah roles
- Jumlah tabel yang dapat diakses

#### Manajemen User (`/admin/users`)
- Tambah user baru
- Edit user existing
- Hapus user
- Assign role ke user

#### Manajemen Role (`/admin/roles`)
- **Tambah Role**: Buat role baru dengan nama dan deskripsi
- **Edit Role**: Ubah nama dan deskripsi role existing
- **Hapus Role**: Hapus role (semua role dapat dihapus)
- **Drag & Drop Permissions**:
  - **Tabel Tersedia**: Tabel yang belum diizinkan untuk role tersebut
  - **Tabel Diizinkan**: Tabel yang dapat diakses oleh role
  - **Select All**: Pindahkan semua tabel ke "Diizinkan"
  - **Clear All**: Pindahkan semua tabel ke "Tersedia"
  - **Indikator Perubahan**: Warning jika ada perubahan yang belum disimpan

### Fitur Drag & Drop Role

1. **Pilih Role** dari daftar di sebelah kiri
2. **Drag tabel** dari "Tabel Tersedia" ke "Tabel Diizinkan"
3. **Klik "Simpan Akses"** untuk menyimpan perubahan
4. **Konfirmasi**: Preview perubahan sebelum menyimpan
5. **Indikator**: Border oranye muncul jika ada perubahan belum disimpan

> ⚠️ **Peringatan**: Jika Anda pindah role saat ada perubahan yang belum disimpan, sistem akan meminta konfirmasi.

---

## 🐛 Troubleshooting

### Error: `permission denied for table`

**Solusi**: Pastikan user database memiliki akses yang cukup:

```sql
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO postgres;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO postgres;
```

### Error: `class not found` setelah composer install

**Solusi**:

```bash
composer dump-autoload
```

### Error: `npm run build` gagal

**Solusi**:

```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Error: `SQLSTATE[08006]` - Connection refused

**Solusi**:
1. Pastikan PostgreSQL service berjalan:
   ```bash
   sudo systemctl status postgresql
   sudo systemctl start postgresql
   ```
2. Cek konfigurasi `.env` (host, port, username, password)
3. Pastikan PostgreSQL menerima koneksi TCP:
   ```bash
   sudo nano /etc/postgresql/14/main/postgresql.conf
   # Pastikan: listen_addresses = 'localhost'
   ```

### Crawler Gagal Login

**Solusi**:
1. Cek credentials di `app/Console/Commands/CrawlDocumentation.php`
2. Pastikan website ERP Guidance dapat diakses
3. Cek cookie/session handling di server

### Queue Worker Tidak Berjalan

**Solusi**:

```bash
# Cek status supervisor
sudo supervisorctl status

# Restart worker
sudo supervisorctl restart mcp-worker:*

# Cek log
tail -f /var/www/mcp-postgresql/storage/logs/laravel.log
```

### Session Error di Production

**Solusi**:

```bash
php artisan config:clear
php artisan cache:clear
php artisan session:table
php artisan migrate
```

---

## 📄 Lisensi

[MIT License](https://opensource.org/licenses/MIT)

---

## 📞 Support

Untuk pertanyaan atau bantuan, silakan hubungi tim development atau buat issue di repository.

---

**Last Updated**: March 2026
