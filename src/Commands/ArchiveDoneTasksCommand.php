<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Console\Command;

class ArchiveDoneTasksCommand extends Command
{
    public $signature = 'finisterre:archive-done-tasks';
    public $description = 'Archive the Finisterre done tasks nobody has touched for longer than the configured number of days';

    public function handle(): int
    {
        $days = Typed::nullableInt(config('finisterre.auto_archive_after_days'));

        if ($days === null || $days < 1) {
            $this->info('Automatic archiving is turned off.');

            return self::SUCCESS;
        }

        // Keyed on updated_at rather than completed_at: unarchiving a task (or a new
        // comment or subtask, which touch it) restarts the count, so a task brought
        // back on purpose is not archived again the next night. It also covers done
        // tasks with no completed_at, which completing always sets before updated_at.
        // A plain query: archiving notifies nobody and leaves no trace on the task,
        // so there is nothing for the model events to do.
        $archived = FinisterreTask::query()
            ->notArchived()
            ->where('status', TaskStatusEnum::Done)
            ->where('updated_at', '<=', now()->subDays($days))
            ->update(['archived' => true]);

        $this->info("Archived {$archived} task(s).");

        return self::SUCCESS;
    }
}
