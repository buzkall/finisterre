<?php

namespace Buzkall\Finisterre\Filament\Pages;

use BackedEnum;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Settings\FinisterreSettings;
use Buzkall\Finisterre\Traits\HasIconOptions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Schema $form
 */
class ManageFinisterreSettings extends Page
{
    use HasIconOptions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;
    protected static ?string $slug = 'finisterre-settings';
    protected string $view = 'finisterre::filament.pages.finisterre-settings';
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

        $this->form->fill([
            'environments'                  => $settings->environments,
            'slug'                          => $settings->slug,
            'hidden_statuses'               => $settings->hidden_statuses,
            'fallback_notifiable_id'        => $settings->fallback_notifiable_id,
            'authenticatable_filter_column' => $settings->authenticatable_filter_column,
            'authenticatable_filter_value'  => $settings->authenticatable_filter_value,
            'comments_display_avatars'      => $settings->comments_display_avatars,
            'comments_icon_action'          => $settings->comments_icon_action,
            'comments_icon_delete'          => $settings->comments_icon_delete,
            'comments_icon_empty'           => $settings->comments_icon_empty,
            'sms_enabled'                   => $settings->sms_enabled,
            'sms_url'                       => $settings->sms_url,
            'sms_auth_key'                  => $settings->sms_auth_key,
            'sms_sender'                    => $settings->sms_sender,
            'sms_notify_to'                 => $settings->sms_notify_to,
            'sms_notify_priorities'         => $settings->sms_notify_priorities,
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
                            ->label(__('finisterre::finisterre.settings.authenticatable_filter_value')),
                    ])
                    ->columns(2),

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
                            ->columnSpanFull(),

                        TextInput::make('sms_auth_key')
                            ->label(__('finisterre::finisterre.settings.sms_auth_key'))
                            ->password()
                            ->revealable()
                            ->visible(fn(Get $get): bool => (bool)$get('sms_enabled')),

                        Grid::make()->columns(2)->schema([
                            TextInput::make('sms_sender')
                                ->label(__('finisterre::finisterre.settings.sms_sender'))
                                ->visible(fn(Get $get): bool => (bool)$get('sms_enabled')),

                            TextInput::make('sms_notify_to')
                                ->label(__('finisterre::finisterre.settings.sms_notify_to'))
                                ->visible(fn(Get $get): bool => (bool)$get('sms_enabled')),
                        ]),

                        CheckboxList::make('sms_notify_priorities')
                            ->label(__('finisterre::finisterre.settings.sms_notify_priorities'))
                            ->helperText(__('finisterre::finisterre.settings.sms_notify_priorities_help'))
                            ->options(collect(TaskPriorityEnum::cases())
                                ->mapWithKeys(fn(TaskPriorityEnum $priority) => [$priority->value => $priority->getLabel()])
                                ->all())
                            ->columns(2)
                            ->visible(fn(Get $get): bool => (bool)$get('sms_enabled'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(FinisterreSettings::class);

        $settings->environments = (string)$data['environments'];
        $settings->slug = $data['slug'];
        $settings->hidden_statuses = $data['hidden_statuses'] ?? [];
        $settings->fallback_notifiable_id = (int)$data['fallback_notifiable_id'];
        $settings->authenticatable_filter_column = (string)$data['authenticatable_filter_column'];
        $settings->authenticatable_filter_value = (string)$data['authenticatable_filter_value'];
        $settings->comments_display_avatars = (bool)$data['comments_display_avatars'];
        $settings->comments_icon_action = $data['comments_icon_action'];
        $settings->comments_icon_delete = $data['comments_icon_delete'];
        $settings->comments_icon_empty = $data['comments_icon_empty'];
        $settings->sms_enabled = (bool)$data['sms_enabled'];
        $settings->sms_url = $data['sms_url'];
        $settings->sms_auth_key = $data['sms_auth_key'] ?: null;
        $settings->sms_sender = $data['sms_sender'] ?: null;
        $settings->sms_notify_to = $data['sms_notify_to'] ?: null;
        $settings->sms_notify_priorities = $data['sms_notify_priorities'] ?? [];

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
        /** @var class-string<Model> $model */
        $model = config('finisterre.authenticatable');

        $attribute = (array)config('finisterre.authenticatable_attribute', 'name');
        $column = $attribute[0] ?? 'name';

        return $model::query()
            ->get()
            ->mapWithKeys(fn(Model $user): array => [
                $user->getKey() => method_exists($user, 'getUserDisplayName')
                    ? $user->getUserDisplayName()
                    : (string)$user->getAttribute($column),
            ])
            ->all();
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
