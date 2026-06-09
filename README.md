# 🚀 SupplierHub & SmartBank B2B Ecosystem

Ekosistem B2B terintegrasi antara **SupplierHub B2B** (pengadaan bahan baku UMKM & Supplier) dengan **SmartBank** (Core Banking & Transaksi Keuangan) berbasis PHP MVC Native, REST API, dan MySQL sesuai petunjuk teknis **Aplikasi.docx**.

---

## 📌 Prasyarat Sistem
Sebelum menjalankan aplikasi, pastikan komputer Anda telah terinstal:
- **Laragon** (Sangat Direkomendasikan) atau **XAMPP**.
- **PHP v8.1+** (dengan ekstensi `pdo_mysql`, `curl`, `json` aktif).
- **MySQL Database Server**.
- Penampil web moderen (Chrome, Edge, dll).

---

## 📂 Lokasi Project di Laragon
Pindahkan kedua folder project ke direktori root Laragon Anda:
- **SupplierHub Web & REST API**: `C:\laragon\www\SupplierHub\`
- **SmartBank Mock API Server**: `C:\laragon\www\SmartBank\`

---

## 🗄️ Langkah Setup Database

### 1. Database SupplierHub (`supplierhub_db`)
- Buka MySQL manager Anda (phpMyAdmin / HeidiSQL).
- Buat database baru bernama `supplierhub_db`.
- Import file SQL dari: `C:\laragon\www\SupplierHub\sql\setup.sql`.

### 2. Database SmartBank (`smartbank_db`)
- Buat database baru bernama `smartbank_db`.
- Import file SQL dari: `C:\laragon\www\SmartBank\sql\setup.sql`.
- *(Atau jalankan skrip `C:\laragon\www\SmartBank\index.php` melalui server untuk aktivasi pertama).*

---

## 🌐 Cara Menjalankan Aplikasi & Membuka Web

### Opsi A: Menggunakan Laragon (Apache Localhost) - Sangat Direkomendasikan
Ketika Laragon aktif (`Start All`), server Apache akan secara otomatis mendeteksi folder virtual host:
1. **Membuka Web Portal SupplierHub (Frontend)**:
   Akses di browser: [http://localhost/SupplierHub](http://localhost/SupplierHub)
2. **Membuka Web Portal SmartBank (Mock)**:
   Akses di browser: [http://localhost/SmartBank](http://localhost/SmartBank)

### Opsi B: Menggunakan PHP Built-in Server (CLI)
Jika tidak menggunakan Apache Laragon, Anda bisa menjalankan server internal PHP secara terpisah:
1. **Jalankan SupplierHub**:
   Buka terminal di dalam `c:\laragon\www\SupplierHub` lalu jalankan:
   ```bash
   php -S localhost:8000
   ```
   Buka browser di: [http://localhost:8000](http://localhost:8000)
2. **Jalankan SmartBank**:
   Buka terminal di dalam `c:\laragon\www\SmartBank` lalu jalankan:
   ```bash
   php -S localhost:8001
   ```
   *Catatan: Jika port berbeda, silakan ubah `SMARTBANK_API_URL` di `C:\laragon\www\SupplierHub\config\constants.php` ke `http://localhost:8001`*.

---

## 🔌 Dokumentasi REST API

### 1. REST API SupplierHub
Menyediakan endpoint untuk integrasi sistem inventori dan order.
- **Base URL**: `http://localhost/SupplierHub/rest-api`
- **Route Utama**:
  - `GET /health` : Cek kesehatan server.
  - `POST /api/v1/auth/login` : Login user JWT.
  - `GET /api/v1/materials` : List bahan baku supplier.
  - `POST /api/v1/orders` : Checkout pembelian bahan baku.

### 2. REST API SmartBank
Menyediakan simulasi 8 endpoint transaksi keuangan terenkripsi (Aturan #6).
- **Base URL**: `http://localhost/SmartBank/api`
- **Route Utama**:
  - `POST /smartbank/registrasi_login_user` : Autentikasi nasabah bank.
  - `GET /smartbank/manajemen_saldo` : Informasi saldo dan riwayat debit/kredit.
  - `POST /smartbank/pembayaran_transaksi` : Gateway pembayaran otomatis belanja.
  - `GET /smartbank/ledger_transaksi` : Audit ledger transaksi bank (Read-only).

---

## 📝 Dokumentasi Swagger (OpenAPI)

Anda dapat membaca kontrak endpoint API secara visual langsung dari browser:

1. **Swagger SupplierHub B2B**:
   - URL Docs: [http://localhost/SupplierHub/rest-api/docs/](http://localhost/SupplierHub/rest-api/docs/)
   - File Spek: `C:\laragon\www\SupplierHub\rest-api\swagger.yaml`
2. **Swagger SmartBank**:
   - URL Docs: [http://localhost/SmartBank/docs/](http://localhost/SmartBank/docs/)
   - File Spek: `C:\laragon\www\SmartBank\swagger.yaml`

*(Pastikan Apache Laragon Anda menyala untuk membuka URL di atas secara lokal).*

---

## 📊 Dokumen Desain Sistem (Tugas Besar RPL 2)
Dokumen desain sistem yang komprehensif untuk tugas besar RPL 2 tersedia di dalam direktori proyek:
- **Lokasi File**: `C:\laragon\www\SupplierHub\dokumentasi\dokumen_desain.md`
- **Link Cepat**: [dokumen_desain.md](file:///c:/laragon/www/SupplierHub/dokumentasi/dokumen_desain.md)

Dokumen tersebut berisi 12 bagian lengkap:
1. Deskripsi Aplikasi B2B
2. Daftar Fitur / Use Case
3. Diagram Arsitektur (Format Graphviz DOT)
4. Alur Input-Proses-Output (IPO)
5. Kontrak API Endpoint Lengkap
6. Detail Integrasi Core Banking SmartBank
7. Skema Database & Relasi ERD (Format Graphviz DOT)
8. Mekanisme Keuangan & Formula Perhitungan Fee (Margin 3%, Bank Fee 1%)
9. Tata Letak UI Sederhana (Text Mockups)
10. Skenario Pengujian Sistem
11. Refleksi Kendala Teknis & Solusi (Race Condition, Simulator Offline)
12. Pembagian Kontribusi Tim

---

## 💳 Akun Simulasi untuk Uji Coba

Anda dapat masuk (Login) ke sistem SupplierHub menggunakan akun berikut:
- **Akun Klien (UMKM)**:
  - **Email**: `umkm@b2blink.com`
  - **Password**: `password123`
  - **Saldo Bank Awal**: Rp 10.000.000
- **Akun Admin (Supplier Gudang)**:
  - **Email**: `supplier@b2blink.com`
  - **Password**: `password123`
  - **Saldo Bank Awal**: Rp 5.000.000
