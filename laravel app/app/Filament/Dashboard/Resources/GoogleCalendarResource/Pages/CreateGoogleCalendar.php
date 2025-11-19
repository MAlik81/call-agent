<?php

namespace App\Filament\Dashboard\Resources\GoogleCalendarResource\Pages;

use App\Filament\Dashboard\Resources\GoogleCalendarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoogleCalendar extends CreateRecord
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
