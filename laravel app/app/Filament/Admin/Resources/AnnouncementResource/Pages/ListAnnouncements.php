<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;

use App\Filament\Admin\Resources\AnnouncementResource;
use App\Filament\ListDefaults;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    use ListDefaults;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
