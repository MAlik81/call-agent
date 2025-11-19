<?php

namespace App\Filament\Dashboard\Resources\GeneralSettingResource\Pages;

use Filament\Resources\Pages\Page;

class GeneralSettingsPage extends Page
{
    protected static string $resource = \App\Filament\Dashboard\Resources\GeneralSettingResource::class;

    protected static string $view = 'filament.dashboard.settings.general-settings';

    protected static ?string $title = 'General Settings';
}
