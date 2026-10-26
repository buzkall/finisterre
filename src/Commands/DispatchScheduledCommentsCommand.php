<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Illuminate\Console\Command;
use Throwable;

class DispatchScheduledCommentsCommand extends Command
{
    public $signature = 'finisterre:dispatch-scheduled-comments';
    public $description = 'Deliver scheduled Finisterre comments whose time has arrived';

    public function handle(): int
    {
        $pending = FinisterreTaskComment::query()
            ->whereNotNull('scheduled_for')
            ->whereNull('sent_at')
            ->where('scheduled_for', '<=', now())
            ->with('task')
            ->get();

        $dispatched = 0;

        foreach ($pending as $comment) {
            // deliver() claims the comment: a run that lost it to another server or
            // an overlapping run gets null and skips it. One that fails before anybody
            // was notified gives the claim back, so the next run delivers it, and this
            // one goes on with the other comments.
            try {
                if ($comment->deliver() !== null) {
                    $dispatched++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->info("Dispatched {$dispatched} scheduled comment(s).");

        return self::SUCCESS;
    }
}
