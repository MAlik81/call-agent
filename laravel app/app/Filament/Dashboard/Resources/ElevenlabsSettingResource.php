<?php

namespace App\Filament\Dashboard\Resources;

use App\Models\ElevenLabs;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Dashboard\Resources\ElevenlabsSettingResource\Pages;

class ElevenlabsSettingResource extends Resource
{
    protected static ?string $model = ElevenLabs::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Api Configuration');
    }

    public static function getNavigationLabel(): string
    {
        return 'ElevenLabs Settings'; // what shows in the sidebar
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                // updated field
                                Forms\Components\TextInput::make('elevenlabs_api_key_encrypted')
                                    ->label('API Key (Encrypted)')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                // updated field
                                Forms\Components\TextInput::make('elevenlabs_voice_id')
                                    ->label('Voice ID')
                                    ->maxLength(64),
                            ]),

                        Forms\Components\Grid::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('stt_provider')
                                    ->label('STT Provider')
                                    ->options([
                                        'elevenlabs' => 'ElevenLabs',   // use plural consistently
                                    ])
                                    ->default('elevenlabs')
                                    ->required(),


                                 Forms\Components\TextInput::make('language')
                                    ->label('Language')
                                    ->maxLength(16),
                            ]),


                        Forms\Components\Grid::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('')
                                    ->content(new HtmlString('
                                        <button type="submit" class="px-4 py-2  bg-green-500 text-white rounded hover:bg-green-600 w-full">
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('elevenlabs_api_key_encrypted')->label('API Key')->limit(30),
                Tables\Columns\TextColumn::make('elevenlabs_voice_id')->label('Voice ID')->limit(15),
                // Tables\Columns\TextColumn::make('stt_provider')->label('STT Provider'),
                // Tables\Columns\TextColumn::make('stt_model')->label('STT Model')->limit(15),
                // Tables\Columns\TextColumn::make('tts_model')->label('TTS Model')->limit(15),
                Tables\Columns\TextColumn::make('language')->label('Language')->limit(10),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime('M d, Y H:i'),
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
            'index' => Pages\ListVoiceSettings::route('/'),
            'create' => Pages\CreateVoiceSetting::route('/create'),
            'edit' => Pages\EditVoiceSetting::route('/{record}/edit'),
        ];
    }
}
