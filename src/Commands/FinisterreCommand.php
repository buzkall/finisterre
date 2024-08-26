<?php

namespace Buzkall\Finisterre\Commands;

use Illuminate\Console\Command;

class FinisterreCommand extends Command
{
    public $signature = 'finisterre';
    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
