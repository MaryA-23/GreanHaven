<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;


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
    $expiredCount = Payment::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(10))
        ->count();

    if ($expiredCount === 0) {
        $this->info('No expired payments.');
        return;
    }

    DB::transaction(function () {
        $expiredPayments = Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->with('order.items.product')
            ->get();

        foreach ($expiredPayments as $payment) {
            $payment->update(['status' => 'failed']);

            if ($payment->order && $payment->order->status !== 'cancelled') {
                foreach ($payment->order->items as $item) {
                    $item->product?->increment('quantity', $item->quantity);
                }
                $payment->order->update(['status' => 'cancelled']);
            }
        }
    });

    $this->info("✅ Expired {$expiredCount} payments → failed + stock restored.");
}
}
