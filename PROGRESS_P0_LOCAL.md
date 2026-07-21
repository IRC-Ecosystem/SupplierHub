# Progress P0 Lokal SupplierHub

**Tanggal pembaruan:** 21 Juli 2026  
**Ruang lingkup:** Perbaikan prioritas P0 yang dapat diselesaikan sepenuhnya di aplikasi SupplierHub tanpa integrasi SmartBank, Inventory, LogistiKita, atau API Gateway eksternal.

## Ringkasan

P0 lokal SupplierHub telah diselesaikan. Alur order dan pembayaran sekarang memisahkan pembuatan order, persetujuan supplier, payment request, dan verifikasi pembayaran sesuai prinsip PRD.

## Perbandingan Sebelum dan Sesudah

| Area | Sebelum dikerjakan | Sesudah dikerjakan | Status |
|---|---|---|---|
| Checkout | Checkout langsung dianggap selesai dan lunas | Checkout hanya membuat order `SUBMITTED/UNPAID` | Selesai |
| Persetujuan supplier | Approve langsung menjalankan pembayaran, mengurangi stok, dan menyelesaikan order | Supplier hanya menerima order dan mengubahnya menjadi `PENDING_PAYMENT/UNPAID` | Selesai |
| Payment request | Tidak dipisahkan dari proses approve | UMKM mengirim payment request melalui tombol **Bayar via SmartBank** | Selesai |
| Verifikasi SmartBank | SmartBank offline dapat berubah menjadi pembayaran sukses simulasi | Order tetap `PENDING` dan menunggu verifikasi SmartBank | Selesai |
| Status pembayaran | Status order dan pembayaran tercampur | `order.status` dan `payment_status` dipisahkan | Selesai |
| Status setelah pembayaran | Order langsung `COMPLETED` | Pembayaran terverifikasi menghasilkan status `PAID`, bukan `COMPLETED` | Selesai |
| Pembayaran gagal | Tidak memiliki alur gagal yang aman | Verifikasi gagal menghasilkan `PAYMENT_FAILED` dan stok tidak berubah | Selesai |
| Stok saat checkout | Stok dapat langsung dikurangi dalam alur checkout/payment | Stok tidak berubah saat checkout atau payment request | Selesai |
| Stok saat payment sukses | Pengurangan stok tidak sepenuhnya aman terhadap kegagalan parsial | Stok hanya berkurang setelah verifikasi sukses dan berada dalam transaksi database | Selesai |
| Pengurangan stok ganda | Retry berpotensi mengurangi stok lebih dari sekali | Idempotency dan unique constraint memastikan stok berkurang tepat satu kali | Selesai |
| Payment log ganda | Tidak ada unique constraint efek pembayaran | Payment log dilindungi indeks unik berdasarkan user, tipe, dan reference | Selesai |
| Idempotency checkout | Double-click/retry dapat membuat order baru | Checkout memakai idempotency key dan mengembalikan order yang sama saat replay | Selesai |
| Idempotency payment request | Request berulang dapat menghasilkan proses baru | Satu order hanya memiliki satu payment request aktif | Selesai |
| Database order | Kode menggunakan `resi_pengiriman`, tetapi setup awal tidak menyediakannya | Skema dan kode sudah sinkron melalui migration | Selesai |
| State machine | Hanya memakai `pending`, `approved`, `completed`, dan `rejected` | Mendukung `submitted`, `pending_payment`, `paid`, `payment_failed`, `processing`, `shipped`, `partially_received`, `received`, `completed`, `rejected`, dan `cancelled` | Selesai |
| Histori status | Hanya menyimpan status terakhir | Setiap transisi penting disimpan dalam `order_status_history` | Selesai |
| Histori reject | Reject tidak selalu tercatat sebagai histori | Reject mencatat aktor, status asal, status tujuan, alasan, dan waktu | Selesai |
| Payment attempt | Tidak ada tabel khusus percobaan pembayaran | Payment request disimpan dalam `payment_attempts` | Selesai |
| Transaksi database | Beberapa operasi stok, order, dan payment log terpisah | Operasi kritis menggunakan transaction, row locking, commit, dan rollback | Selesai |
| Pemeriksaan hasil stok | Hasil `reduceStock()` dapat diabaikan | Kegagalan pengurangan stok membatalkan seluruh transaksi | Selesai |
| Akses detail order | User login dapat mencoba membaca order berdasarkan ID/reference pihak lain | UMKM dan supplier hanya dapat membaca order miliknya | Selesai |
| Validasi supplier item | Supplier dapat dikirim bebas dari browser | Server memastikan seluruh item berasal dari supplier yang dipilih | Selesai |
| Validasi quantity | Validasi belum konsisten | Server menolak material/quantity yang tidak valid dan stok tidak cukup | Selesai |
| Perhitungan harga | Harga berpotensi bergantung pada data client | Harga selalu diambil ulang dari database | Selesai |
| Diskon | Nominal diskon dikirim dari browser dan dapat dimanipulasi | Server menghitung diskon membership dan membatasi diskon bundle | Selesai |
| Mutasi keranjang | Quantity dapat diubah melalui URL GET | Mutasi GET dinonaktifkan dan diganti POST | Selesai |
| CSRF | Aksi berbasis session tidak memiliki CSRF token | Seluruh mutation API utama memerlukan `X-CSRF-Token` | Selesai |
| Session login | Session ID tidak diregenerasi secara eksplisit | Session ID diregenerasi setelah login | Selesai |
| JWT query parameter | JWT dapat diterima melalui URL | JWT hanya diterima melalui Authorization header | Selesai |
| JWT secret | Secret ditulis langsung sebagai konfigurasi tetap | Secret dapat diatur melalui environment dan wajib di production | Selesai |
| Database config | Konfigurasi database hard-coded | Host, database, user, dan password mendukung environment variable | Selesai |
| Payment mode | Mode simulasi sukses menjadi perilaku default | Mode default adalah `pending` | Selesai |
| CORS | API memakai `Access-Control-Allow-Origin: *` | Wildcard CORS dihapus untuk penggunaan same-origin lokal | Selesai |
| HTTP status | Banyak error tetap dikembalikan sebagai HTTP 200 | Error memakai status `403`, `404`, `409`, atau `422` sesuai kasus | Selesai |
| Error code | Error hanya berupa message | Error utama memiliki kode `SUP_FORBIDDEN`, `SUP_NOT_FOUND`, `SUP_STATE_CONFLICT`, dan `SUP_VALIDATION_FAILED` | Selesai |
| API ganda | `api/` dan `rest-api/` memiliki write flow yang berbeda | Endpoint write REST lama dinonaktifkan dengan `410 Gone` agar tidak melewati state machine utama | Selesai |
| UI checkout | Modal menampilkan SmartBank/PIN seolah pembayaran langsung terjadi | Modal menjelaskan bahwa order dibuat sebelum pembayaran | Selesai |
| UI pembayaran | Menampilkan tombol Simulasi Berhasil/Gagal kepada pengguna | Hanya menampilkan tombol **Bayar via SmartBank** | Selesai |
| UI payment pending | Tidak ada status verifikasi yang jelas | Menampilkan **Menunggu Verifikasi SmartBank** | Selesai |
| Automated test | Hanya tersedia script pemeriksaan manual | Tersedia tes alur PRD dan keamanan lokal | Selesai |

## Alur Setelah P0 Lokal

```text
UMKM Checkout
    ↓
SUBMITTED / UNPAID
    ↓
Supplier menerima
    ↓
PENDING_PAYMENT / UNPAID
    ↓
UMKM memilih Bayar via SmartBank
    ↓
PENDING_PAYMENT / PENDING
    ↓
Menunggu verifikasi SmartBank
    ├── Sukses → PAID / stok berkurang tepat satu kali
    └── Gagal  → PAYMENT_FAILED / stok tidak berubah
```

## Migration Database

| Migration | Tujuan | Status |
|---|---|---|
| `sql/migrations/001_p0_transaction_safety.sql` | Sinkronisasi kolom transaksi, payment status, idempotency, dan unique constraint | Diterapkan |
| `sql/migrations/002_prd_payment_flow.sql` | State machine PRD, status history, dan payment attempts | Diterapkan |
| `sql/migrations/003_smartbank_pending_verification.sql` | Menjadikan payment request menunggu verifikasi SmartBank | Diterapkan |

## Hasil Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| PHP syntax check seluruh proyek | Lulus |
| Alur checkout sampai payment verification | `PRD_PAYMENT_FLOW_OK` |
| Tes HTTP/error code lokal | `LOCAL_SECURITY_OK` |
| CSRF melalui HTTP lokal | `LIVE_CSRF_OK` |
| Seluruh pemeriksaan P0 lokal | `ALL_LOCAL_P0_TESTS_OK` |
| Replay payment | Tidak menggandakan transaksi |
| Replay stock update | Tidak mengurangi stok dua kali |
| Payment gagal | Stok tetap |
| Manipulasi diskon dari client | Diabaikan oleh server |
| Akses order user lain | Ditolak |
| Data sementara pengujian | Dibersihkan |

## File Utama yang Ditambahkan

| File | Fungsi |
|---|---|
| `.env.example` | Contoh konfigurasi environment lokal/production |
| `helpers/ApiResponse.php` | Standardisasi HTTP status dan error code |
| `middleware/CsrfMiddleware.php` | Pembuatan dan validasi CSRF token |
| `tests/prd_payment_flow_test.php` | Pengujian state machine, payment, stok, dan idempotency |
| `tests/local_security_test.php` | Pengujian standard HTTP/error lokal |

## Di Luar P0 Lokal

Pekerjaan berikut belum dilakukan karena membutuhkan integrasi aplikasi lain:

| Integrasi | Pekerjaan berikutnya |
|---|---|
| SmartBank | Mengirim payment request nyata dan menerima callback/event bertanda tangan |
| Inventory Module | Membuat inventory receipt dan stock movement lintas aplikasi |
| LogistiKita | Shipment request, tracking, tarif, dan proof of delivery |
| API Gateway | Authentication enforcement, routing, request ID, dan rate limiting lintas aplikasi |
| Notification | Notifikasi perubahan order, payment, dan shipment |
| UMKM Insight | Analytics read model dan laporan procurement |

## Kesimpulan

P0 lokal dinyatakan **selesai**. SupplierHub sekarang memiliki fondasi transaksi lokal yang aman, idempotent, tenant-aware pada kepemilikan order, dan mengikuti alur pembayaran PRD. Tahap berikutnya dapat berfokus pada integrasi SmartBank tanpa mengubah alur utama pengguna.
