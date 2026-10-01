<?php

namespace Arzcode\Finisterre\Filament\Pages;

use Arzcode\Finisterre\Controllers\ResendInboundController;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use Arzcode\Finisterre\Traits\HasIconOptions;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property-read Schema $form
 */
class ManageFinisterreSettings extends Page
{
    use HasIconOptions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;
    protected static ?string $slug = 'finisterre-settings';
    protected string $view = 'finisterre::filament.pages.finisterre-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return FinisterrePlugin::get()->canConfigure();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from the menu — opened via a header action on the Kanban board.
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('finisterre::finisterre.settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('finisterre::finisterre.settings.title');
    }

    public function mount(): void
    {
        $settings = app(FinisterreSettings::class);

        // Secrets (the SMS key, the IMAP password, the Resend key and webhook secret) are
        // left out: the form state is sent to the browser. A blank field keeps them.
        $this->form->fill([
            'environments'                        => $settings->environments,
            'slug'                                => $settings->slug,
            'hidden_statuses'                     => $settings->hidden_statuses,
            'fallback_notifiable_id'              => $settings->fallback_notifiable_id,
            'authenticatable_filter_column'       => $settings->authenticatable_filter_column,
            'authenticatable_filter_value'        => $settings->authenticatable_filter_value,
            'exclude_from_global_search'          => $settings->exclude_from_global_search,
            'subtasks_notify'                     => $settings->subtasks_notify,
            'subtasks_notification_delay_minutes' => $settings->subtasks_notification_delay_minutes,
            'mail_history_entries'                => $settings->mail_history_entries,
            'comments_display_avatars'            => $settings->comments_display_avatars,
            'comments_icon_action'                => $settings->comments_icon_action,
            'comments_icon_delete'                => $settings->comments_icon_delete,
            'comments_icon_empty'                 => $settings->comments_icon_empty,
            'sms_enabled'                         => $settings->sms_enabled,
            'sms_url'                             => $settings->sms_url,
            'sms_sender'                          => $settings->sms_sender,
            'sms_notify_to'                       => $settings->sms_notify_to,
            'sms_notify_priorities'               => $settings->sms_notify_priorities,
            'inbound_enabled'                     => $settings->inbound_enabled,
            'inbound_driver'                      => $settings->inbound_driver,
            'inbound_reply_address'               => $settings->inbound_reply_address,
            'inbound_plus_addressing'             => $settings->inbound_plus_addressing,
            'inbound_imap_host'                   => $settings->inbound_imap_host,
            'inbound_imap_port'                   => $settings->inbound_imap_port,
            'inbound_imap_encryption'             => $settings->inbound_imap_encryption,
            'inbound_imap_username'               => $settings->inbound_imap_username,
            'inbound_imap_folder'                 => $settings->inbound_imap_folder,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('finisterre::finisterre.settings.section_general'))
                    ->schema([
                        TextInput::make('environments')
                            ->label(__('finisterre::finisterre.settings.environments'))
                            ->helperText(__('finisterre::finisterre.settings.environments_help'))
                            ->placeholder('local,production'),

                        TextInput::make('slug')
                            ->label(__('finisterre::finisterre.settings.slug'))
                            ->helperText(__('finisterre::finisterre.settings.slug_help'))
                            ->required(),

                        Toggle::make('exclude_from_global_search')
                            ->label(__('finisterre::finisterre.settings.exclude_from_global_search'))
                            ->helperText(__('finisterre::finisterre.settings.exclude_from_global_search_help'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('finisterre::finisterre.settings.section_tasks'))
                    ->schema([
                        CheckboxList::make('hidden_statuses')
                            ->label(__('finisterre::finisterre.settings.hidden_statuses'))
                            ->helperText(__('finisterre::finisterre.settings.hidden_statuses_help'))
                            ->options(collect(TaskStatusEnum::cases())
                                ->mapWithKeys(fn(TaskStatusEnum $status) => [$status->value => $status->getLabel()])
                                ->all())
                            ->columns(2)
                            ->columnSpanFull(),

                        Select::make('fallback_notifiable_id')
                            ->label(__('finisterre::finisterre.settings.fallback_notifiable_id'))
                            ->helperText(__('finisterre::finisterre.settings.fallback_notifiable_id_help'))
                            ->options(fn(): array => $this->authenticatableOptions())
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('finisterre::finisterre.settings.section_assignable_filter'))
                    ->description(__('finisterre::finisterre.settings.section_assignable_filter_help'))
                    ->schema([
                        TextInput::make('authenticatable_filter_column')
                            ->label(__('finisterre::finisterre.settings.authenticatable_filter_column')),

                        TextInput::make('authenticatable_filter_value')
                            ->label(__('finisterre::finisterre.settings.authenticatable_filter_value'))
                            ->helperText(__('finisterre::finisterre.settings.authenticatable_filter_value_help')),
                    ])
                    ->columns(2),

                Section::make(__('finisterre::finisterre.settings.section_subtasks'))
                    ->schema([
                        Toggle::make('subtasks_notify')
                            ->label(__('finisterre::finisterre.settings.subtasks_notify'))
                            ->helperText(__('finisterre::finisterre.settings.subtasks_notify_help'))
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('subtasks_notification_delay_minutes')
                            ->label(__('finisterre::finisterre.settings.subtasks_notification_delay_minutes'))
                            ->helperText(__('finisterre::finisterre.settings.subtasks_notification_delay_minutes_help'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->visible(fn(Get $get): bool => (bool)$get('subtasks_notify'))
                            ->dehydratedWhenHidden()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('finisterre::finisterre.settings.section_mail'))
                    ->schema([
                        TextInput::make('mail_history_entries')
                            ->label(__('finisterre::finisterre.settings.mail_history_entries'))
                            ->helperText(__('finisterre::finisterre.settings.mail_history_entries_help'))
                            ->integer()
                            ->minValue(0)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('finisterre::finisterre.settings.section_inbound'))
                    ->description(__('finisterre::finisterre.settings.section_inbound_help'))
                    ->schema([
                        Toggle::make('inbound_enabled')
                            ->label(__('finisterre::finisterre.settings.inbound_enabled'))
                            ->live()
                            ->columnSpanFull(),

                        Grid::make()->columns(2)->schema([
                            Select::make('inbound_driver')
                                ->label(__('finisterre::finisterre.settings.inbound_driver'))
                                ->options([
                                    'imap'   => __('finisterre::finisterre.settings.inbound_driver_imap'),
                                    'resend' => __('finisterre::finisterre.settings.inbound_driver_resend'),
                                ])
                                ->native(false)
                                ->required()
                                ->live(),

                            TextInput::make('inbound_reply_address')
                                ->label(__('finisterre::finisterre.settings.inbound_reply_address'))
                                ->helperText(__('finisterre::finisterre.settings.inbound_reply_address_help'))
                                ->email()
                                ->requiredIf('inbound_enabled', true),

                            Toggle::make('inbound_plus_addressing')
                                ->label(__('finisterre::finisterre.settings.inbound_plus_addressing'))
                                ->helperText(__('finisterre::finisterre.settings.inbound_plus_addressing_help'))
                                ->columnSpanFull(),
                        ])
                            ->visible(fn(Get $get): bool => (bool)$get('inbound_enabled')),

                        Grid::make()->columns(2)->schema([
                            TextInput::make('inbound_imap_host')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_host'))
                                ->placeholder('imap.example.com'),

                            TextInput::make('inbound_imap_port')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_port'))
                                ->integer()
                                ->required(),

                            Select::make('inbound_imap_encryption')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_encryption'))
                                ->options([
                                    'ssl'      => 'SSL',
                                    'tls'      => 'TLS',
                                    'starttls' => 'STARTTLS',
                                    'none'     => __('finisterre::finisterre.settings.inbound_imap_encryption_none'),
                                ])
                                ->native(false)
                                ->required(),

                            TextInput::make('inbound_imap_folder')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_folder'))
                                ->placeholder('INBOX'),

                            TextInput::make('inbound_imap_username')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_username')),

                            TextInput::make('inbound_imap_password')
                                ->label(__('finisterre::finisterre.settings.inbound_imap_password'))
                                ->password()
                                ->placeholder(fn(): ?string => $this->secretPlaceholder('inbound_imap_password'))
                                ->revealable(),
                        ])
                            ->visible(fn(Get $get): bool => $get('inbound_enabled') && $get('inbound_driver') === 'imap'),

                        Grid::make()->columns(2)->schema([
                            TextEntry::make('inbound_resend_webhook_url')
                                ->label(__('finisterre::finisterre.settings.inbound_resend_webhook_url'))
                                ->helperText(__('finisterre::finisterre.settings.inbound_resend_webhook_url_help'))
                                ->state(url(ResendInboundController::PATH))
                                ->copyable()
                                ->columnSpanFull(),

                            TextInput::make('inbound_resend_api_key')
                                ->label(__('finisterre::finisterre.settings.inbound_resend_api_key'))
                                ->helperText(__('finisterre::finisterre.settings.inbound_resend_api_key_help'))
                                ->password()
                                ->placeholder(fn(): ?string => $this->secretPlaceholder('inbound_resend_api_key'))
                                ->revealable(),

                            TextInput::make('inbound_resend_webhook_secret')
                                ->label(__('finisterre::finisterre.settings.inbound_resend_webhook_secret'))
                                ->password()
                                ->placeholder(fn(): ?string => $this->secretPlaceholder('inbound_resend_webhook_secret'))
                                ->revealable(),
                        ])
                            ->visible(fn(Get $get): bool => $get('inbound_enabled') && $get('inbound_driver') === 'resend'),
                    ])
                    ->columnSpanFull(),

                Section::make(__('finisterre::finisterre.settings.section_comments'))
                    ->schema([
                        Toggle::make('comments_display_avatars')
                            ->label(__('finisterre::finisterre.settings.comments_display_avatars'))
                            ->columnSpanFull(),

                        $this->heroiconSelect('comments_icon_action', __('finisterre::finisterre.settings.comments_icon_action')),
                        $this->heroiconSelect('comments_icon_delete', __('finisterre::finisterre.settings.comments_icon_delete')),
                        $this->heroiconSelect('comments_icon_empty', __('finisterre::finisterre.settings.comments_icon_empty')),
                    ])
                    ->columns(3),

                Section::make(__('finisterre::finisterre.settings.section_sms'))
                    ->schema([
                        Toggle::make('sms_enabled')
                            ->label(__('finisterre::finisterre.settings.sms_enabled'))
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('sms_url')
                            ->label(__('finisterre::finisterre.settings.sms_url'))
                            ->url()
                            ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                            ->dehydratedWhenHidden()
                            ->columnSpanFull(),

                        TextInput::make('sms_auth_key')
                            ->label(__('finisterre::finisterre.settings.sms_auth_key'))
                            ->password()
                            ->placeholder(fn(): ?string => $this->secretPlaceholder('sms_auth_key'))
                            ->revealable()
                            ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                            ->dehydratedWhenHidden(),

                        Grid::make()->columns(2)->schema([
                            TextInput::make('sms_sender')
                                ->label(__('finisterre::finisterre.settings.sms_sender'))
                                ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                                ->dehydratedWhenHidden(),

                            TextInput::make('sms_notify_to')
                                ->label(__('finisterre::finisterre.settings.sms_notify_to'))
                                ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                                ->dehydratedWhenHidden(),
                        ]),

                        CheckboxList::make('sms_notify_priorities')
                            ->label(__('finisterre::finisterre.settings.sms_notify_priorities'))
                            ->helperText(__('finisterre::finisterre.settings.sms_notify_priorities_help'))
                            ->options(collect(TaskPriorityEnum::cases())
                                ->mapWithKeys(fn(TaskPriorityEnum $priority) => [$priority->value => $priority->getLabel()])
                                ->all())
                            ->columns(2)
                            ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                            ->dehydratedWhenHidden()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(FinisterreSettings::class);

        $settings->environments = Typed::string($data['environments']);
        $settings->slug = Typed::string($data['slug']);
        $settings->hidden_statuses = Typed::strings($data['hidden_statuses'] ?? []);
        $settings->fallback_notifiable_id = Typed::int($data['fallback_notifiable_id']);
        $settings->authenticatable_filter_column = Typed::string($data['authenticatable_filter_column']);
        $settings->authenticatable_filter_value = Typed::string($data['authenticatable_filter_value']);
        $settings->exclude_from_global_search = (bool)$data['exclude_from_global_search'];
        $settings->subtasks_notify = (bool)$data['subtasks_notify'];
        $settings->subtasks_notification_delay_minutes = Typed::int($data['subtasks_notification_delay_minutes']);
        $settings->mail_history_entries = filled($data['mail_history_entries']) ? Typed::int($data['mail_history_entries']) : null;
        $settings->comments_display_avatars = (bool)$data['comments_display_avatars'];
        $settings->comments_icon_action = Typed::string($data['comments_icon_action']);
        $settings->comments_icon_delete = Typed::string($data['comments_icon_delete']);
        $settings->comments_icon_empty = Typed::string($data['comments_icon_empty']);
        $settings->sms_enabled = (bool)$data['sms_enabled'];
        $settings->sms_url = Typed::string($data['sms_url']);
        $settings->sms_auth_key = Typed::nullableString($data['sms_auth_key'] ?? null) ?? $settings->sms_auth_key;
        $settings->sms_sender = Typed::nullableString($data['sms_sender']);
        $settings->sms_notify_to = Typed::nullableString($data['sms_notify_to']);
        $settings->sms_notify_priorities = Typed::strings($data['sms_notify_priorities'] ?? []);
        $settings->inbound_enabled = (bool)$data['inbound_enabled'];
        $settings->inbound_driver = Typed::string($data['inbound_driver'] ?? $settings->inbound_driver);
        $settings->inbound_reply_address = trim(Typed::string($data['inbound_reply_address'] ?? $settings->inbound_reply_address));
        $settings->inbound_plus_addressing = (bool)($data['inbound_plus_addressing'] ?? $settings->inbound_plus_addressing);
        $settings->inbound_imap_host = trim(Typed::string($data['inbound_imap_host'] ?? $settings->inbound_imap_host));
        $settings->inbound_imap_port = Typed::int($data['inbound_imap_port'] ?? $settings->inbound_imap_port);
        $settings->inbound_imap_encryption = Typed::string($data['inbound_imap_encryption'] ?? $settings->inbound_imap_encryption);
        $settings->inbound_imap_username = trim(Typed::string($data['inbound_imap_username'] ?? $settings->inbound_imap_username));
        $settings->inbound_imap_password = Typed::nullableString($data['inbound_imap_password'] ?? null) ?? $settings->inbound_imap_password;
        $settings->inbound_imap_folder = trim(Typed::string($data['inbound_imap_folder'] ?? $settings->inbound_imap_folder)) ?: 'INBOX';
        $settings->inbound_resend_api_key = Typed::nullableString($data['inbound_resend_api_key'] ?? null) ?? $settings->inbound_resend_api_key;
        $settings->inbound_resend_webhook_secret = Typed::nullableString($data['inbound_resend_webhook_secret'] ?? null) ?? $settings->inbound_resend_webhook_secret;

        $settings->save();

        Notification::make()
            ->title(__('finisterre::finisterre.settings.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<int|string, string>
     */
    protected function authenticatableOptions(): array
    {
        $column = Typed::strings(Arr::wrap(config('finisterre.authenticatable_attribute', 'name')))[0] ?? 'name';

        return UserModel::class()::query()
            ->get()
            ->mapWithKeys(fn(Model $user): array => [
                Typed::string($user->getKey()) => method_exists($user, 'getUserDisplayName')
                    ? UserModel::displayName($user)
                    : Typed::string($user->getAttribute($column)),
            ])
            ->all();
    }

    protected function secretPlaceholder(string $name): ?string
    {
        return filled(app(FinisterreSettings::class)->{$name}) ? Typed::string(__('finisterre::finisterre.settings.secret_stored')) : null;
    }

    protected function heroiconSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->required()
            ->native(false)
            ->searchable()
            ->allowHtml()
            ->options(self::getIconOptions())
            ->getOptionLabelUsing(fn(?string $value): ?string => self::iconOptionLabel($value));
    }
}
