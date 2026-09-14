<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table as FilamentTable;

class Table
{
    public static function configure(FilamentTable $table): FilamentTable
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('coverMedia'))
            ->columns([
                // Computed from the media library, so sorting is turned off explicitly:
                // a host may switch it on for every column in its AppServiceProvider.
                ImageColumn::make('cover')
                    ->label(__('finisterre::finisterre.card_image'))
                    ->state(fn(FinisterreTask $record): ?string => $record->coverUrl())
                    ->sortable(false),

                TextColumn::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->badge(),

                TextColumn::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->badge(),

                TextColumn::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->dateTime('d/m/y H:i:s'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
