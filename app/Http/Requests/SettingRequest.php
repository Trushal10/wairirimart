<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:50'],
            'image' => array_merge(['nullable'], CommonHelper::getFileValidationRule('image', ['jpeg', 'jpg', 'png', 'webp'], (1 * 500))),
            // Inner-page banner. A wider allowance than the logo: this is a
            // full-width photograph, not a mark, so 500 KB would reject most
            // usable exports.
            'page_hero_image' => array_merge(['nullable'], CommonHelper::getFileValidationRule('page_hero_image', ['jpeg', 'jpg', 'png', 'webp'], (2 * 1024))),
            'icon' => array_merge(['nullable'], CommonHelper::getFileValidationRule('icon', ['jpeg', 'jpg', 'png', 'webp'], (1 * 500))),
            'social_links' => ['nullable', 'array'],
            // URL-based socials must be valid URLs; WhatsApp is a phone number
            // so it gets its own rule allowing digits, spaces, dashes and +.
            'social_links.facebook'  => ['nullable', 'url'],
            'social_links.twitter'   => ['nullable', 'url'],
            'social_links.linkedin'  => ['nullable', 'url'],
            'social_links.instagram' => ['nullable', 'url'],
            'social_links.whatsapp'  => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],

            // Editable storefront copy blocks (product detail, top-bar, home features).
            'storefront_content' => ['nullable', 'array'],
            'storefront_content.show_track_order' => ['nullable', 'boolean'],
            'storefront_content.product' => ['nullable', 'array'],
            'storefront_content.product.delivery_estimate' => ['nullable', 'string', 'max:200'],
            'storefront_content.product.return_window' => ['nullable', 'string', 'max:200'],
            'storefront_content.product.shipping_copy' => ['nullable', 'string', 'max:2000'],
            'storefront_content.product.return_copy' => ['nullable', 'string', 'max:2000'],
            'storefront_content.topbar_messages' => ['nullable', 'array', 'max:6'],
            'storefront_content.topbar_messages.*' => ['nullable', 'string', 'max:200'],
            'storefront_content.features' => ['nullable', 'array', 'max:8'],
            'storefront_content.features.*.icon' => ['nullable', 'string', 'max:60'],
            'storefront_content.features.*.title' => ['nullable', 'string', 'max:80'],
            'storefront_content.features.*.description' => ['nullable', 'string', 'max:280'],

            // Footer payment badges — admin toggles which methods to show
            'storefront_content.payment_methods' => ['nullable', 'array', 'max:12'],
            'storefront_content.payment_methods.*.key' => ['nullable', 'string', 'max:40'],
            'storefront_content.payment_methods.*.label' => ['nullable', 'string', 'max:60'],
            'storefront_content.payment_methods.*.image' => ['nullable', 'string', 'max:120'],
            'storefront_content.payment_methods.*.enabled' => ['nullable', 'boolean'],

            // About page copy
            'storefront_content.about' => ['nullable', 'array'],
            'storefront_content.about.page_title' => ['nullable', 'string', 'max:120'],
            'storefront_content.about.main_title' => ['nullable', 'string', 'max:180'],
            'storefront_content.about.cta_label'  => ['nullable', 'string', 'max:40'],
            'storefront_content.about.tabs' => ['nullable', 'array', 'max:6'],
            'storefront_content.about.tabs.*.label' => ['nullable', 'string', 'max:60'],
            'storefront_content.about.tabs.*.content' => ['nullable', 'string', 'max:2000'],
        ];

        return $rules;
    }
}
