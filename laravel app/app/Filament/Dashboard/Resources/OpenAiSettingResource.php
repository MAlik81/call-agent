<?php

namespace App\Filament\Dashboard\Resources;

use App\Models\OpenAiSetting;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Dashboard\Resources\OpenAiSettingResource\Pages;

class OpenAiSettingResource extends Resource
{

    protected static ?string $tenantRelationshipName = 'openAiSetting';

    protected static ?string $model = OpenAiSetting::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Api Configuration');
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
                                Forms\Components\TextInput::make('api_key_encrypted')
                                    ->label('API Key (Encrypted)')
                                    ->maxLength(255)
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                Forms\Components\Select::make('default_model')
                                    ->label('Default Model')
                                    ->options([
                                        'gpt-4o-mini' => 'GPT-4o Mini',
                                        'gpt-4' => 'GPT-4',
                                        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                    ])
                                    ->default('gpt-4o-mini')
                                    ->required(),
                            ]),
                        Forms\Components\Select::make('stt_model')
                            ->label('STT Model')
                            ->options([
                                'whisper-1' => 'Whisper-1',
                                'whisper-large-v3' => 'Whisper Large V3',
                            ])
                            ->default('whisper-1')
                            ->required(),

                        Forms\Components\Grid::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\Toggle::make('realtime_enabled')
                                    ->label('Enable Realtime')
                                    ->helperText('Toggle to use realtime Chat Completions where supported.')
                                    ->default(false),
                                Forms\Components\Select::make('realtime_model')
                                    ->label('Realtime Model')
                                    ->options([
                                        'gpt-4o-realtime-preview' => 'gpt-4o-realtime-preview',
                                        'gpt-4o-realtime-mini' => 'gpt-4o-realtime-mini',
                                    ])
                                    ->placeholder('Select a realtime model')
                                    ->visible(fn ($get) => (bool) $get('realtime_enabled')),
                            ]),

                        Forms\Components\Textarea::make('realtime_system_prompt')
                            ->label('Realtime System Prompt')
                            ->rows(3)
                            ->helperText('Optional system prompt that will be sent with realtime requests.')
                            ->visible(fn ($get) => (bool) $get('realtime_enabled')),

                        Forms\Components\Grid::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('realtime_voice')
                                    ->label('Realtime Voice')
                                    ->maxLength(64)
                                    ->helperText('Optional voice identifier to use for realtime responses.')
                                    ->visible(fn ($get) => (bool) $get('realtime_enabled')),
                                Forms\Components\TextInput::make('realtime_language')
                                    ->label('Realtime Language')
                                    ->maxLength(32)
                                    ->helperText('Optional language code to prefer during realtime interactions.')
                                    ->visible(fn ($get) => (bool) $get('realtime_enabled')),
                            ]),


                        Forms\Components\Grid::make()
                            ->columns(1)
                            ->schema([
                                Forms\Components\Textarea::make('instructions')
                                    ->label('Instructions')
                                    ->rows(3),
                            ]),

                        Forms\Components\Grid::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('')
                                    ->content(new HtmlString('
                                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 w-full">
                                        Save
                                    </button>
                                ')),
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'border-2 border-green-500 rounded-lg p-4 bg-white shadow-sm',
                    ]),
            ]);
    }




    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('api_key_encrypted')->label('API Key')->limit(30),
                // Tables\Columns\TextColumn::make('default_model')->label('Model')->limit(30),
                Tables\Columns\TextColumn::make('instructions')->label('Instructions')->limit(50),
                // Tables\Columns\TextColumn::make('stt_model')->label('STT Model')->limit(30),

                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime('M d, Y H:i'),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime('M d, Y H:i'),
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
            'index' => Pages\ListOpenAiSettings::route('/'),
            'create' => Pages\CreateOpenAiSetting::route('/create'),
            'edit' => Pages\EditOpenAiSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return $tenant->openAiSetting()->doesntExist();
        }

        return !OpenAiSetting::query()->exists();
    }
}
