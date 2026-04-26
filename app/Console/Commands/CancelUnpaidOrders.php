<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelUnpaidOrders extends Command
{
    /**
     * Keep the old command signature so your scheduler does not break.
     */
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Expire unpaid pending payment orders';

    public function handle()
    {
        $payments = Payment::with('order')
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No pending payments to expire.');
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($payments) {
            foreach ($payments as $payment) {
                $payment->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);

                if ($payment->order && $payment->order->status === 'pending_payment') {
                    $payment->order->update([
                        'status' => 'expired',
                    ]);
                }
            }
        });

        Log::info('Pending payments expired', [
            'count' => $payments->count(),
        ]);

        $this->info("Expired {$payments->count()} pending payment(s).");

        return Command::SUCCESS;
    }
}