{{--
    Shared quantity stepper.

    Used by the product detail page and the quick-add modal so the two cannot
    drift apart, the same way the variant picker is shared.

    Design rules, all of which also re-apply live in client/js/main.js when the
    shopper switches variant (the cap follows the selected variant):

      cap < 2   the whole block is hidden. A stepper that cannot step is a dead
                control — it invites a click that does nothing. One unit is sent
                on add-to-cart regardless, so nothing is lost by hiding it.
      at 1      the minus button is disabled, not merely inert.
      at cap    the plus button is disabled, not merely inert.
      cap <= 5  a low-stock note appears, which is also what explains the
                disabled plus once the shopper reaches the ceiling.

    Expects:
      $stock  units the shopper can currently buy (the selected variant's stock,
              or the product's own for a single-SKU product)
      $type   input type — 'number' on the detail page, 'text' in the modal,
              matching what each surface already used
      $class  extra classes for the wrapper (the modal styles its title through
              .quick-choose, so that hook has to survive)
      $show   the product's "Quantity selector" switch. False renders nothing at
              all — the shopper adds one per click, which suits a made-to-order
              or one-per-order item.
--}}
@php
    $stock = max(0, (int) ($stock ?? 0));
    $type  = ($type ?? 'number') === 'text' ? 'text' : 'number';
    $class = $class ?? 'my-2';
    $show  = ($show ?? true) !== false;
    $cap   = max(1, $stock);
    // Nothing to choose when at most one unit can go in the cart.
    $hide  = $stock < 2;
    $low   = $stock > 1 && $stock <= 5;
@endphp

@if ($show)

{{-- data-stock carries real availability: `max` is floored at 1 to keep the
     input usable, so it cannot answer "is there anything to choose here". --}}
<div class="tf-product-info-quantity {{ $class }}" data-role="quantity-block"
    data-stock="{{ $stock }}" @if ($hide) hidden @endif>
    <div class="title mb_12">Quantity:</div>
    <div class="wg-quantity">
        <span class="btn-quantity btn-decrease is-disabled" role="button" tabindex="-1"
            aria-label="Decrease quantity" aria-disabled="true">-</span>
        <input class="quantity-product"
            type="{{ $type }}"
            @if ($type === 'text') inputmode="numeric" pattern="[0-9]*" @endif
            name="number"
            value="1"
            min="1"
            max="{{ $cap }}"
            data-min="1"
            data-max="{{ $cap }}"
            aria-label="Quantity">
        <span class="btn-quantity btn-increase" role="button" tabindex="0"
            aria-label="Increase quantity">+</span>
    </div>
    <p class="qty-stock-note" data-role="quantity-note" @unless ($low) hidden @endunless>
        Only {{ $stock }} left
    </p>
</div>
@endif
