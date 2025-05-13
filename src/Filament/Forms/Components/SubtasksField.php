<?php

namespace Buzkall\Finisterre\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class SubtasksField extends Field
{
    protected string $view = 'finisterre::forms.components.subtasks-field';

    public function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(static function(SubtasksField $component, $state): void {
            $component->state($state ?? []);
        });

        // this is the one that saves the subtasks in the db
        $this->dehydrateStateUsing(function($state) {
            return $state ?? [];
        });
    }
}
