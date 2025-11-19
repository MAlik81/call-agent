<?php

namespace App\Filament\Dashboard\Resources\TwilioSettingsResource\Pages;
use App\Filament\Dashboard\Resources\TwilioSettingsResource\Widgets\PhoneNumbersTable;

use App\Filament\Dashboard\Resources\TwilioSettingsResource;
use Filament\Resources\Pages\ListRecords;

class ListTwilioSettings extends ListRecords
{
    protected static string $resource = TwilioSettingsResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            PhoneNumbersTable::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
