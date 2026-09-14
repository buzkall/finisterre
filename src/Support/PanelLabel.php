<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\FinisterrePlugin;
use Throwable;

/**
 * Name given to tasks throughout the panel.
 *
 * `finisterre.label` / `finisterre.plural_label` override it; left null, the
 * translated default is used, which is not the same word for everyone: users
 * restricted to their own tasks see them as issues (task reports).
 */
class PanelLabel
{
    public static function singular(): string
    {
        return self::override('label')
            ?? __(self::forTheirTasksOnly() ? 'finisterre::finisterre.task_report' : 'finisterre::finisterre.task');
    }

    public static function plural(): string
    {
        return self::override('plural_label')
            ?? __(self::forTheirTasksOnly() ? 'finisterre::finisterre.task_reports' : 'finisterre::finisterre.tasks');
    }

    /**
     * The configured label, run through the translator so a translation key can
     * be configured as well as a literal string (`__()` returns the key itself
     * when there is no line for it).
     */
    protected static function override(string $key): ?string
    {
        $label = config('finisterre.' . $key);

        return filled($label) ? (string)__((string)$label) : null;
    }

    /**
     * Labels are also rendered where no Filament panel is bootstrapped (queued
     * notifications, for one), and resolving the plugin throws there — outside
     * a panel nobody is restricted to their own tasks, so fall back to the
     * plain wording instead of blowing up.
     */
    protected static function forTheirTasksOnly(): bool
    {
        try {
            return FinisterrePlugin::get()->canViewOnlyTheirTasks();
        } catch (Throwable) {
            return false;
        }
    }
}
