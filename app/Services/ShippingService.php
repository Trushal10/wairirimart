<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShippingService
{
    protected $token;

    public function __construct()
    {
        $this->token = $this->getToken();
    }

    private function getToken()
    {
        $response = Http::post(
            'https://apiv2.shiprocket.in/v1/external/auth/login',
            [
                'email' => config('services.shiprocket.email'),
                'password' => config('services.shiprocket.password'),
            ]
        );

        return $response->json()['token'] ?? null;
    }

    public function createOrder($order, $items)
    {
        $data = [
            "order_id" => $order->order_no,
            "order_date" => now()->format('Y-m-d'),
            "pickup_location" => "Primary",
            "billing_customer_name" => $order->shipping_name,
            "billing_email" => $order->shipping_email,
            "billing_phone" => $order->shipping_phone,
            "billing_address" => $order->shipping_address,
            "billing_city" => $order->shipping_city,
            "billing_pincode" => $order->shipping_pincode,
            "billing_state" => $order->shipping_state,
            "billing_country" => "India",
            "shipping_is_billing" => true,
            "payment_method" => "COD",
            "sub_total" => $order->sub_total,
            "order_items" => $items,
            "length" => 10,
            "breadth" => 10,
            "height" => 5,
            "weight" => 0.5
        ];
        return Http::withToken($this->token)
            ->post('https://apiv2.shiprocket.in/v1/external/orders/create/adhoc', $data)->json();
    }
}
