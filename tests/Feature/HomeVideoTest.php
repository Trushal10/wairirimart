<?php

namespace Tests\Feature;

use App\Models\HomeVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The home page video reel and the admin page that feeds it.
 */
class HomeVideoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Start from an empty reel regardless of what the dev database holds;
        // the transaction puts the rows back afterwards.
        HomeVideo::query()->delete();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ------------------------------------------------------------------
    // Storefront
    // ------------------------------------------------------------------

    public function test_the_section_is_hidden_without_active_videos(): void
    {
        HomeVideo::create(['video' => 'hidden.mp4', 'status' => false]);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-home-videos', $content);
        $this->assertStringNotContainsString('home-videos.js', $content);
    }

    public function test_active_videos_render_in_priority_order_without_eager_downloads(): void
    {
        HomeVideo::create(['video' => 'second.mp4', 'title' => 'Second clip', 'priority' => 2]);
        HomeVideo::create(['video' => 'first.mp4', 'title' => 'First clip', 'priority' => 1]);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-home-videos', $content);
        $this->assertStringContainsString('home-videos.js', $content);
        $this->assertLessThan(
            strpos($content, 'storage/home-video/second.mp4'),
            strpos($content, 'storage/home-video/first.mp4'),
        );
        // URLs sit in data-src only, so nothing downloads before the reel is near.
        $this->assertStringContainsString('data-src="'.asset('storage/home-video/first.mp4').'"', $content);
        $this->assertStringNotContainsString(' src="'.asset('storage/home-video/first.mp4').'"', $content);
    }

    public function test_an_unsafe_link_is_not_rendered(): void
    {
        HomeVideo::create(['video' => 'a.mp4', 'link_url' => 'javascript:alert(1)']);

        $this->assertStringNotContainsString('javascript:alert(1)', $this->get('/')->getContent());
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    public function test_admin_can_upload_update_and_delete_a_video(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.home-videos.store'), [
            'title' => 'Resin pour',
            'link_url' => '/shop',
            'priority' => 3,
            'status' => 1,
            'video' => UploadedFile::fake()->create('pour.mp4', 1024, 'video/mp4'),
        ])->assertRedirect(route('admin.home-videos'))->assertSessionHasNoErrors();

        $video = HomeVideo::query()->firstOrFail();
        $this->assertSame('Resin pour', $video->title);
        Storage::disk('local')->assertExists('public/home-video/'.$video->video);

        $old = $video->video;
        $this->actingAs($admin)->put(route('admin.home-videos.update', $video), [
            'title' => 'Resin pour, slow',
            'status' => 0,
            'video' => UploadedFile::fake()->create('pour2.webm', 1024, 'video/webm'),
        ])->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertFalse($video->status);
        Storage::disk('local')->assertMissing('public/home-video/'.$old);
        Storage::disk('local')->assertExists('public/home-video/'.$video->video);

        $this->actingAs($admin)->delete(route('admin.home-videos.delete', $video));

        $this->assertNull(HomeVideo::find($video->id));
        Storage::disk('local')->assertMissing('public/home-video/'.$video->video);
    }

    public function test_a_video_file_is_required_and_links_must_be_safe(): void
    {
        $this->actingAs($this->admin())->post(route('admin.home-videos.store'), [
            'status' => 1,
            'link_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['video', 'link_url']);

        $this->assertSame(0, HomeVideo::query()->count());
    }

    public function test_non_admins_cannot_reach_the_admin_page(): void
    {
        $this->get(route('admin.home-videos'))->assertRedirect();
    }
}
