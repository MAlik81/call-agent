<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\CallsResource\Pages;
use App\Models\CallSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;

class CallsResource extends Resource
{
    protected static ?string $model = CallSession::class;

    protected static ?string $navigationLabel = 'Calls';
    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static bool $shouldRegisterNavigation = true;

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Call ID')->sortable(),
                TextColumn::make('call_sid')->label('Call SID')->sortable()->searchable(),
                TextColumn::make('from_number')->label('From')->searchable(),
                TextColumn::make('to_number')->label('To')->searchable(),
                TextColumn::make('status')->label('Status')->sortable(),
                TextColumn::make('direction')->label('Direction')->sortable(),
                TextColumn::make('started_at')->label('Started At')->dateTime()->sortable(),
                TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                ViewAction::make('view')
                    ->label('View')
                    ->url(fn($record) => CallsResource::getUrl('view', ['record' => $record])), // <-- fixed
                DeleteAction::make('delete')->label('Delete'),
            ])

            ->defaultSort('started_at', 'desc');
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalls::route('/'),
            'view' => Pages\ViewCall::route('/{record}'), // <-- Add this
        ];
    }


}
