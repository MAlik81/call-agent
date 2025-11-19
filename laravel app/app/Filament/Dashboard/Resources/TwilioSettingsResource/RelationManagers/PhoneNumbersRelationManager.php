<?php

namespace App\Filament\Dashboard\Resources\TwilioSettingsResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Tables;
use App\Models\PhoneNumbers;
use Illuminate\Database\Eloquent\Model;

class PhoneNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneNumbers';

    protected static ?string $recordTitleAttribute = 'e164';

    // Correct method signature
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Phone Numbers';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function query()
    {
        return PhoneNumbers::query()
            ->where('tenant_id', $this->ownerRecord->tenant_id);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('e164')
                ->label('Phone Number')
                ->required(),
            Forms\Components\TextInput::make('friendly_name')
                ->label('Friendly Name'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Active',
                    'released' => 'Released',
                ]),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('e164')->label('Phone Number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('friendly_name')->label('Friendly Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'released' => 'Released',
                ]),
            ])
            ->defaultSort('e164', 'asc')
            ->paginate(10);
    }
}
