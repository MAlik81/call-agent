<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\GoogleCalendarResource\Pages;
use App\Models\GoogleCalendarApi;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class GoogleCalendarResource extends Resource
{
    protected static ?string $model = GoogleCalendarApi::class;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'Api Configuration';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('')
                    ->columnSpan('full'),

                Forms\Components\Grid::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('use_tenant_key')
                            ->label('Use tenant-specific key')
                            ->default(true)
                            ->reactive(),
                    ]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([

                                Forms\Components\TextInput::make('file_name')
                                    ->label('JSON File Name')
                                    ->required()
                                    ->visible(fn($get) => $get('use_tenant_key')),

                                Forms\Components\FileUpload::make('json_file_path')
                                    ->label('Upload Service Account JSON')
                                    ->disk('local')
                                    ->directory('google_calendar_json')
                                    ->required(fn($get) => $get('use_tenant_key'))
                                    ->visible(fn($get) => $get('use_tenant_key')),

                                Forms\Components\Textarea::make('json_content')
                                    ->label('Service Account JSON Content')
                                    ->rows(10)
                                    ->required(fn($get) => $get('use_tenant_key'))
                                    ->visible(fn($get) => $get('use_tenant_key')),

                                // New calendar_id field
                                Forms\Components\TextInput::make('calendar_id')
                                    ->label('Google Calendar ID')
                                    ->required()
                                    ->placeholder('Enter Google Calendar ID'),
                            ]),

                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                Forms\Components\Placeholder::make('')
                                    ->content(new \Illuminate\Support\HtmlString('
                                        <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 w-48">
                                            Save
                                        </button>
                                    ')),
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'border-2 border-green-500 rounded-lg p-4 bg-white shadow-sm',
                    ])
                    ->visible(fn($get) => $get('use_tenant_key')),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')->label('File Name'),
                TextColumn::make('calendar_id')->label('Calendar ID')->limit(50), 
                TextColumn::make('json_file_path')->label('File Path')->limit(50),
                TextColumn::make('created_at')->label('Created At')->dateTime(),
                TextColumn::make('updated_at')->label('Updated At')->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
{
    return [
        'index' => Pages\ListGoogleCalendars::route('/'),
        'create' => Pages\CreateGoogleCalendar::route('/create'),
        'edit' => Pages\EditGoogleCalendar::route('/{record}/edit'),
    ];
}


    public static function getNavigationGroup(): ?string
    {
        return __('Api Configuration');
    }
}
