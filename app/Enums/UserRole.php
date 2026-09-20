<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function getLabel(): string
    {
        return __("enums.role.{$this->value}");
    }
}
