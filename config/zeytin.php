<?php

/*
| Pembukuan bulanan gaya berkas Excel klien — dipindahkan dari aplikasi
| Zeytin (situs statis + Firestore) ke sini.
|
| Definisi tiap kolom di bawah diambil dari rumus di dalam sel berkas
| aslinya, bukan dikarang ulang. Kalau angkanya suatu saat terlihat aneh,
| yang dibaca adalah berkas Excel-nya, lalu berkas ini.
*/

return [

    /*
    | Channel pemasukan harian, urutannya sama dengan kolom di sheet Income.
    |
    | `in_sales` menandai channel yang ikut Total Sales. Petty cash sengaja
    | TIDAK ikut: rumus aslinya `=SUM(D8:H8)` dimulai dari kolom D dan
    | melewati C. Perilakunya dipertahankan, dan ditandai "excl." di layar
    | supaya tidak dikira hilang karena salah hitung.
    |
    | `is_cash` menandai mana yang berupa uang tunai. Dulu "tunai" berarti
    | langsung nama kolom `cash` yang ditulis di rumus, jadi menambah channel
    | tunai baru berarti mengubah rumusnya juga. Sekarang cukup ditandai di
    | sini; lihat App\Support\Zeytin\Channels.
    */
    'channels' => [
        ['key' => 'petty_cash', 'label' => 'Petty cash', 'in_sales' => false, 'is_cash' => false],
        ['key' => 'cash', 'label' => 'Cash', 'in_sales' => true, 'is_cash' => true],
        ['key' => 'bni', 'label' => 'BNI', 'in_sales' => true, 'is_cash' => false],
        ['key' => 'grab_food', 'label' => 'Grab Food', 'in_sales' => true, 'is_cash' => false],
        ['key' => 'go_food', 'label' => 'Go Food', 'in_sales' => true, 'is_cash' => false],
        ['key' => 'go_pay', 'label' => 'Go Pay', 'in_sales' => true, 'is_cash' => false],
    ],

    /*
    | Saldo awal titipan belanja pemasok.
    |
    | Di berkas Excel angka ini diketik ulang di tiap baris sebagai
    | `=456560+D8`. Di sini jadi satu pengaturan, supaya mengubahnya cukup
    | sekali dan bukan di ratusan baris.
    */
    'supplier_cash_opening' => (int) env('ZEYTIN_SUPPLIER_CASH_OPENING', 456560),

    /*
    | Kop laporan PDF — laporan yang dikirim ke pemilik, pembacanya orang
    | luar negeri. Diambil dari aplikasi Zeytin sebelumnya.
    */
    'letterhead' => [
        'name' => env('ZEYTIN_NAME', 'ZEYTiN'),
        'legal_name' => env('ZEYTIN_LEGAL_NAME', 'PT. Kebap and Meze House'),
        'address' => env('ZEYTIN_ADDRESS', 'Koloni Bali, Jl. Raya Semat No.1, Canggu, Badung, Bali 80361'),

        // Jam "dibuat" di kaki laporan ditulis dalam waktu setempat (WITA),
        // bukan UTC tempat aplikasinya menyimpan waktu.
        'timezone' => env('ZEYTIN_TIMEZONE', 'Asia/Makassar'),
    ],

];
