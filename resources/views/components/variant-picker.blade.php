{{--
    Shared variant option picker.

    Renders one group per declared option type and one pill per value, in the
    presentation the admin chose for that group:
      text  → label-only pill
      color → hex dot + label
      image → thumbnail + label

    The swatch sits INSIDE the pill next to its label; it never replaces the
    label. A bare colour dot gives the shopper no way to name what they picked,
    and reads as decoration rather than as a control.

    Expects:
      $groups   VariantOptionService groups: [{name, type, values:[{value,label,swatch,in_stock}]}]
      $variants VariantOptionService variants (JSON-encoded onto the container for the JS)
      $selected dict of the initially-selected {optionName: value} (opening variant's options)
      $idPrefix unique per render so the detail page and the quick-add modal never collide on input ids

    The `.variant-picker-*`, `.color-btn` and `.variant-option-input` hooks are part
    of the storefront JS contract — keep them on any new markup.
--}}
@php
    $groups   = $groups   ?? [];
    $variants = $variants ?? [];
    $selected = $selected ?? [];
    $idPrefix = $idPrefix ?? 'opt';
@endphp

@if (! empty($groups))
    <div class="tf-product-info-variant-groups" data-variants='@json($variants)'>
        @foreach ($groups as $gi => $group)
            @php
                $groupSlug = $idPrefix . '-g' . $gi;
                $groupType = in_array($group['type'] ?? 'text', ['text', 'color', 'image'], true)
                    ? $group['type']
                    : 'text';
                // Pre-select the opening variant's value; fall back to the first
                // value that is actually in stock, then to the first value.
                $defaultForGroup = $selected[$group['name']] ?? null;
                if ($defaultForGroup === null) {
                    $firstInStock = collect($group['values'])->firstWhere('in_stock', true);
                    $defaultForGroup = $firstInStock['value'] ?? ($group['values'][0]['value'] ?? '');
                }
            @endphp
            <div class="variant-picker-item mb-3" data-option-name="{{ $group['name'] }}" data-option-type="{{ $groupType }}">
                <div class="variant-picker-label mb_12">
                    {{ $group['name'] }}:
                    <span class="text-title variant-picker-label-value" data-role="selected-label">{{ $defaultForGroup }}</span>
                </div>
                <div class="variant-picker-values gap12">
                    @foreach ($group['values'] as $idx => $val)
                        @php
                            $inputId   = $groupSlug . '-' . $idx;
                            $value     = $val['value'];
                            $isChecked = ((string) $value === (string) $defaultForGroup);
                            // A sold-out value the shopper is currently on stays
                            // enabled — disabling it would trap them with no way
                            // to move to another combination.
                            $isOoS     = empty($val['in_stock']);
                            $isBlocked = $isOoS && ! $isChecked;
                            $swatch    = $val['swatch'] ?? null;
                        @endphp
                        <input class="variant-option-input"
                            id="{{ $inputId }}"
                            type="radio"
                            name="{{ $groupSlug }}"
                            value="{{ $value }}"
                            data-value-label="{{ $val['label'] ?? $value }}"
                            {{ $isChecked ? 'checked' : '' }}
                            {{ $isBlocked ? 'disabled' : '' }}>
                        <label
                            class="style-text-1 style-rounded radius-60 color-btn by-swatch by-swatch--{{ $groupType }} {{ $isChecked ? 'active' : '' }} {{ $isBlocked ? 'disabled variant-oos' : '' }}"
                            for="{{ $inputId }}"
                            data-value="{{ $value }}"
                            {{ $isBlocked ? 'aria-disabled=true title=Unavailable' : '' }}>
                            @if ($groupType === 'color' && $swatch)
                                <span class="by-swatch__dot" style="background-color: {{ $swatch }};" aria-hidden="true"></span>
                            @elseif ($groupType === 'image' && $swatch)
                                <span class="by-swatch__thumb" aria-hidden="true">
                                    <img src="{{ $swatch }}" alt="" loading="lazy" decoding="async"
                                        onerror="this.closest('.by-swatch__thumb').style.display='none';">
                                </span>
                            @endif
                            <span class="text-title">{{ $val['label'] ?? $value }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
