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
        $expiredPayments = Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->with('order.items.product')
            ->get();

        foreach ($expiredPayments as $payment) {

            $payment->update(['status' => 'failed']);

            $order = $payment->order;

            if ($order && $order->status !== 'cancelled') {

                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('quantity', $item->quantity);
                    }
                }

                $order->update(['status' => 'cancelled']);
            }
        }

        $this->info("Expired {$expiredPayments->count()} pending payments.");
    }
}
