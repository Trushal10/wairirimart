{{--
    Page hero shown at the top of every client page except the home page,
    which has its own slider.

    The banner artwork is uploaded once in admin Settings and appears on all of
    them; it falls back to the file bundled with the theme when none has been
    uploaded. The artwork carries its own headline, so no page title is painted
    over it — the page name lives in the breadcrumb strip underneath, plus a
    visually hidden <h1> so the page still has one heading for screen readers
    and search engines.

    Usage:
        @include('client.partials.page-hero', [
            'title'  => 'Categories',
            'crumbs' => [
                ['label' => 'Shop', 'url' => route('client.shop')],
                ['label' => 'Categories'],   // current page: no url
            ],
        ])

    The "Homepage" crumb is prepended automatically.
--}}
@php
    $__heroTitle  = trim((string) ($title ?? ''));
    $__heroCrumbs = collect($crumbs ?? [])
        ->filter(fn ($c) => is_array($c) && trim((string) ($c['label'] ?? '')) !== '')
        ->values()
        ->all();

    // One banner for every inner page, uploaded in admin Settings. Falls back
    // to the artwork bundled with the theme so a shop that has never uploaded
    // one still renders a complete page.
    $__heroFile = trim((string) ($settings->page_hero_image ?? ''));
    $__heroSrc  = $__heroFile !== ''
        ? asset('storage/setting/' . $__heroFile)
        : \App\Helper\CommonHelper::assetV('client/images/home/page-hero.webp');
    // Real intrinsic size of the upload, so the browser reserves the right box
    // and the banner does not shift the page as it loads. The bundled file's
    // dimensions are known, so they stay hard-coded.
    $__heroDims = $__heroFile !== ''
        ? \App\Helper\CommonHelper::imageSizeAttrs('setting/' . $__heroFile)
        : 'width="1774" height="696"';
@endphp
<div class="page-hero">
    @if ($__heroTitle !== '')
        <h1 class="page-hero__title">{{ $__heroTitle }}</h1>
    @endif
    <img class="page-hero__img"
         src="{{ $__heroSrc }}"
         {!! $__heroDims !!}
         fetchpriority="high" decoding="async"
         alt="{{ $__heroTitle !== '' ? $__heroTitle . ' — ' . config('app.name') : config('app.name') }}">
    @if ($__heroCrumbs)
        <div class="page-hero__crumbs">
            <div class="container">
                <ul class="breadcrumbs d-flex align-items-center justify-content-center">
                    <li><a class="link" href="{{ route('client.home') }}">Homepage</a></li>
                    @foreach ($__heroCrumbs as $__crumb)
                        <li><i class="icon-arrRight"></i></li>
                        <li>
                            @if (! empty($__crumb['url']))
                                <a class="link" href="{{ $__crumb['url'] }}">{{ $__crumb['label'] }}</a>
                            @else
                                {{ $__crumb['label'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
