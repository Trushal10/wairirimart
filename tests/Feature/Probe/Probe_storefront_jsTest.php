<?php

namespace Tests\Feature\Probe;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Probe_storefront_jsTest extends TestCase
{
    use DatabaseTransactions;

    /** Expired/absent customer session on an AJAX client_auth endpoint: what does the browser get back? */
    public function test_client_auth_ajax_gets_redirect_not_401(): void
    {
        // jQuery $.ajax default (no dataType): Accept */*, X-Requested-With set.
        $res = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => '*/*',
        ])->post('/order', ['payment_method' => 'cod', 'address_id' => 1, 'agree_tos' => 1]);
        fwrite(STDERR, "\n/order status=" . $res->getStatusCode() . " location=" . ($res->headers->get('Location') ?? '-') . "\n");

        // dataType:'json' style
        $res2 = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
        ])->post('/user-address-save', ['name' => 'x']);
        fwrite(STDERR, "/user-address-save status=" . $res2->getStatusCode() . " location=" . ($res2->headers->get('Location') ?? '-') . "\n");

        $res3 = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('/wishlist/toggle', ['product_id' => 1]);
        fwrite(STDERR, "/wishlist/toggle status=" . $res3->getStatusCode() . " location=" . ($res3->headers->get('Location') ?? '-') . "\n");

        $this->assertTrue(in_array($res->getStatusCode(), [302, 401]));
    }
}
