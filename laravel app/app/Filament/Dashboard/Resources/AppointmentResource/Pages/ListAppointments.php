<?php

namespace App\Filament\Dashboard\Resources\AppointmentResource\Pages;

use App\Filament\Dashboard\Resources\AppointmentResource;
use Filament\Resources\Pages\ListRecords;
use App\Models\Appointment;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function afterCreate(Appointment $record): void
{
    AppointmentResource::syncWithGoogleCalendar($record);
}


    protected function afterSave(Appointment $record): void
    {
        AppointmentResource::syncWithGoogleCalendar($record);
    }

    protected function afterDelete(Appointment $record): void
    {
        AppointmentResource::deleteFromGoogleCalendar($record);
    }
}
