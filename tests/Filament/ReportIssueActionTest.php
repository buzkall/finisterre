<?php

use Arzcode\Finisterre\Filament\Actions\ReportIssueAction;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;
use Livewire\Livewire;
use Workbench\App\Models\User;

class ReportIssueHost extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function reportIssueAction(): Action
    {
        return ReportIssueAction::make();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('stores what was typed as escaped paragraphs', function() {
    Notification::fake();
    $this->actingAs(User::factory()->create());

    Livewire::test(ReportIssueHost::class)
        ->callAction('reportIssue', [
            'title'       => 'Broken page',
            'description' => "It <b>fails</b> <img src=x onerror=alert(1)>\non save\n\nSecond paragraph & more",
        ])
        ->assertHasNoActionErrors();

    $task = FinisterreTask::where('title', 'Broken page')->firstOrFail();

    // Escaped, and then sanitized as every description is when saved.
    expect($task->description)->toBe('<p>It &lt;b&gt;fails&lt;/b&gt; &lt;img src&#61;x onerror&#61;alert(1)&gt;<br />' . "\n" . 'on save</p><p>Second paragraph &amp; more</p>');
});
