<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;

interface PaymentGatewayContract
{
    /** Underlying admin DB row (credentials, mode, config). */
    public function getModel(): PaymentGateway;

    /** Unique code — must match PaymentGateway.code. */
    public function code(): string;

    /** Display name shown in the admin panel. */
    public function name(): string;

    /** Which capabilities the gateway supports: refund, partial_refund, webhook, upi, cards, wallets. */
    public function supports(): array;

    /**
     * Fields the admin must provide. Format:
     *   [ 'field_name' => ['label' => 'Public Key', 'type' => 'text|password|url', 'required' => true, 'help' => '...'] ]
     */
    public function credentialSchema(): array;

    /**
     * Quick health-check against the provider — returns [ok => bool, message => string].
     * Never expose raw credentials on error.
     */
    public function testConnection(): array;

    /**
     * Create a checkout session / order on the gateway. Returns a normalized payload
     * the frontend can hand to the client-side SDK.
     *
     *   [
     *     'gateway'          => 'razorpay',
     *     'gateway_order_id' => '<id>',
     *     'amount'           => 12345,          // int, minor units (paise/cents)
     *     'currency'         => 'INR',
     *     'callback_url'     => '<url>',
     *     'sdk_config'       => [...]           // provider-specific extras
     *   ]
     */
    public function createCheckout(Order $order, Payment $payment): array;

    /**
     * Verify a client callback and mark the Payment paid. Throws on failure.
     */
    public function verifyCallback(Order $order, Payment $payment, array $callbackPayload): array;

    /**
     * Verify webhook signature. Returns true when signature matches.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Refund a captured payment (partial or full). Returns provider response.
     */
    public function refund(Payment $payment, float $amount, ?string $reason = null): array;
}
