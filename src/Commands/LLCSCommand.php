<?php

namespace LCSLaravel\Commands;

use Illuminate\Console\Command;
use LCSEngine\LCS;

class LLCSCommand extends Command
{
    public $signature = 'locospec {action? : The action to perform (clear-cache|cache-status)}';

    public $description = 'Manage Locospec (LLCS) operations: clear cache, check cache status, and more.';

    public function handle(): int
    {
        $action = $this->argument('action');
        $this->info('==============================================');
        $this->info('     Locospec Laravel Lowcode Framework ');
        $this->info('==============================================');

        if ($action === 'clear-cache') {
            if (LCS::clearCache()) {
                $this->info('Registry cache cleared successfully.');
            } else {
                $this->error('Failed to clear registry cache.');
            }
        } elseif ($action === 'cache-status') {
            $status = LCS::checkCacheStatus();
            if (($status['exists'] ?? false)) {
                $this->info('Cache file exists.');
                $this->line('Last modified: '.date('Y-m-d H:i:s', $status['modified']));
                $this->line('Size: '.$status['size'].' bytes');
            } else {
                $this->warn('Cache file does not exist.');
            }
        } else {
            $this->line('');
            $this->line('Available actions:');
            $this->line('  clear-cache   : Clears the registry cache.');
            $this->line('  cache-status  : Displays the current cache status.');
            $this->line('');
            $this->line('Usage:');
            $this->line('  php artisan locospec clear-cache');
            $this->line('  php artisan locospec cache-status');
        }

        return self::SUCCESS;
    }
}
