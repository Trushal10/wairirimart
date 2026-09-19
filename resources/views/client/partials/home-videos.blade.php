{{--
    "What Our Makers Say" — home page video reel.

    A centred coverflow: the active clip is the large card in the middle, its
    neighbours sit smaller on either side. Clips play one at a time in priority
    order; when one ends the next slides into the centre, looping after the last.

    Nothing downloads until the section nears the viewport: every <video> gets
    its URL from data-src (see client/js/home-videos.js), so the reel does not
    compete with the hero image for bandwidth. With reduced motion requested the
    reel waits for a tap instead of autoplaying.

    Expects $videos: a collection of App\Models\HomeVideo.
--}}
@if ($videos->isNotEmpty())
    @push('preload')
        <style>{!! \App\Helper\CommonHelper::inlineCss(['client/css/home-videos.css']) !!}</style>
    @endpush

    {{-- data-interval: ms each card stays in the centre before the reel moves on. --}}
    <section class="flat-spacing hv-section" data-home-videos data-interval="6000" aria-roledescription="carousel" aria-label="What our makers say">
        <div class="container">
            <div class="heading-section text-center wow fadeInUp">
                <h3 class="heading">What Our Makers Say</h3>
                <p class="subheading text-secondary">Real makers, real results — hear it from the people who cast with our moulds.</p>
            </div>

            <div class="hv-stage" data-hv-stage tabindex="0">
                <svg class="hv-ribbon" viewBox="0 0 400 620" fill="none" aria-hidden="true" focusable="false">
                    <path d="M40 600C120 470 330 430 352 280 374 130 250 30 160 92 70 154 118 318 258 372 338 403 386 486 392 612" stroke="currentColor" stroke-width="54" stroke-linecap="round"/>
                </svg>

                @foreach ($videos as $i => $video)
                    @php $__hvLink = $video->safeLink(); @endphp
                    <article class="hv-card" data-hv-card aria-roledescription="slide" aria-label="{{ ($i + 1) . ' of ' . $videos->count() }}{{ $video->title ? ': ' . $video->title : '' }}">
                        <div class="hv-media">
                            <video class="hv-video" muted playsinline preload="none"
                                   data-src="{{ $video->video_src }}"
                                   @if ($video->poster_src) poster="{{ $video->poster_src }}" @endif
                                   @if ($video->title) aria-label="{{ $video->title }}" @endif></video>

                            @if ($video->title || $video->subtitle)
                                <div class="hv-caption">
                                    @if ($video->title)
                                        <h4 class="hv-title">{{ $video->title }}</h4>
                                    @endif
                                    @if ($video->subtitle)
                                        <p class="hv-subtitle">{{ $video->subtitle }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($__hvLink)
                                <a href="{{ $__hvLink }}" class="hv-cta">
                                    <span>Shop now</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                </a>
                            @endif

                            <button type="button" class="hv-fab hv-play" data-hv-play aria-label="Play {{ $video->title ?: 'video' }}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.52.85l10.9-6.86a1 1 0 0 0 0-1.7L9.52 4.29A1 1 0 0 0 8 5.14z"/></svg>
                            </button>
                            <button type="button" class="hv-fab hv-sound" data-hv-sound aria-pressed="false" aria-label="Turn sound on">
                                <svg class="hv-ico-muted" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m23 9-6 6"/><path d="m17 9 6 6"/></svg>
                                <svg class="hv-ico-sound" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

        </div>
    </section>

    @push('page-scripts')
        @php $__hvJs = public_path('client/js/home-videos.js'); @endphp
        <script src="{{ asset('client/js/home-videos.js') }}{{ is_file($__hvJs) ? '?v=' . filemtime($__hvJs) : '' }}" defer></script>
    @endpush
@endif
