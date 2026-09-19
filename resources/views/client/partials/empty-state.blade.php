{{--
    Shared empty state for product grids and lists.

    Shop had one (an emoji and an h5), category had none at all — filtering to
    zero results there produced a blank page with pagination arrows under it and
    no explanation. One partial so both read the same, and so the next listing
    that needs one does not invent a third variant.

    @param string      $title
    @param string|null $message
    @param string|null $ctaLabel
    @param string|null $ctaUrl
--}}
@php
    $title    = $title    ?? 'Nothing here yet';
    $message  = $message  ?? null;
    $ctaLabel = $ctaLabel ?? null;
    $ctaUrl   = $ctaUrl   ?? null;
@endphp
<div class="es-block" style="grid-column:1/-1">
    <div class="es-icon" aria-hidden="true">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
    </div>
    <h2 class="es-title">{{ $title }}</h2>
    @if ($message)
        <p class="es-copy">{{ $message }}</p>
    @endif
    @if ($ctaLabel && $ctaUrl)
        <a href="{{ $ctaUrl }}" class="es-cta">{{ $ctaLabel }}</a>
    @endif
</div>

