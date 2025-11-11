<?php

namespace Buzkall\Finisterre\Filament\Livewire;

use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Tags\Tag;

/**
 * @property ComponentContainer $form
 */
class FilterTasks extends Component implements HasForms
{
    use InteractsWithForms;

    #[Url]
    public ?string $filter_text = null;

    #[Url]
    public array $filter_tags = [];

    #[Url]
    public ?int $filter_assignee = null;

    #[Url]
    public bool $filter_show_archived = false;

    public function mount(): void
    {
        // Load from session if URL params are empty
        if (empty($this->filter_text) && empty($this->filter_tags) && empty($this->filter_assignee)) {
            $sessionFilters = session('finisterre.filters', []);
            $this->filter_text = $sessionFilters['filter_text'] ?? null;
            $this->filter_tags = $sessionFilters['filter_tags'] ?? [];
            $this->filter_assignee = $sessionFilters['filter_assignee'] ?? null;
            $this->filter_show_archived = $sessionFilters['filter_show_archived'] ?? false;
        }

        $filters = $this->getFilters();
        $this->form->fill($filters);
        $this->dispatch('filtersUpdated', $filters);
    }

    private function getFilters(): array
    {
        return [
            'filter_text'          => $this->filter_text,
            'filter_tags'          => $this->filter_tags,
            'filter_assignee'      => $this->filter_assignee,
            'filter_show_archived' => $this->filter_show_archived,
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('filter_text')
                        ->label(__('finisterre::finisterre.filter.text'))
                        ->placeholder(__('finisterre::finisterre.filter.text_description'))
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn() => $this->dispatchFilters()),

                    Select::make('filter_tags')
                        ->multiple()
                        ->label(__('finisterre::finisterre.tags'))
                        ->options(fn() => Tag::withType('tasks')->pluck('name', 'id'))
                        ->live()
                        ->afterStateUpdated(fn() => $this->dispatchFilters()),

                    Select::make('filter_assignee')
                        ->label(__('finisterre::finisterre.filter.assignee'))
                        ->options(
                            fn() => FinisterreTask::query()
                                ->distinct('assignee_id')
                                ->with('assignee')
                                ->get()
                                ->pluck('assignee.name', 'assignee.id')
                        )
                        ->live()
                        ->afterStateUpdated(fn() => $this->dispatchFilters()),

                    Toggle::make('filter_show_archived')
                        ->label(__('finisterre::finisterre.filter.show_archived'))
                        ->inline(false)
                        ->live()
                        ->afterStateUpdated(fn() => $this->dispatchFilters())
                ])
                ->columns(4)
                ->compact()
                ->extraAttributes([
                    'x-data' => '{}',
                    'class'  => 'relative'
                ])
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['filter_text', 'filter_tags', 'filter_assignee', 'filter_show_archived']);

        $this->form->fill($this->getFilters());

        // Clear session
        session()->forget('finisterre.filters');

        $this->dispatchFilters();
    }

    protected function dispatchFilters(): void
    {
        $filters = $this->getFilters();

        // Persist to session
        session(['finisterre.filters' => $filters]);

        $this->dispatch('filtersUpdated', $filters);
    }

    public function render(): View
    {
        return view('finisterre::forms.filter-tasks');
    }
}
