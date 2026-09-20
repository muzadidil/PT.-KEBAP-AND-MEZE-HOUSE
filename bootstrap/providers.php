<?php

return [
    App\Providers\AppServiceProvider::class,
    // Admin lebih dulu: panel kasir dipasang di akar situs, jadi rutenya
    // harus didaftarkan belakangan agar /admin tidak tertangkap olehnya.
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\CashierPanelProvider::class,
];
