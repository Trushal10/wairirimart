<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin-managed inner-page banner.
 *
 * One upload in Settings feeds the hero strip on every page that is not the
 * home page. Before this it was a file baked into the theme, so changing the
 * banner meant editing a Blade template and redeploying.
 *
 * One storefront request per test method, on purpose: the settings view
 * composer resolves once per application instance (see AppServiceProvider), so
 * a second render inside the same test would reuse the first one's settings.
 */
class PageHeroBannerTest extends TestCase
{
    use DatabaseTransactions;

    /** Pages that share the partial; a representative spread of the 15. */
    public const PAGES = ['/shop', '/about', '/contact', '/shopping-cart'];

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function settings(): Setting
    {
        return Setting::query()->first() ?? Setting::create([]);
    }

    private function setBanner(?string $file): void
    {
        $this->settings()->forceFill(['page_hero_image' => $file])->save();
    }

    /** The settings row as the admin form posts it back, minus the file inputs. */
    private function formPayload(array $overrides = []): array
    {
        $s = $this->settings();

        return array_merge([
            'name'    => $s->name ?: 'Test Shop',
            'email'   => $s->email ?: 'shop@example.test',
            'phone'   => $s->phone ?: '9999999999',
            'city'    => $s->city ?: 'Ahmedabad',
            'address' => $s->address ?: '1 Test Street',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Storefront
    // ------------------------------------------------------------------

    public function test_the_uploaded_banner_replaces_the_bundled_artwork(): void
    {
        $this->setBanner('custom-banner.webp');

        $content = $this->get('/shop')->getContent();

        $this->assertStringContainsString('storage/setting/custom-banner.webp', $content);
        $this->assertStringNotContainsString('client/images/home/page-hero.webp', $content);
    }

    public function test_the_bundled_artwork_shows_until_one_is_uploaded(): void
    {
        $this->setBanner(null);

        // A shop that never uploads a banner still renders a complete page.
        $this->assertStringContainsString(
            'client/images/home/page-hero.webp',
            $this->get('/shop')->getContent()
        );
    }

    public function test_one_upload_feeds_every_page_that_shares_the_partial(): void
    {
        $this->setBanner('custom-banner.webp');

        // The whole point of the feature: update once, not per page.
        foreach (self::PAGES as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertStringContainsString(
                'storage/setting/custom-banner.webp',
                $response->getContent(),
                "{$url} should render the uploaded banner"
            );
        }
    }

    public function test_the_home_page_keeps_its_own_slider(): void
    {
        $this->setBanner('custom-banner.webp');

        // The home page has a managed slider and no page-hero strip, so the
        // banner must not leak into it. Asserted on the image URL rather than
        // the CSS class: page-hero.css is inlined into every page by the
        // layout, so the class name is present on the home page regardless.
        $content = $this->get(route('client.home'))->getContent();
        $this->assertStringNotContainsString('storage/setting/custom-banner.webp', $content);
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    public function test_an_admin_upload_is_stored_and_recorded(): void
    {
        Storage::fake('local');
        $this->setBanner(null);
        $setting = $this->settings();

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                'page_hero_image' => UploadedFile::fake()->image('banner.jpg', 1774, 696),
            ]))
            ->assertSessionHasNoErrors();

        $stored = $setting->fresh()->page_hero_image;
        $this->assertNotEmpty($stored);
        Storage::disk('local')->assertExists('public/setting/' . $stored);
    }

    public function test_replacing_the_banner_removes_the_previous_file(): void
    {
        Storage::fake('local');
        $setting = $this->settings();

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                'page_hero_image' => UploadedFile::fake()->image('first.jpg', 1200, 500),
            ]))
            ->assertSessionHasNoErrors();
        $first = $setting->fresh()->page_hero_image;

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                'page_hero_image' => UploadedFile::fake()->image('second.jpg', 1200, 500),
            ]))
            ->assertSessionHasNoErrors();
        $second = $setting->fresh()->page_hero_image;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing('public/setting/' . $first);
        Storage::disk('local')->assertExists('public/setting/' . $second);
    }

    public function test_clearing_the_banner_falls_back_rather_than_dangling(): void
    {
        Storage::fake('local');
        $setting = $this->settings();

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                'page_hero_image' => UploadedFile::fake()->image('banner.jpg', 1200, 500),
            ]))
            ->assertSessionHasNoErrors();
        $stored = $setting->fresh()->page_hero_image;

        // Posting without the field is how the admin form clears it.
        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload())
            ->assertSessionHasNoErrors();

        $this->assertNull($setting->fresh()->page_hero_image, 'must be null, not a path to a deleted file');
        Storage::disk('local')->assertMissing('public/setting/' . $stored);
    }

    public function test_an_oversized_or_wrong_type_upload_is_refused(): void
    {
        $setting = $this->settings();

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                'page_hero_image' => UploadedFile::fake()->create('banner.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['page_hero_image']);

        $this->actingAs($this->admin())
            ->put(route('admin.setting.update', $setting->id), $this->formPayload([
                // 2 MB is the cap; this is comfortably past it.
                'page_hero_image' => UploadedFile::fake()->create('banner.jpg', 3000, 'image/jpeg'),
            ]))
            ->assertSessionHasErrors(['page_hero_image']);
    }
}
