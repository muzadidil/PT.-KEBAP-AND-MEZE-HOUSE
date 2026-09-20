{{--
    Halaman 403.

    Paling sering muncul bukan karena ada yang mencoba menerobos, melainkan
    karena kasir yang sedang masuk membuka /admin. Tanpa penjelasan, layarnya
    hanya bertuliskan "403 Forbidden" dan orangnya menyangka aplikasinya rusak.
    Karena itu halaman ini menyebutkan siapa yang sedang masuk dan memberi
    jalan keluar: pindah ke halaman yang boleh dibukanya, atau ganti akun.

    Berdiri sendiri, tanpa tata letak Filament, supaya tetap tampil walau
    galatnya terjadi sebelum panel sempat disiapkan.
--}}
@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('error.forbidden.title') }} — {{ config('business.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--pos-surface-sunken);
            color: var(--pos-text);
            font-family: ui-sans-serif, system-ui, "Segoe UI", sans-serif;
        }

        .gate {
            width: 100%;
            max-width: 26rem;
            padding: 1.75rem;
            border: 1px solid var(--pos-border);
            border-radius: .75rem;
            background: var(--pos-surface);
            box-shadow: var(--pos-shadow);
            text-align: center;
        }

        .gate__code {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            color: var(--pos-text-muted);
        }

        .gate__title {
            margin: .5rem 0 .75rem;
            font-size: 1.25rem;
            line-height: 1.3;
        }

        .gate__body {
            margin: 0 0 1.25rem;
            font-size: .875rem;
            line-height: 1.55;
            color: var(--pos-text-muted);
        }

        .gate__actions {
            display: grid;
            gap: .5rem;
        }

        .gate__button,
        .gate__link {
            display: block;
            width: 100%;
            padding: .625rem .75rem;
            border-radius: .5rem;
            font: inherit;
            font-size: .9375rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .gate__button {
            border: 0;
            background: var(--pos-accent);
            color: var(--pos-accent-contrast);
        }

        .gate__link {
            border: 1px solid var(--pos-border);
            background: var(--pos-surface);
            color: var(--pos-text-muted);
        }

        .gate__link:hover {
            color: var(--pos-text);
        }
    </style>
</head>
<body>
    <main class="gate">
        <p class="gate__code">403</p>
        <h1 class="gate__title">{{ __('error.forbidden.title') }}</h1>

        <p class="gate__body">
            @if ($user)
                {{ __('error.forbidden.wrong_account', [
                    'name' => $user->name,
                    'role' => $user->role?->getLabel() ?? '',
                ]) }}
            @else
                {{ __('error.forbidden.guest') }}
            @endif
        </p>

        <div class="gate__actions">
            @if ($user)
                {{-- Arahkan ke halaman yang memang boleh dibuka akun ini. --}}
                <a class="gate__button" href="{{ $isAdmin ? route('filament.admin.pages.dashboard') : route('filament.cashier.pages.register') }}">
                    {{ $isAdmin ? __('error.forbidden.admin') : __('error.forbidden.register') }}
                </a>

                <form method="POST" action="{{ route('filament.cashier.auth.logout') }}">
                    @csrf
                    <button type="submit" class="gate__link">{{ __('error.forbidden.switch') }}</button>
                </form>
            @else
                <a class="gate__button" href="{{ route('filament.cashier.auth.login') }}">
                    {{ __('error.forbidden.sign_in') }}
                </a>
            @endif
        </div>
    </main>
</body>
</html>
