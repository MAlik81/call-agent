<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UpdateUserLastSeenAt;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

           ->brandLogo(null)
->brandLogoHeight('80px')   // was 28px — try 40–56px as you like
->brandName('AICALLAGENT')
->favicon(asset('images/favicon.ico'))
     // optional

            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigation()
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('User Dashboard'))
                    ->visible(fn () => true)
                    ->url(fn () => route('dashboard'))
                    ->icon('heroicon-s-face-smile'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->pages([
                // custom Panel pages if any
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // dashboard widgets if any
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(__('Revenue'))
                    ->icon('heroicon-s-rocket-launch')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Tenancy'))
                    ->icon('heroicon-s-home')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Product Management'))
                    ->icon('heroicon-s-shopping-cart')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('User Management'))
                    ->icon('heroicon-s-users')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Settings'))
                    ->icon('heroicon-s-cog')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Announcements'))
                    ->icon('heroicon-s-megaphone')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Blog'))
                    ->icon('heroicon-s-newspaper')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Roadmap'))
                    ->icon('heroicon-s-bug-ant')
                    ->collapsed(),
            ])
            ->plugins([
                BreezyCore::make()->myProfile(
                    shouldRegisterUserMenu: true,
                    shouldRegisterNavigation: false,
                    hasAvatars: false,
                    slug: 'my-profile'
                ),
            ])
            ->sidebarCollapsibleOnDesktop();
    }
}
