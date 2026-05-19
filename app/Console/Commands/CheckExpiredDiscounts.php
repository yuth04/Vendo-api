<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Discount;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\DiscountExpiredMail;

class CheckExpiredDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'discounts:check-expired';

    /**
     * The console command description.
     */
    protected $description = 'Send email to admin when discount expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $discounts = Discount::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->get();

        $admin = User::where('role', 'admin')->first();

        if (!$admin) {
            $this->error('Admin not found.');
            return;
        }

        foreach ($discounts as $discount) {

            // Send email
            Mail::to($admin->email)
                ->send(new DiscountExpiredMail($discount));

            // Deactivate discount
            $discount->update([
                'is_active' => false
            ]);
        }

        $this->info('Expired discounts checked successfully.');
    }
}