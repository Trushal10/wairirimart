<?php

namespace App\Notifications;

use App\Helper\OrderStatusHelper;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->load(['orderItems.product', 'orderItems.variant']);
        $appName = config('app.name');
        $currency = '₹';

        $mail = (new MailMessage)
            ->subject("Order Confirmed – #{$order->order_no} | {$appName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Thank you for shopping with us! Your order **#{$order->order_no}** has been placed successfully.")
            ->line('');

        // Order summary table
        $itemLines = '';
        foreach ($order->orderItems as $item) {
            $name = $item->product?->name ?? 'Product';
            $sku  = $item->product_name_snapshot ?? $name;
            $itemLines .= "• {$name} (Qty: {$item->quantity}) — {$currency}" . number_format((float) $item->price * $item->quantity, 2) . "\n";
        }

        $mail->line('**Order Items:**')
            ->line($itemLines)
            ->line("Subtotal: {$currency}" . number_format((float) $order->sub_total, 2))
            ->line("Discount: -{$currency}" . number_format((float) $order->discount, 2))
            ->line("Shipping: " . ((float) $order->shipping > 0 ? "{$currency}" . number_format((float) $order->shipping, 2) : 'Free'))
            ->line("**Total: {$currency}" . number_format((float) $order->total, 2) . "**")
            ->line('')
            ->line('**Shipping to:**')
            ->line("{$order->shipping_name}, {$order->shipping_address}, {$order->shipping_city}, {$order->shipping_state} — {$order->shipping_pincode}")
            ->line("Phone: {$order->shipping_phone}");

        // Only when the storefront actually offers tracking — with it switched
        // off the /track routes 404, and this mail outlives the setting.
        if (OrderStatusHelper::publicTrackingVisible()) {
            $mail->action('Track Your Order', route('client.track.form'));
        }

        $mail->line("We will notify you once your order is shipped.")
            ->salutation("Thank you,\n{$appName} Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return ['order_id' => $this->order->id, 'order_no' => $this->order->order_no];
    }
}
