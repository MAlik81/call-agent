<?php

namespace App\Providers\Filament;

use App\Constants\AnnouncementPlacement;
use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Pages\TenantSettings;
use App\Filament\Dashboard\Pages\TwoFactorAuth\TwoFactorAuth;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
       return $panel
    ->default()
    ->id('dashboard')
    ->path('dashboard')

    ->brandLogo(null)
    ->brandLogoHeight('80px')
    ->brandName('AICALLAGENT')
    ->favicon(asset('images/ailogo.png'))

            ->resources([
                // \App\Filament\Dashboard\Resources\StatsResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('Admin Panel'))
                    ->visible(fn () => auth()->user()->isAdmin())
                    ->url(fn () => route('filament.admin.pages.dashboard'))
                    ->icon('heroicon-s-cog-8-tooth'),
                MenuItem::make()
                    ->label(__('Workspace Settings'))
                    ->visible(function () {
                        $tenantPermissionService = app(TenantPermissionService::class);

                        return $tenantPermissionService->tenantUserHasPermissionTo(
                            Filament::getTenant(),
                            auth()->user(),
                            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
                        );
                    })
                    ->icon('heroicon-s-cog-8-tooth')
                    ->url(fn () => TenantSettings::getUrl()),
                MenuItem::make()
                    ->label(__('2-Factor Authentication'))
                    ->visible(fn () => config('app.two_factor_auth_enabled'))
                    ->url(fn () => TwoFactorAuth::getUrl())
                    ->icon('heroicon-s-cog-8-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\\Filament\\Dashboard\\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->viteTheme('resources/css/filament/dashboard/theme.css')
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\\Filament\\Dashboard\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                UpdateUserLastSeenAt::class,
            ])
            ->renderHook('panels::head.start', fn () => view('components.layouts.partials.analytics'))
            ->navigationGroups([
                NavigationGroup::make()->label(__('Team'))->icon('heroicon-s-users')->collapsed(),
                NavigationGroup::make()->label(__('Api Configuration'))->icon('heroicon-s-cog')->collapsed(),
                NavigationGroup::make()->label(__('Global Setting'))->icon('heroicon-s-globe-alt')->collapsed(),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render(
                    "@livewire('announcement.view', ['placement' => '" . AnnouncementPlacement::USER_DASHBOARD->value . "'])"
                )
            )
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: false,
                        slug: 'my-profile'
                    )
                    ->myProfileComponents([
                        \App\Livewire\AddressForm::class,
                    ]),
            ])
            ->tenantMenu()
            ->tenant(Tenant::class, 'uuid');
    }
}
