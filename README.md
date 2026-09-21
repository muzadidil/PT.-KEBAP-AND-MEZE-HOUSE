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

## Tiga peran, dua panel

| Peran | Panel | Yang dipegang |
|---|---|---|
| **Super Admin** | `/admin` | Mengatur: pengguna, menu, kategori, pemasok, barang belanja, cara bayar, pemilik, tampilan — dan **Penggajian**: karyawan, komponen gaji, slip gaji, gaji bulanan |
| **Admin** | `/admin` | Laporan: Pengeluaran, Modal Pemilik, Pembukuan Bulanan, 10 laporan |
| **Kasir** | `/` | Penjualan: Kasir (POS), Rekap Harian, Transaksi Saya |

Ketiganya tidak saling tumpang tindih. Super Admin tidak melihat laporan,
Admin tidak melihat pengaturan, dan **tidak ada menu Penjualan di panel
admin** — penjualan dipegang kasir, dan angkanya sampai ke Admin lewat
laporan. Admin juga tidak lagi membuka halaman kasir.

Menu yang bukan milik perannya tidak tampil, dan alamatnya ditolak dengan
halaman 403 yang menyebut siapa yang sedang masuk dan menunjuk ke halaman
yang boleh dibukanya. Pembagiannya ditulis sekali per halaman lewat
`App\Filament\Admin\Concerns\ForSuperAdmin` dan `ForAdmin`.

Halaman kasir sengaja dipasang di **akar situs**, jadi membuka alamatnya
langsung mendarat di halaman transaksi tanpa satu klik pun lagi. Siapa pun
boleh masuk lewat halaman masuk mana saja; setelah masuk, tiap orang dibawa
ke panelnya sendiri.

## Halaman masuk

Berpanel dua: sisi kiri slideshow latar bergambar, sisi kanan kartu masuk.
Di layar sempit sisi gambarnya disembunyikan — yang dicari orang di halaman
itu adalah kolom isian, bukan foto.

Logo, nama, tagline, dan daftar latarnya diatur pemilik lewat
**Super Admin → Data Induk → Tampilan**, tanpa menyentuh kode. Kalau belum diatur,
dipakai tiga latar bawaan di `public/img/login-*.svg`: gambar vektor
buatan sendiri bertema panggangan arang, meja meze, dan teh Turki. Sengaja
vektor, bukan foto stok — tidak ada urusan lisensi, tajam di layar seberapa
pun, dan masing-masing hanya beberapa kilobita. Pemilik tinggal menggantinya
dengan foto asli restoran lewat halaman Tampilan.

Unggahannya mendarat di `public/uploads/branding`, **bukan** lewat
`storage:link`: shared hosting sering menolak symlink, dan latar halaman
masuk harus bisa dilihat orang yang belum masuk sama sekali.

Tata letaknya diganti lewat `$layout`, jadi pembaruan Filament tidak pernah
bentrok dengan tampilan buatan sendiri di sini. Dari logika autentikasinya
hanya satu hal yang diubah: akun diterima di halaman masuk panel mana pun,
lalu `App\Http\Responses\LoginResponse` membawanya ke panelnya sendiri.
Tanpa itu, admin yang mengetik alamat utama situs ditolak dengan pesan
"kredensial tidak cocok" padahal kata sandinya benar.

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

Di atas itu ada **Pembukuan Bulanan**: pembukuan gaya berkas Excel bulanan
klien, lengkap dengan pengimpor berkasnya. Berdiri sendiri di samping
laporan kasir, tidak menggantikannya — [rinciannya di bawah](#pembukuan-bulanan).

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
| superadmin@kebaphouse.test | password | Super Admin | Indonesia |
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

## Pembukuan bulanan

Menu **Pembukuan Bulanan** menggantikan berkas Excel bulanan klien, tanpa
membuang konsep pelaporannya: definisi tiap kolom diambil dari rumus di dalam
sel berkas aslinya, bukan dikarang ulang.

| Halaman | Sheet asalnya |
|---|---|
| Buku Besar Bulanan | — (laporannya sendiri, unduh Excel & PDF) |
| Pemasukan Harian | `Income` |
| Belanja Tunai | `Expense` |
| Transfer Pemasok | `Supplier Transfer Payment` |
| Gaji | `Payroll` |
| Tagihan Belum Dibayar | `Outstanding INV` |
| Impor Excel | keenamnya sekaligus |

Semuanya milik **Admin**, kecuali **Gaji**: gaji per orang hanya boleh
dilihat Super Admin, jadi halamannya ada di grup Penggajian, dan unduhan
Excel buku besar hanya memuat total gaji per bulan. Admin tetap melihat
total gajinya di buku besar dan dasbor. Data induknya — **Barang Belanja**
dan **Cara Bayar** — ada di Data Induk milik **Super Admin**, bersama
pemasok dan menu.

Ini hasil memindahkan aplikasi laporan Zeytin (situs statis + Firestore di
repo `Zeytin`) ke sini. Ketiga halamannya punya padanan:

| Aplikasi Zeytin | Di sini |
|---|---|
| Dashboard | Dasbor Admin — kartu saldo global & angka bulan berjalan |
| Handler (tab per sheet + impor) | menu Pembukuan Bulanan |
| Reports (harian/bulanan/tahunan/kustom, PDF, Excel) | Buku Besar Bulanan |

PDF-nya mengikuti bentuk PDF aplikasi Zeytin: kop berwarna, ringkasan, lalu
rincian dengan baris total. Kop suratnya diatur di `config/zeytin.php`
(`letterhead`), dan jam "dibuat" ditulis dalam WITA. Unduhan Excel memuat
ringkasan, rincian harian/bulanan/tahunan, dan **catatan mentahnya** —
belanja, transfer, gaji, tagihan — yang baris totalnya sama dengan angka di
ringkasan.

Tabelnya **terpisah dari `sales` dan `expenses`**, bukan dilebur. Alasannya
bukan kerapian, tapi asal-usul: `sales` berisi transaksi per struk dari mesin
kasir, sedangkan sheet Income berisi satu baris rekap per hari per channel
yang ditulis tangan. Meleburnya berarti satu hari penjualan bisa terhitung
dua kali — sekali dari struk, sekali dari rekap — dan tidak ada cara
membedakannya setelah tercampur. Laporan kasir yang sudah ada tidak berubah
sedikit pun.

### Konsep yang dipertahankan dari berkas Excel

Diambil dari rumus di dalam selnya, bukan ditebak dari angkanya:

| Kolom | Rumus asli | Di aplikasi |
|---|---|---|
| Total Sales | `=SUM(D8:H8)` | cash + bni + grabFood + goFood + goPay |
| Supplier Cash | `=456560+D8` | saldo awal (`config/zeytin.php`) + tunai |
| Remaining Supplier Cash | `=J8-K8` | Supplier Cash − belanja hari itu |
| Grand Income | `=I8-D8` | Total Sales − tunai (nontunai) |
| Total baris belanja | `=((E*G)+I-H)` | (qty × price) + tax − disc |

**Petty cash sengaja tidak ikut Total Sales**, karena rumus aslinya memang
mulai dari kolom D dan melewati C. Perilaku itu dipertahankan, dan ditandai
`excl.` di buku besar supaya tidak dikira hilang karena salah hitung.

Pembagian tunai/nontunai mengikuti penanda `is_cash` di `config/zeytin.php`,
bukan nama channel yang ditulis di dalam rumus. Menambah channel tunai baru
karena itu cukup ditandai di konfigurasi.

### Tiga hal yang sengaja diperbaiki, atas persetujuan klien

1. **Grand Expense** di berkas aslinya menunjuk satu baris belanja
   (`Expense!J80`), bukan totalnya. Di sini dijumlahkan penuh.
2. **Transfer ke pemasok** tidak pernah ikut hitungan laba. Di sini ikut
   sebagai pengeluaran.
3. **Rentang belanja harian** di berkas aslinya diketik tangan
   (`Expense!J5:J8`, `J9:J12`, …) sehingga ada baris yang terlewat. Di sini
   pengelompokannya berdasarkan tanggal, jadi tidak ada yang bisa luput.

### Saldo global

```
Saldo global = penjualan − (belanja tunai + transfer pemasok + gaji)
             − tagihan yang belum dibayar
```

Sisa titipan belanja pemasok **sengaja tidak ikut ditambahkan**. Rumusnya
`saldo awal + tunai − belanja`, jadi uang tunai di dalamnya sudah terhitung
di Total Sales; menambahkannya membuat tunai dihitung dua kali. Angkanya
tetap tampil sebagai kartu tersendiri, jadi tidak ada yang hilang dari layar.

Sisa titipan adalah **keadaan hari terakhir yang tercatat**, bukan jumlah
antar hari — menjumlahkan saldo tiap hari tidak menghasilkan angka yang bisa
dibaca. Tagihan yang belum lunas **tidak dibatasi rentang**: utang bulan lalu
tetap utang hari ini.

Semuanya dihitung di satu tempat (`App\Support\Zeytin\DailyLedger`) dan
dipakai dasbor, buku besar, tabel harian/bulanan/tahunan, serta unduhan Excel
dan PDF. Baris mana yang masuk hitungan juga ditentukan di sana, jadi sheet
catatan mentah di Excel berisi persis baris yang dijumlahkan ringkasan.

### Mengimpor berkas Excel bulanan

**Pembukuan Bulanan → Impor Excel.** Pengimpornya tidak menganggap "baris 1
header, sisanya data", karena berkas klien tidak begitu: tanggal hanya ada di
baris pertama tiap kelompok, header muncul dua kali dengan nama kolom
berbeda, dan tiap sheet mulai di baris berlainan.

Ia mencari baris header berdasarkan nama kolom, mewarisi tanggal yang kosong,
dan **melaporkan sheet yang bentuknya berubah** alih-alih menebak. Galat diam
di pengimpor adalah cara tercepat merusak laporan tanpa ada yang sadar.

Tanggalnya dua bentuk sekaligus: serial Excel dan teks ketikan tangan
("31/08/2026"). Keduanya dibaca **hari dulu**, dan tanggal mustahil
("31/13/2026") ditolak, bukan digeser ke bulan berikutnya.

Tabel hasil impor menampilkan **rentang tanggal yang terbaca tiap sheet**.
Itu bukan hiasan: salah ketik tahun di satu sel — di berkas Agustus ada satu
baris tertulis `18/8/2028` — tidak mengubah jumlah baris dan tidak
memunculkan galat apa pun. Yang berubah cuma ujung rentangnya.

Bulan untuk sheet Payroll **ditanyakan, tidak ditebak** — judulnya di berkas
asli berupa kalimat bebas yang tidak dijamin bentuknya.

Berkas yang diunggah **tidak disimpan di server**: isinya nomor rekening
pemasok dan gaji tiap karyawan, dan tidak ada gunanya menumpuk salinannya.
Seluruh berkas masuk dalam satu transaksi, jadi tidak ada keadaan "separuh
bulan terimpor" yang harus dibereskan tanpa tahu separuh mana.

Tombol **Unduh template** membangun berkas contoh berisi keenam sheet dengan
judul kolom yang benar — diturunkan dari daftar kolom yang sama dipakai
pembacanya, bukan berkas contoh yang disimpan di repo. Berkas yang disimpan
akan diam-diam ketinggalan zaman begitu satu kolom berubah, dan template yang
judulnya meleset sedikit saja akan ditolak saat diunggah.

### Mengimpor ulang berkas yang sama itu aman

Tiap baris impor diberi nomor yang **diturunkan dari isinya**, bukan nomor
acak. Akibatnya impor kedua atas berkas yang sama menimpa baris yang sama,
tidak menambah baris baru.

Kalau satu sel dibetulkan di Excel lalu berkasnya diimpor ulang, baris versi
lamanya dibuang — bukan ditinggalkan berdampingan dengan yang baru. Yang
dibuang hanya baris bertanda **Excel** dan hanya di dalam rentang tanggal
berkas itu.

**Apa yang diketik lewat halaman tidak pernah disentuh impor.** Tiap baris
menampilkan penandanya di kolom "Diisi oleh", dan baris hasil impor yang Anda
betulkan lewat halaman berubah jadi ketikan Anda — impor bulan depan tidak
akan mengembalikannya ke angka yang salah.

Pemasok dari sheet `Supplier Database` **tidak menggandakan** daftar pemasok
yang sudah ada: nama yang sudah terdaftar hanya diisi kolom rekening dan cara
bayarnya. Nama, narahubung, telepon, dan alamat yang sudah diketik tidak
diambil alih berkas Excel.

Diuji dengan berkas Agustus klien yang sebenarnya: 230 baris belanja, dan
mengimpornya berulang kali tetap 230.

## Penggajian

Menu **Penggajian** (Super Admin saja) meniru aplikasi Slip Gaji di repo
`slip_gaji_cv_alfarisy`:

| Halaman | Padanan di aplikasi Slip Gaji |
|---|---|
| Karyawan | tab Karyawan — nama, NIK, jabatan, gaji pokok, ditambah bagian (Front/Kitchen) |
| Komponen Gaji | Item Pendapatan & Item Potongan — nominal bawaan dan tanda "fix" |
| Slip Gaji | tab Buat Slip + Riwayat — pratinjau kertas slip berubah seiring isian, unduh PDF |
| Slip Gaji → Kop & penandatangan | tab Pengaturan — nama, alamat, kota, penandatangan |

Logo kop tidak diunggah terpisah: dipakai logo usaha dari **Data Induk →
Tampilan**. PDF slipnya meniru bentuk aslinya — kop bergaris ganda,
nomor `SG/2026/IX/001`, tabel Pendapatan | Potongan, Gaji Bersih, terbilang,
dan tanda tangan — dan **selalu berbahasa Indonesia**, karena diserahkan
ke karyawan. Pratinjau dan PDF merender satu templat yang sama
(`resources/views/payslips/paper.blade.php`).

Yang dibuat lebih ketat daripada aslinya, dan dijaga model, bukan cuma
formulir:

- **Nomor slip tidak pernah kembar.** Aslinya menghitung jumlah slip di
  bulan itu, jadi menghapus satu slip membuat slip berikutnya memakai nomor
  yang sudah ada. Di sini diambil dari nomor terbesar.
- **Satu karyawan satu slip per bulan**, dijaga formulir dan indeks unik.
- **Nominal item "fix" dikunci** juga saat menyimpan, bukan hanya di layar.
- **Karyawan keluar dinonaktifkan, bukan dihapus**, dan nama, NIK, serta
  jabatan disalin ke slip — mengubah data karyawan tidak mengubah slip lama.

Slip gaji **belum** masuk ke buku besar; menyambungkannya ke Gaji bulanan
dikerjakan belakangan, bersama keputusan soal gaji yang sekarang bisa
tercatat di dua tempat (Pengeluaran kategori Gaji dan Gaji bulanan).

## Struktur

```
app/Support/Ledger.php              sumber tunggal semua angka laporan
app/Support/Money.php               format, pembacaan, dan pembagian rupiah
app/Support/Branding.php            logo & latar halaman masuk
app/Filament/Auth/Login.php         halaman masuk berpanel dua
app/Filament/Cashier/Pages/         Kasir, Rekap Harian, Transaksi Saya
app/Filament/Admin/Pages/Reports/   10 laporan
app/Filament/Admin/Resources/       data induk + pengeluaran
app/Filament/Admin/Concerns/        pembagian menu: ForSuperAdmin, ForAdmin
app/Filament/Admin/Widgets/         dasbor per peran
app/Http/Responses/LoginResponse    tujuan setelah masuk mengikuti peran
app/Enums/                          channel, cara bayar, kategori, peran

config/zeytin.php                   channel, saldo awal titipan, kop PDF
app/Support/Zeytin/DailyLedger.php  sumber tunggal angka pembukuan bulanan
app/Support/Zeytin/PeriodExport.php unduhan Excel buku besar
app/Support/Zeytin/PeriodPdf.php    unduhan PDF buku besar (dompdf)
resources/views/pdf/                templat PDF
app/Support/Zeytin/Workbook/        pembaca & template berkas Excel klien

app/Filament/Admin/Resources/Employees, PayComponents, Payslips   penggajian
app/Support/Payroll/                PDF slip & kop slip
app/Support/Terbilang.php           angka rupiah dalam kata-kata
resources/views/payslips/paper      kertas slip, dipakai pratinjau & PDF
app/Filament/Admin/Pages/Zeytin/    buku besar bulanan + impor Excel
app/Filament/Admin/Resources/Zeytin/  keenam sheet + dua data induknya

public/css/pos.css                  gaya buatan sendiri, tanpa langkah build
public/css/auth.css                 gaya halaman masuk
public/img/login-*.svg              tiga latar bawaan, gambar vektor sendiri
lang/en, lang/id                    istilah domain (chrome Filament bawaan)
tests/Feature/                      invarian laporan & neraca
```

## Tes

```bash
php artisan test
```

**240 tes, 1.168 asersi, semuanya lolos** (diverifikasi 21 September 2026).

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
- tiap halaman benar-benar terbuka untuk perannya dan tertutup untuk dua
  peran lainnya — menunya tidak tampil, alamatnya ditolak
- kasir tidak bisa masuk ke panel admin, Admin dan Super Admin tidak bisa
  masuk ke halaman kasir, dan akun nonaktif tidak bisa masuk ke mana pun
- admin yang masuk di alamat utama dibawa ke backoffice, bukan ditolak;
  alamat tujuan di panel lain diabaikan supaya tidak mendarat di 403
- halaman baru yang belum diputuskan milik peran mana akan menggagalkan
  `PanelAccessTest`
- halaman masuk tidak pernah tampil kosong: belum diatur, semua slide
  dihapus, atau berkas gambarnya hilang dari disk, semuanya jatuh ke bawaan
- tiap kunci bahasa Inggris punya terjemahan Indonesianya, dan sebaliknya

Dan untuk pembukuan bulanan:

- Total Sales benar-benar melewati petty cash; tunai/nontunai ditentukan
  penanda `is_cash`, bukan nama channel
- sisa titipan adalah keadaan hari terakhir, bukan jumlah antar hari
- saldo global tidak menghitung uang tunai dua kali
- tagihan yang sudah lunas tidak dihitung sebagai utang, apa pun ejaan
  statusnya ("PAID", "Sudah lunas", "settled")
- rekap bulanan dan tahunan berjumlah sama dengan harian, per channel maupun
  keseluruhan
- serial tanggal Excel 46235 dibaca sebagai 1 Agustus 2026, "31/08/2026"
  dibaca hari-dulu, dan "31/13/2026" ditolak — bukan digeser ke bulan
  berikutnya
- mengimpor berkas yang sama dua kali tidak menggandakan apa pun, sedangkan
  dua baris yang benar-benar kembar dalam satu hari tetap dua baris
- membetulkan satu sel lalu mengimpor ulang membuang baris versi lamanya
- baris yang diketik orang tidak pernah disentuh impor
- sheet yang hilang atau judul kolomnya tidak dikenali dilaporkan, bukan
  ditebak posisi kolomnya
- template kosong yang diisi lalu diunggah terbaca utuh oleh pembacanya —
  jadi judul kolom yang berubah tanpa templatenya ikut berubah akan
  menggagalkan tes, bukan menggagalkan penggunanya
- PDF memuat angka ringkasan yang sama dengan layar, dan laporan harian
  membuang hari kosong tanpa mengubah totalnya
- baris Total di tiap sheet catatan mentah Excel sama dengan angka di
  ringkasan, dan tagihan yang sudah lunas tidak ikut; gaji hanya totalnya

Dan untuk penggajian:

- nomor slip berurut per bulan dan tidak kembar walau ada slip yang dihapus
- item "fix" selalu bernominal bawaannya, apa pun yang diketik
- data karyawan yang diubah belakangan tidak mengubah slip lama
- slip kedua untuk karyawan dan bulan yang sama ditolak
- terbilang benar untuk sebelas, seratus, seribu, juta, dan miliar
- PDF slip berbahasa Indonesia walau aplikasinya berbahasa Inggris

## Yang belum dikerjakan

- **Sheet Payroll diimpor sebagian** — nama, gaji pokok, potongan BPJS, dan
  totalnya. Kolom jam kerja, lembur, dan sisa cuti belum ikut.
- **Lampiran foto nota** pada pengeluaran. Tabelnya sudah punya kolom
  catatan, tapi belum ada unggahan berkas.
- **Belum diuji di peramban sungguhan.** Seluruh tes lolos dan tiap
  halaman terbukti mengembalikan HTTP 200 dengan aset yang tersaji benar,
  tapi tata letak halaman kasir di layar tablet meja kasir belum dilihat
  langsung. Jalankan `php artisan serve` dan buka `/` sebelum dipakai
  melayani tamu.
