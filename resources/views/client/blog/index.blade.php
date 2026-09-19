@extends('layouts.client')

@section('title')
    Blog | {{ config('app.name') }}
@endsection

@section('meta_description')
    Tips, tutorials and inspiration for candle makers — wax guides, fragrance pairing, wick sizing and more from the {{ config('app.name') }} team.
@endsection

@php
    /** Rough reading time in minutes based on 220 words per minute. */
    $readingTime = function ($html) {
        $words = str_word_count(strip_tags((string) $html));
        return max(1, (int) ceil($words / 220));
    };

    /** Initials for the author avatar (e.g. "Priya Sharma" → "PS"). */
    $initials = function ($name) {
        if (! $name) return '';
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return strtoupper($first . $last);
    };
@endphp

@section('style')
<style>
    .blog-page-body { background: var(--ds-cream); padding-bottom: 96px; }

    /* Small intro block above the grid */
    .blog-intro {
        max-width: 720px; margin: 0 auto 44px; text-align: center;
        padding: 0 16px;
    }
    .blog-intro__kicker {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 11.5px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ds-copper); font-weight: 600;
        margin-bottom: 12px;
    }
    .blog-intro__kicker::before,
    .blog-intro__kicker::after {
        content: ''; width: 24px; height: 1px; background: currentColor; opacity: .5;
    }
    .blog-intro__heading {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: clamp(28px, 4vw, 40px);
        line-height: 1.15; font-weight: 600;
        color: var(--ds-charcoal);
        margin: 0 0 12px;
    }
    .blog-intro__sub {
        font-size: 15px; color: var(--ds-muted); line-height: 1.6;
        margin: 0;
    }

    /* Filter row */
    .blog-filters {
        display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px;
        margin: 0 0 36px; justify-content: center;
    }
    .blog-filters__label {
        font-size: 11px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ds-muted); font-weight: 600; margin-right: 6px;
    }
    .blog-filter-chip {
        display: inline-flex; align-items: center;
        padding: 7px 14px;
        border: 1px solid var(--ds-line);
        border-radius: 999px;
        background: #fff;
        font-size: 13px; color: var(--ds-charcoal);
        text-decoration: none;
        transition: all var(--ds-t-med);
    }
    .blog-filter-chip:hover { border-color: var(--ds-copper); color: var(--ds-copper); }
    .blog-filter-chip.is-active {
        background: var(--ds-copper); border-color: var(--ds-copper); color: #fff;
    }

    /* Featured card (first post) */
    .blog-featured {
        display: grid; grid-template-columns: 1fr;
        gap: 0;
        background: #fff;
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-xl);
        overflow: hidden;
        margin-bottom: 40px;
        transition: box-shadow .25s ease, border-color .25s ease;
    }
    .blog-featured:hover { box-shadow: var(--ds-shadow-lg); border-color: var(--ds-copper); }
    @media (min-width: 900px) {
        .blog-featured { grid-template-columns: 1.15fr 1fr; }
    }
    .blog-featured__media {
        aspect-ratio: 16 / 10;
        background: var(--ds-cream-2);
        overflow: hidden;
    }
    @media (min-width: 900px) { .blog-featured__media { aspect-ratio: auto; height: 100%; min-height: 340px; } }
    .blog-featured__media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .blog-featured:hover .blog-featured__media img { transform: scale(1.04); }
    .blog-featured__body {
        padding: 32px clamp(24px, 3vw, 40px);
        display: flex; flex-direction: column; justify-content: center; gap: 14px;
    }
    .blog-featured__eyebrow {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
        color: var(--ds-copper); font-weight: 600;
    }
    .blog-featured__eyebrow a { color: inherit; text-decoration: none; }
    .blog-featured__eyebrow .dot {
        display: inline-block; width: 3px; height: 3px; border-radius: 50%;
        background: currentColor; opacity: .5;
    }
    .blog-featured__title {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: clamp(22px, 2.6vw, 30px);
        line-height: 1.2; font-weight: 600; margin: 0;
        color: var(--ds-charcoal);
    }
    .blog-featured__title a { color: inherit; text-decoration: none; }
    .blog-featured__title a:hover { color: var(--ds-copper); }
    .blog-featured__excerpt {
        font-size: 15px; color: var(--ds-muted); line-height: 1.65;
        margin: 0;
        display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-featured__cta {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--ds-copper); font-weight: 600; font-size: 14px;
        text-decoration: none;
    }
    .blog-featured__cta:hover { color: var(--ds-copper-2); }
    .blog-featured__cta::after {
        content: '→';
        transition: transform .2s ease;
    }
    .blog-featured__cta:hover::after { transform: translateX(4px); }

    /* Regular grid */
    .blog-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 28px; }
    @media (min-width: 768px) { .blog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .blog-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

    .blog-card {
        background: #fff;
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-lg);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform var(--ds-t-med), box-shadow var(--ds-t-med), border-color var(--ds-t-med);
    }
    .blog-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--ds-shadow-md);
        border-color: var(--ds-copper);
    }
    .blog-card__media {
        aspect-ratio: 16 / 10;
        background: var(--ds-cream-2);
        overflow: hidden;
        position: relative;
        display: block;
    }
    .blog-card__media img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s ease; }
    .blog-card:hover .blog-card__media img { transform: scale(1.05); }
    .blog-card__media .placeholder {
        display: flex; align-items: center; justify-content: center; height: 100%;
        color: var(--ds-muted); font-size: 13px; letter-spacing: .08em; text-transform: uppercase;
    }

    /* Reading-time chip overlaid on the image */
    .blog-card__readtime {
        position: absolute; top: 12px; left: 12px;
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 10px;
        background: rgba(255, 255, 255, .92);
        backdrop-filter: blur(6px);
        border-radius: 999px;
        font-size: 11.5px; font-weight: 600;
        color: var(--ds-charcoal);
    }
    .blog-card__readtime svg { width: 12px; height: 12px; }

    .blog-card__body { padding: 22px 22px 20px; display: flex; flex-direction: column; flex: 1; gap: 10px; }
    .blog-card__category {
        display: inline-block; font-size: 11px; letter-spacing: .14em;
        text-transform: uppercase; font-weight: 600;
        color: var(--ds-copper);
        text-decoration: none;
        align-self: flex-start;
    }
    .blog-card__category:hover { color: var(--ds-copper-2); }
    .blog-card__title {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: 19px; font-weight: 600; line-height: 1.3;
        color: var(--ds-charcoal);
        margin: 0;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .blog-card__title a { color: inherit; text-decoration: none; }
    .blog-card__title a:hover { color: var(--ds-copper); }
    .blog-card__excerpt {
        font-size: 14px; color: var(--ds-muted); line-height: 1.55;
        margin: 0;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .blog-card__meta {
        margin-top: auto; padding-top: 14px;
        border-top: 1px solid var(--ds-line);
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px;
    }
    .blog-card__author {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 12.5px; color: var(--ds-muted); min-width: 0;
    }
    .blog-card__avatar {
        flex-shrink: 0;
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--ds-cream-2);
        border: 1px solid var(--ds-line);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 10.5px; font-weight: 700; letter-spacing: .05em;
        color: var(--ds-copper);
    }
    .blog-card__author-info { min-width: 0; line-height: 1.25; }
    .blog-card__author-name { font-weight: 600; color: var(--ds-charcoal); font-size: 12.5px; }
    .blog-card__date { font-size: 11.5px; opacity: .8; }
    .blog-card__read {
        display: inline-flex; align-items: center; gap: 4px;
        color: var(--ds-copper);
        font-weight: 600; text-decoration: none;
        font-size: 13px; flex-shrink: 0;
    }
    .blog-card__read:hover { color: var(--ds-copper-2); }
    .blog-card__read::after { content: '→'; transition: transform .2s ease; }
    .blog-card__read:hover::after { transform: translateX(3px); }

    /* Empty state */
    .blog-empty {
        text-align: center; padding: 72px 24px;
        border: 1px dashed var(--ds-line);
        border-radius: var(--ds-r-xl); background: #fff;
        color: var(--ds-muted);
    }
    .blog-empty__icon {
        width: 56px; height: 56px; margin: 0 auto 16px;
        border-radius: 50%; background: var(--ds-cream-2);
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--ds-copper);
    }
    .blog-empty h4 { color: var(--ds-charcoal); font-family: 'Instrument Sans', system-ui, sans-serif; font-size: 22px; margin: 0 0 8px; }
    .blog-empty p { margin: 0; font-size: 14.5px; }

    /* Pagination */
    .blog-pager { margin-top: 48px; display: flex; justify-content: center; }
    .blog-pager .pagination { gap: 4px; }
</style>
@endsection

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'The Journal',
        'crumbs' => [['label' => 'Blog']],
    ])
    <!-- /page-hero -->

    <section class="flat-spacing blog-page-body">
        <div class="container">
            <div class="blog-intro">
                <div class="blog-intro__kicker">Stories &amp; guides</div>
                <h2 class="blog-intro__heading">
                    @if ($activeCategory)
                        {{ $activeCategory }}
                    @elseif ($search)
                        Search results
                    @else
                        Craft the perfect candle
                    @endif
                </h2>
                <p class="blog-intro__sub">
                    @if ($search)
                        Showing posts matching "{{ $search }}".
                    @elseif ($activeCategory)
                        All the latest {{ strtolower($activeCategory) }} tutorials, tips and product breakdowns.
                    @else
                        Behind the wax — sourcing notes, wick sizing, fragrance pairings, and the small tricks that make a big difference.
                    @endif
                </p>
            </div>

            @if ($blogCategories->isNotEmpty() || $search)
                <div class="blog-filters">
                    <span class="blog-filters__label">Browse</span>
                    <a href="{{ route('client.blog.index') }}"
                        class="blog-filter-chip {{ $activeCategory === '' && $search === '' ? 'is-active' : '' }}">
                        All posts
                    </a>
                    @foreach ($blogCategories as $cat)
                        <a href="{{ route('client.blog.index', ['category' => $cat]) }}"
                            class="blog-filter-chip {{ $activeCategory === $cat ? 'is-active' : '' }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                    @if ($search)
                        <span class="blog-filter-chip is-active">
                            Search: "{{ $search }}"
                        </span>
                    @endif
                </div>
            @endif

            @if ($posts->isEmpty())
                <div class="blog-empty">
                    <div class="blog-empty__icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    </div>
                    <h4>No articles yet</h4>
                    <p>Check back soon — we're brewing new stories for makers like you.</p>
                </div>
            @else
                @php
                    $isFirstPage = ! request('page') || request('page') == 1;
                    $noFilter = $activeCategory === '' && $search === '';
                    $showFeatured = $isFirstPage && $noFilter && $posts->count() > 1;
                    $featured = $showFeatured ? $posts->first() : null;
                    $rest = $showFeatured ? $posts->slice(1) : $posts;
                @endphp

                @if ($featured)
                    <article class="blog-featured">
                        <a href="{{ route('client.blog.show', $featured->slug) }}" class="blog-featured__media" aria-label="{{ $featured->title }}">
                            @if ($featured->image)
                                <img src="{{ asset('storage/blog/' . $featured->image) }}" alt="{{ $featured->title }}" loading="lazy">
                            @endif
                        </a>
                        <div class="blog-featured__body">
                            <div class="blog-featured__eyebrow">
                                @if ($featured->category)
                                    <a href="{{ route('client.blog.index', ['category' => $featured->category]) }}">{{ $featured->category }}</a>
                                    <span class="dot"></span>
                                @endif
                                <span>{{ $readingTime($featured->content) }} min read</span>
                            </div>
                            <h3 class="blog-featured__title">
                                <a href="{{ route('client.blog.show', $featured->slug) }}">{{ $featured->title }}</a>
                            </h3>
                            @if ($featured->excerpt)
                                <p class="blog-featured__excerpt">{{ $featured->excerpt }}</p>
                            @endif
                            <div>
                                <a href="{{ route('client.blog.show', $featured->slug) }}" class="blog-featured__cta">Continue reading</a>
                            </div>
                        </div>
                    </article>
                @endif

                <div class="blog-grid">
                    @foreach ($rest as $post)
                        <article class="blog-card">
                            <a href="{{ route('client.blog.show', $post->slug) }}" class="blog-card__media" aria-label="{{ $post->title }}">
                                @if ($post->image)
                                    <img src="{{ asset('storage/blog/' . $post->image) }}" alt="{{ $post->title }}" loading="lazy">
                                @else
                                    <div class="placeholder">No cover image</div>
                                @endif
                                <span class="blog-card__readtime" aria-label="Reading time">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    {{ $readingTime($post->content) }} min
                                </span>
                            </a>
                            <div class="blog-card__body">
                                @if ($post->category)
                                    <a href="{{ route('client.blog.index', ['category' => $post->category]) }}" class="blog-card__category">
                                        {{ $post->category }}
                                    </a>
                                @endif
                                <h3 class="blog-card__title">
                                    <a href="{{ route('client.blog.show', $post->slug) }}">{{ $post->title }}</a>
                                </h3>
                                @if ($post->excerpt)
                                    <p class="blog-card__excerpt">{{ $post->excerpt }}</p>
                                @endif
                                <div class="blog-card__meta">
                                    <span class="blog-card__author">
                                        <span class="blog-card__avatar" aria-hidden="true">
                                            {{ $initials($post->author ?: config('app.name')) }}
                                        </span>
                                        <span class="blog-card__author-info">
                                            @if ($post->author)
                                                <span class="blog-card__author-name">{{ $post->author }}</span>
                                            @endif
                                            <span class="blog-card__date">
                                                {{ optional($post->published_at ?? $post->created_at)->format('M d, Y') }}
                                            </span>
                                        </span>
                                    </span>
                                    <a href="{{ route('client.blog.show', $post->slug) }}" class="blog-card__read">Read</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($posts->hasPages())
                    <div class="blog-pager">
                        {{ $posts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
@endsection
