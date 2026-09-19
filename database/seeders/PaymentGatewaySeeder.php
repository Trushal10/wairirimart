<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'cod',
                'name' => 'Cash on Delivery',
                'description' => 'Customer pays at delivery. No online charges.',
                'mode' => 'live',
                'is_active' => true,
                'is_default' => false,
                'credentials' => [],
                'config' => ['max_amount' => 20000],
                'supports' => [],
                'priority' => 10,
            ],
            [
                'code' => 'razorpay',
                'name' => 'Razorpay',
                'description' => 'Cards / UPI / netbanking / wallets (India).',
                'mode' => 'test',
                'is_active' => false,
                'is_default' => false,
                'credentials' => [],
                'config' => [],
                'supports' => ['refund', 'partial_refund', 'webhook', 'upi', 'cards'],
                'priority' => 100,
            ],
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Cards & wallets (global).',
                'mode' => 'test',
                'is_active' => false,
                'is_default' => false,
                'credentials' => [],
                'config' => ['currency' => 'inr'],
                'supports' => ['refund', 'partial_refund', 'webhook', 'cards'],
                'priority' => 90,
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal',
                'description' => 'PayPal balance / cards (global).',
                'mode' => 'test',
                'is_active' => false,
                'is_default' => false,
                'credentials' => [],
                'config' => ['currency' => 'USD'],
                'supports' => ['refund', 'partial_refund', 'webhook'],
                'priority' => 80,
            ],
        ];

        foreach ($rows as $r) {
            PaymentGateway::updateOrCreate(['code' => $r['code']], $r);
        }
    }
}
