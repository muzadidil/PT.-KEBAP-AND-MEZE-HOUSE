<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * In  — pemilik menyetor uang ke usaha.
 * Out — pemilik menarik uang dari usaha (prive).
 */
enum CapitalDirection: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return __("enums.capital.{$this->value}");
    }

    public function getColor(): string
    {
        return $this === self::In ? 'success' : 'danger';
    }
}
