<?php

namespace App\Console\Commands;

use App\Models\Coupon;
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
        // Deactivate expired discounts
        $discounts = Discount::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->get();

        if ($discounts->isEmpty()) {
            $this->info('No expired discounts found.');
        } else {
            $admin = User::where('role', 'admin')->first();

            if (!$admin) {
                $this->error('Admin not found.');
            } else {
                foreach ($discounts as $discount) {
                    $discount->update(['is_active' => false]);
                    Mail::to($admin->email)->send(new DiscountExpiredMail($discount));
                    $this->info("Discount [{$discount->name}] deactivated and admin notified.");
                }
            }
        }

        // Deactivate expired coupons
        $coupons = Coupon::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->get();

        if ($coupons->isEmpty()) {
            $this->info('No expired coupons found.');
        } else {
            foreach ($coupons as $coupon) {
                $coupon->update(['status' => 'inactive']);
                $this->info("Coupon [{$coupon->name}] deactivated.");
            }
        }

        $this->info('Expired discounts and coupons checked successfully.');
    }
}