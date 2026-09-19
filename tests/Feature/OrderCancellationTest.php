<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Customer-initiated cancellation of an order that has not been confirmed yet.
 *
 * Uses DatabaseTransactions rather than RefreshDatabase on purpose: phpunit.xml
 * does not point the suite at its own database, so these run against whatever
 * connection is configured — rolling back is safe, migrating fresh would drop
 * a working database.
 */
class OrderCancellationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOrder(string $status, ?string $paymentType, bool $stockCommitted, int $stock = 10, int $qty = 3): array
    {
        $customer = Customer::create([
            'name'     => 'Cancel Test',
            'email'    => 'cancel-test-' . uniqid() . '@example.com',
            'password' => bcrypt('secret1234'),
        ]);

        $product = Product::factory()->create([
            'price'  => 100,
            'stock'  => $stock,
            'status' => 1,
        ]);

        $order = Order::create([
            'order_no'         => 'CT' . strtoupper(substr(uniqid(), -8)),
            'customer_id'      => $customer->id,
            'status'           => $status,
            'sub_total'        => 300,
            'total'            => 300,
            'shipping_name'    => 'Cancel Test',
            'shipping_email'   => 'cancel-test@example.com',
            'shipping_phone'   => '9999999999',
            'shipping_city'    => 'Ahmedabad',
            'shipping_pincode' => '380001',
            'shipping_state'   => 'Gujarat',
            'shipping_address' => '1 Test Street',
        ]);
        if ($stockCommitted) {
            $order->forceFill(['stock_committed_at' => now()])->save();
        }

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => $qty,
            'price'      => 100,
        ]);

        if ($paymentType) {
            Payment::create([
                'order_id' => $order->id,
                'type'     => $paymentType,
                'status'   => Payment::STATUS_PENDING,
                'amount'   => 300,
            ]);
        }

        return [$customer, $product, $order];
    }

    public function test_customer_can_cancel_a_pending_cod_order_and_stock_comes_back(): void
    {
        Notification::fake();
        [$customer, $product, $order] = $this->makeOrder(Order::PENDING, 'cod', true, stock: 10, qty: 3);

        $response = $this->actingAs($customer, 'customer')
            ->from(route('client.profile'))
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]), ['reason' => 'ordered by mistake']);

        $response->assertRedirect(route('client.profile'));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        // Stock committed at placement for COD, so cancelling must hand it back.
        $this->assertSame(13, (int) $product->fresh()->stock);
        $this->assertNull($order->stock_committed_at, 'the stock stamp must clear so a replay cannot restore twice');

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(Order::CANCELLED, $history->status);
        $this->assertSame(OrderStatusHistory::SOURCE_CUSTOMER, $history->source);
        $this->assertStringContainsString('ordered by mistake', $history->comment);
    }

    public function test_cancelling_an_unpaid_prepaid_order_does_not_invent_stock(): void
    {
        Notification::fake();
        // Prepaid orders only commit stock when the payment is captured, so a
        // pending one has taken nothing off the shelf yet.
        [$customer, $product, $order] = $this->makeOrder(Order::PENDING, 'razorpay', false, stock: 10, qty: 3);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock, 'stock that was never taken must not be handed back');
    }

    public function test_a_confirmed_order_can_still_be_cancelled_until_it_ships(): void
    {
        Notification::fake();
        [$customer, $product, $order] = $this->makeOrder(Order::CONFIRMED, 'cod', true, stock: 10, qty: 3);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(13, (int) $product->fresh()->stock);
    }

    public function test_an_order_already_with_the_courier_cannot_be_cancelled(): void
    {
        Notification::fake();
        [$customer, $product, $order] = $this->makeOrder(Order::CONFIRMED, 'cod', true, stock: 10, qty: 3);
        \App\Models\Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'status'   => \App\Models\Shipment::STATUS_AWB_ASSIGNED,
            'awb_code' => 'AWB' . uniqid(),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('error');

        $this->assertSame(Order::CONFIRMED, $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_a_cancelled_shipment_does_not_block_cancelling(): void
    {
        Notification::fake();
        [$customer, , $order] = $this->makeOrder(Order::CONFIRMED, 'cod', true);
        \App\Models\Shipment::create([
            'order_id' => $order->id,
            'provider' => 'shiprocket',
            'status'   => \App\Models\Shipment::STATUS_CANCELLED,
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_cancelling_a_paid_online_order_flags_the_refund(): void
    {
        Notification::fake();
        [$customer, , $order] = $this->makeOrder(Order::CONFIRMED, 'razorpay', true);
        Payment::where('order_id', $order->id)->update(['status' => Payment::STATUS_PAID]);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'refund'));

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertStringContainsString('Refund due: ₹300.00', $history->comment);
    }

    public function test_a_customer_cannot_cancel_someone_elses_order(): void
    {
        Notification::fake();
        [, $product, $order] = $this->makeOrder(Order::PENDING, 'cod', true, stock: 10, qty: 3);

        $intruder = Customer::create([
            'name'     => 'Intruder',
            'email'    => 'intruder-' . uniqid() . '@example.com',
            'password' => bcrypt('secret1234'),
        ]);

        $this->actingAs($intruder, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('error');

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_a_second_cancel_does_not_restore_stock_twice(): void
    {
        Notification::fake();
        [$customer, $product, $order] = $this->makeOrder(Order::PENDING, 'cod', true, stock: 10, qty: 3);

        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('success');
        $this->assertSame(13, (int) $product->fresh()->stock);

        // A double submit, or a stale tab, must not top the stock up again.
        $this->actingAs($customer, 'customer')
            ->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertSessionHas('error');
        $this->assertSame(13, (int) $product->fresh()->stock);
    }

    public function test_guests_are_not_allowed_to_cancel(): void
    {
        [, , $order] = $this->makeOrder(Order::PENDING, 'cod', true);

        $this->post(route('client.order.cancel', ['orderNo' => $order->order_no]))
            ->assertRedirect();

        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    /**
     * The admin side had the same hole: cancelling only flipped the status, so
     * the stock stayed reserved against an order nobody was going to ship.
     */
    public function test_admin_cancelling_an_order_also_returns_the_stock(): void
    {
        Notification::fake();
        [, $product, $order] = $this->makeOrder(Order::CONFIRMED, 'cod', true, stock: 10, qty: 3);

        $admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin-cancel-' . uniqid() . '@example.com',
            'password' => bcrypt('secret1234'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.order.update', ['id' => $order->id]), [
                'status'  => Order::CANCELLED,
                'comment' => 'Customer rang up to cancel.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(13, (int) $product->fresh()->stock);
        $this->assertNull($order->fresh()->stock_committed_at);
    }
}
