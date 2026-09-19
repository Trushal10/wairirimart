<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\VariantOptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Options & variants, end to end.
 *
 * Three layers, because a break in any one of them is invisible from the others:
 *   admin      what the form is allowed to save, and what lands in option_types
 *   service    how that JSON becomes the storefront's variants + option groups
 *   storefront what the shopper actually sees rendered
 *
 * DatabaseTransactions rather than RefreshDatabase on purpose — see
 * OrderCancellationTest: phpunit.xml does not point the suite at its own
 * database, so rolling back is safe and migrating fresh would drop a working one.
 */
class ProductVariantsTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function category(): Category
    {
        $tag = uniqid();

        return Category::create(['name' => 'Variants ' . $tag, 'slug' => 'variants-' . $tag, 'status' => 1]);
    }

    /** A product payload for the admin form. Variants are opt-in via $overrides. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'              => 'Variant Test ' . uniqid(),
            'short_description' => 'Variant test product',
            'description'       => '<p>Variant test product</p>',
            'categories'        => [$this->category()->id],
            'price'             => 100,
            'compere_price'     => 120,
            'sku'               => 'VT-' . uniqid(),
            'stock'             => 5,
            'status'            => 1,
            'has_variants'      => 0,
        ], $overrides);
    }

    /** An option group in the stored shape: values carry their own swatch. */
    private function group(string $name, string $type, array $values): array
    {
        return [
            'name'   => $name,
            'type'   => $type,
            'values' => collect($values)
                ->map(fn ($swatch, $value) => array_filter(
                    ['value' => (string) $value, 'swatch' => $swatch],
                    fn ($v) => $v !== null
                ))
                ->values()
                ->all(),
        ];
    }

    private function variant(array $options, array $overrides = []): array
    {
        return array_merge([
            'sku'        => 'V-' . uniqid(),
            'price'      => null,
            'stock'      => 3,
            'status'     => 1,
            'is_default' => 0,
            'options'    => $options,
        ], $overrides);
    }

    private function storeProduct(array $payload): Product
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.products'));

        return Product::where('name', $payload['name'])->firstOrFail();
    }

    /** A saved product with a colour group and a text group. */
    private function colourAndSizeProduct(): Product
    {
        return $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [
                $this->group('Colour', 'color', ['Red' => '#C62828', 'Blue' => '#1565c0']),
                $this->group('Size', 'text', ['S' => null, 'M' => null]),
            ],
            'variants' => [
                $this->variant(['Colour' => 'Red', 'Size' => 'S'], ['is_default' => 1]),
                $this->variant(['Colour' => 'Red', 'Size' => 'M']),
                $this->variant(['Colour' => 'Blue', 'Size' => 'S'], ['stock' => 0]),
                $this->variant(['Colour' => 'Blue', 'Size' => 'M'], ['stock' => 0]),
            ],
        ]));
    }

    // ------------------------------------------------------------------
    // Admin — what gets saved
    // ------------------------------------------------------------------

    public function test_option_values_are_saved_with_their_swatches_and_order(): void
    {
        $product = $this->colourAndSizeProduct();
        $types   = $product->option_types;

        $this->assertTrue($product->has_variants);
        $this->assertSame(0, (int) $product->stock, 'base stock is meaningless once variants exist');

        $this->assertSame('Colour', $types[0]['name']);
        $this->assertSame('color', $types[0]['type']);
        // Declared order is preserved, and the hex is normalized to lower case.
        $this->assertSame(
            [['value' => 'Red', 'swatch' => '#c62828'], ['value' => 'Blue', 'swatch' => '#1565c0']],
            $types[0]['values']
        );

        // A text group keeps its values but never a swatch.
        $this->assertSame('text', $types[1]['type']);
        $this->assertSame(['S', 'M'], array_column($types[1]['values'], 'value'));
        $this->assertNull($types[1]['values'][0]['swatch']);
    }

    public function test_an_unparseable_colour_is_dropped_rather_than_stored(): void
    {
        // Anything that is not #rgb / #rrggbb would end up inside a style
        // attribute on the storefront, so it is refused at the boundary.
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Colour', 'color', ['Red' => 'javascript:alert(1)'])],
            'variants'     => [$this->variant(['Colour' => 'Red'], ['is_default' => 1])],
        ]));

        $this->assertNull($product->option_types[0]['values'][0]['swatch']);
    }

    public function test_image_swatch_uploads_are_stored_and_the_replaced_file_is_removed(): void
    {
        Storage::fake('local');

        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [[
                'name'   => 'Finish',
                'type'   => 'image',
                'values' => [['value' => 'Matte', 'swatch_file' => UploadedFile::fake()->image('matte.jpg', 80, 80)]],
            ]],
            'variants' => [$this->variant(['Finish' => 'Matte'], ['is_default' => 1])],
        ]));

        $first = $product->option_types[0]['values'][0]['swatch'];
        $this->assertNotEmpty($first);
        Storage::disk('local')->assertExists('public/product/' . $first);

        $this->actingAs($this->admin())
            ->put(route('admin.product.update', $product), $this->payload([
                'name'         => $product->name,
                'has_variants' => 1,
                'option_types' => [[
                    'name'   => 'Finish',
                    'type'   => 'image',
                    'values' => [['value' => 'Matte', 'swatch_file' => UploadedFile::fake()->image('matte2.jpg', 80, 80)]],
                ]],
                'variants' => [$this->variant(['Finish' => 'Matte'], [
                    'id' => $product->variants->first()->id, 'is_default' => 1,
                ])],
            ]))
            ->assertSessionHasNoErrors();

        $second = $product->fresh()->option_types[0]['values'][0]['swatch'];
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing('public/product/' . $first);
        Storage::disk('local')->assertExists('public/product/' . $second);
    }

    public function test_a_swatch_filename_the_product_does_not_own_is_refused(): void
    {
        // The filename field only exists so an unchanged swatch survives a save.
        // Accepting an arbitrary string would let the form point at any file.
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [[
                'name'   => 'Finish',
                'type'   => 'image',
                'values' => [['value' => 'Matte', 'swatch' => '../../../.env']],
            ]],
            'variants' => [$this->variant(['Finish' => 'Matte'], ['is_default' => 1])],
        ]));

        $this->assertNull($product->option_types[0]['values'][0]['swatch']);
    }

    public function test_the_variants_toggle_is_authoritative_when_switched_off(): void
    {
        $product = $this->colourAndSizeProduct();
        $variantIds = $product->variants->pluck('id');

        // Toggle off, but the browser still posts the matrix it had on screen.
        $this->actingAs($this->admin())
            ->put(route('admin.product.update', $product), $this->payload([
                'name'         => $product->name,
                'has_variants' => 0,
                'stock'        => 7,
                'option_types' => [$this->group('Colour', 'color', ['Red' => '#c62828'])],
                'variants'     => [$this->variant(['Colour' => 'Red'], ['is_default' => 1])],
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertFalse($product->has_variants, 'the toggle wins over a non-empty variants array');
        $this->assertNull($product->option_types);
        $this->assertSame(7, (int) $product->stock);
        $this->assertCount(0, $product->variants);
        $this->assertSame(
            $variantIds->count(),
            ProductVariant::onlyTrashed()->whereIn('id', $variantIds)->count(),
            'the old matrix is pruned so stock rollups stop counting it'
        );
    }

    public function test_a_variant_image_is_kept_on_disk_while_an_order_still_references_it(): void
    {
        Storage::fake('local');

        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Size', 'text', ['S' => null, 'M' => null])],
            'variants'     => [
                $this->variant(['Size' => 'S'], [
                    'is_default' => 1,
                    'image_file' => UploadedFile::fake()->image('s.jpg', 80, 80),
                ]),
                $this->variant(['Size' => 'M'], ['image_file' => UploadedFile::fake()->image('m.jpg', 80, 80)]),
            ],
        ]));

        [$sold, $unsold] = [
            $product->variants->firstWhere('options.Size', 'S'),
            $product->variants->firstWhere('options.Size', 'M'),
        ];
        $soldImage   = $sold->image_url;
        $unsoldImage = $unsold->image_url;

        $customer = Customer::create([
            'name' => 'Variant Buyer', 'email' => 'vb-' . uniqid() . '@example.test',
            'password' => bcrypt('secret1234'),
        ]);
        $order = Order::create([
            'order_no' => 'VT' . strtoupper(substr(uniqid(), -8)),
            'customer_id' => $customer->id, 'status' => Order::PENDING,
            'sub_total' => 100, 'total' => 100,
            'shipping_name' => 'V', 'shipping_email' => 'v@example.test', 'shipping_phone' => '9999999999',
            'shipping_city' => 'Ahmedabad', 'shipping_pincode' => '380001',
            'shipping_state' => 'Gujarat', 'shipping_address' => '1 Test Street',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_variant_id' => $sold->id, 'quantity' => 1, 'price' => 100,
        ]);

        // Drop both variants by switching the toggle off.
        $this->actingAs($this->admin())
            ->put(route('admin.product.update', $product), $this->payload([
                'name' => $product->name, 'has_variants' => 0, 'stock' => 4,
            ]))
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists('public/product/' . $soldImage);
        Storage::disk('local')->assertMissing('public/product/' . $unsoldImage);
    }

    public function test_an_inactive_default_hands_the_flag_to_the_first_active_variant(): void
    {
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Size', 'text', ['S' => null, 'M' => null, 'L' => null])],
            'variants'     => [
                $this->variant(['Size' => 'S'], ['is_default' => 1, 'status' => 0]),
                $this->variant(['Size' => 'M']),
                $this->variant(['Size' => 'L']),
            ],
        ]));

        $defaults = $product->variants->where('is_default', true);
        $this->assertCount(1, $defaults);
        $this->assertSame('M', $defaults->first()->options['Size']);
    }

    // ------------------------------------------------------------------
    // Admin — what is refused
    // ------------------------------------------------------------------

    public function test_enabling_variants_with_an_empty_matrix_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload(['has_variants' => 1, 'variants' => []]))
            ->assertSessionHasErrors(['variants']);
    }

    public function test_stock_is_required_when_variants_are_disabled(): void
    {
        $payload = $this->payload();
        unset($payload['stock']);

        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $payload)
            ->assertSessionHasErrors(['stock']);
    }

    public function test_duplicate_option_type_names_are_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload([
                'has_variants' => 1,
                'option_types' => [
                    $this->group('Colour', 'text', ['Red' => null]),
                    $this->group('colour', 'text', ['Blue' => null]),
                ],
                'variants' => [$this->variant(['Colour' => 'Red'], ['is_default' => 1])],
            ]))
            ->assertSessionHasErrors(['option_types']);
    }

    public function test_several_variants_need_an_option_type_to_tell_them_apart(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload([
                'has_variants' => 1,
                'variants'     => [$this->variant([], ['is_default' => 1]), $this->variant([])],
            ]))
            ->assertSessionHasErrors(['option_types']);
    }

    public function test_blank_and_duplicate_option_combinations_are_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload([
                'has_variants' => 1,
                'option_types' => [$this->group('Size', 'text', ['S' => null])],
                'variants'     => [
                    $this->variant(['Size' => 'S'], ['is_default' => 1]),
                    $this->variant(['Size' => 'S']),
                    $this->variant(['Size' => '']),
                ],
            ]))
            ->assertSessionHasErrors(['variants.1.options', 'variants.2.options']);
    }

    public function test_at_least_one_variant_must_be_active(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload([
                'has_variants' => 1,
                'option_types' => [$this->group('Size', 'text', ['S' => null])],
                'variants'     => [$this->variant(['Size' => 'S'], ['status' => 0, 'is_default' => 1])],
            ]))
            ->assertSessionHasErrors(['variants']);
    }

    public function test_an_option_name_with_brackets_is_refused(): void
    {
        // The name becomes a form-data key (variants[0][options][Colour]).
        $this->actingAs($this->admin())
            ->post(route('admin.product.store'), $this->payload([
                'has_variants' => 1,
                'option_types' => [$this->group('Col[our]', 'text', ['Red' => null])],
                'variants'     => [$this->variant(['Col[our]' => 'Red'], ['is_default' => 1])],
            ]))
            ->assertSessionHasErrors(['option_types.0.name']);
    }

    // ------------------------------------------------------------------
    // Service — option_types JSON becomes the picker payload
    // ------------------------------------------------------------------

    public function test_groups_carry_type_swatch_and_availability(): void
    {
        $payload = app(VariantOptionService::class)->payload($this->colourAndSizeProduct());
        $colour  = $payload['groups'][0];

        $this->assertSame('color', $colour['type']);
        $this->assertSame('#c62828', $colour['values'][0]['swatch']);
        $this->assertSame('Red', $colour['values'][0]['label'], 'a swatch never replaces its label');
        $this->assertTrue($colour['values'][0]['in_stock']);
        $this->assertFalse($colour['values'][1]['in_stock'], 'a colour sold out in every size is flagged');

        // A null variant price inherits the product price.
        $this->assertSame(100.0, $payload['variants'][0]['price']);
    }

    public function test_a_value_no_variant_uses_is_dropped_from_the_picker(): void
    {
        // A pill for a value with no SKU behind it can never resolve to a variant.
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Colour', 'color', ['Red' => '#c62828', 'Green' => '#2e7d32'])],
            'variants'     => [$this->variant(['Colour' => 'Red'], ['is_default' => 1])],
        ]));

        $groups = app(VariantOptionService::class)->payload($product)['groups'];
        $this->assertSame(['Red'], array_column($groups[0]['values'], 'value'));
    }

    public function test_an_image_group_falls_back_to_the_variant_photo(): void
    {
        Storage::fake('local');

        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [[
                'name'   => 'Finish',
                'type'   => 'image',
                'values' => [['value' => 'Matte'], ['value' => 'Gloss']],
            ]],
            'variants' => [
                $this->variant(['Finish' => 'Matte'], [
                    'is_default' => 1,
                    'image_file' => UploadedFile::fake()->image('m.jpg', 80, 80),
                ]),
                $this->variant(['Finish' => 'Gloss']),
            ],
        ]));

        $values = app(VariantOptionService::class)->payload($product)['groups'][0]['values'];
        $matte  = $product->variants->firstWhere('options.Finish', 'Matte');

        $this->assertStringContainsString($matte->image_url, $values[0]['swatch']);
        $this->assertNull($values[1]['swatch'], 'no upload and no variant photo leaves it a text pill');
    }

    public function test_a_product_without_variants_yields_an_empty_payload(): void
    {
        $product = $this->storeProduct($this->payload());

        $this->assertSame(
            ['variants' => [], 'groups' => []],
            app(VariantOptionService::class)->payload($product)
        );
    }

    public function test_the_pre_migration_swatch_map_still_renders(): void
    {
        // Products saved before the value list moved onto the option type keep
        // their swatches until the migration reaches them.
        $product = $this->colourAndSizeProduct();
        $product->forceFill(['option_types' => [
            ['name' => 'Colour', 'type' => 'color', 'swatches' => ['Red' => '#c62828', 'Blue' => '#1565c0']],
            ['name' => 'Size', 'type' => 'text'],
        ]])->save();

        $groups = app(VariantOptionService::class)->payload($product->fresh())['groups'];

        $this->assertSame('#c62828', $groups[0]['values'][0]['swatch']);
        $this->assertSame(['S', 'M'], array_column($groups[1]['values'], 'value'));
    }

    public function test_the_opening_variant_skips_a_sold_out_default(): void
    {
        $service = app(VariantOptionService::class);
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Size', 'text', ['S' => null, 'M' => null])],
            'variants'     => [
                $this->variant(['Size' => 'S'], ['is_default' => 1, 'stock' => 0]),
                $this->variant(['Size' => 'M'], ['stock' => 4, 'price' => 150]),
            ],
        ]));

        $variants = $service->payload($product)['variants'];
        $opening  = $service->displayVariant($variants);

        $this->assertSame('M', $opening['options']['Size']);
        $this->assertSame(150.0, $opening['price']);
    }

    // ------------------------------------------------------------------
    // Storefront — what the shopper sees
    // ------------------------------------------------------------------

    public function test_the_product_page_renders_a_colour_dot_beside_its_label(): void
    {
        $product = $this->colourAndSizeProduct();

        $response = $this->get(route('client.product', ['productSlug' => $product->slug]));
        $response->assertOk();

        $response->assertSee('by-swatch--color', false);
        $response->assertSee('background-color: #c62828;', false);
        // The label survives next to the dot — a bare swatch names nothing.
        $response->assertSee('<span class="text-title">Red</span>', false);
        $response->assertSee('<span class="text-title">Blue</span>', false);
        // The text group stays a plain pill.
        $response->assertSee('by-swatch--text', false);
        $response->assertSee('<span class="text-title">S</span>', false);
    }

    public function test_a_sold_out_value_is_disabled_but_the_current_pick_is_not(): void
    {
        $product = $this->colourAndSizeProduct();

        $content = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();

        // Blue has no stock in any size, and is not the opening selection.
        $this->assertStringContainsString('variant-oos', $content);
        // Red is the opening selection and must stay selectable.
        $this->assertMatchesRegularExpression(
            '/value="Red"[^>]*checked(?![^>]*disabled)/',
            $content,
            'the opening value must never render disabled'
        );
    }

    public function test_the_buy_box_and_the_sticky_bar_share_the_opening_variant(): void
    {
        $product = $this->colourAndSizeProduct();
        $opening = $product->variants->firstWhere('is_default', true);

        $content = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();

        // Both add-to-cart buttons carry it, so the mobile bar posts a real
        // variant instead of a bare product id.
        $this->assertSame(2, substr_count($content, 'data-variant-id="' . $opening->id . '"'));
        // Both price nodes use the same data-role, which is how one picker keeps
        // them in step without knowing about the bar.
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'data-role="variant-price"'));
    }

    public function test_the_quick_add_modal_uses_the_same_picker(): void
    {
        $product = $this->colourAndSizeProduct();

        $response = $this->post(route('client.quickAdd'), ['productId' => $product->id]);
        $response->assertOk();

        $response->assertSee('by-swatch--color', false);
        $response->assertSee('background-color: #1565c0;', false);
        $response->assertSee('<span class="text-title">Blue</span>', false);
        // A distinct id prefix so the two pickers never collide on a page that
        // renders both.
        $response->assertSee('quick-opt-g0-0', false);
    }

    // ------------------------------------------------------------------
    // Storefront — the quantity stepper
    // ------------------------------------------------------------------

    public function test_the_quantity_stepper_is_hidden_when_only_one_can_be_bought(): void
    {
        // A stepper that cannot step is a dead control, so it is not rendered.
        $product = $this->storeProduct($this->payload(['stock' => 1]));

        $content = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();

        $this->assertMatchesRegularExpression(
            '/data-role="quantity-block"[^>]*\bhidden\b/',
            $content,
            'one buyable unit leaves nothing to choose'
        );
    }

    public function test_the_quantity_stepper_shows_with_its_cap_when_there_is_a_choice(): void
    {
        $product = $this->storeProduct($this->payload(['stock' => 8]));

        $content = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/data-role="quantity-block"[^>]*\bhidden\b/',
            $content
        );
        $this->assertStringContainsString('data-stock="8"', $content);
        $this->assertStringContainsString('max="8"', $content);
        // Minus starts disabled because the field opens at its minimum.
        $this->assertStringContainsString('btn-decrease is-disabled', $content);
    }

    public function test_a_low_stock_note_appears_only_when_stock_is_low(): void
    {
        $low = $this->storeProduct($this->payload(['stock' => 3]));
        $this->assertStringContainsString(
            'Only 3 left',
            $this->get(route('client.product', ['productSlug' => $low->slug]))->getContent()
        );

        $plenty = $this->storeProduct($this->payload(['stock' => 40]));
        $this->assertMatchesRegularExpression(
            '/data-role="quantity-note"[^>]*\bhidden\b/',
            $this->get(route('client.product', ['productSlug' => $plenty->slug]))->getContent(),
            'a healthy stock level should not shout about it'
        );
    }

    public function test_the_stepper_opens_against_the_variant_the_page_opens_on(): void
    {
        // The cap is per variant, so it has to follow the opening variant rather
        // than the product's own (always 0) stock column.
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Size', 'text', ['S' => null, 'M' => null])],
            'variants'     => [
                $this->variant(['Size' => 'S'], ['is_default' => 1, 'stock' => 0]),
                $this->variant(['Size' => 'M'], ['stock' => 4]),
            ],
        ]));

        $content = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();

        $this->assertStringContainsString('data-stock="4"', $content);
        $this->assertStringContainsString('Only 4 left', $content);
    }

    public function test_the_quantity_selector_defaults_to_on_for_a_new_product(): void
    {
        // The admin form always sends the switch, but a payload without it must
        // not silently hide the stepper on every product it touches.
        $product = $this->storeProduct($this->payload(['stock' => 8]));

        $this->assertTrue($product->show_quantity);
        $this->assertStringContainsString(
            'data-role="quantity-block"',
            $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent()
        );
    }

    public function test_turning_the_quantity_selector_off_removes_it_from_both_surfaces(): void
    {
        $product = $this->storeProduct($this->payload(['stock' => 8, 'show_quantity' => 0]));
        $this->assertFalse($product->show_quantity);

        // Not merely hidden — the markup is absent, so there is no stepper to
        // find on the page or in the modal.
        $page = $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent();
        $this->assertStringNotContainsString('data-role="quantity-block"', $page);
        // The shopper can still buy it; add-to-cart falls back to one per click.
        $this->assertStringContainsString('add-to-cart', $page);

        $modal = $this->post(route('client.quickAdd'), ['productId' => $product->id])->getContent();
        $this->assertStringNotContainsString('data-role="quantity-block"', $modal);
    }

    public function test_the_quantity_selector_can_be_switched_back_on(): void
    {
        $product = $this->storeProduct($this->payload(['stock' => 8, 'show_quantity' => 0]));

        $this->actingAs($this->admin())
            ->put(route('admin.product.update', $product), $this->payload([
                'name' => $product->name, 'stock' => 8, 'show_quantity' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->show_quantity);
        $this->assertStringContainsString(
            'data-role="quantity-block"',
            $this->get(route('client.product', ['productSlug' => $product->slug]))->getContent()
        );
    }

    public function test_the_quick_add_modal_shares_the_quantity_component(): void
    {
        $product = $this->storeProduct($this->payload([
            'has_variants' => 1,
            'option_types' => [$this->group('Size', 'text', ['S' => null])],
            'variants'     => [$this->variant(['Size' => 'S'], ['is_default' => 1, 'stock' => 1])],
        ]));

        $content = $this->post(route('client.quickAdd'), ['productId' => $product->id])->getContent();

        // Same component, so the same rule applies inside the modal.
        $this->assertMatchesRegularExpression(
            '/data-role="quantity-block"[^>]*\bhidden\b/',
            $content
        );
        // And the modal keeps the class its own title styling hangs off.
        $this->assertStringContainsString('quick-choose', $content);
    }

    public function test_the_picker_is_absent_for_a_single_sku_product(): void
    {
        $product = $this->storeProduct($this->payload());

        $response = $this->get(route('client.product', ['productSlug' => $product->slug]));
        $response->assertOk();
        $response->assertDontSee('tf-product-info-variant-groups', false);
    }
}
