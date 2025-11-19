<?php

namespace App\Filament\Dashboard\Resources\TwilioSettingsResource\Widgets;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\PhoneNumbers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PhoneNumbersTable extends BaseWidget
{
    use InteractsWithForms;

    protected int|string|array $columnSpan = 'full';

    public ?int $tenantId = null;

    public function getHeading(): string
    {
        return 'Phone Numbers';
    }

    protected function getTenantId(): int
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'No logged-in user.');
        }

        $tenantId = $user->tenant_id ?? $user->tenants()->first()?->id;

        if (!$tenantId) {
            abort(403, 'Tenant not found for user.');
        }

        return $tenantId;
    }

    protected function getTableQuery(): Builder
    {
        $this->tenantId = $this->getTenantId();

        return PhoneNumbers::query()
            ->where('tenant_id', $this->tenantId);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('e164')->label('Phone Number'),
            Tables\Columns\TextColumn::make('friendly_name')->label('Friendly Name'),
            Tables\Columns\TextColumn::make('status')->label('Status'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\CreateAction::make()
                ->label('Add Number')
                ->modalHeading('Add Phone Number')
                ->modalSubmitActionLabel('Save Phone Number')
                ->form([
                    Forms\Components\TextInput::make('e164')
                        ->label('Phone Number')
                        ->required(),
                    Forms\Components\TextInput::make('friendly_name')
                        ->label('Friendly Name')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'released' => 'Released',
                        ])
                        ->required(),
                ])
                ->using(function (array $data) {
                    return PhoneNumbers::create(array_merge($data, [
                        'tenant_id' => $this->tenantId,
                    ]));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\EditAction::make()
                ->modalHeading('Edit Phone Number')
                ->modalSubmitActionLabel('Update Phone Number')
                ->form([
                    Forms\Components\TextInput::make('e164')
                        ->label('Phone Number')
                        ->required(),
                    Forms\Components\TextInput::make('friendly_name')
                        ->label('Friendly Name')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'released' => 'Released',
                        ])
                        ->required(),
                ]),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    // Bulk actions (for multiple selection delete)
protected function getTableBulkActions(): array
{
    return [
        Tables\Actions\DeleteBulkAction::make()
            ->label('Delete') // Just show "Delete"
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion(),
    ];
}




    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25, 50];
    }
}
