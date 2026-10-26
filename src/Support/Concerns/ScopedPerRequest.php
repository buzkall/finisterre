<?php

namespace Arzcode\Finisterre\Support\Concerns;

/**
 * One instance for the length of a request or queued job: the container drops scoped
 * instances between them, so an Octane worker or a queue worker starts each one
 * empty. Bound on first use rather than by the service provider, so it also works
 * where the provider is not booted.
 */
trait ScopedPerRequest
{
    public static function current(): self
    {
        if (! app()->bound(self::class)) {
            app()->scoped(self::class);
        }

        return app(self::class);
    }
}
