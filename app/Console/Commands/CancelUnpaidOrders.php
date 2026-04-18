<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class CancelUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-unpaid';

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
         $orders = Order::where('status', 'pending')
        ->where('created_at', '<=', Carbon::now()->subMinutes(30))
        ->get();

        foreach ($orders as $order) {
            $order->update(['status' => 'cancelled']);
        }

        $this->info('Unpaid orders cancelled successfully.');
    }
}
