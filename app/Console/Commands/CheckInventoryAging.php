<?php

namespace App\Console\Commands;

use App\Models\InventoryBatch;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class CheckInventoryAging extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:check-aging';

    /**
     * The console command description.
     */
    protected $description = 'Automatically check aging inventory and notify admins';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for aging stock...');

        // 1. Fetch Admin Users
        $admins = User::whereIn('role', ['admin','super-admin'])->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found.');
            return 0;
        }

        // 2. Define Aging Ranges
        $categories = [
            [
                'type' => 'slow_moving',
                'min_months' => 6,
                'max_months' => 9,
                'title' => 'Inventory Alert: Slow Moving Stock',
            ],
            [
                'type' => 'old_stock',
                'min_months' => 9,
                'max_months' => 12,
                'title' => 'Inventory Alert: Old Stock',
            ],
            [
                'type' => 'dead_stock',
                'min_months' => 12,
                'max_months' => null,
                'title' => 'Inventory Alert: Dead Stock',
            ],
        ];

        $notificationsCreated = 0;

        foreach ($categories as $cat) {
            $query = InventoryBatch::with(['productVariant.product'])
                ->where('remaining_quantity', '>', 0)
                ->where('stock_in_date', '<=', now()->subMonths($cat['min_months']));

            if (!is_null($cat['max_months'])) {
                $query->where('stock_in_date', '>', now()->subMonths($cat['max_months']));
            }

            $batches = $query->get();

            foreach ($batches as $batch) {
                $variant = $batch->productVariant;
                $product = $variant->product ?? null;

                if (!$product)
                    continue;

                $productName = $product->name;
                $size = $variant->size ?? '—';
                $color = $variant->color ?? '—';
                $statusLabel = str_replace('_', ' ', $cat['type']);
                $message = "{$productName} ({$size} · {$color}) has been in stock for {$batch->age_in_months} months ({$statusLabel}).";

                foreach ($admins as $admin) {
                    // Avoid creating duplicate unread notifications for the same product & alert type
                    $exists = Notification::where('user_id', $admin->id)
                        ->where('type', $cat['type'])
                        ->where('is_read', false)
                        ->where('message', 'like', "%{$productName}%")
                        ->exists();

                    if (!$exists) {
                        Notification::create([
                            'user_id' => $admin->id,
                            'title' => $cat['title'],
                            'message' => $message,
                            'type' => $cat['type'],
                            'url' => '/admin/inventory/alerts',
                            'is_read' => false,
                            'icon' => 'alert-triangle',
                        ]);

                        $notificationsCreated++;
                    }
                }
            }
        }

        $this->info("Completed. Created {$notificationsCreated} notification(s).");
        return 0;
    }
}