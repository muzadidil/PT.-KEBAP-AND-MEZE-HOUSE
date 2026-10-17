<?php

namespace App\Filament\Admin\Resources\Zeytin\DailyIncomes\Pages;

use App\Filament\Admin\Actions\ExcelActions;
use App\Filament\Admin\Resources\Zeytin\DailyIncomes\DailyIncomeResource;
use App\Filament\Admin\Actions\PosSalesAction;
use App\Filament\Admin\Actions\TelegramSalesAction;
use App\Filament\Admin\Resources\Zeytin\TelegramReports\TelegramReportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyIncomes extends ManageRecords
{
    protected static string $resource = DailyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...ExcelActions::make(DailyIncomeResource::excel()),
            PosSalesAction::make(),
            Action::make('telegramQueue')
                ->label(__('telegram.nav'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->url(fn () => TelegramReportResource::getUrl()),
            TelegramSalesAction::make(),
            CreateAction::make(),
        ];
    }
}
