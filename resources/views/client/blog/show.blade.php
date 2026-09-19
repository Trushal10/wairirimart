@extends('layouts.client')

@section('title')
    {{ $post->meta_title ?: $post->title }} | {{ config('app.name') }}
@endsection

@section('meta_description')
    {{ $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($post->content), 155)) }}
@endsection

@section('og_type', 'article')
@section('og_image', $post->image ? asset('storage/blog/' . $post->image) : ($__ogImage ?? asset('client/images/logo/logo.svg')))

@section('structured_data')
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": {!! json_encode($post->title, JSON_UNESCAPED_UNICODE) !!},
        "datePublished": {!! json_encode(optional($post->published_at ?? $post->created_at)->toIso8601String()) !!},
        "dateModified": {!! json_encode(optional($post->updated_at)->toIso8601String()) !!},
        @if ($post->author)
        "author": { "@type": "Person", "name": {!! json_encode($post->author, JSON_UNESCAPED_UNICODE) !!} },
        @endif
        @if ($post->image)
        "image": {!! json_encode(asset('storage/blog/' . $post->image)) !!},
        @endif
        "mainEntityOfPage": {!! json_encode(url()->current()) !!}
    }
    </script>
@endsection

@php
    $wordCount = str_word_count(strip_tags((string) $post->content));
    $readingTime = max(1, (int) ceil($wordCount / 220));

    $initials = function ($name) {
        if (! $name) return '';
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return strtoupper($first . $last);
    };

    $shareUrl = url()->current();
    $shareText = urlencode($post->title . ' — ' . config('app.name'));
@endphp

@section('style')
<style>
    .blog-single { background: var(--ds-cream); padding-bottom: 96px; }

    /* ---------- Article column (col-lg-8) ---------- */
    .blog-article {
        background: #fff;
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-xl);
        overflow: hidden;
    }
    .blog-article__hero {
        padding: 40px 40px 24px;
        text-align: center;
    }
    @media (max-width: 767px) {
        .blog-article__hero { padding: 28px 22px 20px; }
    }
    .blog-article__eyebrow {
        display: inline-flex; align-items: center; gap: 10px;
        font-size: 11px; letter-spacing: .18em;
        text-transform: uppercase; font-weight: 600;
        color: var(--ds-copper);
        margin-bottom: 16px;
    }
    .blog-article__eyebrow a { color: inherit; text-decoration: none; }
    .blog-article__eyebrow .dot {
        display: inline-block; width: 3px; height: 3px; border-radius: 50%;
        background: currentColor; opacity: .5;
    }
    .blog-article__title {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: clamp(24px, 3.4vw, 38px);
        line-height: 1.15; font-weight: 700;
        color: var(--ds-charcoal);
        margin: 0 0 18px;
        letter-spacing: -.005em;
    }
    .blog-article__meta {
        font-size: 13.5px; color: var(--ds-muted);
        display: inline-flex; align-items: center; gap: 12px;
        flex-wrap: wrap; justify-content: center;
    }
    .blog-article__meta .dot {
        display: inline-block; width: 4px; height: 4px; border-radius: 50%;
        background: currentColor; opacity: .5;
    }
    .blog-article__meta strong { color: var(--ds-charcoal); font-weight: 600; }

    .blog-article__cover {
        margin: 0 0 24px;
        background: var(--ds-cream-2);
        overflow: hidden;
        border-top: 1px solid var(--ds-line);
        border-bottom: 1px solid var(--ds-line);
    }
    .blog-article__cover img { width: 100%; height: auto; display: block; }

    /* Body typography */
    .blog-article__body {
        padding: 8px 40px 40px;
        font-size: 16.5px; line-height: 1.75;
        color: var(--ds-charcoal);
    }
    @media (max-width: 767px) {
        .blog-article__body { padding: 8px 22px 32px; font-size: 15.5px; }
    }
    .blog-article__body h1, .blog-article__body h2, .blog-article__body h3,
    .blog-article__body h4, .blog-article__body h5, .blog-article__body h6 {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        margin: 1.8em 0 .6em;
        line-height: 1.25; color: var(--ds-charcoal);
        letter-spacing: -.005em;
    }
    .blog-article__body h1 { font-size: 26px; }
    .blog-article__body h2 { font-size: 23px; }
    .blog-article__body h3 { font-size: 19px; }
    .blog-article__body h4 { font-size: 17px; }
    .blog-article__body p { margin: 0 0 1.1em; }
    .blog-article__body a { color: var(--ds-copper); text-decoration: underline; text-underline-offset: 3px; }
    .blog-article__body a:hover { color: var(--ds-copper-2); }
    .blog-article__body ul, .blog-article__body ol { margin: 0 0 1.1em; padding-left: 1.4em; }
    .blog-article__body li { margin-bottom: .4em; }
    .blog-article__body li::marker { color: var(--ds-copper); }
    .blog-article__body blockquote {
        border-left: 3px solid var(--ds-copper);
        margin: 1.4em 0; padding: .2em 0 .2em 1.2em;
        color: var(--ds-charcoal);
        font-style: italic; font-size: 1.02em;
    }
    .blog-article__body img {
        max-width: 100%; height: auto; border-radius: 10px;
        border: 1px solid var(--ds-line); margin: 1.3em 0;
        display: block;
    }
    .blog-article__body pre, .blog-article__body code {
        background: #f6f2ea; color: #3f2f1e; border-radius: 6px;
        padding: 2px 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .92em;
    }
    .blog-article__body pre { padding: 14px 18px; overflow: auto; margin: 1.3em 0; }
    .blog-article__body pre code { background: transparent; padding: 0; }
    .blog-article__body table {
        width: 100%; border-collapse: collapse; margin: 1.4em 0;
        font-size: .94em;
    }
    .blog-article__body th, .blog-article__body td {
        border: 1px solid var(--ds-line); padding: 9px 12px; text-align: left;
    }
    .blog-article__body th { background: var(--ds-cream-2); font-weight: 600; }
    .blog-article__body hr {
        border: 0; border-top: 1px solid var(--ds-line);
        margin: 2em 0;
    }

    /* Article footer inside the card */
    .blog-article__footer {
        padding: 20px 40px 28px;
        border-top: 1px solid var(--ds-line);
        display: flex; flex-wrap: wrap; gap: 16px;
        align-items: center; justify-content: space-between;
    }
    @media (max-width: 767px) { .blog-article__footer { padding: 18px 22px 22px; } }
    .blog-share {
        display: inline-flex; align-items: center; gap: 8px;
    }
    .blog-share__label {
        font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ds-muted); font-weight: 600; margin-right: 4px;
    }
    .blog-share a {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 50%;
        border: 1px solid var(--ds-line);
        background: #fff; color: var(--ds-muted);
        text-decoration: none;
        transition: all var(--ds-t-med);
    }
    .blog-share a:hover {
        color: var(--ds-copper); border-color: var(--ds-copper);
    }
    .blog-share a svg { width: 14px; height: 14px; }
    .blog-back-link {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--ds-charcoal); font-weight: 600; font-size: 13.5px;
        text-decoration: none;
    }
    .blog-back-link:hover { color: var(--ds-copper); }

    /* Author card below the article */
    .blog-author {
        margin-top: 24px;
        padding: 22px 24px;
        background: #fff;
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-lg);
        display: flex; align-items: center; gap: 16px;
    }
    .blog-author__avatar {
        flex-shrink: 0;
        width: 52px; height: 52px; border-radius: 50%;
        background: var(--ds-cream-2);
        border: 1px solid var(--ds-line);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 16px; font-weight: 700; letter-spacing: .05em;
        color: var(--ds-copper);
    }
    .blog-author__label {
        font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ds-muted); font-weight: 600; margin-bottom: 3px;
    }
    .blog-author__name { font-size: 16px; font-weight: 600; color: var(--ds-charcoal); }

    /* ---------- Sidebar (col-lg-4) ---------- */
    .blog-sidebar {
        display: flex; flex-direction: column; gap: 24px;
    }
    @media (min-width: 992px) {
        .blog-sidebar { position: sticky; top: 100px; align-self: flex-start; }
    }
    .blog-side-card {
        background: #fff;
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-lg);
        overflow: hidden;
    }
    .blog-side-card__header {
        padding: 18px 22px;
        border-bottom: 1px solid var(--ds-line);
    }
    .blog-side-card__kicker {
        display: block; font-size: 10.5px; letter-spacing: .18em;
        text-transform: uppercase; color: var(--ds-copper);
        font-weight: 600; margin-bottom: 4px;
    }
    .blog-side-card__title {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: 18px; font-weight: 600; color: var(--ds-charcoal);
        margin: 0;
    }

    /* Related posts list inside sidebar */
    .blog-side-list { display: flex; flex-direction: column; }
    .blog-side-post {
        display: grid;
        grid-template-columns: 88px 1fr;
        gap: 14px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--ds-line);
        text-decoration: none;
        transition: background .15s ease;
    }
    .blog-side-post:last-child { border-bottom: 0; }
    .blog-side-post:hover { background: var(--ds-cream); }
    .blog-side-post__media {
        aspect-ratio: 1 / 1;
        background: var(--ds-cream-2);
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--ds-line);
    }
    .blog-side-post__media img {
        width: 100%; height: 100%; object-fit: cover; display: block;
        transition: transform .4s ease;
    }
    .blog-side-post:hover .blog-side-post__media img { transform: scale(1.06); }
    .blog-side-post__media-placeholder {
        display: flex; align-items: center; justify-content: center; height: 100%;
        color: var(--ds-copper);
    }
    .blog-side-post__body {
        display: flex; flex-direction: column; justify-content: center;
        min-width: 0;
    }
    .blog-side-post__category {
        font-size: 10px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ds-copper); font-weight: 600;
        margin-bottom: 4px;
    }
    .blog-side-post__title {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: 14px; font-weight: 600; line-height: 1.35;
        color: var(--ds-charcoal); margin: 0 0 4px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .blog-side-post:hover .blog-side-post__title { color: var(--ds-copper); }
    .blog-side-post__meta {
        font-size: 11.5px; color: var(--ds-muted);
    }

    .blog-side-card__footer {
        padding: 14px 22px;
        border-top: 1px solid var(--ds-line);
        text-align: center;
    }
    .blog-side-card__footer a {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 12.5px; font-weight: 600;
        color: var(--ds-copper);
        text-decoration: none;
    }
    .blog-side-card__footer a:hover { color: var(--ds-copper-2); }
    .blog-side-card__footer a::after { content: '→'; transition: transform .2s ease; }
    .blog-side-card__footer a:hover::after { transform: translateX(3px); }

    /* Categories chip cloud in sidebar */
    .blog-side-cats {
        display: flex; flex-wrap: wrap; gap: 8px;
        padding: 18px 22px 22px;
    }
    .blog-side-cats a {
        display: inline-flex; align-items: center;
        padding: 6px 12px;
        border: 1px solid var(--ds-line);
        border-radius: 999px;
        background: #fff;
        font-size: 12px; color: var(--ds-charcoal);
        text-decoration: none;
        transition: all var(--ds-t-med);
    }
    .blog-side-cats a:hover { border-color: var(--ds-copper); color: var(--ds-copper); }

    /* Shop-supplies CTA card in sidebar */
    .blog-side-cta {
        padding: 26px 24px;
        text-align: center;
        background: linear-gradient(180deg, #FDF9F1 0%, #F5EFE6 100%);
        border: 1px solid var(--ds-line);
        border-radius: var(--ds-r-lg);
    }
    .blog-side-cta__kicker {
        font-size: 10.5px; letter-spacing: .18em; text-transform: uppercase;
        color: var(--ds-copper); font-weight: 600; margin-bottom: 8px;
    }
    .blog-side-cta__heading {
        font-family: 'Instrument Sans', system-ui, sans-serif;
        font-size: 20px; font-weight: 600; line-height: 1.3;
        color: var(--ds-charcoal); margin: 0 0 8px;
    }
    .blog-side-cta__sub {
        font-size: 13.5px; color: var(--ds-muted); margin: 0 0 16px; line-height: 1.55;
    }
</style>
@endsection

@section('content')
    {{-- No hero title here: the article already renders its own <h1>. --}}
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'crumbs' => [
            ['label' => 'Blog', 'url' => route('client.blog.index')],
            ['label' => Str::limit($post->title, 40)],
        ],
    ])
    <!-- /page-hero -->

    <section class="flat-spacing blog-single">
        <div class="container">
            <div class="row g-4">

                {{-- ===== col-lg-8: article content ===== --}}
                <div class="col-lg-8">
                    <article class="blog-article">
                        <div class="blog-article__hero">
                            <div class="blog-article__eyebrow">
                                @if ($post->category)
                                    <a href="{{ route('client.blog.index', ['category' => $post->category]) }}">{{ $post->category }}</a>
                                    <span class="dot"></span>
                                @endif
                                <span>{{ $readingTime }} min read</span>
                            </div>
                            <h1 class="blog-article__title">{{ $post->title }}</h1>
                            <div class="blog-article__meta">
                                <span>{{ optional($post->published_at ?? $post->created_at)->format('M d, Y') }}</span>
                                @if ($post->author)
                                    <span class="dot"></span>
                                    <span>By <strong>{{ $post->author }}</strong></span>
                                @endif
                            </div>
                        </div>

                        @if ($post->image)
                            <div class="blog-article__cover">
                                <img src="{{ asset('storage/blog/' . $post->image) }}" alt="{{ $post->title }}">
                            </div>
                        @endif

                        <div class="blog-article__body">
                            {!! $post->content !!}
                        </div>

                        <div class="blog-article__footer">
                            <a href="{{ route('client.blog.index') }}" class="blog-back-link">
                                <i class="icon-arrLeft"></i> Back to all posts
                            </a>
                            <div class="blog-share">
                                <span class="blog-share__label">Share</span>
                                <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ $shareText }}"
                                    target="_blank" rel="noopener" aria-label="Share on Twitter/X">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 4h3l-7.5 8.6L22 20h-6.7l-5.2-6.4L4 20H1l8-9.2L1.5 4H8l4.7 5.8z"/></svg>
                                </a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                                    target="_blank" rel="noopener" aria-label="Share on Facebook">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                                </a>
                                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}"
                                    target="_blank" rel="noopener" aria-label="Share on LinkedIn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 1 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                                </a>
                                <a href="https://api.whatsapp.com/send?text={{ $shareText }}%20{{ urlencode($shareUrl) }}"
                                    target="_blank" rel="noopener" aria-label="Share on WhatsApp">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-1.2 4.3L21 21l-5.4-1.2A8.5 8.5 0 1 1 21 11.5z"/></svg>
                                </a>
                                {{-- navigator.clipboard is undefined outside a secure context, and
                                     the optional-chaining call swallowed that silently — on http the
                                     button looked live and did nothing, with no feedback either way.
                                     js-copy-link handles both, and says so. --}}
                                <a href="#" class="js-copy-link" aria-label="Copy link">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
                                </a>
                            </div>
                        </div>
                    </article>

                    @if ($post->author)
                        <div class="blog-author">
                            <div class="blog-author__avatar" aria-hidden="true">{{ $initials($post->author) }}</div>
                            <div>
                                <div class="blog-author__label">Written by</div>
                                <div class="blog-author__name">{{ $post->author }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ===== col-lg-4: sidebar ===== --}}
                <aside class="col-lg-4">
                    <div class="blog-sidebar">

                        {{-- Related posts list --}}
                        @if ($related->isNotEmpty())
                            <div class="blog-side-card">
                                <div class="blog-side-card__header">
                                    <span class="blog-side-card__kicker">Keep reading</span>
                                    <h4 class="blog-side-card__title">Related posts</h4>
                                </div>
                                <div class="blog-side-list">
                                    @foreach ($related as $r)
                                        <a href="{{ route('client.blog.show', $r->slug) }}" class="blog-side-post">
                                            <div class="blog-side-post__media">
                                                @if ($r->image)
                                                    <img src="{{ asset('storage/blog/' . $r->image) }}" alt="{{ $r->title }}" loading="lazy">
                                                @else
                                                    <div class="blog-side-post__media-placeholder">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="blog-side-post__body">
                                                @if ($r->category)
                                                    <span class="blog-side-post__category">{{ $r->category }}</span>
                                                @endif
                                                <h5 class="blog-side-post__title">{{ $r->title }}</h5>
                                                <span class="blog-side-post__meta">
                                                    {{ optional($r->published_at ?? $r->created_at)->format('M d, Y') }}
                                                </span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                <div class="blog-side-card__footer">
                                    <a href="{{ route('client.blog.index') }}">Browse all posts</a>
                                </div>
                            </div>
                        @endif

                        {{-- Categories chip cloud (only when there are 2+) --}}
                        @if ($allCategories->count() > 1)
                            <div class="blog-side-card">
                                <div class="blog-side-card__header">
                                    <span class="blog-side-card__kicker">Explore</span>
                                    <h4 class="blog-side-card__title">Categories</h4>
                                </div>
                                <div class="blog-side-cats">
                                    @foreach ($allCategories as $cat)
                                        <a href="{{ route('client.blog.index', ['category' => $cat]) }}">{{ $cat }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Shop CTA --}}
                        <div class="blog-side-cta">
                            <div class="blog-side-cta__kicker">Ready to make</div>
                            <h4 class="blog-side-cta__heading">Shop candle supplies</h4>
                            <p class="blog-side-cta__sub">Waxes, wicks, fragrance oils and moulds — everything you need to get pouring.</p>
                            <a href="{{ route('client.shop') }}" class="tf-btn btn-fill w-100">
                                <span class="text text-button">Browse the shop</span>
                            </a>
                        </div>

                    </div>
                </aside>

            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('.js-copy-link') : null;
        if (!link) return;
        e.preventDefault();

        var url = window.location.href;
        var done = function () {
            if (typeof showSweetAlert === 'function') showSweetAlert('success', 'Link copied.');
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(done).catch(function () {});
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        document.body.removeChild(ta);
    });
})();
</script>
@endsection
