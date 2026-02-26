<?php

namespace Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
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
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
