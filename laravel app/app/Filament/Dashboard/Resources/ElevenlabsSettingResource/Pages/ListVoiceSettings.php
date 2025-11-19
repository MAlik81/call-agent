<?php

namespace App\Filament\Dashboard\Resources\ElevenlabsSettingResource\Pages;

use App\Filament\Dashboard\Resources\ElevenlabsSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoiceSettings extends ListRecords
{
    protected static string $resource = ElevenlabsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Key')
                ->color('primary')
                ->icon('heroicon-o-plus'),
        ];
    }
}
