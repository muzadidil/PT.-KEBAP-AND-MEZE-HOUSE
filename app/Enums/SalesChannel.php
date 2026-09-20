<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cara tamu membayar. Ketiganya dilaporkan terpisah karena uangnya
 * mendarat di tempat berbeda: Cash di laci kasir, Cashless & Grab di
 * rekening. Neraca bergantung pada pemisahan ini.
 */
enum SalesChannel: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Cashless = 'cashless';
    case Grab = 'grab';

    public function getLabel(): string
    {
        return __("enums.channel.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Cashless => 'info',
            self::Grab => 'warning',
        };
    }

    /** Channel yang menambah uang tunai di laci, bukan saldo rekening. */
    public function isCash(): bool
    {
        return $this === self::Cash;
    }
}
