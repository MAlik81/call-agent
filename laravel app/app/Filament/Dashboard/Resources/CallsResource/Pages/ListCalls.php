<?php

namespace App\Filament\Dashboard\Resources\CallsResource\Pages;

use App\Filament\Dashboard\Resources\CallsResource;
use Filament\Resources\Pages\ListRecords;

class ListCalls extends ListRecords
{
    protected static string $resource = CallsResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();

        // Show only calls for current tenant
        if ($tenantId = auth()->user()?->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
