<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FreshGreenhaven extends Command
{
    protected $signature = 'greenhaven:fresh';

    protected $description = 'Fresh migration for Greenhaven and system database';

    public function handle(): int
    {
        $this->info('Cleaning system database...');

        DB::connection('system')->statement('SET FOREIGN_KEY_CHECKS=0');

        DB::connection('system')->statement('DROP TABLE IF EXISTS hostnames');
        DB::connection('system')->statement('DROP TABLE IF EXISTS websites');

        DB::connection('system')->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Running fresh migrations...');

        Artisan::call('migrate:fresh');

        $this->output->write(Artisan::output());

        $this->info('Greenhaven database rebuilt successfully.');

        return self::SUCCESS;
    }
}