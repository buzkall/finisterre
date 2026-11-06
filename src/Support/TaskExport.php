<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use DateTimeInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every task as CSV, for reports made outside the panel. The columns are the
 * ones a database export of the tasks table would give, with the assignee's
 * name, the number of comments and the tags added. It covers every task, not
 * only those the user who downloads it can see on the board.
 */
class TaskExport
{
    public const array COLUMNS = [
        'id', 'title', 'status', 'priority', 'archived', 'assignee',
        'created_at', 'completed_at', 'due_at', 'comments', 'tags'
    ];

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function(): void {
            $handle = fopen('php://output', 'w');

            if ($handle !== false) {
                $this->write($handle);
                fclose($handle);
            }
        }, $this->filename(), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $handle
     */
    public function write($handle): void
    {
        fputcsv($handle, self::COLUMNS, escape: '');

        foreach ($this->rows() as $row) {
            fputcsv($handle, $row, escape: '');
        }
    }

    /**
     * @return iterable<int, list<int|string>>
     */
    public function rows(): iterable
    {
        $model = new FinisterreTask;
        $hasArchived = ColumnExists::in($model->getTable(), 'archived');

        $tasks = FinisterreTask::query()
            ->withoutGlobalScope('canViewOnlyTheirTasks')
            ->with(['assignee', 'tags'])
            ->withCount('comments')
            ->orderBy('created_at')
            ->orderBy($model->getKeyName())
            ->lazy();

        foreach ($tasks as $task) {
            yield [
                $task->id,
                $task->title,
                $task->status->value,
                $task->priority->value,
                $hasArchived && $task->archived ? 1 : 0,
                $task->assigneeName() ?? '',
                $this->date($task->created_at),
                $this->date($task->completed_at),
                $this->date($task->due_at),
                Typed::int($task->getAttribute('comments_count')),
                $task->tags->map(fn(FinisterreTag $tag): string => $tag->name)->implode('|'),
            ];
        }
    }

    public function filename(): string
    {
        return 'finisterre-tasks-' . Str::slug(Typed::string(config('app.name'))) . '-' . now()->format('Y-m-d') . '.csv';
    }

    protected function date(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : '';
    }
}
