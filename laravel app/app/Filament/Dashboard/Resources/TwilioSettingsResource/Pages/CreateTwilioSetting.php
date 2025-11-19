<?php

namespace App\Filament\Dashboard\Resources\TwilioSettingsResource\Pages;

use App\Filament\Dashboard\Resources\TwilioSettingsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTwilioSetting extends CreateRecord
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

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Twilio Setting Created Successfully';
    }
}













