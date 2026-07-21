# Progress Implementasi Lokal SupplierHub — P0 & P1

**Tanggal pembaruan:** 21 Juli 2026  
**Ruang lingkup:** Implementasi prioritas P0 dan P1 yang dapat diselesaikan sepenuhnya di SupplierHub tanpa mengaktifkan integrasi SmartBank, Inventory, LogistiKita, atau API Gateway eksternal.

## Ringkasan

P0 dan P1 lokal SupplierHub telah diselesaikan. P0 mengamankan order dan payment flow. P1 melanjutkan procurement dari estimasi supplier, fulfillment, pengiriman lokal, penerimaan penuh/sebagian, pembatalan, dispute, supplier master, performance, hingga transactional outbox.

## Ringkasan Status Prioritas

| Prioritas | Fokus | Status | Integrasi eksternal |
|---|---|---|---|
| P0 lokal | Keamanan transaksi, payment state, idempotency, authorization, dan histori status | Selesai | Belum diaktifkan |
| P1 lokal | Fulfillment, goods receipt, partial receipt, cancellation, dispute, supplier master, performance, dan outbox | Selesai | Belum diaktifkan |

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

## Di Luar Implementasi Lokal P0 & P1

Pekerjaan berikut belum dilakukan karena membutuhkan integrasi aplikasi lain:

| Integrasi | Pekerjaan berikutnya |
|---|---|
| SmartBank | Mengirim payment request nyata dan menerima callback/event bertanda tangan |
| Inventory Module | Membuat inventory receipt dan stock movement lintas aplikasi |
| LogistiKita | Shipment request, tracking, tarif, dan proof of delivery |
| API Gateway | Authentication enforcement, routing, request ID, dan rate limiting lintas aplikasi |
| Notification | Notifikasi perubahan order, payment, dan shipment |
| UMKM Insight | Analytics read model dan laporan procurement |

## Progress P1 Lokal

**Acuan PRD:** FR-SUP-002, FR-SUP-004, FR-SUP-005, FR-SUP-006, FR-SUP-007 dan fitur MVP SupplierHub.

P1 lokal menyelesaikan siklus procurement setelah pembayaran tanpa memalsukan integrasi eksternal. SupplierHub mencatat fakta bisnis dan transactional outbox; aplikasi pemilik domain eksternal tetap menjadi satu-satunya writer saat integrasi diaktifkan.

### Perbandingan Sebelum dan Sesudah P1

| Area | Sebelum P1 | Sesudah P1 lokal | FR/PRD | Status |
|---|---|---|---|---|
| Estimasi pemenuhan | Supplier hanya menerima/menolak | Supplier dapat mencatat estimasi sebelum konfirmasi | FR-SUP-002 | Selesai |
| Fulfillment | Order berhenti pada `PAID` | Mendukung `PAID -> PROCESSING -> SHIPPED` | FR-SUP-006 | Selesai |
| Referensi pengiriman | Belum menjadi bagian alur | Referensi dan waktu pengiriman lokal disimpan | Fitur MVP | Selesai |
| Goods receipt | Tidak tersedia | Receipt header dan item tersimpan dengan aktor/waktu | FR-SUP-004 | Selesai |
| Partial receipt | Status tersedia tanpa proses | Quantity dapat diterima bertahap per order item | FR-SUP-005 | Selesai |
| Full receipt | Tidak tersedia | Order menjadi `RECEIVED` setelah semua quantity diproses | FR-SUP-004 | Selesai |
| Barang ditolak | Tidak tercatat | Quantity ditolak dan alasannya disimpan | Partial receipt | Selesai |
| Retry receipt | Berisiko membuat efek ganda | Idempotency key mengembalikan receipt sebelumnya | NFR idempotency | Selesai |
| Over-receipt | Belum divalidasi | Total diterima/ditolak tidak boleh melebihi order | AC-SUP-003/004 | Selesai |
| Inventory ownership | Berpotensi mencampur katalog supplier dengan stok UMKM | Receipt berstatus `inventory_sync_status=pending`; stok Inventory tidak ditulis lokal | FR-INV-004 | Selesai lokal |
| Pembatalan unpaid | Belum memiliki policy | Order unpaid dapat dibatalkan dengan alasan | FR-SUP-007 | Selesai |
| Pembatalan payment pending | Masih berpotensi dibatalkan saat verifikasi | Ditolak hingga hasil SmartBank tersedia | Financial integrity | Selesai |
| Pembatalan paid | Belum dibedakan | Ditolak karena membutuhkan refund/reversal SmartBank | FR-SUP-007 | Menunggu integrasi |
| Dispute | Tidak tersedia | UMKM dapat membuka satu dispute aktif per order | Fitur MVP | Selesai dasar |
| Supplier master | Hanya data user | Profil usaha, kontak, alamat, lead time, dan status aktif | Fitur MVP | Selesai |
| Supplier performance | Tidak tersedia | Total order, completion rate, fulfillment time, dan dispute | Fitur MVP | Selesai |
| Transactional outbox | Tidak tersedia | Event domain disimpan atomik bersama transaksi | Arsitektur PRD | Selesai lokal |
| Automated test | Belum ada pengujian P1 | Receipt, replay, ownership, cancellation, dispute, stok, dan outbox diuji | Definition of Done | Selesai |

### State Machine P1 Lokal

```text
SUBMITTED
  |-- supplier reject  -> REJECTED
  |-- UMKM cancel      -> CANCELLED
  `-- supplier confirm -> PENDING_PAYMENT
                              |
                       SmartBank boundary
                              |
                            PAID
                              |
                         PROCESSING
                              |
                           SHIPPED
                         /         \
          PARTIALLY_RECEIVED     RECEIVED
```

Order dengan payment request `PENDING` tidak dapat dibatalkan. Order yang sudah `PAID` juga tidak dapat dibatalkan lokal karena membutuhkan refund/reversal dari SmartBank.

### Event Outbox P1 Lokal

| Event | Pemicu | Status publikasi |
|---|---|---|
| `SUPPLIER_ORDER_CONFIRMED` | Supplier menerima order | Pending integrasi |
| `SUPPLIER_ORDER_PAID` | Pembayaran SmartBank terverifikasi | Pending integrasi |
| `SUPPLIER_ORDER_PROCESSING` | Supplier mulai menyiapkan barang | Pending integrasi |
| `SUPPLIER_ORDER_SHIPPED` | Supplier mencatat pengiriman lokal | Pending integrasi |
| `GOODS_PARTIALLY_RECEIVED` | UMKM menerima sebagian | Pending integrasi |
| `RESTOCK_COMPLETED` | Seluruh quantity sudah diproses | Pending Inventory |
| `SUPPLIER_ORDER_CANCELLED` | Order unpaid dibatalkan | Pending integrasi |
| `PROCUREMENT_DISPUTE_OPENED` | UMKM membuka dispute | Pending integrasi |

### Migration dan File Utama P1

| File | Fungsi | Status |
|---|---|---|
| `sql/migrations/004_p1_local_procurement.sql` | Tabel receipt, dispute, supplier profile, outbox, dan kolom fulfillment | Diterapkan |
| `models/Procurement.php` | Aturan state transition, receipt, cancellation, dispute, performance, dan outbox | Selesai |
| `api/supplier_profile.php` | Pengelolaan supplier master lokal | Selesai |
| `views/supplier/profil.php` | UI profil dan performa supplier | Selesai |
| `tests/p1_local_procurement_test.php` | Pengujian aturan procurement P1 | Lulus |

### Hasil Verifikasi Gabungan

| Pemeriksaan | Hasil |
|---|---|
| P1 procurement test | `P1_LOCAL_PROCUREMENT_OK` |
| P0 payment regression | `PRD_PAYMENT_FLOW_OK` |
| Security regression | `LOCAL_SECURITY_OK` |
| Receipt replay | Tidak membuat receipt/event ganda |
| Over-receipt | Ditolak |
| Cross-owner receipt | Ditolak |
| Receipt lokal mengubah stok Inventory/katalog | Tidak |
| Pembatalan payment pending/paid | Ditolak |
| Browser UI Supplier dan UMKM | Lulus tanpa console error |
| PHP syntax | Lulus |

## Kesimpulan

P0 dan P1 lokal dinyatakan **selesai**. SupplierHub sekarang memiliki transaksi yang aman dan idempotent serta siklus procurement lokal sampai goods receipt. Tahap berikutnya adalah menghubungkan boundary yang sudah tersedia ke SmartBank, Inventory Module, dan LogistiKita tanpa memindahkan kepemilikan data ke SupplierHub.

## Portal Integrator & Swagger UI

| Area | Sebelum | Sesudah | Status |
|---|---|---|---|
| Role dokumentasi API | Tidak tersedia | Role `integrator` terpisah dari UMKM dan supplier | Selesai |
| Akun lokal | Tidak tersedia | `integrator@b2blink.com` dengan kredensial demo terdokumentasi | Selesai |
| Swagger | Hanya spec REST SupplierHub lama | OpenAPI gabungan SupplierHub, SmartBank, LogistiKita, UMKM Insight, dan Outbox | Selesai |
| Akses OpenAPI | File statis | Spec hanya dapat dibaca oleh session role integrator | Selesai |
| Status integrasi | Tidak tersedia | Endpoint kesiapan seluruh integrasi | Selesai |
| SmartBank callback | Hanya seam internal | Endpoint callback dengan signature HMAC | Integration-ready |
| LogistiKita event | Tidak tersedia | Endpoint shipment event dengan signature HMAC | Integration-ready |
| UMKM Insight | Tidak tersedia | Procurement summary read-only | Selesai lokal |
| Outbox monitoring | Hanya tabel database | Endpoint read-only untuk event terbaru | Selesai lokal |

### Endpoint Integrasi

| Aplikasi | Endpoint utama | Authorization |
|---|---|---|
| Integrator | `GET api/integrations.php?action=status` | JWT/session role integrator |
| Outbox | `GET api/integrations.php?action=outbox` | JWT/session role integrator |
| SmartBank | `POST api/integrations.php?action=smartbank_payment_callback` | `X-B2BLink-Signature` |
| LogistiKita | `POST api/integrations.php?action=logistics_shipment_event` | `X-B2BLink-Signature` |
| UMKM Insight | `GET api/integrations.php?action=insight_procurement_summary` | JWT/session role integrator |

### Verifikasi Portal Integrator

| Pemeriksaan | Hasil |
|---|---|
| Login dan redirect role integrator | Lulus |
| Portal integrator | HTTP 200 |
| OpenAPI melalui endpoint terlindungi | HTTP 200 |
| Akses langsung file YAML | HTTP 403 |
| Akun UMKM mengakses integration API | HTTP 403 |
| Webhook dengan signature salah | HTTP 401 |
| Automated test | `INTEGRATOR_PORTAL_OK` |

## P2 Lokal — Reliability & Operasional

| Area | Sebelum | Sesudah | Status |
|---|---|---|---|
| Outbox delivery | Event hanya tersimpan | Worker mock dengan retry, backoff, dan `dead_letter` | Selesai lokal |
| Event duplicate | Belum ada inbox dedup | `inbox_events` unik per source/event | Selesai lokal |
| Webhook audit | Tidak ada receipt | `webhook_receipts` menyimpan hash, status, dan error | Selesai lokal |
| Rekonsiliasi | Tidak ada deteksi timeout | Deteksi payment pending, shipment hilang, inventory sync tertunda | Selesai lokal |
| Refund | Belum ada state lokal | `refund_pending`, `refunded`, `refund_failed` + mock completion | Selesai lokal |
| Dispute supplier | Hanya bisa dibuka | Supplier dapat resolve/reject dengan catatan | Selesai lokal |
| Integrator monitoring | Hanya status/outbox | Tombol worker mock, rekonsiliasi, dan daftar issue | Selesai lokal |

Implementasi utama: `sql/migrations/006_p2_local_reliability.sql`, `services/ReliabilityService.php`, `services/MockIntegrationAdapter.php`, serta endpoint baru di `api/integrations.php` dan `api/orders.php`.

Verifikasi: `P2_LOCAL_RELIABILITY_OK` dan seluruh regression test P0/P1 tetap lulus.
