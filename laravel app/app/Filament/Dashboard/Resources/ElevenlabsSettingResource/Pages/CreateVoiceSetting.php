<?php

namespace App\Filament\Dashboard\Resources\ElevenlabsSettingResource\Pages;

use App\Filament\Dashboard\Resources\ElevenlabsSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVoiceSetting extends CreateRecord
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
    protected static string $resource = ElevenlabsSettingResource::class;
}
