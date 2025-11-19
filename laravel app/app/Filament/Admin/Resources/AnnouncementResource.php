<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Announcements');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('Title'))
                    ->helperText(__('The title of the announcement (for internal use only).'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\RichEditor::make('content')
                    ->label(__('Content'))
                    ->helperText(__('The content of the announcement.'))
                    ->required()
                    ->toolbarButtons([
                        'blockquote',
                        'bold',
                        'italic',
                        'link',
                        'redo',
                        'strike',
                        'underline',
                        'undo',
                    ])
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label(__('Starts At'))
                    ->helperText(__('The date and time the announcement will start displaying.'))
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label(__('Ends At'))
                    ->helperText(__('The date and time the announcement will stop displaying.'))
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('is_dismissible')
                    ->label(__('Is Dismissible'))
                    ->helperText(__('If enabled, users will be able to dismiss the announcement.'))
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('show_on_frontend')
                    ->label(__('Show on frontend'))
                    ->helperText(__('If enabled, the announcement will be displayed on the frontend website.'))
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('show_on_user_dashboard')
                    ->label(__('Show on user dashboard'))
                    ->helperText(__('If enabled, the announcement will be displayed on the user dashboard.'))
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('show_for_customers')
                    ->label(__('Show for customers'))
                    ->helperText(__('If enabled, the announcement will be displayed for customers (users who either bought a product or subscribed to a plan).'))
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('Starts At'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('Ends At'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('Active')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Announcements');
    }
}
