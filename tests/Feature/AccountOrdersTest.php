<?php

namespace Tests\Feature;

use App\Helper\OrderStatusHelper;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Where a customer can cancel from: every order card in "Your Orders" and the
 * order's tracking page, each showing the button only while the order can
 * still be cancelled (pending, or confirmed and not yet with the courier).
 */
class AccountOrdersTest extends TestCase
{
    use DatabaseTransactions;

    /** The button's class list; the page scripts also name these classes. */
    private const CARD_CANCEL = 'ao-cancel order-card__cancel"';
    private const TRACK_CANCEL = 'ou-btn-danger js-cancel-order"';

    private function customer(): Customer
    {
        return Customer::create([
            'name'     => 'Orders Test',
            'email'    => 'orders-test-' . uniqid() . '@example.com',
            'password' => bcrypt('secret1234'),
        ]);
    }

    private function order(Customer $customer, string $status): Order
    {
        $order = Order::create([
            'order_no'         => 'AO' . strtoupper(substr(uniqid(), -8)),
            'customer_id'      => $customer->id,
            'status'           => $status,
            'sub_total'        => 300,
            'total'            => 300,
            'shipping_name'    => 'Orders Test',
            'shipping_email'   => 'orders-test@example.com',
            'shipping_phone'   => '9999999999',
            'shipping_city'    => 'Ahmedabad',
            'shipping_pincode' => '380001',
            'shipping_state'   => 'Gujarat',
            'shipping_address' => '1 Test Street',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => Product::factory()->create(['price' => 100, 'status' => 1])->id,
            'quantity'   => 3,
            'price'      => 100,
        ]);

        return $order;
    }

    private function ship(Order $order): void
    {
        Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'status'   => Shipment::STATUS_IN_TRANSIT,
            'awb_code' => 'AWB' . uniqid(),
        ]);
    }

    private function ordersPage(Customer $customer): string
    {
        return $this->actingAs($customer, 'customer')
            ->get(route('client.profile', ['type' => 'orders']))
            ->assertOk()
            ->getContent();
    }

    // ------------------------------------------------------------------
    // Your Orders
    // ------------------------------------------------------------------

    public function test_each_pending_order_gets_its_own_cancel_button(): void
    {
        $customer = $this->customer();
        $first = $this->order($customer, Order::PENDING);
        $second = $this->order($customer, Order::PENDING);

        $content = $this->ordersPage($customer);

        $this->assertSame(2, substr_count($content, self::CARD_CANCEL));
        $this->assertStringContainsString('data-order-no="' . $first->order_no . '"', $content);
        $this->assertStringContainsString('data-order-no="' . $second->order_no . '"', $content);
    }

    public function test_a_confirmed_order_not_yet_shipped_can_be_cancelled(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, Order::CONFIRMED);

        $content = $this->ordersPage($customer);

        $this->assertSame(1, substr_count($content, self::CARD_CANCEL));
        $this->assertStringContainsString('data-order-no="' . $order->order_no . '"', $content);
    }

    public function test_shipped_and_cancelled_orders_have_no_cancel_button(): void
    {
        $customer = $this->customer();
        $shipped = $this->order($customer, Order::CONFIRMED);
        $this->ship($shipped);
        $cancelled = $this->order($customer, Order::CANCELLED);

        $content = $this->ordersPage($customer);

        $this->assertStringContainsString('#' . $shipped->order_no, $content);
        $this->assertStringContainsString('#' . $cancelled->order_no, $content);
        $this->assertStringNotContainsString(self::CARD_CANCEL, $content);
        $this->assertStringContainsString('This order was cancelled', $content);
    }

    // ------------------------------------------------------------------
    // Tracking page
    // ------------------------------------------------------------------

    private function trackPage(Customer $customer, Order $order): string
    {
        if (! OrderStatusHelper::trackingEnabled()) {
            $this->markTestSkipped('Order tracking is disabled for this deployment (no configured courier).');
        }
        $settings = Setting::query()->first() ?: new Setting();
        $settings->storefront_content = array_merge($settings->storefront_content ?? [], ['show_track_order' => true]);
        $settings->save();

        return $this->actingAs($customer, 'customer')
            ->get(route('client.track.show', ['orderNo' => $order->order_no]))
            ->assertOk()
            ->getContent();
    }

    public function test_the_tracking_page_offers_cancel_while_the_order_can_be_cancelled(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, Order::CONFIRMED);

        $content = $this->trackPage($customer, $order);

        $this->assertStringContainsString(self::TRACK_CANCEL, $content);
        $this->assertStringContainsString(route('client.order.cancel', ['orderNo' => $order->order_no]), $content);
    }

    public function test_the_tracking_page_hides_cancel_once_the_order_ships(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, Order::CONFIRMED);
        $this->ship($order);

        $this->assertStringNotContainsString(self::TRACK_CANCEL, $this->trackPage($customer, $order));
    }

    public function test_the_tracking_page_keeps_courier_details_and_journey_private(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, Order::CONFIRMED);
        $this->ship($order);

        $content = $this->trackPage($customer, $order);

        foreach (['Journey', 'Tracking no.', 'Open courier tracking', 'id="tr-awb"', 'id="tr-timeline"'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $content);
        }
        // The progress itself is still there.
        $this->assertStringContainsString('id="tr-rail"', $content);
    }
}
