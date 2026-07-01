<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Illuminate\Console\Command;

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

        foreach ($pending as $comment) {
            $comment->deliver();
        }

        $this->info("Dispatched {$pending->count()} scheduled comment(s).");

        return self::SUCCESS;
    }
}
