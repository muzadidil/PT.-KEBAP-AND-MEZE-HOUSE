{{--
    Kop perusahaan untuk hasil Cetak dari peramban; disisipkan di atas isi
    tiap halaman panel oleh AppServiceProvider dan disembunyikan di layar.
    Judul laporannya tetap judul halaman Filament di bawah kop ini.
--}}
<div class="print-head" aria-hidden="true">
    <div>
        <div class="print-head__brand">{{ $letterhead['name'] }}</div>
        <div class="print-head__legal">{{ $letterhead['legal_name'] }}</div>
        <div class="print-head__address">{{ $letterhead['address'] }}</div>
    </div>
    <div class="print-head__meta">
        <div>{{ __('zeytin.pdf.generated') }}</div>
        <div data-print-time>{{ now($letterhead['timezone'])->translatedFormat('j M Y, H:i') }}</div>
        <div>{{ __('zeytin.pdf.currency') }}</div>
    </div>
</div>
