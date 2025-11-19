<?php

namespace App\Filament\Dashboard\Resources\OpenAiSettingResource\Pages;

use App\Filament\Dashboard\Resources\OpenAiSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditOpenAiSetting extends EditRecord
{
    protected static string $resource = OpenAiSettingResource::class;

    protected function getFormActions(): array
    {
        return []; // no default form footer buttons
    }
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
