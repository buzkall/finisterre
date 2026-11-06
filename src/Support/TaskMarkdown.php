<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use DateTimeInterface;
use League\HTMLToMarkdown\HtmlConverter;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A task as markdown, to paste into a coding assistant or an issue: its details,
 * description, subtasks, attachment names and comments, oldest first. Scheduled
 * comments not sent yet are left out, unless the user who copies wrote them.
 */
class TaskMarkdown
{
    public function __construct(protected FinisterreTask $task) {}

    public function toMarkdown(): string
    {
        $task = $this->task;

        $sections = [
            '# ' . $task->title,
            $this->details(),
            $this->section('description', $this->html($task->description)),
            $this->section('subtasks', $task->subtasks
                ->map(fn(FinisterreSubtask $subtask): string => '- [' . ($subtask->completed ? 'x' : ' ') . '] ' . $subtask->title)
                ->implode("\n")),
            $this->section('attachments', $task->getMedia('tasks')
                ->map(fn(Media $media): string => '- ' . $media->file_name)
                ->implode("\n")),
            $this->section('comments', $this->comments()),
        ];

        return implode("\n\n", array_filter($sections, fn(string $section): bool => $section !== '')) . "\n";
    }

    protected function details(): string
    {
        $task = $this->task;

        $details = [
            'url'      => $task->viewUrl(),
            'status'   => $task->status->getLabel(),
            'priority' => $task->priority->getLabel(),
            'assignee' => $task->assigneeName(),
            'tags'     => $task->tags->map(fn(FinisterreTag $tag): string => $tag->name)->implode(', '),
            'due_at'   => $this->date($task->due_at),
            'created'  => $this->date($task->created_at) . ' · ' . $task->creatorName(),
        ];

        return collect($details)
            ->filter(fn(?string $value): bool => filled($value))
            ->map(fn(string $value, string $key): string => '- **' . $this->label($key) . ':** ' . $value)
            ->implode("\n");
    }

    protected function comments(): string
    {
        return $this->task->comments()
            ->visibleTo(UserModel::authId())
            ->with('creator')
            ->oldest()
            ->oldest('id')
            ->get()
            ->map(function(FinisterreTaskComment $comment): string {
                $author = $comment->creator ? UserModel::displayName($comment->creator) : 'N/A';

                return '### ' . $author . ' · ' . $this->date($comment->created_at) . "\n\n" . $this->html($comment->comment);
            })
            ->implode("\n\n");
    }

    protected function section(string $key, string $body): string
    {
        return trim($body) === '' ? '' : '## ' . $this->label($key) . "\n\n" . $body;
    }

    protected function html(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $converter = new HtmlConverter([
            'strip_tags'   => true,
            'remove_nodes' => 'script style',
            'header_style' => 'atx',
            'hard_break'   => true,
        ]);

        return trim($converter->convert($html));
    }

    protected function label(string $key): string
    {
        return Typed::string(__('finisterre::finisterre.copy_markdown.' . $key));
    }

    protected function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i:s') : null;
    }
}
