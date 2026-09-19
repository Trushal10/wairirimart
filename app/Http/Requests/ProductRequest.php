<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $rules = [
            'name'              => ['required', 'string', 'max:255'],
            'categories'        => ['required', 'array', 'min:1'],
            'categories.*'      => ['required', 'integer', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'return_policy'     => ['nullable', 'string'],

            // Legacy JSON options: kept optional to preserve backward compatibility.
            // New admin UIs should send `variants` instead. When both are absent the
            // product is treated as a single-variant SKU.
            'sizes'   => ['nullable', 'array'],
            'sizes.*' => ['string'],
            'color'   => ['nullable', 'string'],

            'price'         => ['required', 'numeric', 'min:0'],
            'compere_price' => ['required', 'numeric', 'min:0'],
            'sku'           => ['required', 'string', 'max:255'],
            'barcode'       => ['nullable', 'string', 'max:100'],
            // Base stock is only meaningful for single-SKU products. When the
            // admin has enabled variants, per-variant stock takes over and the
            // base value is forced to 0 in validated(). Requiredness is enforced
            // in withValidator() against the has_variants toggle —
            // `required_without:variants` was wrong because a variant-enabled
            // product legitimately posts no base stock.
            'stock'         => ['nullable', 'numeric', 'min:0'],
            'weight'        => ['nullable', 'numeric', 'min:0'],
            'brand'         => ['nullable', 'string', 'max:128'],
            'tax_class'     => ['nullable', 'string', 'max:64'],
            'hs_code'       => ['nullable', 'string', 'max:32'],
            'status'        => ['nullable'],
            'featured'      => ['nullable'],
            'has_variants'  => ['nullable', 'boolean'],
            // Storefront quantity stepper. Absent means "leave it on", so an
            // older client that does not send the field cannot silently hide it.
            'show_quantity' => ['nullable', 'boolean'],

            // SEO fields
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords'    => ['nullable', 'string', 'max:500'],
            'canonical_url'    => ['nullable', 'string', 'max:500'],
            'og_image'         => ['nullable', 'string', 'max:255'],

            // Product gallery (product-level media)
            'gallery'           => ['nullable', 'array'],
            'gallery.*.file'    => ['nullable', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(5 * 1024)],
            'gallery.*.url'     => ['nullable', 'string', 'max:255'],
            'gallery.*.alt'     => ['nullable', 'string', 'max:255'],
            'gallery.*.primary' => ['nullable', 'boolean'],

            // Option group definitions for THIS product (e.g. Variety / Weight).
            // Free-form per product — no global attributes table.
            //
            // `values` carries the presentation data for each allowed value of
            // the group: the value string itself plus an optional swatch
            // (hex colour for type=color, uploaded filename for type=image).
            // Without this the storefront has no way to render anything but text
            // pills, which is why the colour/image option types never worked.
            'option_types'          => ['nullable', 'array', 'max:5'],
            // No brackets: the name becomes a form-data key (variants[0][options][Colour]).
            'option_types.*.name'   => ['required_with:option_types', 'string', 'max:64', 'regex:/^[^\[\]]+$/'],
            'option_types.*.type'   => ['nullable', 'in:text,color,image'],
            'option_types.*.values'               => ['nullable', 'array', 'max:60'],
            'option_types.*.values.*.value'       => ['required', 'string', 'max:128'],
            'option_types.*.values.*.swatch'      => ['nullable', 'string', 'max:255'],
            'option_types.*.values.*.swatch_file' => ['nullable', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(2 * 1024)],

            // Variants: one row per SKU. `options` is a dict of option name → value string.
            'variants'                 => ['nullable', 'array'],
            'variants.*.id'            => ['nullable', 'integer'],
            'variants.*.sku'           => ['nullable', 'string', 'max:100'],
            'variants.*.barcode'       => ['nullable', 'string', 'max:100'],
            'variants.*.price'         => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'         => ['nullable', 'integer', 'min:0'],
            'variants.*.weight'        => ['nullable', 'numeric', 'min:0'],
            'variants.*.image_url'     => ['nullable', 'string', 'max:255'],
            'variants.*.image_file'    => ['nullable', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(5 * 1024)],
            'variants.*.position'      => ['nullable', 'integer', 'min:0'],
            'variants.*.status'        => ['nullable', 'boolean'],
            'variants.*.is_default'    => ['nullable', 'boolean'],
            'variants.*.options'       => ['nullable', 'array'],
        ];

        if (in_array($this->method(), ['PUT', 'PATCH'])) {
            $product = $this->route('product');
            $rules['name'] = [
                'required',
                'string',
                'max:255',
                Rule::unique(Product::class)->ignore($product),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'categories.required'    => 'Select at least one category',
            'categories.min'         => 'Select at least one category',
            'categories.*.integer'   => 'Select a valid category',
            'categories.*.exists'    => 'One of the selected categories no longer exists',
            'gallery.*.file.mimes'   => 'Only JPG, JPEG, PNG, and WEBP images are allowed',
            'gallery.*.file.max'     => 'Images must be less than 5 MB',
            'option_types.*.name.required_with'  => 'Each option type needs a name (e.g. Variety, Weight).',
            'option_types.*.name.regex'          => 'Option type names cannot contain [ or ].',
            'option_types.*.values.*.value.required' => 'Every option value needs a label.',
            'option_types.*.values.*.swatch_file.mimes' => 'Swatch images must be JPG, PNG or WEBP.',
            'option_types.*.values.*.swatch_file.max'   => 'Swatch images must be under 2 MB.',
        ];
    }

    /**
     * Cross-field rules that only make sense once "Enable variants" is known.
     *
     * Enforces the two halves of the toggle:
     *   OFF → base stock is the product's inventory, so it must be supplied.
     *   ON  → at least one variant row, at least one of them sellable, every
     *         declared option filled on every row, and no two rows sharing the
     *         same option combination (a duplicate makes the storefront picker
     *         ambiguous — two variants would match the same selection).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasVariants = $this->boolean('has_variants');
            $variants    = (array) $this->input('variants', []);
            $optionTypes = (array) $this->input('option_types', []);

            if (! $hasVariants) {
                if ($this->input('stock', null) === null || $this->input('stock') === '') {
                    $validator->errors()->add('stock', 'Stock is required when variants are disabled.');
                }

                return;
            }

            if (empty($variants)) {
                $validator->errors()->add(
                    'variants',
                    'Add at least one variant, or switch "Enable variants" off to sell this product as a single SKU.'
                );

                return;
            }

            // A matrix where every row is inactive cannot be bought at all.
            $anyActive = collect($variants)->contains(
                fn ($row) => ! array_key_exists('status', $row) || filter_var($row['status'], FILTER_VALIDATE_BOOLEAN)
            );
            if (! $anyActive) {
                $validator->errors()->add('variants', 'At least one variant must be active.');
            }

            $declaredNames = [];
            foreach ($optionTypes as $ot) {
                $name = trim((string) ($ot['name'] ?? ''));
                if ($name !== '') {
                    $declaredNames[] = $name;
                }
            }
            // Case-insensitive: the options dict is keyed by name, so "Colour"
            // and "colour" would collide into one key on the variant row.
            $lowered = array_map('mb_strtolower', $declaredNames);
            if (count($lowered) !== count(array_unique($lowered))) {
                $validator->errors()->add('option_types', 'Option type names must be unique.');
            }

            // Two or more variants with nothing to tell them apart: the picker
            // has no group to draw, so only the default is ever reachable.
            if (empty($declaredNames) && count($variants) > 1) {
                $validator->errors()->add(
                    'option_types',
                    'Add at least one option type (Size, Colour…) so shoppers can tell the variants apart, or keep a single variant.'
                );

                return;
            }

            $seenCombos = [];
            foreach ($variants as $i => $row) {
                $options = (array) ($row['options'] ?? []);
                $parts   = [];
                foreach ($declaredNames as $name) {
                    $value = trim((string) ($options[$name] ?? ''));
                    if ($value === '') {
                        $validator->errors()->add(
                            "variants.{$i}.options",
                            'Variant #' . ($i + 1) . " is missing a value for \"{$name}\"."
                        );
                        continue 2;
                    }
                    $parts[] = $name . '=' . mb_strtolower($value);
                }

                $combo = implode('|', $parts);
                if (isset($seenCombos[$combo])) {
                    $validator->errors()->add(
                        "variants.{$i}.options",
                        'Two variants share the same option combination — each combination must be unique.'
                    );
                }
                $seenCombos[$combo] = true;
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $inputs = parent::validated();
        $inputs['status']   = $inputs['status'] ?? 0;
        $inputs['featured'] = $inputs['featured'] ?? 0;
        // Default on: a product saved without the field keeps its stepper.
        $inputs['show_quantity'] = $this->has('show_quantity') ? $this->boolean('show_quantity') : true;

        // The admin's "Enable variants" switch is authoritative. Deriving this
        // from `! empty($variants)` meant switching the toggle OFF silently left
        // has_variants=true whenever stale variant rows were still posted, and
        // switching it ON with an empty matrix silently produced a variant-less
        // "variant product".
        $inputs['has_variants'] = $this->boolean('has_variants');

        if ($inputs['has_variants']) {
            // The product row's stock is meaningless when variants exist — force
            // it to 0 so read paths that fall back to it can't return a stale
            // legacy number.
            $inputs['stock'] = 0;
        } else {
            $inputs['stock']        = (int) ($inputs['stock'] ?? 0);
            $inputs['variants']     = [];
            $inputs['option_types'] = [];
        }

        $inputs['slug'] = Str::slug($inputs['name'], '-') ?: CommonHelper::makeSlug($inputs['name']);

        return $inputs;
    }
}
