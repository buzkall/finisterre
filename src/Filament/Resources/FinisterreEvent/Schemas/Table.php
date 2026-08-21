<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Schemas;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table as FilamentTable;

class Table
{
    public static function configure(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->badge(),

                TextColumn::make('scheduled_start_at')
                    ->label(__('finisterre::finisterre.events.scheduled_start_at'))
                    ->dateTime('d/m/y H:i')
                    ->sortable(),

                TextColumn::make('attendees_count')
                    ->label(__('finisterre::finisterre.events.attendees'))
                    ->counts('attendees'),

                TextColumn::make('creator.name')
                    ->label(__('finisterre::finisterre.created_by'))
                    ->state(fn($record) => $record->creatorName()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->options(EventStatusEnum::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
