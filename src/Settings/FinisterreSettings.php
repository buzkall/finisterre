<?php

namespace Arzcode\Finisterre\Settings;

use Spatie\LaravelSettings\Settings;

class FinisterreSettings extends Settings
{
    public string $environments;
    public string $slug;

    /** @var array<int, string> */
    public array $hidden_statuses;

    public int $fallback_notifiable_id;
    public string $authenticatable_filter_column;
    public string $authenticatable_filter_value;
    public bool $comments_display_avatars;
    public string $comments_icon_action;
    public string $comments_icon_delete;
    public string $comments_icon_empty;
    public bool $sms_enabled;
    public string $sms_url;
    public ?string $sms_auth_key;
    public ?string $sms_sender;
    public ?string $sms_notify_to;

    /** @var array<int, string> */
    public array $sms_notify_priorities;

    public static function group(): string
    {
        return 'finisterre';
    }
}
