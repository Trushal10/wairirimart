<?php

namespace Database\Seeders;

use App\Models\DeliveryPartner;
use Illuminate\Database\Seeder;

class DeliveryPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Shiprocket',
                'code' => DeliveryPartner::CODE_SHIPROCKET,
                'description' => 'India-wide aggregator with 20+ courier integrations.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'manifest', 'invoice', 'cod', 'cancel'],
                'priority' => 100,
            ],
            [
                'name' => 'Delhivery',
                'code' => DeliveryPartner::CODE_DELHIVERY,
                'description' => 'Direct integration with Delhivery.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'cancel', 'cod'],
                'priority' => 90,
            ],
            [
                'name' => 'Blue Dart',
                'code' => DeliveryPartner::CODE_BLUEDART,
                'description' => 'Premium courier — direct API integration.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'cancel', 'cod'],
                'priority' => 80,
            ],
            [
                'name' => 'DTDC',
                'code' => DeliveryPartner::CODE_DTDC,
                'description' => 'Direct integration with DTDC B2C API.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'cancel', 'cod'],
                'priority' => 70,
            ],
            [
                'name' => 'Xpressbees',
                'code' => DeliveryPartner::CODE_XPRESSBEES,
                'description' => 'Direct integration with Xpressbees.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'cancel', 'cod', 'serviceability'],
                'priority' => 60,
            ],
            [
                'name' => 'Shadowfax',
                'code' => DeliveryPartner::CODE_SHADOWFAX,
                'description' => 'Same-day / next-day delivery aggregator with hyperlocal reach.',
                'is_third_party' => true,
                'is_active' => false,
                'is_default' => false,
                'mode' => 'test',
                'supports' => ['pickup', 'labels', 'tracking', 'cancel', 'cod', 'serviceability'],
                'priority' => 65,
            ],
            [
                'name' => 'Local Delivery',
                'code' => DeliveryPartner::CODE_LOCAL,
                'description' => 'In-house delivery — no external API.',
                'is_third_party' => false,
                'is_active' => true,
                'is_default' => true,
                'mode' => 'live',
                'supports' => [],
                'priority' => 10,
            ],
        ];

        foreach ($rows as $r) {
            DeliveryPartner::updateOrCreate(['code' => $r['code']], $r);
        }
    }
}
