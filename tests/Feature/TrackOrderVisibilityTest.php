<?php

namespace Tests\Feature;

use App\Helper\OrderStatusHelper;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The "Offer order tracking" setting.
 *
 * Off must remove tracking from the whole storefront, not just the marketing
 * links: the customer's own order surfaces, the order mails, and the /track
 * routes themselves. Hiding links while leaving the routes open is a
 * half-measure, because those URLs are guessable and already sit in inboxes.
 *
 * One storefront request per test method, on purpose. The settings view
 * composer resolves once per application instance (see AppServiceProvider), so
 * a second render inside the same test would reuse the first one's settings and
 * quietly assert nothing. Laravel rebuilds the application between methods,
 * which is what makes each case independent.
 */
class TrackOrderVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function setTracking(bool $on): void
    {
        $settings = Setting::query()->first() ?: new Setting();
        $content = $settings->storefront_content ?? [];
        $content['show_track_order'] = $on;
        $settings->storefront_content = $content;
        $settings->save();
    }

    private function order(): Order
    {
        $customer = Customer::create([
            'name'     => 'Track Test',
            'email'    => 'track-' . uniqid() . '@example.test',
            'password' => bcrypt('secret1234'),
        ]);

        return Order::create([
            'order_no'         => 'TR' . strtoupper(substr(uniqid(), -8)),
            'customer_id'      => $customer->id,
            'status'           => Order::PENDING,
            'sub_total'        => 100,
            'total'            => 100,
            'shipping_name'    => 'Track Test',
            'shipping_email'   => 'track@example.test',
            'shipping_phone'   => '9999999999',
            'shipping_city'    => 'Ahmedabad',
            'shipping_pincode' => '380001',
            'shipping_state'   => 'Gujarat',
            'shipping_address' => '1 Test Street',
        ]);
    }

    /**
     * The deployment-level switch (kill switch + a configured courier) is a
     * precondition for any of this. Where it is already off, the "on" cases
     * cannot be exercised — the setting is only ever the second gate.
     */
    private function skipWithoutCourier(): void
    {
        if (! OrderStatusHelper::trackingEnabled()) {
            $this->markTestSkipped('Order tracking is disabled for this deployment (no configured courier).');
        }
    }

    // ------------------------------------------------------------------
    // Storefront entry points
    // ------------------------------------------------------------------

    public function test_the_storefront_offers_tracking_while_the_setting_is_on(): void
    {
        $this->skipWithoutCourier();
        $this->setTracking(true);

        $this->assertStringContainsString(
            route('client.track.form'),
            $this->get(route('client.home'))->getContent()
        );
    }

    public function test_the_storefront_drops_every_track_link_when_the_setting_is_off(): void
    {
        $this->setTracking(false);

        // Covers the nav, the account menu, the mobile menu and both footer
        // links in one pass — none of them may render the URL at all.
        $this->assertStringNotContainsString(
            route('client.track.form'),
            $this->get(route('client.home'))->getContent()
        );
    }

    // ------------------------------------------------------------------
    // The routes themselves
    // ------------------------------------------------------------------

    public function test_the_track_routes_answer_404_when_the_setting_is_off(): void
    {
        $this->setTracking(false);

        // Hiding the links is not enough — these URLs are bookmarked and mailed.
        $this->get(route('client.track.form'))->assertNotFound();
        $this->get('/track/ABC123')->assertNotFound();
    }

    public function test_the_track_form_answers_while_the_setting_is_on(): void
    {
        $this->skipWithoutCourier();
        $this->setTracking(true);

        $this->get(route('client.track.form'))->assertOk();
    }

    // ------------------------------------------------------------------
    // The customer's own order
    // ------------------------------------------------------------------

    public function test_the_order_confirmation_offers_tracking_while_the_setting_is_on(): void
    {
        $this->skipWithoutCourier();
        $this->setTracking(true);
        $order = $this->order();

        $this->assertStringContainsString(
            route('client.track.show', ['orderNo' => $order->order_no]),
            $this->actingAs($order->customer, 'customer')
                ->get(route('client.order.confirmation', ['orderNo' => $order->order_no]))
                ->getContent()
        );
    }

    public function test_the_order_confirmation_drops_it_when_the_setting_is_off(): void
    {
        $this->setTracking(false);
        $order = $this->order();

        // The routes are closed, so a link here would send the customer to a 404.
        $this->assertStringNotContainsString(
            route('client.track.show', ['orderNo' => $order->order_no]),
            $this->actingAs($order->customer, 'customer')
                ->get(route('client.order.confirmation', ['orderNo' => $order->order_no]))
                ->getContent()
        );
    }

    // ------------------------------------------------------------------
    // Order mail
    // ------------------------------------------------------------------

    public function test_order_mail_offers_tracking_while_the_setting_is_on(): void
    {
        $this->skipWithoutCourier();
        $this->setTracking(true);
        $order = $this->order();

        $this->assertStringContainsString(
            route('client.track.form'),
            (string) (new OrderPlaced($order))->toMail($order->customer)->render()
        );
    }

    public function test_order_mail_drops_the_track_button_when_the_setting_is_off(): void
    {
        $this->setTracking(false);
        $order = $this->order();

        // The mail outlives the setting, so it must not promise a dead page.
        $this->assertStringNotContainsString(
            route('client.track.form'),
            (string) (new OrderPlaced($order))->toMail($order->customer)->render()
        );
    }

    // ------------------------------------------------------------------
    // The helper
    // ------------------------------------------------------------------

    public function test_the_helper_resolves_the_setting_without_being_handed_one(): void
    {
        // The middleware and the mailers have no shared $settings to pass.
        $this->setTracking(false);
        $this->assertFalse(OrderStatusHelper::publicTrackingVisible());
    }

    public function test_the_helper_reads_a_freshly_changed_setting(): void
    {
        $this->skipWithoutCourier();

        // Not memoized in a static: a queue worker would otherwise keep mailing
        // a link to a page the admin had since switched off.
        $this->setTracking(false);
        $this->assertFalse(OrderStatusHelper::publicTrackingVisible());

        $this->setTracking(true);
        $this->assertTrue(OrderStatusHelper::publicTrackingVisible());
    }
}
