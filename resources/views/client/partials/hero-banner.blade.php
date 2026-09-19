@php
    $__caption   = trim((string) ($slider->caption    ?? ''));
    $__actionUrl = trim((string) ($slider->action_url ?? ''));
    $__hasOverlay = $__caption !== '' || $__actionUrl !== '';
    // The first hero slide is the page's LCP candidate: it must load eagerly at
    // high priority. Every slide after it is off-screen until the carousel
    // advances, so it waits rather than competing for bandwidth up front.
    $__isLcp = ! empty($eager);
@endphp
<div class="wrap-slider">
    @php $__heroSrcset = \App\Helper\CommonHelper::srcsetFor('slider/' . $slider->url); @endphp
    @if ($__isLcp)
        <img src="{{ asset('storage/slider/' . $slider->url) }}"
             @if ($__heroSrcset) srcset="{{ $__heroSrcset }}" sizes="100vw" @endif
             {!! \App\Helper\CommonHelper::imageSizeAttrs('slider/' . $slider->url) !!}
             fetchpriority="high" loading="eager"
             decoding="async"
             alt="{{ $__caption ?: 'Featured banner' }}">
    @else
        {{-- loading="lazy" is not enough for an off-screen carousel slide. On a
             throttled connection Chrome widens its lazy-loading threshold to
             thousands of pixels, so every slide downloads during the initial
             load anyway and competes with the LCP image for bandwidth. Handing
             these to lazysizes (already loaded site-wide) keeps them off the
             critical path until the carousel actually reaches them. --}}
        <img class="lazyload"
             data-src="{{ asset('storage/slider/' . $slider->url) }}"
             @if ($__heroSrcset) data-srcset="{{ $__heroSrcset }}" data-sizes="100vw" @endif
             {!! \App\Helper\CommonHelper::imageSizeAttrs('slider/' . $slider->url) !!}
             decoding="async"
             alt="{{ $__caption ?: 'Featured banner' }}">
    @endif
    @if ($__hasOverlay)
        <div class="hero-overlay hero-overlay--center">
            <div class="hero-overlay__inner">
                @if ($__caption !== '')
                    <h2 class="hero-caption">{{ $__caption }}</h2>
                @endif
                @if ($__actionUrl !== '')
                    <a href="{{ $__actionUrl }}" class="hero-cta">
                        <span>Explore Collection</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
