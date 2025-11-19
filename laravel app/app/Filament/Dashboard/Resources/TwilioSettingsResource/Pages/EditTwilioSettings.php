<?php

namespace App\Filament\Dashboard\Resources\TwilioSettingsResource\Pages;

use App\Filament\Dashboard\Resources\TwilioSettingsResource;
use Filament\Resources\Pages\EditRecord;

class EditTwilioSettings extends EditRecord
{
    protected static string $resource = TwilioSettingsResource::class;

    protected function getFormActions(): array
    {
        return []; // no default form footer buttons
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Twilio Setting Updated Successfully';
    }


}
