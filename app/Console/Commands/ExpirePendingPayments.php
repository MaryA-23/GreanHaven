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
            ->with('order')
            ->get();

        foreach ($expiredPayments as $payment) {

            $payment->update(['status' => 'failed']);

            if ($payment->order && $payment->order->status !== 'cancelled') {

                foreach ($payment->order->items as $item) {
                    $item->product->increment('quantity', $item->quantity);
                }

                $payment->order->update(['status' => 'cancelled']);
            }
        }
        $this->info("Expired " . $expiredPayments->count() . " pending payments.");
    }
}
