<?php

namespace Workbench\App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => [
                    '50'  => '239 246 255',
                    '100' => '219 234 254',
                    '200' => '191 219 254',
                    '300' => '147 197 253',
                    '400' => '96 165 250',
                    '500' => '59 130 246',
                    '600' => '37 99 235',
                    '700' => '29 78 216',
                    '800' => '30 64 175',
                    '900' => '30 58 138',
                    '950' => '23 37 84',
                ],
            ])
            ->discoverResources(in: base_path('workbench/app/Filament/Resources'), for: 'Workbench\\App\\Filament\\Resources')
            ->discoverPages(in: base_path('workbench/app/Filament/Pages'), for: 'Workbench\\App\\Filament\\Pages')
            ->discoverWidgets(in: base_path('workbench/app/Filament/Widgets'), for: 'Workbench\\App\\Filament\\Widgets')
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
