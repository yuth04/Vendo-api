<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ExpirePendingKhqrPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-khqr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending KHQR payments and cancel related orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payments = Payment::with('order')
            ->where('payment_method', 'KHQR')
            ->where('status', 'pending')
            ->whereNotNull('khqr_expires_at')
            ->where('khqr_expires_at', '<=', now())
            ->get();

        foreach ($payments as $payment) {

            DB::transaction(function () use ($payment) {

                $payment->update([
                    'status' => 'expired'
                ]);

                if ($payment->order) {
                    $payment->order->update([
                        'status' => 'cancelled'
                    ]);
                }

            });

            $this->info("Expired payment #{$payment->id}");
        }

        return self::SUCCESS;
    }
}