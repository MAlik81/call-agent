<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;

use App\Filament\Admin\Resources\AnnouncementResource;
use App\Filament\CrudDefaults;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = AnnouncementResource::class;
}
