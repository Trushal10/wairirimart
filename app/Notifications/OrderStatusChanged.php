<?php

namespace App\Notifications;

use App\Helper\OrderStatusHelper;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    private static array $labels = [
        'pending'   => 'Order Placed',
        'confirmed' => 'Payment Confirmed',
        'delivered' => 'Delivered',
        'canceled'  => 'Cancelled',
    ];

    public function __construct(
        public Order  $order,
        public string $newStatus,
        public string $comment = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $label   = self::$labels[$this->newStatus] ?? ucfirst($this->newStatus);

        $mail = (new MailMessage)
            ->subject("Order #{$this->order->order_no} — {$label} | {$appName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order **#{$this->order->order_no}** status has been updated to **{$label}**.");

        if ($this->comment) {
            $mail->line("Note: {$this->comment}");
        }

        if ($this->newStatus === 'canceled') {
            $mail->line("If you believe this is a mistake or have any questions, please contact us.");
        }

        // Only when the storefront still serves /track; it 404s with tracking off.
        if (OrderStatusHelper::publicTrackingVisible()) {
            $mail->action('View Order', route('client.track.form'));
        }

        $mail->salutation("Thank you,\n{$appName} Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id'   => $this->order->id,
            'order_no'   => $this->order->order_no,
            'new_status' => $this->newStatus,
        ];
    }
}
