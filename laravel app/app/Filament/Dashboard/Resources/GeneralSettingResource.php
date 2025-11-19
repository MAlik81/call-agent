<?php

namespace App\Filament\Dashboard\Resources;

use Filament\Resources\Resource;
use Filament\Navigation\NavigationGroup;
use App\Filament\Dashboard\Resources\GeneralSettingResource\Pages;

class GeneralSettingResource extends Resource
{
    // No model needed
    protected static ?string $model = null;

    public static function getNavigationGroup(): ?string
    {
        return __('Global Setting');
    }

    public static function getNavigationLabel(): string
    {
        return __('General Settings');
    }

    // public static function getNavigationIcon(): ?string
    // {
    //     return 'heroicon-s-globe-al'; // valid solid icon
    // }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    // Do NOT set icon here; remove ->icon() to avoid errors
    // public static function navigationGroups(): array
    // {
    //     return [
    //         NavigationGroup::make()
    //             ->label(__('Global Setting'))
    //             ->collapsed(),
    //     ];
    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\GeneralSettingsPage::route('/'),
        ];
    }
}
