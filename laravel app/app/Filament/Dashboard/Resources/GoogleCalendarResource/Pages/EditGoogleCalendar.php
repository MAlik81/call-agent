<?php

namespace App\Filament\Dashboard\Resources\GoogleCalendarResource\Pages;

use App\Filament\Dashboard\Resources\GoogleCalendarResource;
use Filament\Resources\Pages\EditRecord;

class EditGoogleCalendar extends EditRecord
{
     protected function getFormActions(): array
    {
        return []; // no default form footer buttons
    }

     protected function getRedirectUrl(): string
    {
        // Redirect back to the resource index after edit
        return $this->getResource()::getUrl('index');
    }
    protected static string $resource = GoogleCalendarResource::class;
}
