<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;
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
            'finisterre.environments'                        => Typed::string(config('finisterre.environments', '')),
            'finisterre.slug'                                => config('finisterre.slug', 'tasks'),
            'finisterre.hidden_statuses'                     => config('finisterre.hidden_statuses', []),
            'finisterre.fallback_notifiable_id'              => Typed::int(config('finisterre.fallback_notifiable_id', 1)),
            'finisterre.authenticatable_filter_column'       => config('finisterre.authenticatable_filter_column', ''),
            'finisterre.authenticatable_filter_value'        => config('finisterre.authenticatable_filter_value', ''),
            'finisterre.exclude_from_global_search'          => (bool)config('finisterre.exclude_from_global_search', true),
            'finisterre.subtasks_notify'                     => (bool)config('finisterre.subtasks.notify', true),
            'finisterre.subtasks_notification_delay_minutes' => Typed::int(config('finisterre.subtasks.notification_delay_minutes', 5)),
            'finisterre.mail_history_entries'                => config('finisterre.mail.history_entries'),
            'finisterre.comments_display_avatars'            => (bool)config('finisterre.comments.display_avatars', true),
            'finisterre.comments_icon_action'                => config('finisterre.comments.icons.action', 'heroicon-o-chat-bubble-left-right'),
            'finisterre.comments_icon_delete'                => config('finisterre.comments.icons.delete', 'heroicon-o-trash'),
            'finisterre.comments_icon_empty'                 => config('finisterre.comments.icons.empty', 'heroicon-o-chat-bubble-left-right'),
            'finisterre.sms_enabled'                         => (bool)config('finisterre.sms_notification.enabled', false),
            'finisterre.sms_url'                             => config('finisterre.sms_notification.url', 'https://api.smsarena.es/http/sms.php'),
            'finisterre.sms_auth_key'                        => config('finisterre.sms_notification.auth_key'),
            'finisterre.sms_sender'                          => config('finisterre.sms_notification.sender'),
            'finisterre.sms_notify_to'                       => config('finisterre.sms_notification.notify_to'),
            'finisterre.sms_notify_priorities'               => collect(Arr::wrap(config('finisterre.sms_notification.notify_priorities', [TaskPriorityEnum::Urgent])))
                ->map(fn($priority) => $priority instanceof TaskPriorityEnum ? $priority->value : $priority)
                ->values()
                ->all(),
            'finisterre.inbound_enabled'               => (bool)config('finisterre.mail.inbound.enabled', false),
            'finisterre.inbound_driver'                => Typed::string(config('finisterre.mail.inbound.driver', 'imap')),
            'finisterre.inbound_reply_address'         => Typed::string(config('finisterre.mail.inbound.reply_address', '')),
            'finisterre.inbound_plus_addressing'       => (bool)config('finisterre.mail.inbound.plus_addressing', false),
            'finisterre.inbound_imap_host'             => Typed::string(config('finisterre.mail.inbound.imap.host', '')),
            'finisterre.inbound_imap_port'             => Typed::int(config('finisterre.mail.inbound.imap.port', 993)),
            'finisterre.inbound_imap_encryption'       => Typed::string(config('finisterre.mail.inbound.imap.encryption', 'ssl')),
            'finisterre.inbound_imap_username'         => Typed::string(config('finisterre.mail.inbound.imap.username', '')),
            'finisterre.inbound_imap_password'         => config('finisterre.mail.inbound.imap.password') ?: null,
            'finisterre.inbound_imap_folder'           => Typed::string(config('finisterre.mail.inbound.imap.folder', 'INBOX')),
            'finisterre.inbound_resend_api_key'        => config('finisterre.mail.inbound.resend.api_key') ?: null,
            'finisterre.inbound_resend_webhook_secret' => config('finisterre.mail.inbound.resend.webhook_secret') ?: null,
        ];
    }

    /**
     * Whether a stored setting is kept encrypted, so seeding encrypts its default too.
     */
    public static function isEncrypted(string $property): bool
    {
        return in_array(str($property)->after('finisterre.')->toString(), FinisterreSettings::encrypted(), true);
    }

    /**
     * Settings properties that have no stored row yet.
     *
     * Either the settings were never seeded, or — after upgrading the package —
     * this version declares settings the stored ones predate.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $migrator = app(SettingsMigrator::class);

        return array_values(array_filter(
            array_keys(self::defaults()),
            fn(string $property): bool => ! $migrator->exists($property)
        ));
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
        $defaults = self::defaults();

        $created = 0;

        foreach (self::missing() as $property) {
            try {
                $migrator->add($property, $defaults[$property], self::isEncrypted($property));
                $created++;
            } catch (SettingAlreadyExists|UniqueConstraintViolationException) {
                // Another request seeded it first: this also runs while the panel
                // boots, and two page loads can get here together.
            }
        }

        if ($created > 0) {
            self::forgetLoaded();
        }

        return $created;
    }

    /**
     * Overwrite the runtime config('finisterre.*') values with the ones stored
     * in the database. This lets every existing config() read keep working
     * unchanged while the settings page becomes the source of truth.
     *
     * A version of the package that adds a setting finds no row for it in a host
     * that upgraded, and the settings refuse to load without it. Those rows are
     * seeded from the config defaults on the spot (see heal()), so upgrading never
     * turns Finisterre off. Only when nothing is stored at all (a fresh install
     * whose settings have not been migrated yet) does it stay inactive.
     */
    public static function apply(): void
    {
        try {
            self::applyStored();
        } catch (Throwable) {
            if (self::heal()) {
                return;
            }

            // Settings are missing (not migrated / not seeded yet) — the plugin
            // isn't really installed, so stay inactive UNLESS an explicit
            // environment list was configured via env/config. An empty list here
            // means "not configured", not "active everywhere" (that only applies
            // once the settings row exists), so we don't activate a half-installed
            // plugin and 500 the panel on its not-yet-registered routes.
            $environments = Typed::string(config('finisterre.environments', ''));

            config(['finisterre.active' => $environments !== '' && self::isActiveForEnvironments($environments)]);
        }
    }

    /**
     * Seed the settings a newer version added and load them again. False when
     * there is nothing to build on: no settings table, or none of Finisterre's
     * rows in it.
     */
    protected static function heal(): bool
    {
        try {
            if (count(self::missing()) === count(self::defaults())) {
                return false;
            }

            self::seedMissing();
            self::forgetLoaded();
            self::applyStored();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Drop the settings object held in the container and in spatie's cache, both
     * loaded before the missing rows existed.
     */
    protected static function forgetLoaded(): void
    {
        app()->forgetInstance(FinisterreSettings::class);

        $cache = app(SettingsCacheFactory::class)->build(FinisterreSettings::repository());

        if ($cache->isEnabled()) {
            $cache->clear();
        }
    }

    protected static function applyStored(): void
    {
        $settings = app(FinisterreSettings::class);

        config([
            'finisterre.environments'                        => $settings->environments,
            'finisterre.active'                              => self::isActiveForEnvironments($settings->environments),
            'finisterre.slug'                                => $settings->slug,
            'finisterre.hidden_statuses'                     => $settings->hidden_statuses,
            'finisterre.fallback_notifiable_id'              => $settings->fallback_notifiable_id,
            'finisterre.authenticatable_filter_column'       => $settings->authenticatable_filter_column,
            'finisterre.authenticatable_filter_value'        => $settings->authenticatable_filter_value,
            'finisterre.exclude_from_global_search'          => $settings->exclude_from_global_search,
            'finisterre.subtasks.notify'                     => $settings->subtasks_notify,
            'finisterre.subtasks.notification_delay_minutes' => $settings->subtasks_notification_delay_minutes,
            'finisterre.mail.history_entries'                => $settings->mail_history_entries,
            'finisterre.comments.display_avatars'            => $settings->comments_display_avatars,
            'finisterre.comments.icons.action'               => $settings->comments_icon_action,
            'finisterre.comments.icons.delete'               => $settings->comments_icon_delete,
            'finisterre.comments.icons.empty'                => $settings->comments_icon_empty,
            'finisterre.sms_notification.enabled'            => $settings->sms_enabled,
            'finisterre.sms_notification.url'                => $settings->sms_url,
            'finisterre.sms_notification.auth_key'           => $settings->sms_auth_key,
            'finisterre.sms_notification.sender'             => $settings->sms_sender,
            'finisterre.sms_notification.notify_to'          => $settings->sms_notify_to,
            'finisterre.sms_notification.notify_priorities'  => collect($settings->sms_notify_priorities)
                ->map(fn($value) => TaskPriorityEnum::tryFrom($value))
                ->filter()
                ->values()
                ->all(),
            'finisterre.mail.inbound.enabled'               => $settings->inbound_enabled,
            'finisterre.mail.inbound.driver'                => $settings->inbound_driver,
            'finisterre.mail.inbound.reply_address'         => $settings->inbound_reply_address,
            'finisterre.mail.inbound.plus_addressing'       => $settings->inbound_plus_addressing,
            'finisterre.mail.inbound.imap.host'             => $settings->inbound_imap_host,
            'finisterre.mail.inbound.imap.port'             => $settings->inbound_imap_port,
            'finisterre.mail.inbound.imap.encryption'       => $settings->inbound_imap_encryption,
            'finisterre.mail.inbound.imap.username'         => $settings->inbound_imap_username,
            'finisterre.mail.inbound.imap.password'         => $settings->inbound_imap_password,
            'finisterre.mail.inbound.imap.folder'           => $settings->inbound_imap_folder,
            'finisterre.mail.inbound.resend.api_key'        => $settings->inbound_resend_api_key,
            'finisterre.mail.inbound.resend.webhook_secret' => $settings->inbound_resend_webhook_secret,
        ]);
    }

    /**
     * Decide whether Finisterre is active for the current app environment.
     *
     * The stored value is a comma-separated list of environments (e.g.
     * "local,production"). An empty list means "active everywhere".
     */
    protected static function isActiveForEnvironments(string $environments): bool
    {
        $list = array_values(array_filter(array_map(trim(...), explode(',', $environments))));

        return $list === [] || app()->environment($list);
    }
}
