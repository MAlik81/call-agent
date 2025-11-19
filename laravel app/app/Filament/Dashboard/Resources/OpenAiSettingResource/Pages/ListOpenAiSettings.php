<?php

namespace App\Filament\Dashboard\Resources\OpenAiSettingResource\Pages;

use App\Filament\Dashboard\Resources\OpenAiSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOpenAiSettings extends ListRecords
{
    protected static string $resource = OpenAiSettingResource::class;

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
