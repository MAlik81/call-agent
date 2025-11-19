<?php

namespace App\Filament\Dashboard\Resources\GoogleCalendarResource\Pages;

use App\Filament\Dashboard\Resources\GoogleCalendarResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
class ListGoogleCalendars extends ListRecords
{
    protected static string $resource = GoogleCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Key')
                ->color('primary')
                ->icon('heroicon-o-plus'),
        ];
    }
}
