<?php

namespace Arzcode\Finisterre\Support;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * What an upgrade of the package left to do, for the people who configure it to
 * see in the panel instead of finding out from something that does not work.
 *
 * Only what is safe from a web request is ever applied from here: the migrations
 * the package loads itself and the settings a version added. Nothing is published
 * into the application and nothing is built, so a server deployed from git ends up
 * with no file its repository does not have.
 */
class PendingUpgrade
{
    /**
     * What the Apply button takes care of: the package's own migrations that have
     * not run. Cheap enough to ask on every load of the board.
     *
     * @return list<string>
     */
    public static function appliable(): array
    {
        return rescue(PackageMigrations::pendingAuto(...), [], report: false);
    }

    /**
     * What only `php artisan finisterre:update` can settle: migrations of earlier
     * versions that were never published, or were published and never run. Reads
     * the application's migrations and schema dumps, so it is for the settings
     * page rather than for every request.
     *
     * @return list<string>
     */
    public static function needsConsole(): array
    {
        return rescue(
            fn(): array => array_values(array_unique([...PackageMigrations::unpublished(), ...PackageMigrations::pending()])),
            [],
            report: false
        );
    }

    /**
     * Run the package's pending migrations and seed the settings this version
     * added. Returns the migrations that are still pending afterwards.
     *
     * @return list<string>
     */
    public static function apply(): array
    {
        // A migration that fails is reported and left pending: the page says which
        // one, instead of answering the click with an error page.
        if (self::appliable() !== []) {
            rescue(function(): void {
                $exitCode = Artisan::call('migrate', [
                    '--path'     => [PackageMigrations::autoPath()],
                    '--realpath' => true,
                    '--force'    => true,
                ]);

                // A migrate that fails without throwing says why in its output only.
                throw_if($exitCode !== 0, RuntimeException::class, 'Finisterre migrations failed: ' . trim(Artisan::output()));
            });

            // The followers table may be there now.
            TaskFollowers::forgetTable();
        }

        rescue(SettingsConfig::seedMissing(...), report: false);

        return self::appliable();
    }
}
