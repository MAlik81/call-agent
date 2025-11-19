<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;

use App\Filament\Admin\Resources\AnnouncementResource;
use App\Filament\CrudDefaults;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
