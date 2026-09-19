@extends('layouts.client')

@section('title')
    Your Shopping Cart | {{ config('app.name') }}
@endsection

@section('meta_description')
Review the moulds, kits and craft supplies in your {{ config('app.name') }} cart. Update quantities, remove items, then head to checkout — most orders ship in 24 hours.
@endsection

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'Shopping Cart',
        'crumbs' => [
            ['label' => 'Shop', 'url' => route('client.shop')],
            ['label' => 'Shopping Cart'],
        ],
    ])
    <!-- /page-hero -->
    <!-- Section cart -->
    <section class="flat-spacing">
        <div class="container">
            <div class="row">
                <div class="col-xl-8">
                    <div class="tf-cart-sold d-none">
                        <div class="notification-sold bg-surface">
                            <img class="icon" src="{{asset('client/images/logo/icon-fire.webp')}}" alt="img">
                            <div class="count-text">Your cart will expire in <div class="js-countdown time-count" data-timer="600" data-labels=":,:,:,"></div> minutes! Please checkout now before your items sell out!</div>  
                        </div>
                        <div class="notification-progress">
                            <div class="text">Buy <span class="fw-semibold text-primary">₹999</span> more to get <span class="fw-semibold">Freeship</span></div>
                            <div class="progress-cart">
                                <div class="value" style="width: 0%;" data-progress="50">
                                    <span class="round"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form>
                        <table class="tf-table-page-cart">
                            <thead>
                                <tr>
                                    <th>Products</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="table-body">
                                @php $subTotal = 0; @endphp
                                @if(!empty($cart))
                                    @foreach ($cart as $product)
                                        @php
                                            $defaultImage = asset('client/images/home/product-placeholder.webp');
                                            $mainImage = !empty($product['image']) ? asset('storage/product/' . $product['image']) : $defaultImage;
                                            $unitPrice = (float) ($product['price'] ?? 0);
                                            $lineQty   = (int) ($product['quantity'] ?? 1);
                                            $lineStock = (int) ($product['stock'] ?? 0);
                                            $lineTotal = $unitPrice * $lineQty;
                                            $subTotal += $lineTotal;
                                            // Modern per-product option chips (Variety / Weight / Colour…).
                                            // Falls back to legacy color/size fields for pre-variant items.
                                            $lineOptions = is_array($product['options'] ?? null) ? $product['options'] : [];
                                            if (empty($lineOptions)) {
                                                if (!empty($product['color'])) $lineOptions['Color'] = $product['color'];
                                                if (!empty($product['size']))  $lineOptions['Size']  = $product['size'];
                                            }
                                            $lineIsLowStock = $lineStock > 0 && $lineStock <= 5;
                                            $lineIsOoS      = $lineStock <= 0;
                                        @endphp
                                        <tr
                                            class="tf-cart-item file-delete {{ $lineIsOoS ? 'is-oos' : '' }}"
                                            data-product-id="{{ $product['id'] }}"
                                            data-variant-id="{{ $product['variant_id'] ?? '' }}"
                                            data-cart-key="{{ $product['cart_key'] ?? '' }}"
                                            data-stock="{{ $lineStock }}"
                                            data-unit-price="{{ number_format($unitPrice, 2, '.', '') }}"
                                            data-url="{{ route('client.updateCartItem') }}"
                                            data-remove-cart-url="{{ route('client.removeCheckOutItem') }}"
                                        >
                                            <td class="tf-cart-item_product">
                                                <a href="{{ route('client.product', ['productSlug' => $product['slug']]) }}" class="img-box">
                                                    <img src="{{ $mainImage }}" alt="{{ $product['name'] }}">
                                                </a>
                                                <div class="cart-info">
                                                    <a href="{{ route('client.product', ['productSlug' => $product['slug']]) }}" class="cart-title link">{{ $product['name'] }}</a>
                                                    @if(!empty($product['sku']))
                                                        <div class="cart-sku text-caption-1">SKU: {{ $product['sku'] }}</div>
                                                    @endif
                                                    @if(!empty($lineOptions))
                                                        <ul class="cart-variant-chips">
                                                            @foreach($lineOptions as $optName => $optValue)
                                                                <li class="cart-variant-chip">
                                                                    <span class="chip-label">{{ $optName }}:</span>
                                                                    <span class="chip-value">{{ $optValue }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                    @if($lineIsOoS)
                                                        <div class="cart-line-note text-danger">Out of stock — please remove or wait for restock.</div>
                                                    @elseif($lineIsLowStock)
                                                        <div class="cart-line-note text-warning">Only {{ $lineStock }} left in stock.</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td data-cart-title="Price" class="tf-cart-item_price text-center">
                                                <div class="cart-price text-button price-on-sale">₹{{ number_format($unitPrice, 2) }}</div>
                                            </td>
                                            <td data-cart-title="Quantity" class="tf-cart-item_quantity">
                                                <div class="wg-quantity mx-md-auto">
                                                    <span class="btn-quantity btn-decrease" aria-label="Decrease quantity">−</span>
                                                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="quantity-product" name="number" value="{{ $lineQty }}" data-min="1" data-max="{{ max(1, $lineStock) }}">
                                                    <span class="btn-quantity btn-increase" aria-label="Increase quantity">+</span>
                                                </div>
                                            </td>
                                            <td data-cart-title="Total" class="tf-cart-item_total text-center">
                                                <div class="cart-total text-button total-price">₹{{ number_format($lineTotal, 2) }}</div>
                                            </td>
                                            <td data-cart-title="Remove" class="remove-cart">
                                                <button type="button" class="remove-cart-btn" aria-label="Remove {{ $product['name'] }} from cart">
                                                    <span class="remove icon icon-close"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                <tr>
                                    <td colspan="5">
                                        <div class="cart-empty-state">
                                            <div class="cart-empty-illustration" aria-hidden="true">
                                                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="9" cy="21" r="1"/>
                                                    <circle cx="20" cy="21" r="1"/>
                                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                                </svg>
                                            </div>
                                            <h5 class="cart-empty-title">Your cart is empty</h5>
                                            <p class="cart-empty-copy">Looks like you haven't added anything yet. Browse the shop and add your favourites.</p>
                                            <a href="{{ route('client.shop') }}" class="tf-btn btn-fill radius-4 cart-empty-cta">Continue shopping</a>
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                        <div class="ip-discount-code d-none">
                            <input type="text" placeholder="Add voucher discount">
                            <button class="tf-btn"><span class="text">Apply Code</span></button>
                        </div>
                        <div class="group-discount d-none">
                            <div class="box-discount">
                                <div class="discount-top">
                                    <div class="discount-off">
                                        <div class="text-caption-1">Discount</div>
                                        <span class="sale-off text-btn-uppercase">10% OFF</span>
                                    </div>
                                    <div class="discount-from">
                                        <p class="text-caption-1">For all orders <br> from 200$</p>
                                    </div>
                                </div>
                                <div class="discount-bot">
                                    <span class="text-btn-uppercase">Mo234231</span>
                                    <button class="tf-btn"><span class="text">Apply Code</span></button>
                                </div>
                            </div>
                            <div class="box-discount active">
                                <div class="discount-top">
                                    <div class="discount-off">
                                        <div class="text-caption-1">Discount</div>
                                        <span class="sale-off text-btn-uppercase">10% OFF</span>
                                    </div>
                                    <div class="discount-from">
                                        <p class="text-caption-1">For all orders <br> from 200$</p>
                                    </div>
                                </div>
                                <div class="discount-bot">
                                    <span class="text-btn-uppercase">Mo234231</span>
                                    <button class="tf-btn"><span class="text">Apply Code</span></button>
                                </div>
                            </div>
                            <div class="box-discount">
                                <div class="discount-top">
                                    <div class="discount-off">
                                        <div class="text-caption-1">Discount</div>
                                        <span class="sale-off text-btn-uppercase">10% OFF</span>
                                    </div>
                                    <div class="discount-from">
                                        <p class="text-caption-1">For all orders <br> from 200$</p>
                                    </div>
                                </div>
                                <div class="discount-bot">
                                    <span class="text-btn-uppercase">Mo234231</span>
                                    <button class="tf-btn"><span class="text">Apply Code</span></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-xl-4">
                    <div class="fl-sidebar-cart">
                        <div class="box-order bg-surface">
                            <h5 class="title">Order Summary</h5>
                            <div class="subtotal text-button d-flex justify-content-between align-items-center">
                                <span>Subtotal</span>
                                <span class="total">₹{{ number_format($subTotal, 2) }}</span>
                            </div>
                            <div class="discount text-button d-flex justify-content-between align-items-center">
                                <span>Discounts</span>
                                <span class="total">₹0.00</span>
                            </div>
                            <div class="ship">
                                <span class="text-button">Shipping</span>
                                <div class="flex-grow-1">
                                    <fieldset class="ship-item">
                                        <label for="free">
                                            <span>Free Shipping</span>
                                            <span class="price">₹0.00</span>
                                        </label>
                                    </fieldset>
                                </div>
                            </div>
                            <h5 class="total-order d-flex justify-content-between align-items-center">
                                <span>Total</span>
                                <span class="total">₹{{ number_format($subTotal, 2) }}</span>
                            </h5>
                            <div class="box-progress-checkout">
                                <fieldset class="check-agree">
                                    <input type="checkbox" id="check-agree" class="tf-check-rounded" required>
                                    <label for="check-agree">
                                        I agree with the <a href="{{ route('client.contact') }}">terms and conditions</a>
                                    </label>
                                </fieldset>
                                @if(!empty($cart))
                                <a href="{{route('client.checkout')}}" class="tf-btn btn-fill radius-4 w-100" onclick="if(!document.getElementById('check-agree').checked){event.preventDefault();if(typeof showSweetAlert==='function'){showSweetAlert('error','Please agree to the terms and conditions.');}else{alert('Please agree to the terms and conditions.');}}"><span class="text">Process To Checkout</span></a>
                                @else
                                <a href="#" class="tf-btn btn-fill radius-4 w-100 disabled" aria-disabled="true" onclick="event.preventDefault();"><span class="text">Process To Checkout</span></a>
                                @endif
                                <a href="{{route('client.shop')}}" class="link text-center text-btn-uppercase">Or continue shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /Section cart -->

@endsection

@section('scripts')
    {{-- The .js-countdown "your cart expires in" timer is the only use of this
         library anywhere in the app, so it loads here instead of site-wide. --}}
    <script type="text/javascript" src="{{asset('client/js/min/count-down.min.js')}}"></script>
    <script>
        (function () {
            var rupee = function (n) {
                return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            function updateSubTotal() {
                var subTotal = 0;
                $('#table-body').find('.total-price').each(function () {
                    subTotal += parseFloat($(this).text().replace(/[^\d.]/g, '')) || 0;
                });
                $('.subtotal').find('.total').text(rupee(subTotal));
                $('.total-order').find('.total').text(rupee(subTotal));
            }

            // Debounced qty-change dispatcher so rapid +/− clicks (or typed input)
            // don't fire a request per keystroke.
            var pendingReqs = {};
            function dispatchQtyChange(tr) {
                var key = tr.data('cart-key') || (tr.data('product-id') + ':' + (tr.data('variant-id') || 0));
                clearTimeout(pendingReqs[key]);
                pendingReqs[key] = setTimeout(function () { sendQtyUpdate(tr); }, 220);
            }

            function sendQtyUpdate(tr) {
                var input     = tr.find('.quantity-product');
                var quantity  = Math.max(1, parseInt(input.val(), 10) || 1);
                var maxQty    = parseInt(input.attr('data-max'), 10);
                if (Number.isFinite(maxQty) && quantity > maxQty) {
                    quantity = maxQty;
                    input.val(quantity);
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert('warning', 'Only ' + maxQty + ' available for this option.');
                    }
                }
                input.val(quantity);

                var productId = tr.data('product-id');
                var variantId = tr.data('variant-id') || null;
                var url       = tr.data('url');
                var unitPrice = parseFloat(tr.data('unit-price')) || parseFloat(tr.find('.cart-price').text().replace(/[^\d.]/g, '')) || 0;

                tr.addClass('is-updating');
                $.ajax({
                    url: url,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        productId: productId,
                        variantId: variantId,
                        quantity: quantity,
                    },
                    success: function (response) {
                        tr.removeClass('is-updating');
                        if (!response.success) return;
                        tr.find('.total-price').text(rupee(unitPrice * quantity));
                        updateSubTotal();
                    },
                    error: function (xhr) {
                        tr.removeClass('is-updating');
                        var msg = 'Could not update quantity.';
                        if (xhr && xhr.responseJSON) {
                            if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                            // Server-side cap: reset the input to what the server allows.
                            if (xhr.responseJSON.cappedQuantity != null) {
                                input.val(xhr.responseJSON.cappedQuantity);
                                tr.find('.total-price').text(rupee(unitPrice * xhr.responseJSON.cappedQuantity));
                                updateSubTotal();
                            }
                        }
                        if (typeof showSweetAlert === 'function') showSweetAlert('error', msg);
                    }
                });
            }

            // The +/- buttons are handled once, globally, in main.js: it clamps
            // to data-min/data-max and fires `change`, which lands in the
            // handler below. A second click handler here stacked with it and
            // stepped the quantity by two per click.

            $(document).on('input change', '#table-body .quantity-product', function () {
                dispatchQtyChange($(this).closest('tr'));
            });

            $(document).on('click', '#table-body .remove-cart-btn, #table-body .remove', function (e) {
                e.preventDefault();
                var tr        = $(this).closest('tr');
                var url       = tr.data('remove-cart-url');
                var productId = tr.data('product-id');
                var variantId = tr.data('variant-id') || null;
                var cartKey   = tr.data('cart-key') || null;

                $.ajax({
                    url: url,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        productId: productId,
                        variantId: variantId,
                        cartKey: cartKey,
                    },
                    success: function (response) {
                        if (!response.success) return;
                        tr.fadeOut(180, function () {
                            $(this).remove();
                            updateSubTotal();
                            if ($('#table-body tr').length === 0) {
                                location.reload();
                            }
                            if (typeof updateCartCount === 'function' && response.cart) {
                                updateCartCount(response.cart);
                            }
                        });
                    },
                    error: function (xhr) {
                        var msg = 'Could not remove item.';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        if (typeof showSweetAlert === 'function') showSweetAlert('error', msg);
                    }
                });
            });
        })();
    </script>
@endsection