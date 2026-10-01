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
    public bool $exclude_from_global_search;
    public bool $subtasks_notify;
    public int $subtasks_notification_delay_minutes;
    public ?int $mail_history_entries = null;
    public bool $comments_display_avatars;
    public string $comments_icon_action;
    public string $comments_icon_delete;
    public string $comments_icon_empty;
    public bool $sms_enabled;
    public string $sms_url;
    public ?string $sms_auth_key = null;
    public ?string $sms_sender = null;
    public ?string $sms_notify_to = null;

    /** @var array<int, string> */
    public array $sms_notify_priorities;

    public bool $inbound_enabled;
    public string $inbound_driver;
    public string $inbound_reply_address;
    public bool $inbound_plus_addressing;
    public string $inbound_imap_host;
    public int $inbound_imap_port;
    public string $inbound_imap_encryption;
    public string $inbound_imap_username;
    public ?string $inbound_imap_password = null;
    public string $inbound_imap_folder;
    public ?string $inbound_resend_api_key = null;
    public ?string $inbound_resend_webhook_secret = null;

    public static function group(): string
    {
        return 'finisterre';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['inbound_imap_password', 'inbound_resend_api_key', 'inbound_resend_webhook_secret'];
    }
}
