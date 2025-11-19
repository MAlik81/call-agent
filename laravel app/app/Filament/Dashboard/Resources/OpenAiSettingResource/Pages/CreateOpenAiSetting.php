<?php

namespace App\Filament\Dashboard\Resources\OpenAiSettingResource\Pages;

use App\Filament\Dashboard\Resources\OpenAiSettingResource;
use App\Models\OpenAiSetting;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOpenAiSetting extends CreateRecord
{
    protected static string $resource = OpenAiSettingResource::class;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $existingRecord = $tenant
            ? $tenant->openAiSetting()->first()
            : OpenAiSetting::query()->first();

        if ($existingRecord) {
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $existingRecord]));

            return;
        }

        parent::mount();
    }

    /**
     * Remove Filament's default Create/Cancel buttons.
     */

  protected function getFormActions(): array
    {
        return []; // no default form footer buttons
    }

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $query = OpenAiSetting::query();

        if ($tenant) {
            $existing = $tenant->openAiSetting()->first();
            $data['tenant_id'] = $tenant->id;
        } else {
            $existing = $query->first();
        }

        if ($existing) {
            $existing->fill($data);
            $existing->save();

            return $existing;
        }

        return $query->create($data);
    }

    /**
     * Redirect to the index page after creation.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
