<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\PendingTaskNotifications;
use Arzcode\Finisterre\Support\TaskFollowers;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

/**
 * The people picker shared by the followers of a task and the recipients of a
 * comment (see UserPicker).
 */
class FollowersSelect
{
    /**
     * @param  list<int>|Closure(): array<int>  $except  People left out of the options, read when they are asked for
     */
    public static function make(string $name = 'followers', array|Closure $except = []): ToggleButtons|Select
    {
        $except = fn(): array => TaskFollowers::normalize($except instanceof Closure ? $except() : $except);

        return UserPicker::make($name, fn() => TaskFollowers::candidatesExcept($except()), multiple: true, icon: Heroicon::OutlinedUserPlus)
            ->label(__('finisterre::finisterre.followers.label'));
    }

    /**
     * Who follows the task from now on is exactly who the field holds. People who
     * already followed stay without being checked again; anybody new has to be a
     * user the host lets be added.
     *
     * @param  array<mixed>  $ids
     */
    public static function persist(FinisterreTask $record, array $ids): void
    {
        $wanted = TaskFollowers::normalize($ids);
        $current = TaskFollowers::ids($record);

        $record->unfollow(array_diff($current, $wanted));
        $record->follow(TaskFollowers::onlyCandidates(array_values(array_diff($wanted, $current))));
    }

    /**
     * The followers picked while creating a task. The creator and the assignee follow
     * it already; anybody else picked hears about the new task with its assignee, in
     * the notification sent after the response, so the attachments saved after this
     * field are listed in the email.
     *
     * @param  array<mixed>  $ids
     */
    public static function persistOnCreate(FinisterreTask $record, array $ids): void
    {
        $added = TaskFollowers::onlyCandidates(TaskFollowers::normalize($ids));
        $record->follow($added);

        PendingTaskNotifications::current()->addRecipients($record, $added);
    }
}
