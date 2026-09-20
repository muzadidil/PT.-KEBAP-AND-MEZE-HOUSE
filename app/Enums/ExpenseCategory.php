<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasLabel
{
    case Operational = 'operational';
    case Supplier = 'supplier';
    case Salary = 'salary';
    case Tax = 'tax';
    case Owner = 'owner';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("enums.expense.{$this->value}");
    }
}
