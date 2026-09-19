<?php

namespace App\Notifications;

use App\Helper\OrderStatusHelper;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order    $order,
        public Shipment $shipment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $mail = (new MailMessage)
            ->subject("Your Order #{$this->order->order_no} Has Been Shipped! | {$appName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Great news! Your order **#{$this->order->order_no}** is on its way.")
            ->line('');

        if ($this->shipment->awb_code) {
            $mail->line("**Tracking Number (AWB):** {$this->shipment->awb_code}")
                ->line("**Courier:** " . ($this->shipment->courier_name ?? $this->shipment->provider));
        }

        if ($this->shipment->tracking_url) {
            // The courier's own URL, so it stands regardless of our setting.
            $mail->action('Track Shipment', $this->shipment->tracking_url);
        } elseif (OrderStatusHelper::publicTrackingVisible()) {
            // Ours only when the storefront still serves it — with tracking
            // switched off /track answers 404.
            $mail->action('Track Your Order', route('client.track.form'));
        }

        if ($this->shipment->shipped_at) {
            $mail->line("Shipped on: " . $this->shipment->shipped_at->format('d M Y'));
        }

        $mail->line("Estimated delivery: " . ($this->order->shipping_eta ?? 'Within 3–5 business days'))
            ->line("If you have any questions, reply to this email.")
            ->salutation("Thanks,\n{$appName} Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id'    => $this->order->id,
            'order_no'    => $this->order->order_no,
            'shipment_id' => $this->shipment->id,
            'awb_code'    => $this->shipment->awb_code,
        ];
    }
}
