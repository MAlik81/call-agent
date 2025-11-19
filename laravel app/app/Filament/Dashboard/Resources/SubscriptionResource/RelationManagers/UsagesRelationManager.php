<?php

namespace App\Filament\Dashboard\Resources\SubscriptionResource\RelationManagers;

use App\Constants\PlanType;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('unit_count')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Unit Usages'))
            ->recordTitleAttribute('unit_count')
            ->columns([
                Tables\Columns\TextColumn::make('unit_count')->label(function () {
                    return __('Unit Count').' ('.str()->plural(__($this->ownerRecord->plan->meter->name)).')';
                }),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created At'))
                    ->dateTime(config('app.datetime_format'))
                    ->searchable()->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([

            ])
            ->actions([
            ])
            ->bulkActions([

            ]);
    }

    public static function canViewForRecord(Model|Subscription $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->plan->type === PlanType::USAGE_BASED->value;
    }
}
