<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\GoogleCalendarApi;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Carbon\Carbon;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationLabel = 'Appointments';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static bool $shouldRegisterNavigation = true;

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')->label('Client')->searchable(),
                TextColumn::make('client_phone')->label('Phone')->searchable(),
                TextColumn::make('service')->label('Service')->searchable(),
                TextColumn::make('start_at')
                    ->label('Start')
                    ->formatStateUsing(fn($state) => Carbon::parse($state)->format('M d, Y H:i'))
                    ->sortable(),
                TextColumn::make('end_at')->label('End')->dateTime()->sortable(),
                TextColumn::make('status')->label('Status')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->actions([
                Action::make('calendar')
                    ->label('Google Calendar')
                    ->icon('heroicon-o-calendar')
                    ->action(null)
                    ->requiresConfirmation(false)
                    ->modalHeading('')
                    ->modalContent(fn(Appointment $record) => view(
                        'filament.dashboard.pages.calendar-view',
                        [
                            'url' => static::googleCalendarUrl($record),
                            'record' => $record,
                            // PASS the calendar_id from DB
                            'calendarId' => optional(GoogleCalendarApi::first())->calendar_id,
                        ]
                    ))
                    ->modalWidth('lg')
                    ->modalFooter(null)
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),


                DeleteAction::make()
                    ->requiresConfirmation()
                    ->label('Delete')
                    ->modalHeading('Delete Appointment')
                    ->modalSubheading('Are you sure you want to delete this appointment?'),
            ])
            ->defaultSort('start_at', 'asc');
    }

    /** 🔹 Google Client directly inside resource */
    protected static function getGoogleService(): array
    {
        $googleApi = GoogleCalendarApi::first();
$credentials = json_decode($googleApi->json_content, true); // or load from file

        $client = new Google_Client();
        $client->setAuthConfig($credentials);
        $client->addScope(Google_Service_Calendar::CALENDAR);

        return [new Google_Service_Calendar($client), $googleApi->calendar_id];
    }

    /** 🔹 Create or update event in Google Calendar */
    public static function syncWithGoogleCalendar(Appointment $record): void
    {
        [$service, $calendarId] = self::getGoogleService();

        if ($record->google_event_id) {
            $event = $service->events->get($calendarId, $record->google_event_id);
        } else {
            $event = new \Google_Service_Calendar_Event();
        }

        $event->setSummary($record->service . ' - ' . $record->client_name);
        $event->setDescription("Client: {$record->client_name}\nPhone: {$record->client_phone}\nStatus: {$record->status}");

        $event->setStart([
            'dateTime' => Carbon::parse($record->start_at)->toRfc3339String(),
            'timeZone' => config('app.timezone'),
        ]);
        $event->setEnd([
            'dateTime' => Carbon::parse($record->end_at)->toRfc3339String(),
            'timeZone' => config('app.timezone'),
        ]);

        $savedEvent = $record->google_event_id
            ? $service->events->update($calendarId, $record->google_event_id, $event)
            : $service->events->insert($calendarId, $event);

        if (!$record->google_event_id) {
            $record->google_event_id = $savedEvent->id;
            $record->save();
        }
    }


    /** 🔹 Delete event */
    public static function deleteFromGoogleCalendar(Appointment $record): void
    {
        if (!$record->google_event_id) {
            return;
        }

        [$service, $calendarId] = self::getGoogleService();
        $service->events->delete($calendarId, $record->google_event_id);
    }

    protected static function googleCalendarUrl(Appointment $record): string
    {
        $start = Carbon::parse($record->start_at)->utc()->format('Ymd\THis\Z');
        $end = Carbon::parse($record->end_at)->utc()->format('Ymd\THis\Z');

        $title = $record->service
            ? "{$record->service} – {$record->client_name}"
            : "Appointment – {$record->client_name}";

        $details = "Client: {$record->client_name}\nPhone: {$record->client_phone}\nStatus: {$record->status}";

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . urlencode($title)
            . '&details=' . urlencode($details)
            . '&dates=' . $start . '/' . $end
            . '&ctz=' . urlencode(config('app.timezone', 'UTC'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
        ];
    }
}
