<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;


class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-pending-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Payment::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(30))
        ->update(['status' => 'failed']);

    $this->info("$count pending payments expired.");
    }
}
