<?php

namespace Buzkall\Finisterre\Support;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Settings\FinisterreSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Throwable;

class SettingsConfig
{
    /**
     * Default value for every stored setting, derived from the config file.
     *
     * Single source of truth shared by the settings migration (initial seed)
     * and the installer's idempotent reseed, so the two never drift apart.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'finisterre.environments'                  => (string)config('finisterre.environments', ''),
            'finisterre.slug'                          => config('finisterre.slug', 'tasks'),
            'finisterre.hidden_statuses'               => config('finisterre.hidden_statuses', []),
            'finisterre.fallback_notifiable_id'        => (int)config('finisterre.fallback_notifiable_id', 1),
            'finisterre.authenticatable_filter_column' => config('finisterre.authenticatable_filter_column', ''),
            'finisterre.authenticatable_filter_value'  => config('finisterre.authenticatable_filter_value', ''),
            'finisterre.comments_display_avatars'      => (bool)config('finisterre.comments.display_avatars', true),
            'finisterre.comments_icon_action'          => config('finisterre.comments.icons.action', 'heroicon-o-chat-bubble-left-right'),
            'finisterre.comments_icon_delete'          => config('finisterre.comments.icons.delete', 'heroicon-o-trash'),
            'finisterre.comments_icon_empty'           => config('finisterre.comments.icons.empty', 'heroicon-o-chat-bubble-left-right'),
            'finisterre.sms_enabled'                   => (bool)config('finisterre.sms_notification.enabled', false),
            'finisterre.sms_url'                       => config('finisterre.sms_notification.url', 'https://api.smsarena.es/http/sms.php'),
            'finisterre.sms_auth_key'                  => config('finisterre.sms_notification.auth_key'),
            'finisterre.sms_sender'                    => config('finisterre.sms_notification.sender'),
            'finisterre.sms_notify_to'                 => config('finisterre.sms_notification.notify_to'),
            'finisterre.sms_notify_priorities'         => collect(config('finisterre.sms_notification.notify_priorities', [TaskPriorityEnum::Urgent]))
                ->map(fn($priority) => $priority instanceof TaskPriorityEnum ? $priority->value : $priority)
                ->values()
                ->all(),
        ];
    }

    /**
     * Create any missing finisterre settings rows from the config defaults.
     *
     * The settings migration normally seeds these, but it only runs once: if the
     * rows are later removed (e.g. by the uninstaller, which leaves the migration
     * record in place) a plain `migrate` won't recreate them. This reseeds the
     * gaps idempotently so the installer can always load and configure settings.
     *
     * @return int number of rows created
     */
    public static function seedMissing(): int
    {
        $migrator = app(SettingsMigrator::class);

        $created = 0;

        foreach (self::defaults() as $property => $value) {
            if (! $migrator->exists($property)) {
                $migrator->add($property, $value);
                $created++;
            }
        }

        if ($created > 0) {
            app()->forgetInstance(FinisterreSettings::class);
        }

        return $created;
    }

    /**
     * Overwrite the runtime config('finisterre.*') values with the ones stored
     * in the database. This lets every existing config() read keep working
     * unchanged while the settings page becomes the source of truth.
     *
     * Wrapped in a try/catch so a fresh install whose settings table has not
     * been migrated yet simply falls back to the config-file defaults.
     */
    public static function apply(): void
    {
        try {
            $settings = app(FinisterreSettings::class);

            config([
                'finisterre.environments'                       => $settings->environments,
                'finisterre.active'                             => self::isActiveForEnvironments($settings->environments),
                'finisterre.slug'                               => $settings->slug,
                'finisterre.hidden_statuses'                    => $settings->hidden_statuses,
                'finisterre.fallback_notifiable_id'             => $settings->fallback_notifiable_id,
                'finisterre.authenticatable_filter_column'      => $settings->authenticatable_filter_column,
                'finisterre.authenticatable_filter_value'       => $settings->authenticatable_filter_value,
                'finisterre.comments.display_avatars'           => $settings->comments_display_avatars,
                'finisterre.comments.icons.action'              => $settings->comments_icon_action,
                'finisterre.comments.icons.delete'              => $settings->comments_icon_delete,
                'finisterre.comments.icons.empty'               => $settings->comments_icon_empty,
                'finisterre.sms_notification.enabled'           => $settings->sms_enabled,
                'finisterre.sms_notification.url'               => $settings->sms_url,
                'finisterre.sms_notification.auth_key'          => $settings->sms_auth_key,
                'finisterre.sms_notification.sender'            => $settings->sms_sender,
                'finisterre.sms_notification.notify_to'         => $settings->sms_notify_to,
                'finisterre.sms_notification.notify_priorities' => collect($settings->sms_notify_priorities)
                    ->map(fn($value) => TaskPriorityEnum::tryFrom($value))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        } catch (Throwable) {
            // Settings are missing (not migrated / not seeded yet) — the plugin
            // isn't really installed, so stay inactive UNLESS an explicit
            // environment list was configured via env/config. An empty list here
            // means "not configured", not "active everywhere" (that only applies
            // once the settings row exists), so we don't activate a half-installed
            // plugin and 500 the panel on its not-yet-registered routes.
            $environments = (string)config('finisterre.environments', '');

            config(['finisterre.active' => $environments !== '' && self::isActiveForEnvironments($environments)]);
        }
    }

    /**
     * Decide whether Finisterre is active for the current app environment.
     *
     * The stored value is a comma-separated list of environments (e.g.
     * "local,production"). An empty list means "active everywhere".
     */
    protected static function isActiveForEnvironments(string $environments): bool
    {
        $list = array_values(array_filter(array_map('trim', explode(',', $environments))));

        return $list === [] || app()->environment($list);
    }
}
