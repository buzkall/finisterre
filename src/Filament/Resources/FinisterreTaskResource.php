<?php

namespace Arzcode\Finisterre\Filament\Resources;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\CreateFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\EditFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ListFinisterreTasks;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\Form as TaskForm;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\Infolist as TaskInfolist;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\Table as TaskTable;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\PanelLabel;
use Arzcode\Finisterre\Support\TaskFollowers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FinisterreTaskResource extends Resource
{
    protected static ?string $model = FinisterreTask::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static bool $hasTitleCaseModelLabel = false;
    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool)config('finisterre.active', false);
    }

    public static function canGloballySearch(): bool
    {
        if (config('finisterre.exclude_from_global_search', true)) {
            return false;
        }

        return parent::canGloballySearch();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! config('finisterre.active', false)) {
            return false;
        }

        $plugin = FinisterrePlugin::get();

        // The board is for users who see all tasks. Everybody else finds theirs in
        // this list: the ones they created, and the ones they follow.
        return $plugin->canViewOnlyTheirTasks()
            || (! $plugin->canViewAllTasks() && TaskFollowers::followsAny(auth()->id()));
    }

    public static function getModelLabel(): string
    {
        return PanelLabel::singular();
    }

    public static function getPluralLabel(): ?string
    {
        return PanelLabel::plural();
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    /**
     * The task page shows the tags in its badges and in the modal that edits them.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with('tags');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFinisterreTasks::route('/'),
            'create' => CreateFinisterreTask::route('/create'),
            'view'   => ViewFinisterreTask::route('/{record}'),
            'edit'   => EditFinisterreTask::route('/{record}/edit'),
        ];
    }
}
