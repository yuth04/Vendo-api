<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    // database/seeders/StoreSettingSeeder.php

    public function run(): void
    {
        $settings = [
            // Brand
            ['key' => 'brand_title', 'value' => 'Vendo', 'group' => 'brand', 'type' => 'text', 'label' => 'Store Name'],
            ['key' => 'brand_description', 'value' => 'Vendo is your trusted online shopping...', 'group' => 'brand', 'type' => 'text', 'label' => 'Store Description'],

            // Navigation
            [
                'key' => 'nav_support',
                'value' => json_encode([
                    'heading' => 'Support',
                    'links' => [
                        ['label' => 'FAQ', 'url' => '/faq'],
                        ['label' => 'Shipping & Delivery', 'url' => '/shipping'],
                        ['label' => 'Returns & Exchanges', 'url' => '/returns'],
                        ['label' => 'Order Tracking', 'url' => '/track-order'],
                    ],
                ]),
                'group' => 'navigation',
                'type' => 'json',
                'label' => 'Support Links'
            ],

            [
                'key' => 'nav_quicklinks',
                'value' => json_encode([
                    'heading' => 'Quick Links',
                    'links' => [
                        ['label' => 'About Us', 'url' => '/about'],
                        ['label' => 'New Arrivals', 'url' => '/new-arrivals'],
                        ['label' => 'Best Sellers', 'url' => '/best-sellers'],
                        ['label' => 'Our Blog', 'url' => '/blog'],
                    ],
                ]),
                'group' => 'navigation',
                'type' => 'json',
                'label' => 'Quick Links'
            ],

            // Contact
            ['key' => 'contact_heading', 'value' => 'Contact Us', 'group' => 'contact', 'type' => 'text', 'label' => 'Contact Heading'],
            ['key' => 'contact_phone_display', 'value' => '+855 90 206 072', 'group' => 'contact', 'type' => 'phone', 'label' => 'Phone Display'],
            ['key' => 'contact_phone_raw', 'value' => '+85590206072', 'group' => 'contact', 'type' => 'phone', 'label' => 'Phone Raw'],
            ['key' => 'contact_email', 'value' => 'support@vendo.com', 'group' => 'contact', 'type' => 'email', 'label' => 'Email'],
            [
                'key' => 'contact_address',
                'value' => json_encode([
                    'street' => 'Street 2',
                    'city' => 'Phnom Penh',
                    'country' => 'Cambodia',
                    'display_text' => 'Street 2, Phnom Penh, Cambodia',
                ]),
                'group' => 'contact',
                'type' => 'json',
                'label' => 'Address'
            ],
        ];

        foreach ($settings as $setting) {
            StoreSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}