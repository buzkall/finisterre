<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The tags field shared by the task form and the quick "tags" action on the
 * task page, so both offer the same options and create tags the same way.
 */
class TagsSelect
{
    public static function make(string $name = 'tags'): Select
    {
        return Select::make($name)
            ->label(__('finisterre::finisterre.tags'))
            ->multiple()
            // Avoid ->relationship() here: on PostgreSQL it triggers
            // `select distinct "tags".*` through the MorphToMany pivot,
            // which fails on the json `name`/`slug` columns (no equality operator).
            ->options(fn() => FinisterreTag::withType('tasks')->get()->pluck('name', 'id'))
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')
                    ->label(__('finisterre::finisterre.tags'))
                    ->required(),
            ])
            ->createOptionUsing(
                fn(array $data) => FinisterreTag::findOrCreateFromString($data['name'], 'tasks')->getKey()
            )
            ->createOptionAction(fn(Action $action) => $action->extraModalFooterActions([]));
    }

    /**
     * Same persistence the form uses. Syncing a pivot never bumps updated_at,
     * so touch the task like an ordinary edit would; the observer ignores
     * updated_at-only saves, so no notification goes out for a tag change.
     *
     * @param  array<int|string>|null  $ids
     */
    public static function persist(FinisterreTask $record, ?array $ids): void
    {
        $record->tags()->sync($ids ?? []);
        $record->touch();
        $record->unsetRelation('tags');
    }
}
