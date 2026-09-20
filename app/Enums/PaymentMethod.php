<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Cashless = 'cashless';

    public function getLabel(): string
    {
        return __("enums.method.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Transfer => 'info',
            self::Cashless => 'gray',
        };
    }

    /** Keluar dari laci kasir; selain ini keluar dari rekening. */
    public function isCash(): bool
    {
        return $this === self::Cash;
    }
}
