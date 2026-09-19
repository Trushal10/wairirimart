@extends('layouts.client')

@section('title', 'Return ' . $return->return_no . ' | ' . config('app.name'))
@section('meta_description', 'Track the status of your return request.')

@section('style')
<style>
    .return-page { max-width: 720px; margin: 0 auto; }
    .card { background:#fff; border:1px solid #eee; border-radius:12px; padding:20px; margin-bottom:16px; }
    .card h4 { font-size:16px; font-weight:600; color:#111; margin:0 0 12px; }

    .head-row { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .ref { font-size:18px; font-weight:700; color:#111; }
    .sub { font-size:12px; color:#888; margin-top:2px; }

    .pill { display:inline-block; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .pill-requested { background:#e5f0ff; color:#1758c6; }
    .pill-approved { background:#e2f7e9; color:#177a3b; }
    .pill-received { background:#fff8e6; color:#a06600; }
    .pill-refunded { background:#efe6ff; color:#5a30a8; }
    .pill-rejected { background:#fde3e3; color:#a52323; }
    .pill-cancelled { background:#f0f0f0; color:#666; }

    .item { display:flex; gap:12px; align-items:center; padding:10px 0; border-bottom:1px dashed #eee; }
    .item:last-child { border-bottom:0; }
    .item img { width:52px; height:52px; object-fit:cover; border-radius:8px; }
    .item .name { font-weight:600; color:#111; font-size:14px; }
    .item .meta { font-size:12px; color:#888; }
    .item .amt { font-weight:600; color:#111; font-size:14px; }

    .timeline { position:relative; padding-left:22px; border-left:2px solid #eee; }
    .timeline-item { position:relative; padding-bottom:16px; }
    .timeline-item::before {
        content:''; position:absolute; left:-29px; top:4px; width:12px; height:12px;
        border-radius:50%; background:#ccc; border:2px solid #fff; box-shadow:0 0 0 2px #eee;
    }
    .timeline-item.done::before { background:#177a3b; box-shadow:0 0 0 2px #d5edd8; }
    .timeline-item.warn::before { background:#a52323; box-shadow:0 0 0 2px #f7d7d7; }
    .timeline-item small { color:#888; font-size:11px; }
    .timeline-item strong { display:block; color:#111; font-size:13px; }

    .footer-row { display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding-top:14px; border-top:1px solid #eee; }
    .btn-danger { background:transparent; color:#a52323; border:0; font-size:13px; cursor:pointer; }
    .btn-danger:hover { text-decoration:underline; }
    .btn-ghost { color:#666; text-decoration:none; font-size:13px; }
</style>
@endsection

@section('content')
@include('client.partials.page-hero', [
    'title'  => 'Return ' . $return->return_no,
    'crumbs' => [
        ['label' => 'My orders', 'url' => route('client.profile') . '?type=orders'],
        ['label' => $return->return_no],
    ],
])

<section class="flat-spacing">
    <div class="container return-page">

        {{-- Header --}}
        <div class="card">
            <div class="head-row">
                <div>
                    <div class="ref">{{ $return->return_no }}</div>
                    <div class="sub">
                        Order <a href="{{ route('client.profile') }}?type=orders">#{{ $return->order->order_no }}</a>
                        · Requested {{ optional($return->requested_at)->format('d M Y') }}
                    </div>
                </div>
                <span class="pill pill-{{ $return->status }}">{{ str_replace('_', ' ', $return->status) }}</span>
            </div>
        </div>

        {{-- Items --}}
        <div class="card">
            <h4>Items in this return</h4>
            @foreach ($return->items as $item)
                @php
                    $product = $item->orderItem?->product;
                    $img = ! empty($item->orderItem?->media?->url)
                        ? asset('storage/product/' . $item->orderItem->media->url)
                        : asset('client/images/home/product-placeholder.webp');
                @endphp
                <div class="item">
                    <img src="{{ $img }}" alt="{{ $product->name ?? 'Product' }}"
                        onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'">
                    <div style="flex:1;min-width:0">
                        <div class="name">{{ $product->name ?? 'Product' }}</div>
                        <div class="meta">Quantity {{ $item->quantity }} · ₹{{ number_format($item->unit_price, 2) }} each</div>
                    </div>
                    <div class="amt">₹{{ number_format($item->lineTotal(), 2) }}</div>
                </div>
            @endforeach
            <div style="display:flex;justify-content:space-between;padding-top:12px;border-top:1px solid #eee;margin-top:10px">
                <strong>Expected refund</strong>
                <strong>₹{{ number_format($return->refund_amount, 2) }}</strong>
            </div>
        </div>

        {{-- Reason --}}
        <div class="card">
            <h4>Reason</h4>
            <p style="font-size:14px;color:#333;margin:0 0 6px"><strong>{{ $return->reasonLabel() }}</strong></p>
            @if ($return->comment)
                <p style="font-size:13px;color:#555;margin:0">{{ $return->comment }}</p>
            @endif
            @if ($return->photo)
                <div style="margin-top:12px">
                    <img src="{{ asset('storage/returns/' . $return->photo) }}"
                        alt="Return photo"
                        style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid #eee">
                </div>
            @endif
        </div>

        {{-- Timeline --}}
        <div class="card">
            <h4>Timeline</h4>
            <div class="timeline">
                <div class="timeline-item done">
                    <strong>Requested</strong>
                    <small>{{ optional($return->requested_at)->format('d M Y · H:i') }}</small>
                </div>
                @if ($return->approved_at)
                    <div class="timeline-item done">
                        <strong>Approved</strong>
                        <small>{{ $return->approved_at->format('d M Y · H:i') }} — Please ship the item(s) back.</small>
                    </div>
                @endif
                @if ($return->rejected_at)
                    <div class="timeline-item warn">
                        <strong>Rejected</strong>
                        <small>{{ $return->rejected_at->format('d M Y · H:i') }}</small>
                        @if ($return->rejection_reason)
                            <div style="font-size:13px;color:#666;margin-top:4px">{{ $return->rejection_reason }}</div>
                        @endif
                    </div>
                @endif
                @if ($return->received_at)
                    <div class="timeline-item done">
                        <strong>Package received</strong>
                        <small>{{ $return->received_at->format('d M Y · H:i') }}</small>
                    </div>
                @endif
                @if ($return->refunded_at)
                    <div class="timeline-item done">
                        <strong>Refunded ₹{{ number_format($return->refund_amount, 2) }}</strong>
                        <small>{{ $return->refunded_at->format('d M Y · H:i') }}</small>
                    </div>
                @endif
                @if ($return->status === 'cancelled')
                    <div class="timeline-item warn">
                        <strong>Cancelled</strong>
                        <small>Return request was cancelled.</small>
                    </div>
                @endif
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
            <a class="btn-ghost" href="{{ route('client.profile') }}?type=orders">← Back to orders</a>
            @if ($return->status === 'requested')
                {{-- SweetAlert, like the order-cancel action in the profile and on the
                     confirmation page. A native confirm() here was the last place in
                     the app where a destructive action used the browser dialog. --}}
                <form method="POST" id="cancel-return-form"
                    action="{{ route('client.returns.cancel', ['returnNo' => $return->return_no]) }}">
                    @csrf
                    <button type="button" class="btn-danger" id="cancel-return-btn">Cancel return request</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    var btn = document.getElementById('cancel-return-btn');
    var form = document.getElementById('cancel-return-form');
    if (!btn || !form) return;

    btn.addEventListener('click', function () {
        if (typeof Swal === 'undefined') {
            // SweetAlert loads deferred; don't strand the action if someone
            // clicks before it arrives.
            if (window.confirm('Cancel this return request?')) form.submit();
            return;
        }
        Swal.fire({
            title: 'Cancel this return request?',
            text: 'You can raise a new one later while the return window is still open.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#a83b32',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'Keep it'
        }).then(function (result) {
            if (result.isConfirmed) {
                btn.disabled = true;
                form.submit();
            }
        });
    });
})();
</script>
@endsection
