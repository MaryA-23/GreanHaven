<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FreshGreenhavenSystem extends Command
{
    protected $signature = 'greenhaven:system-fresh';

    protected $description = 'Reset Greenhaven system tenancy tables';

    public function handle(): int
    {
        $this->info('Resetting greenhaven_system...');

        DB::connection('system')->statement('SET FOREIGN_KEY_CHECKS=0');

        DB::connection('system')->statement('DROP TABLE IF EXISTS hostnames');
        DB::connection('system')->statement('DROP TABLE IF EXISTS websites');

        DB::connection('system')->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('greenhaven_system reset successfully.');

        return self::SUCCESS;
    }
}