<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-unpaid {--hours=0.5 : Hours before cancel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel unpaid pending orders and restore stock';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);

        $orders = Order::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->with('items.product')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No unpaid orders to cancel.');
            return;
        }

        DB::transaction(function () use ($orders) {
            foreach ($orders as $order) {
                // Restore stock
                foreach ($order->items as $item) {
                    $item->product?->increment('quantity', $item->quantity);
                }

                // Cancel order
                $order->update(['status' => 'cancelled']);
            }
        });

        Log::info('Unpaid orders cancelled', [
            'count' => $orders->count(),
            'hours' => $hours
        ]);

        $this->info("✅ Cancelled {$orders->count()} unpaid orders (>{$hours}h old). Stock restored.");
    }
}
