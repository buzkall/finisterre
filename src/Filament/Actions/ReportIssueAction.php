<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class ReportIssueAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reportIssue';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('finisterre::finisterre.report_issue'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->modalHeading(__('finisterre::finisterre.report_issue_heading'))
            ->modalSubmitActionLabel(__('finisterre::finisterre.report_issue'))
            ->schema([
                Callout::make(__('finisterre::finisterre.report_issue_callout_title'))
                    ->description(__('finisterre::finisterre.report_issue_callout_description'))
                    ->info(),

                TextInput::make('title')
                    ->label(__('finisterre::finisterre.title'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('finisterre::finisterre.description'))
                    ->required()
                    ->rows(4),

                FileUpload::make('attachments')
                    ->label(__('finisterre::finisterre.attachments'))
                    ->multiple()
                    ->acceptedFileTypes(['image/*', 'application/pdf', 'video/*'])
                    ->maxSize(3072)
                    ->helperText(__('finisterre::finisterre.attachments_max_size', ['size' => '3 MB']))
                    ->openable()
                    ->downloadable()
                    ->disk(config('finisterre.attachments_disk') ?? 'public')
                    // keep the uploads as UploadedFile instances so they can be pushed
                    // into the media library after the task is created
                    ->storeFiles(false),
            ])
            ->action(function(?Model $record, array $data): void {
                $task = new FinisterreTask([
                    'title'        => $data['title'],
                    'description'  => $data['description'],
                    'priority'     => TaskPriorityEnum::Medium,
                    'order_column' => 0,
                    // status, creator_id and assignee_id (fallback) are set by FinisterreTaskObserver
                ]);

                if ($record instanceof FinisterreReportable) {
                    $task->subject()->associate($record);
                }

                $task->save();

                foreach ((array)($data['attachments'] ?? []) as $file) {
                    if ($file instanceof UploadedFile) {
                        $task->addMedia($file)
                            ->toMediaCollection('tasks', config('finisterre.attachments_disk') ?? 'public');
                    }
                }

                Notification::make()
                    ->title(__('finisterre::finisterre.report_issue_sent'))
                    ->success()
                    ->send();
            });
    }
}
