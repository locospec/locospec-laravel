<?php

namespace Locospec\LLCS\Commands;

use Illuminate\Console\Command;

class LLCSCommand extends Command
{
    public $signature = 'locospec-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
