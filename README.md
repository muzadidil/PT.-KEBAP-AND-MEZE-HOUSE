# Kebap & Meze House — Kasir & Pembukuan

Aplikasi kasir untuk **PT. Kebap and Meze House**: halaman transaksi harian
di meja kasir, plus laporan penjualan, pengeluaran, dan neraca untuk pemilik.

Laravel 12 + **Filament 5** + Livewire. **Tanpa Node/npm/Vite.** Aset
Filament sudah prebuilt di dalam paketnya dan disalin ke `public/` oleh
`php artisan filament:assets` — perintah PHP biasa, bukan langkah build.
Gaya buatan sendiri ditulis sebagai CSS biasa di `public/css/pos.css` dan
ikut di-commit. Alasannya sama seperti proyek Regio dan Pembukuan BUM Desa:
shared hosting umumnya menonaktifkan `exec()` dan menolak binary native, jadi
tidak ada satu pun alat build yang bisa jalan di sana.

Dua bahasa: **Inggris (bawaan)** dan Indonesia. Pilihan bahasa menempel di
akun, bukan di sesi.

## Dua panel, dua peran

| Alamat | Panel | Siapa |
|---|---|---|
| `/` | Kasir | Kasir & admin |
| `/admin` | Backoffice | Admin saja |

Halaman kasir sengaja dipasang di **akar situs**, jadi membuka alamatnya
langsung mendarat di halaman transaksi tanpa satu klik pun lagi.

**Panel kasir** — Kasir (POS), Rekap Harian, Transaksi Saya.
**Panel admin** — Penjualan, Pengeluaran, Modal Pemilik, 10 laporan, dan
data induk (menu, kategori, pemasok, pemilik, pengguna).

## Yang sudah jalan

Seluruh 13 item di daftar tugas:

| # | Tugas | Di mana |
|---|---|---|
| — | Halaman transaksi kasir | `/` — daftar menu, keranjang, Cash/Cashless/Grab, struk cetak |
| — | Input harian Cash & Cashless | Kasir → Rekap Harian |
| 1 | Daily sales (cash, cashless, grab) | Laporan → Daily Sales |
| 2 | Weekly sales | Laporan → Weekly Sales |
| 3 | Monthly sales | Laporan → Monthly Sales |
| 4 | Yearly sales | Laporan → Yearly Sales |
| 5 | Expenses cash | Laporan → Cash Expenses |
| 6 | Transfer online | Laporan → Online Transfers |
| 7 | Data supplier | Data Induk → Suppliers |
| 8 | Salary own | Laporan → Salary |
| 9 | Tax | Laporan → Tax |
| 10 | Neraca | Laporan → Balance Sheet |
| 13 | Monthly expenses by owner, Aslan 60% / Leo 40% | Laporan → Owner Expenses |

Tambahan yang diperlukan supaya neraca bisa seimbang: **Modal Pemilik**
(setoran & penarikan) dan penanda **belum dibayar** pada pengeluaran.

## Menjalankan

```bash
composer install

cp .env.example .env
php artisan key:generate

# Basis data MySQL/MariaDB
mysql -u root -e "CREATE DATABASE kebap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed

php artisan filament:assets   # salin aset Filament ke public/ (PHP, bukan build)

php artisan test              # membuktikan invarian laporannya
php artisan serve
```

Akun bawaan seeder (**ganti sebelum dipakai sungguhan**):

| Email | Kata sandi | Peran | Bahasa |
|---|---|---|---|
| admin@kebaphouse.test | password | Administrator | Inggris |
| kasir@kebaphouse.test | password | Kasir | Indonesia |

Seeder juga mengisi Aslan (60%) & Leo (40%), 24 menu Turki dalam dua bahasa,
dan empat pemasok. Aman dijalankan ulang — semuanya `updateOrCreate`.

Identitas usaha untuk kepala struk diisi lewat `.env`:

```
BUSINESS_NAME="PT. Kebap and Meze House"
BUSINESS_ADDRESS="..."
BUSINESS_PHONE="..."
```

## Keputusan rancangan yang perlu diketahui

**Uang disimpan sebagai bilangan bulat rupiah, bukan desimal.** Restoran ini
tidak pernah menagih pecahan rupiah, dan bilangan bulat menutup seluruh
kemungkinan galat pembulatan. Di neraca, selisih satu rupiah berarti laporan
tidak seimbang.

**Tidak ada tabel saldo.** Semua angka laporan dihitung ulang dari `sales`,
`expenses`, dan `capital_entries` setiap kali diminta (`App\Support\Ledger`).
Saldo yang disimpan terpisah bisa menyimpang dari transaksinya, dan itu jenis
galat yang paling sulit dilacak.

**Transaksi kasir dan rekap harian tinggal di tabel yang sama.** Rekap harian
menulis baris `sales` dengan `source = quick`, satu per channel, tanpa
rincian item. Seluruh laporan karena itu membaca satu sumber, dan tidak ada
angka yang perlu dijumlahkan dari dua tempat. Menyimpan tanggal yang sama
**mengganti**, bukan menambah — kasir yang ragu boleh menyimpan ulang tanpa
takut terhitung dua kali.

**Keempat laporan berkala dijumlahkan dari satu primitif yang sama.**
Harian, mingguan, bulanan, dan tahunan semuanya digulung dari agregat harian
`Ledger::dailyTotals()` di PHP, bukan dari fungsi tanggal khas satu mesin
basis data. Karena itu laporan bulanan tidak mungkin berbeda dari laporan
harian untuk rentang yang sama, dan caranya tidak bergantung pada MySQL.

**Nama dan harga menu disalin ke baris transaksi.** Mengubah harga atau
menghapus menu dari daftar tidak mengubah struk yang sudah jadi.

**Lima laporan pengeluaran adalah satu tabel yang disaring berbeda.** Cash
Expenses, Online Transfers, Salary, Tax, dan Owner Expenses semuanya membaca
`expenses`. Satu pengeluaran karena itu tidak pernah tercatat di dua tempat.

**Modal pemilik bukan beban.** Setoran dan penarikan pemilik tinggal di
tabel sendiri: keduanya hanya memindahkan uang antara pemilik dan usaha,
tidak mengurangi laba. Kalau dicampur ke pengeluaran, laba akan terlihat
lebih kecil setiap kali pemilik menarik uang.

**Bahasa dipasang di grup middleware `web`, bukan hanya di panel.** Tiap
ketukan tombol di halaman kasir adalah permintaan Livewire ke
`/livewire/update`, dan permintaan itu tidak melewati middleware panel. Kalau
bahasanya hanya dipasang di panel, halaman tampil berbahasa Indonesia tapi
nama menu yang tersalin ke keranjang dan struk akan berbahasa Inggris.

## Neraca: mengapa selalu seimbang

Dua aturan yang menjaganya, keduanya dipaksakan di model, bukan hanya di
formulir:

- **Pengeluaran yang belum dibayar** tidak mengurangi kas maupun bank. Ia
  tetap mengurangi laba (kewajibannya sudah timbul) dan muncul sebagai utang.
- **Pengeluaran yang ditalangi pemilik** tidak mengurangi uang usaha sama
  sekali — uangnya dari kantong pemilik — melainkan menambah modal pemilik
  itu. Karena itu `Expense` memaksa `is_paid = true` setiap kali
  `paid_by_owner_id` terisi; kombinasi "ditalangi tapi belum dibayar" akan
  merusak keseimbangan.

Dengan dua aturan itu, **Aset = Kewajiban + Ekuitas** berlaku secara aljabar
untuk kombinasi transaksi apa pun, bukan kebetulan cocok di satu skenario.
Penurunannya ada di komentar `Ledger::balanceSheet()`, dan `BalanceSheetTest`
membuktikannya pada 40 kombinasi acak sekaligus.

## Pembagian 60/40

Tiap pemilik menanggung porsi yang disepakati, tapi yang benar-benar merogoh
kantong bisa siapa saja. Laporan Owner Expenses menghitung selisih antara
yang dibayar dan yang seharusnya ditanggung, sehingga terbaca siapa yang
berhak menerima dan siapa yang masih harus menyetor.

Porsinya **data, bukan angka mati di kode** — mengubah kesepakatan jadi 50/50
cukup lewat Data Induk → Owners, tanpa menyentuh satu baris kode pun.

Sisa pembagian rupiah dibagikan lewat `Money::split()` dengan metode sisa
terbesar, jadi jumlah tanggungan selalu **persis** sama dengan total yang
ditalangi dan selisih seluruh pemilik selalu berjumlah **nol** — tidak ada
rupiah yang hilang atau tercipta di pembulatan.

## Struktur

```
app/Support/Ledger.php              sumber tunggal semua angka laporan
app/Support/Money.php               format, pembacaan, dan pembagian rupiah
app/Filament/Cashier/Pages/         Kasir, Rekap Harian, Transaksi Saya
app/Filament/Admin/Pages/Reports/   10 laporan
app/Filament/Admin/Resources/       data induk + penjualan & pengeluaran
app/Enums/                          channel, cara bayar, kategori, peran
public/css/pos.css                  gaya buatan sendiri, tanpa langkah build
lang/en, lang/id                    istilah domain (chrome Filament bawaan)
tests/Feature/                      invarian laporan & neraca
```

## Tes

```bash
php artisan test
```

**96 tes, 807 asersi, semuanya lolos** (diverifikasi 20 September 2026).

Tes berjalan di **MySQL**, mesin yang sama dengan produksi, bukan SQLite
dalam memori — yang diuji di sini adalah angka laporan, dan perbedaan cara
MySQL dan SQLite memperlakukan pengelompokan dan tanggal justru bagian yang
tidak boleh lolos tanpa diuji. Basis data tesnya perlu dibuat sekali:

```bash
mysql -u root -e "CREATE DATABASE kebap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Yang diuji bukan sekadar "halaman terbuka", tapi hal-hal yang membuat laporan
salah kalau rusak:

- keempat laporan berkala menghasilkan total identik untuk rentang yang sama,
  per channel maupun keseluruhan
- hari tanpa penjualan tetap muncul sebagai nol, bukan hilang dari daftar
- rekap harian mengganti, bukan menambah, dan tidak menyentuh transaksi kasir
- uang tunai kurang dari total, keranjang kosong, dan diskon melebihi subtotal
  semuanya ditolak tanpa menghasilkan transaksi
- struk lama tidak berubah saat harga menu diubah
- neraca seimbang pada 40 kombinasi acak penjualan, pengeluaran, talangan
  pemilik, tagihan belum dibayar, dan setoran/penarikan modal
- selisih pembagian pemilik selalu berjumlah nol, termasuk pada nilai yang
  menyisakan rupiah ganjil
- pengeluaran talangan selalu berstatus lunas, walau dipaksa sebaliknya
- tiap halaman di kedua panel benar-benar terbuka, kasir tidak bisa masuk ke
  panel admin, dan akun nonaktif tidak bisa masuk ke mana pun
- halaman baru yang belum ikut diuji akan menggagalkan `PanelAccessTest`

## Yang belum dikerjakan

- **Ekspor ke .xlsx dan PDF.** Sekarang baru ada unduhan CSV di laporan
  penjualan dan tombol Cetak lewat dialog cetak peramban di semua laporan.
  Menambah PhpSpreadsheet dan dompdf seperti di proyek BUM Desa tidak butuh
  Node, jadi bisa disusulkan tanpa mengubah rancangan.
- **Lampiran foto nota** pada pengeluaran. Tabelnya sudah punya kolom
  catatan, tapi belum ada unggahan berkas.
- **Belum diuji di peramban sungguhan.** Seluruh 96 tes lolos dan tiap
  halaman terbukti mengembalikan HTTP 200 dengan aset yang tersaji benar,
  tapi tata letak halaman kasir di layar tablet meja kasir belum dilihat
  langsung. Jalankan `php artisan serve` dan buka `/` sebelum dipakai
  melayani tamu.
