@extends('layouts.client')

@section('title')
    Page not found | {{ config('app.name') }}
@endsection

@section('meta_description')
    That page has moved or no longer exists. Browse the shop or head back to the homepage.
@endsection

{{-- A 404 has nothing worth indexing, and the layout emitted an empty
     description tag for it because this page never set one. --}}
@section('structured_data')
    <meta name="robots" content="noindex, follow">
@endsection

@section('content')
    <!-- 404 -->
    <section class="flat-spacing page-404">
        <div class="container">
            <div class="page-404-inner">
                <div class="image">
                    {{-- alt="" because it is decorative: the heading beside it already
                         says what happened, and the old alt ("Image not found")
                         described a broken image rather than this illustration. --}}
                    <img src="{{ \App\Helper\CommonHelper::assetV('client/images/404/404.webp') }}"
                        alt="" width="480" height="360" decoding="async"
                        style="max-width:100%;height:auto">
                </div>
                <div class="content">
                    <div class="heading">Oops!</div>
                    <div>
                        <h2 class="title mb_4">Something is Missing.</h2>
                        <div class="text body-text-1 text-secondary">
                            The page you're looking for has moved or no longer exists.
                            Try one of these instead.
                        </div>
                    </div>
                    {{-- A single "back to homepage" button makes a dead end out of
                         every mistyped product URL. Give the shopper the two things
                         they were probably after. --}}
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('client.shop') }}" class="tf-btn btn-fill radius-4">
                            <span class="text text-button">Browse the shop</span>
                        </a>
                        <a href="{{ route('client.home') }}" class="tf-btn btn-white has-border radius-4">
                            <span class="text text-button">Back to homepage</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /404 -->
@endsection