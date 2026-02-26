<?php

namespace Buzkall\Finisterre\Filament\Resources;

use BackedEnum;
use Buzkall\Finisterre\Filament\Resources\FinisterreTask\Pages;
use Buzkall\Finisterre\FinisterrePlugin;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinisterreTaskResource extends Resource
{
    protected static ?string $model = FinisterreTask::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function shouldRegisterNavigation(): bool
    {
        return FinisterrePlugin::get()->canViewOnlyTheirTasks();
    }

    public static function getModelLabel(): string
    {
        return FinisterrePlugin::get()->canViewOnlyTheirTasks() ?
            __('finisterre::finisterre.task_report') :
            __('finisterre.task');
    }

    public static function getPluralLabel(): ?string
    {
        return FinisterrePlugin::get()->canViewOnlyTheirTasks() ?
            __('finisterre::finisterre.task_reports') :
            __('finisterre.tasks');
    }

    public static function form(Schema $schema): Schema
    {
        return FinisterreTaskResource\Form::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinisterreTaskResource\Table::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinisterreTasks::route('/'),
            'create' => Pages\CreateFinisterreTask::route('/create'),
            'edit'   => Pages\EditFinisterreTask::route('/{record}/edit'),
        ];
    }
}
