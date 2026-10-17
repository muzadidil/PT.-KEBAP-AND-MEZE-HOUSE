<?php

namespace App\Filament\Admin\Resources\Zeytin\TelegramReports\Pages;

use App\Filament\Admin\Actions\TelegramPullAction;
use App\Filament\Admin\Resources\Zeytin\TelegramReports\TelegramReportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTelegramReports extends ManageRecords
{
    protected static string $resource = TelegramReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TelegramPullAction::make(),
        ];
    }
}
