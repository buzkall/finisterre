<?php

namespace Arzcode\Finisterre\Filament\Resources;

use Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Pages;
use Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Schemas\Form as EventForm;
use Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Schemas\Table as EventTable;
use Arzcode\Finisterre\Models\FinisterreEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinisterreEventResource extends Resource
{
    protected static ?string $model = FinisterreEvent::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function canAccess(): bool
    {
        return (bool)config('finisterre.active', false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool)config('finisterre.active', false);
    }

    public static function getModelLabel(): string
    {
        return __('finisterre::finisterre.events.event');
    }

    public static function getPluralLabel(): ?string
    {
        return __('finisterre::finisterre.events.events');
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinisterreEvents::route('/'),
            'create' => Pages\CreateFinisterreEvent::route('/create'),
            'edit'   => Pages\EditFinisterreEvent::route('/{record}/edit'),
        ];
    }
}
