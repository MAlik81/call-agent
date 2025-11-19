<?php

namespace App\Filament\Dashboard\Resources;

use App\Models\TwilioSetting;
use App\Filament\Dashboard\Resources\TwilioSettingsResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Dashboard\Resources\TwilioSettingsResource\Pages;

class TwilioSettingsResource extends Resource
{
    protected static ?string $model = TwilioSetting::class;
    protected static ?string $navigationGroup = 'Api Configuration';

 public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Placeholder::make('')
            ->columnSpan('full'),

        Forms\Components\Grid::make()
            ->columns(2)
            ->schema([
                Forms\Components\Toggle::make('use_tenant_key')
                    ->label('Use tenant key')
                    ->default(true)
                    ->reactive(),
            ]),

        Forms\Components\Group::make() // 🟢 Card wrapper
            ->extraAttributes([
                'class' => 'border-2 border-green-500 rounded-lg p-4 bg-white shadow-sm',
            ])
            ->schema([
                Forms\Components\TextInput::make('account_sid')
                    ->label('Account SID')
                    ->required(fn($get) => $get('use_tenant_key'))
                    ->disabled(fn($get) => !$get('use_tenant_key')),

                Forms\Components\TextInput::make('auth_token_encrypted')
                    ->label('Auth Token (Encrypted)')
                    ->required(fn($get) => $get('use_tenant_key'))
                    ->disabled(fn($get) => !$get('use_tenant_key'))
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('validate')
                            ->label('Validate')
                            ->color('danger')
                            ->action(fn() => null)
                    ),

                Forms\Components\TextInput::make('phone_numbers')
                    ->label('Phone Numbers')
                    ->helperText('Comma-separated if multiple')
                    ->maxLength(255)
                    ->disabled(fn($get) => !$get('use_tenant_key')),

                Forms\Components\Placeholder::make('')
                    ->content(new HtmlString('
                        <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 w-64">
                            Save
                        </button>
                    ')),
            ])
            ->visible(fn($get) => $get('use_tenant_key')),
    ]);
}



    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('account_sid')->label('Account SID')->limit(30),
            // Tables\Columns\TextColumn::make('application_sid')->label('Application SID')->limit(20),
            // Tables\Columns\TextColumn::make('webhook_token')->label('Webhook Token')->limit(20),
            Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime('M d, Y H:i'),
            Tables\Columns\TextColumn::make('phone_numbers')
                ->label('Phone Numbers')
                ->limit(30),

        ])->actions([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->bulkActions([
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
            'index' => Pages\ListTwilioSettings::route('/'),
            'create' => Pages\CreateTwilioSetting::route('/create'),
            'edit' => Pages\EditTwilioSettings::route('/{record}/edit'),
        ];
    }
}
