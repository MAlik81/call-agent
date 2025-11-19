<?php

namespace App\Filament\Dashboard\Resources\CallsResource\Pages;

use App\Filament\Dashboard\Resources\CallsResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Pages\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewCall extends ViewRecord
{
    protected static string $resource = CallsResource::class;

    public function getView(): string
    {
        return 'filament.dashboard.calls.view-call';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_chat')
                ->label('Export Chat')
                ->icon('heroicon-o-arrow-down')
                ->color('success')
                ->action(function () {
                    // Sort messages by creation order to match chat sequence
                    $messages = $this->record->messages->sortBy('id');
                    $filename = 'call_' . $this->record->id . '_chat.csv';

                    return new StreamedResponse(function () use ($messages) {
                        $handle = fopen('php://output', 'w');

                        // CSV header
                        fputcsv($handle, ['Role', 'Text']);

                        // CSV rows
                        foreach ($messages as $msg) {
                            fputcsv($handle, [
                                $msg->role ?? '',
                                $msg->text ?? '',
                            ]);
                        }

                        fclose($handle);
                    }, 200, [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                }),
        ];
    }

    // 🔹 Override the title
    public function getTitle(): string
    {
        return 'Call Log';
    }
}
