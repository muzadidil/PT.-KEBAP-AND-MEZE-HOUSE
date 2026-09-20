{{--
    Tata letak halaman masuk: panel kiri bergambar, panel kanan berisi
    formulir Filament apa adanya.

    Yang dirender di panel kanan adalah $slot, yaitu halaman Login bawaan
    Filament. Jadi tampilannya saja yang dibuat sendiri; seluruh logika
    masuk, pembatasan percobaan, dan pesan galatnya tetap milik Filament.

    Slide diambil dari App\Support\Branding: kalau pemilik sudah mengunggah
    gambarnya lewat Admin → Tampilan, itu yang dipakai; kalau belum, tiga
    gambar vektor bawaan di public/img.
--}}
@php
    use App\Support\Branding;

    $slides = Branding::slides();
    $logo = Branding::logoUrl();
    $brandName = Branding::businessName();
    $tagline = Branding::tagline();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="auth">
        <section class="auth__visual" aria-hidden="true">
            <div class="auth__brand">
                @if ($logo)
                    <img class="auth__logo auth__logo--img" src="{{ $logo }}" alt="{{ $brandName }}">
                @else
                    <span class="auth__logo">{{ Branding::initial() }}</span>
                @endif

                <span class="auth__brand-text">
                    <strong>{{ $brandName }}</strong>
                    <span>{{ $tagline }}</span>
                </span>
            </div>

            <div class="auth__slides" data-auth-slides>
                @foreach ($slides as $i => $slide)
                    <article
                        class="auth__slide @if ($i === 0) is-active @endif"
                        @if ($slide['image']) style="background-image:url('{{ $slide['image'] }}')" @endif
                    >
                        <div class="auth__slide-body">
                            @if ($slide['eyebrow'])
                                <span class="auth__eyebrow"><i></i>{{ $slide['eyebrow'] }}</span>
                            @endif
                            @if ($slide['title'])
                                <h2>{{ $slide['title'] }}</h2>
                            @endif
                            @if ($slide['text'])
                                <p>{{ $slide['text'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if (count($slides) > 1)
                <div class="auth__controls">
                    <div class="auth__dots" data-auth-dots>
                        @foreach ($slides as $i => $slide)
                            <button
                                type="button"
                                class="auth__dot @if ($i === 0) is-active @endif"
                                data-slide="{{ $i }}"
                                aria-label="{{ $i + 1 }}"
                            ></button>
                        @endforeach
                    </div>

                    <div class="auth__arrows">
                        <button type="button" class="auth__arrow" data-auth-prev aria-label="&larr;">&lsaquo;</button>
                        <button type="button" class="auth__arrow" data-auth-next aria-label="&rarr;">&rsaquo;</button>
                    </div>
                </div>

                <div class="auth__progress"><span data-auth-progress></span></div>
            @endif
        </section>

        <section class="auth__panel">
            <div class="auth__card">
                <div class="auth__brand auth__brand--mobile">
                    @if ($logo)
                        <img class="auth__logo auth__logo--img" src="{{ $logo }}" alt="{{ $brandName }}">
                    @else
                        <span class="auth__logo">{{ Branding::initial() }}</span>
                    @endif

                    <span class="auth__brand-text">
                        <strong>{{ $brandName }}</strong>
                        <span>{{ $tagline }}</span>
                    </span>
                </div>

                {{ $slot }}
            </div>
        </section>
    </div>

    <script src="{{ asset('js/auth.js') }}?v={{ @filemtime(public_path('js/auth.js')) }}" defer></script>
</x-filament-panels::layout.base>
