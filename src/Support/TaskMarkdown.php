<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Controllers\SharedFileController;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use DateTimeInterface;
use League\HTMLToMarkdown\HtmlConverter;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A task as markdown, to paste into a coding assistant or an issue: its details,
 * description, subtasks, attachments and comments, oldest first. Scheduled
 * comments not sent yet are left out, unless the user who copies wrote them.
 *
 * The images in the description and comments, and the attachments, link to the
 * file through a signed URL that opens it without a login for a few minutes
 * (see SharedFileController): the assistant has no session in the panel.
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
                ->map(fn(Media $media): string => '- [' . $media->file_name . '](' . SharedFileController::url($media->disk, $media->getPathRelativeToRoot()) . ')')
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

        return trim($converter->convert($this->shareImages($html)));
    }

    /**
     * Points each image of the task's own on the attachments disk at a signed link.
     * Any other image keeps its URL.
     */
    protected function shareImages(string $html): string
    {
        return (string)preg_replace_callback('~(<img\b[^>]*?\bsrc=)(["\'])(.*?)\2~i', function(array $match): string {
            $file = $this->imageOnDisk($match[3]);

            return $file === null
                ? $match[0]
                : $match[1] . $match[2] . e(SharedFileController::url(AttachmentsDisk::name(), $file)) . $match[2];
        }, $html);
    }

    /**
     * The image's path on the attachments disk. On a private disk only an image that
     * belongs to this task, the same rule FilamentRouteController follows: a
     * description can load any file's URL, and its link must not unlock it.
     */
    protected function imageOnDisk(string $src): ?string
    {
        if (AttachmentsDisk::isPublic()) {
            return preg_match(AttachmentsDisk::PUBLIC_IMAGE, 'src="' . $src . '"', $match) ? $match[2] : null;
        }

        if (! preg_match(EditorFiles::PATTERN, $src . '"', $match) || ! EditorFiles::columnExists()) {
            return null;
        }

        return EditorFiles::belongsTo($this->task, $match[1]) ? $match[1] : null;
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
