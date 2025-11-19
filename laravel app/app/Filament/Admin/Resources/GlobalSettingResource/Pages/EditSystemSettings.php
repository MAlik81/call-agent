<?php

namespace App\Filament\Admin\Resources\GlobalSettingResource\Pages;

use App\Filament\Admin\Resources\GlobalSettingResource;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use App\Models\SystemSetting;
use Filament\Notifications\Notification;
use App\Http\Controllers\Turns\TurnIngestController;

class EditSystemSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public $status = 'Loading...';
    public $activeCalls = 0;
    public $value; // holds the proxy host value

    protected static string $resource = GlobalSettingResource::class;
    protected static ?string $title = 'Update Settings';
    protected static string $view = 'filament.admin.pages.global-setting';

    public function mount(): void
    {
        // Load Proxy Host
        $setting = SystemSetting::where('key', 'PROXY_HOST')->first();
        $this->value = $setting->value ?? '';

        // Load WebSocket status
        try {
            $controller = new TurnIngestController();
            $response = $controller->wsHealth();

            if (method_exists($response, 'getData')) {
                $data = $response->getData(true);
            } else {
                $data = $response;
            }

            $this->status = $data['status'] ?? 'Offline';
            $this->activeCalls = $data['active_calls'] ?? 0;
        } catch (\Exception $e) {
            $this->status = 'Offline';
            $this->activeCalls = 0;
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('value')
                ->label('Proxy EndPoint')
                ->required()
                ->columnSpanFull(),
        ];
    }

public function submit()
{
    SystemSetting::updateOrCreate(
        ['key' => 'PROXY_HOST'],
        ['value' => $this->value]
    );

    Notification::make()
        ->success()
        ->title('Settings updated successfully!')
        ->send();
}


}
