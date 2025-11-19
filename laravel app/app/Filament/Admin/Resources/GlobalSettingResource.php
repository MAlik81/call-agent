<?php

namespace App\Filament\Admin\Resources;

use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Placeholder;
use App\Filament\Admin\Resources\GlobalSettingResource\Pages;
use Illuminate\Support\HtmlString;

class GlobalSettingResource extends Resource
{
    protected static ?string $navigationGroup = 'Settings';
    // protected static ?string $navigationIcon = 'heroicon-s-cog';
    protected static ?string $navigationLabel = 'Proxy Key Configuration    ';

    protected static ?string $model = SystemSetting::class;


    public static function getPages(): array
{
    return [
        'index' => Pages\EditSystemSettings::route('/'),
    ];
}

}
