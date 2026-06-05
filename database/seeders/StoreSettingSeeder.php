<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Brand
            ['key' => 'store_name',        'value' => 'Vendo',                      'group' => 'brand',      'type' => 'text',  'label' => 'Store Name',        'is_public' => true],
            ['key' => 'store_description', 'value' => 'Vendo is your trusted online shopping destination for stylish and quality clothing. We specialize in offering a wide range of fashionable apparel designed to fit every lifestyle. From casual everyday wear to modern trendy styles, Vendo provides clothing that combines comfort, quality, and affordability.', 'group' => 'brand', 'type' => 'text', 'label' => 'Store Description', 'is_public' => true],
            ['key' => 'store_logo',        'value' => '',                            'group' => 'brand',      'type' => 'image', 'label' => 'Store Logo',        'is_public' => true],

            // Contact
            ['key' => 'store_phone',   'value' => '+885 90 206 072',       'group' => 'contact', 'type' => 'phone', 'label' => 'Phone',   'is_public' => true],
            ['key' => 'store_email',   'value' => 'seakeii6072@gmail.com', 'group' => 'contact', 'type' => 'email', 'label' => 'Email',   'is_public' => true],
            ['key' => 'store_address', 'value' => 'St 2, Phnom Penh',      'group' => 'contact', 'type' => 'text',  'label' => 'Address', 'is_public' => true],
            ['key' => 'store_website', 'value' => 'https://vendo-ecommerce-two.vercel.app', 'group' => 'contact', 'type' => 'url', 'label' => 'Website', 'is_public' => true],
            ['key' => 'store_hours',   'value' => 'Monday to Friday, 9:00 AM - 6:00 PM',   'group' => 'contact', 'type' => 'text', 'label' => 'Support Hours', 'is_public' => true],

            // Social
            ['key' => 'social_facebook',  'value' => 'fb.com/vendo', 'group' => 'social', 'type' => 'url',  'label' => 'Facebook',  'is_public' => true],
            ['key' => 'social_instagram', 'value' => '@vendostore',  'group' => 'social', 'type' => 'text', 'label' => 'Instagram', 'is_public' => true],

            // Support links
            ['key' => 'support_about',   'value' => '/about',   'group' => 'support', 'type' => 'url', 'label' => 'About Us URL',    'is_public' => true],
            ['key' => 'support_contact', 'value' => '/contact', 'group' => 'support', 'type' => 'url', 'label' => 'Contact Us URL',  'is_public' => true],
            ['key' => 'support_faq',     'value' => '/faq',     'group' => 'support', 'type' => 'url', 'label' => 'FAQ URL',         'is_public' => true],

            // Quick Links
            ['key' => 'quicklink_returns',  'value' => '/returns',  'group' => 'quicklinks', 'type' => 'url', 'label' => 'Returns URL',  'is_public' => true],
            ['key' => 'quicklink_shipping', 'value' => '/shipping', 'group' => 'quicklinks', 'type' => 'url', 'label' => 'Shipping URL', 'is_public' => true],
            ['key' => 'quicklink_support',  'value' => '/support',  'group' => 'quicklinks', 'type' => 'url', 'label' => 'Support URL',  'is_public' => true],

            // Footer
            ['key' => 'footer_copyright', 'value' => '© 2026 Vendo. All rights reserved.', 'group' => 'footer', 'type' => 'text', 'label' => 'Copyright Text', 'is_public' => true],
        ];

        foreach ($settings as $s) {
            StoreSetting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}