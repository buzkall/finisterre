<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\EditFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Arzcode\Finisterre\Support\TaskFollowers;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->user = User::factory()->create(['name' => 'Uma Viewer']);
    $this->actingAs($this->user);
});

afterEach(fn() => TaskFollowers::forgetTable());

function followedTask(array $attributes = []): FinisterreTask
{
    $owner = User::factory()->create(['name' => 'Olga Owner']);

    return FinisterreTask::factory()->create([
        'creator_id'  => $owner->id,
        'assignee_id' => $owner->id,
        ...$attributes,
    ]);
}

/** Nobody sees all tasks; `$ownTasks` makes the user a reporter, limited to the ones they created. */
function restrictTasks(bool $ownTasks = false): void
{
    FinisterrePlugin::get()
        ->userCanViewAllTasks(fn(): bool => false)
        ->userCanViewOnlyTheirTasks(fn(): bool => $ownTasks);
}

function followerComments(FinisterreTask $task)
{
    return Livewire::test(FinisterreCommentsComponent::class, ['record' => $task]);
}

describe('who follows a task', function() {
    it('makes the creator and the assignee followers of a new task', function() {
        $assignee = User::factory()->create();

        $task = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => $assignee->id]);

        expect(TaskFollowers::ids($task))->toBe([$this->user->id, $assignee->id])
            ->and($task->followers()->count())->toBe(2)
            ->and($task->isFollowedBy($assignee))->toBeTrue();
    });

    it('adds a new assignee to the followers and keeps the previous one', function() {
        $task = followedTask();
        $owner = $task->creator_id;
        $next = User::factory()->create();

        $task->update(['assignee_id' => $next->id]);

        expect(TaskFollowers::ids($task))->toBe([$owner, $next->id]);
    });

    it('follows and unfollows without duplicating anybody', function() {
        $task = followedTask();
        $other = User::factory()->create();

        $task->follow($other)->follow([$other->id, null, (string)$this->user->id]);

        expect(TaskFollowers::ids($task))->toBe([$task->creator_id, $other->id, $this->user->id]);

        $task->unfollow([$other]);

        expect($task->isFollowedBy($other))->toBeFalse()
            ->and($task->isFollowedBy(null))->toBeFalse()
            ->and(TaskFollowers::followsAny($this->user->id))->toBeTrue();
    });

    it('backfills the followers of existing tasks when the table is created', function() {
        $assignee = User::factory()->create();
        $task = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => $assignee->id]);
        $own = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => $this->user->id]);

        Schema::drop('finisterre_task_followers');
        TaskFollowers::forgetTable();

        (include __DIR__ . '/../../database/auto/2026_10_11_000000_create_finisterre_task_followers_table.php')->up();

        expect(TaskFollowers::ids($task))->toEqualCanonicalizing([$this->user->id, $assignee->id])
            ->and(TaskFollowers::ids($own))->toBe([$this->user->id]);
    });

    it('keeps working until the host has created the followers table', function() {
        Schema::drop('finisterre_task_followers');
        TaskFollowers::forgetTable();

        $assignee = User::factory()->create();
        $task = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => $assignee->id]);

        $task->follow(User::factory()->create())->unfollow($assignee);

        // Comments still reach the people they always did, who count as following.
        expect(TaskFollowers::ids($task))->toBe([$this->user->id, $assignee->id])
            ->and($task->isFollowedBy($assignee))->toBeTrue()
            ->and($task->isFollowedBy(User::factory()->create()))->toBeFalse()
            ->and(TaskFollowers::followsAny($assignee->id))->toBeTrue();

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertOk()
            ->assertDontSeeHtml('data-finisterre-followers');

        followerComments($task)->assertSet('data.notify', [$assignee->id]);

        followedTask();
        restrictTasks();

        // So they still open the tasks their notifications lead to, and no other.
        expect(FinisterreTask::query()->pluck('id')->all())->toBe([$task->id])
            ->and(Gate::allows('view', $task))->toBeTrue();
    });

    it('asks whether the table is there once per request while it is missing', function() {
        Schema::drop('finisterre_task_followers');
        TaskFollowers::forgetTable();

        DB::enableQueryLog();
        TaskFollowers::available();
        $first = count(DB::getQueryLog());
        TaskFollowers::available();
        TaskFollowers::available();

        expect($first)->toBeGreaterThan(0)
            ->and(count(DB::getQueryLog()))->toBe($first);

        DB::disableQueryLog();
    });

    it('remembers for the request whether somebody follows anything, until that changes', function() {
        $task = followedTask();

        DB::enableQueryLog();
        expect(TaskFollowers::followsAny($this->user->id))->toBeFalse();
        $first = count(DB::getQueryLog());
        TaskFollowers::followsAny($this->user->id);

        expect(count(DB::getQueryLog()))->toBe($first);

        DB::disableQueryLog();

        $task->follow($this->user);

        expect(TaskFollowers::followsAny($this->user->id))->toBeTrue();
    });

    it('writes the assignee down for the comments scheduled before, which an empty list sent to them', function() {
        $assignee = User::factory()->create();
        $task = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => $assignee->id]);

        $comment = fn(array $attributes) => $task->comments()->create([
            'comment'       => '<p>Later</p>',
            'creator_id'    => $this->user->id,
            'scheduled_for' => now()->addDay(),
            ...$attributes,
        ]);

        $empty = $comment(['notify_user_ids' => []]);
        $picked = $comment(['notify_user_ids' => [$this->user->id]]);
        $sent = $comment(['notify_user_ids' => [], 'sent_at' => now()]);

        Schema::drop('finisterre_task_followers');
        TaskFollowers::forgetTable();

        (include __DIR__ . '/../../database/auto/2026_10_11_000000_create_finisterre_task_followers_table.php')->up();

        expect($empty->refresh()->notify_user_ids)->toBe([$assignee->id])
            ->and($picked->refresh()->notify_user_ids)->toBe([$this->user->id])
            ->and($sent->refresh()->notify_user_ids)->toBe([]);
    });
});

describe('what a follower who cannot see all tasks gets', function() {
    it('shows them only the tasks they follow', function() {
        $followed = followedTask();
        $followed->follow($this->user);
        followedTask();

        restrictTasks();

        expect(FinisterreTask::query()->pluck('id')->all())->toBe([$followed->id]);
    });

    it('shows a reporter the tasks they created and the ones they follow', function() {
        $created = FinisterreTask::factory()->create(['creator_id' => $this->user->id, 'assignee_id' => null]);
        $created->unfollow($this->user);
        $followed = followedTask();
        $followed->follow($this->user);
        followedTask();

        restrictTasks(ownTasks: true);

        expect(FinisterreTask::query()->orderBy('id')->pluck('id')->all())->toBe([$created->id, $followed->id]);
    });

    it('leaves a request without a user unrestricted, so a reply by email finds its task', function() {
        followedTask();
        restrictTasks();
        auth()->logout();

        expect(FinisterreTask::query()->count())->toBe(1);
    });

    it('lets them read and comment on a followed task, but not edit it', function() {
        $task = followedTask();
        $task->follow($this->user);
        $created = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
        $unrelated = followedTask();

        restrictTasks(ownTasks: true);

        expect(Gate::allows('view', $task))->toBeTrue()
            ->and(Gate::allows('update', $task))->toBeFalse()
            ->and(Gate::allows('update', $created))->toBeTrue()
            ->and(Gate::allows('view', $unrelated))->toBeFalse();

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertOk()
            ->assertActionHidden('edit')
            ->assertActionHidden('archive')
            ->assertSeeHtml('data-finisterre-followers')
            ->assertSchemaComponentHidden('quick_actions');

        Livewire::test(EditFinisterreTask::class, ['record' => $task->getKey()])->assertForbidden();
    });

    it('lets them edit the task assigned to them', function() {
        $task = followedTask(['assignee_id' => $this->user->id]);
        $followed = followedTask();
        $followed->follow($this->user);

        restrictTasks();

        expect(Gate::allows('update', $task))->toBeTrue()
            ->and(Gate::allows('update', $followed))->toBeFalse();

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertSchemaComponentVisible('quick_actions');
    });

    it('does not open a task they do not follow', function() {
        $task = followedTask();

        restrictTasks();
        config()->set('app.env', 'local');

        // Over HTTP: Livewire::test() throws for a missing record up to 4.4.3 and answers 404 after it.
        $this->get(FinisterreTaskResource::getUrl('view', ['record' => $task->getKey()]))->assertNotFound();
    });

    it('gives somebody with no task permission the list once they follow a task', function() {
        restrictTasks();

        expect(Gate::allows('viewAny', FinisterreTask::class))->toBeFalse()
            ->and(FinisterreTaskResource::shouldRegisterNavigation())->toBeFalse();

        followedTask()->follow($this->user);

        expect(Gate::allows('viewAny', FinisterreTask::class))->toBeTrue()
            ->and(FinisterreTaskResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('lets them comment and tell the other followers, but not bring anybody new in', function() {
        $task = followedTask();
        $task->follow($this->user);
        $stranger = User::factory()->create();

        restrictTasks();

        followerComments($task)
            ->assertSet('data.notify', [$task->creator_id])
            ->assertActionHidden('addNotify')
            ->set('data.comment', '<p>Thanks</p>')
            ->set('data.notify', [$task->creator_id, $stranger->id])
            ->call('create')
            ->assertHasNoErrors();

        expect($task->comments()->count())->toBe(1)
            ->and($task->isFollowedBy($stranger))->toBeFalse();

        Notification::assertSentTo($task->creator, TaskCommentNotification::class);
        Notification::assertNotSentTo($stranger, TaskCommentNotification::class);
    });
});

describe('the followers of the task page', function() {
    it('shows the avatars of the followers', function() {
        $task = followedTask();
        $task->follow(User::factory()->create(['name' => 'Fiona Follower']));

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertSeeHtml('title="Olga Owner"')
            ->assertSeeHtml('title="Fiona Follower"')
            ->assertSee('FF');
    });

    it('stacks the first avatars and counts the rest', function() {
        $task = followedTask();
        $task->follow(User::factory()->count(5)->create());
        $task->follow(User::factory()->create(['name' => 'Zoe Last']));

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertSeeHtml('title="Olga Owner"')
            ->assertSeeHtml('title="Zoe Last"')
            ->assertSee('+2');
    });

    it('says so on the badge when nobody follows the task', function() {
        $task = followedTask();
        $task->unfollow($task->creator_id);

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->assertSee(__('finisterre::finisterre.followers.empty'));
    });

    it('adds and removes followers from the task page', function() {
        $task = followedTask();
        $owner = $task->creator_id;
        $added = User::factory()->create();

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->callAction(
                TestAction::make('quick_followers')->schemaComponent('quick_actions'),
                ['followers' => [$added->id, $this->user->id]]
            )
            ->assertHasNoActionErrors();

        expect(TaskFollowers::ids($task))->toEqualCanonicalizing([$added->id, $this->user->id])
            ->and($task->isFollowedBy($owner))->toBeFalse();
    });

    it('only adds the people the host lets follow', function() {
        $task = followedTask();
        $staff = User::factory()->create(['name' => 'Staff Sam']);
        $outsider = User::factory()->create(['name' => 'Outsider Otto']);

        FinisterrePlugin::get()->modifyFollowersQueryUsing(fn(Builder $query) => $query->whereKeyNot($outsider->id));

        expect(TaskFollowers::options())->toHaveKey((string)$staff->id)->not->toHaveKey((string)$outsider->id)
            ->and(TaskFollowers::options('otto'))->toBe([])
            ->and(TaskFollowers::options('sam', [$staff->id]))->toBe([]);

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->callAction(
                TestAction::make('quick_followers')->schemaComponent('quick_actions'),
                ['followers' => [$task->creator_id, $staff->id, $outsider->id]]
            );

        expect(TaskFollowers::ids($task))->toBe([$task->creator_id, $staff->id]);
    });
});

describe('the followers of the board card', function() {
    it('tucks the other followers in behind the assignee, without a query per card', function() {
        $task = followedTask();
        $task->follow(User::factory()->create(['name' => 'Fiona Follower']));
        followedTask()->follow($this->user);

        $board = Livewire::test(TasksKanbanBoard::class)->instance();
        $cardFollowers = (fn(FinisterreTask $record): array => $this->cardFollowers($record))->bindTo($board, $board);

        DB::enableQueryLog();
        $followers = $cardFollowers($task->fresh())['shown'];
        $cardFollowers($task->fresh());
        $queries = collect(DB::getQueryLog())->filter(fn(array $query): bool => str_contains($query['query'], 'finisterre_task_followers'));
        DB::disableQueryLog();

        // The assignee, who is also the creator here, leads the card on their own.
        expect(array_column($followers, 'name'))->toBe(['Fiona Follower'])
            ->and($followers[0]['initials'])->toBe('FF')
            ->and($queries->count())->toBeLessThanOrEqual(2);
    });

    it('keeps showing the creator until the followers table exists', function() {
        $creator = User::factory()->create(['name' => 'Carla Creator']);
        $task = FinisterreTask::factory()->create(['creator_id' => $creator->id, 'assignee_id' => $this->user->id]);

        Schema::drop('finisterre_task_followers');
        TaskFollowers::forgetTable();

        $board = Livewire::test(TasksKanbanBoard::class)->instance();
        $cardFollowers = (fn(FinisterreTask $record): array => $this->cardFollowers($record))->bindTo($board, $board);

        expect(array_column($cardFollowers($task)['shown'], 'name'))->toBe(['Carla Creator']);
    });

    it('loads the followers a card shows and only counts the others', function() {
        $task = followedTask();
        $task->follow(User::factory()->count(5)->create());

        $board = Livewire::test(TasksKanbanBoard::class)->instance();
        $cardFollowers = (fn(FinisterreTask $record): array => $this->cardFollowers($record))->bindTo($board, $board);
        $loaded = (fn(): array => $this->cardUsers ?? [])->bindTo($board, $board);

        $followers = $cardFollowers($task->fresh());

        // The assignee and the three faces behind them, not all six followers.
        expect($followers['shown'])->toHaveCount(3)
            ->and($followers['more'])->toBe(2)
            ->and($loaded())->toHaveCount(4);
    });

    it('draws the followers it is given and the count of the rest', function() {
        $people = fn(int $count): array => array_map(
            fn(int $i): array => ['name' => 'Person ' . $i, 'initials' => 'P' . $i, 'avatar' => null, 'creator' => $i === 1],
            range(1, $count)
        );

        $render = fn(array $followers, int $more = 0): string => view('finisterre::tasks.task-card-info', [
            'assignee'         => 'Alicia',
            'assigneeInitials' => 'A',
            'assigneeAvatar'   => null,
            'followers'        => $followers,
            'moreFollowers'    => $more,
            'priority'         => null,
            'priorityColor'    => null,
            'tagNames'         => collect(),
            'mediaCount'       => 0,
            'commentsCount'    => 0,
            'subtasksCount'    => 0,
            'subtasksDone'     => 0,
            'viewUrl'          => '#',
            'createdAt'        => '12 sep',
            'createdAtFull'    => '12/09/2026 10:14',
            'updatedAt'        => null,
            'updatedAtFull'    => null,
            'hasChanges'       => false,
        ])->render();

        expect($render($people(3), 2))->toContain('Creada por: Person 1')->toContain('Seguidor: Person 3')->toContain('+2')
            ->and($render($people(2)))->toContain('Person 2')->not->toContain('+');
    });
});

describe('who a comment is notified to', function() {
    it('shows the followers as avatars under the comment, and takes one out for this comment only', function() {
        $task = followedTask();
        $second = User::factory()->create(['name' => 'Second Sara']);
        $task->follow([$second, $this->user]);

        followerComments($task)
            ->assertSet('data.notify', [$task->creator_id, $second->id])
            ->assertSeeHtml('title="Second Sara"')
            ->call('removeNotify', $second->id)
            ->assertSet('data.notify', [$task->creator_id])
            ->assertDontSeeHtml('title="Second Sara"')
            ->set('data.comment', '<p>Only for Olga</p>')
            ->call('create');

        Notification::assertSentTo($task->creator, TaskCommentNotification::class);
        Notification::assertNotSentTo($second, TaskCommentNotification::class);

        expect($task->isFollowedBy($second))->toBeTrue();
    });

    it('makes somebody added to a comment a follower of the task', function() {
        $task = followedTask();
        $added = User::factory()->create(['name' => 'Added Ada']);

        followerComments($task)
            ->assertActionVisible('addNotify')
            ->callAction('addNotify', ['people' => [$added->id]])
            ->assertHasNoActionErrors()
            ->assertSet('data.notify', [$task->creator_id, $added->id])
            ->assertSeeHtml('title="Added Ada"')
            ->set('data.comment', '<p>Have a look</p>')
            ->call('create')
            // The next comment starts from the followers again.
            ->assertSet('data.notify', [$task->creator_id, $added->id]);

        Notification::assertSentTo([$task->creator, $added], TaskCommentNotification::class);

        expect($task->isFollowedBy($added))->toBeTrue();
    });

    it('offers no plus once nobody is left to add, and brings it back for somebody taken out', function() {
        $task = followedTask();

        // Everybody there is either writes the comment or is already on it.
        followerComments($task)
            ->assertActionHidden('addNotify')
            ->call('removeNotify', $task->creator_id)
            ->assertActionVisible('addNotify');
    });

    it('notifies nobody once every avatar was removed', function() {
        $task = followedTask();

        followerComments($task)
            ->call('removeNotify', $task->creator_id)
            ->assertSee(__('finisterre::finisterre.comments.notify_nobody'))
            ->set('data.comment', '<p>A note to self</p>')
            ->call('create');

        Notification::assertNothingSent();

        expect($task->comments()->count())->toBe(1);
    });

    it('never notifies somebody the host does not let follow', function() {
        $task = followedTask();
        $outsider = User::factory()->create();

        FinisterrePlugin::get()->modifyFollowersQueryUsing(fn(Builder $query) => $query->whereKeyNot($outsider->id));

        followerComments($task)
            ->set('data.notify', [$outsider->id])
            ->set('data.comment', '<p>Hello</p>')
            ->call('create');

        Notification::assertNothingSent();

        expect(DB::table('finisterre_task_followers')->where('user_id', $outsider->id)->exists())->toBeFalse();
    });
});
