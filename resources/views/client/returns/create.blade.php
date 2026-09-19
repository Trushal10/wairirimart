@extends('layouts.client')

@section('title', 'Return items · Order ' . $order->order_no . ' | ' . config('app.name'))
@section('meta_description', 'Request a return for items in your order.')

@section('style')
<style>
    .return-page { max-width: 720px; margin: 0 auto; }
    .return-card { background:#fff; border:1px solid #eee; border-radius:12px; padding:20px; margin-bottom:16px; }
    .return-card h4 { font-size:16px; font-weight:600; color:#111; margin:0 0 4px; }
    .return-card p.hint { font-size:12px; color:#888; margin:0 0 14px; }

    .item-row { display:flex; gap:12px; align-items:center; padding:12px; border:1px solid #eee; border-radius:10px; margin-bottom:10px; transition:.15s; cursor:pointer; }
    .item-row.selected { border-color:var(--ds-charcoal); background:#fafafa; }
    .item-row img { width:56px; height:56px; object-fit:cover; border-radius:8px; flex-shrink:0; }
    .item-row .info { flex:1; min-width:0; }
    .item-row .name { font-weight:600; color:#111; font-size:14px; }
    .item-row .meta { font-size:12px; color:#888; }
    .item-row .qty { display:flex; align-items:center; gap:8px; }
    .item-row input[type="number"] { width:60px; padding:6px 8px; border:1px solid #ddd; border-radius:6px; font-size:13px; text-align:center; }
    .item-row input[type="number"]:disabled { background:#f5f5f5; color:#aaa; }
    .item-row input[type="checkbox"] { accent-color:#111; width:18px; height:18px; }

    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:13px; font-weight:500; color:#333; margin-bottom:6px; }
    .form-group select, .form-group textarea, .form-group input[type="file"] {
        width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:14px;
    }
    .form-group textarea { min-height:80px; resize:vertical; }
    .form-group .field-hint { font-size:11px; color:#999; margin-top:4px; }

    .footer-row { display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid #eee; }
    .refund-preview { font-size:14px; color:#333; }
    .refund-preview strong { color:#111; font-size:18px; margin-left:6px; }

    .btn-primary { background:var(--ds-charcoal); color:#fff; padding:11px 24px; border-radius:8px; font-weight:600; font-size:14px; border:0; cursor:pointer; transition:.15s; }
    .btn-primary:hover { background:#333; }
    .btn-primary:disabled { opacity:.5; cursor:not-allowed; }
    .btn-ghost { color:#666; text-decoration:none; font-size:14px; }

    .alert-danger { background:#fdecec; color:var(--ds-danger); padding:10px 12px; border-radius:8px; font-size:13px; margin-bottom:14px; }
</style>
@endsection

@section('content')
@include('client.partials.page-hero', [
    'title'  => 'Return items',
    'crumbs' => [
        ['label' => 'My orders', 'url' => route('client.profile') . '?type=orders'],
        ['label' => 'Return · ' . $order->order_no],
    ],
])

<section class="flat-spacing">
    <div class="container return-page">
        @if ($errors->any())
            <div class="alert-danger">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('client.returns.store', ['orderNo' => $order->order_no]) }}" enctype="multipart/form-data" id="return-form">
            @csrf

            {{-- Pick items --}}
            <div class="return-card">
                <h4>Which items do you want to return?</h4>
                <p class="hint">Tick each item and set the quantity. Only items still eligible for return are listed.</p>

                @foreach ($items as $i => $item)
                    @php
                        $img = ! empty($item->media?->url)
                            ? asset('storage/product/' . $item->media->url)
                            : asset('client/images/home/product-placeholder.webp');
                    @endphp
                    <label class="item-row" data-item-id="{{ $item->id }}" data-price="{{ $item->price }}">
                        <input type="checkbox" name="items[{{ $i }}][enabled]" value="1" class="item-check">
                        <input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $item->id }}">
                        <img src="{{ $img }}" alt="{{ $item->product->name ?? 'Product' }}"
                            onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'">
                        <div class="info">
                            <div class="name">{{ $item->product->name ?? 'Product' }}</div>
                            <div class="meta">
                                Ordered {{ $item->quantity }} · ₹{{ number_format($item->price, 2) }} each
                                @if ($item->remaining_qty < $item->quantity)
                                    · <span style="color:#a06600">{{ $item->quantity - $item->remaining_qty }} already returned</span>
                                @endif
                            </div>
                        </div>
                        <div class="qty">
                            <input type="number" name="items[{{ $i }}][quantity]"
                                min="1" max="{{ $item->remaining_qty }}" value="1"
                                class="item-qty" disabled
                                data-max="{{ $item->remaining_qty }}">
                            <span style="font-size:11px;color:#999">of {{ $item->remaining_qty }}</span>
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- Reason --}}
            <div class="return-card">
                <h4>Why are you returning?</h4>
                <p class="hint">A clear reason helps us process your return faster.</p>

                <div class="form-group">
                    <label for="reason">Reason *</label>
                    <select name="reason" id="reason" required>
                        <option value="">Choose a reason…</option>
                        @foreach ($reasons as $key => $label)
                            <option value="{{ $key }}" {{ old('reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="comment">Additional details</label>
                    <textarea name="comment" id="comment" maxlength="500" placeholder="Anything else we should know? (optional)">{{ old('comment') }}</textarea>
                    <div class="field-hint">Max 500 characters.</div>
                </div>

                <div class="form-group">
                    <label for="photo">Attach a photo (recommended for damaged items)</label>
                    <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp">
                    <div class="field-hint">Max 5 MB. JPG, PNG or WebP.</div>
                </div>
            </div>

            <div class="footer-row">
                <a class="btn-ghost" href="{{ route('client.profile') }}?type=orders">← Cancel</a>
                <div style="display:flex;align-items:center;gap:16px">
                    <span class="refund-preview">Expected refund <strong id="refund-preview">₹0.00</strong></span>
                    <button type="submit" class="btn-primary" id="submit-btn" disabled>Submit request</button>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
(function () {
    const money = v => '₹' + Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

    const rows = document.querySelectorAll('.item-row');
    const preview = document.getElementById('refund-preview');
    const submitBtn = document.getElementById('submit-btn');

    function recalc() {
        let total = 0;
        let anyChecked = false;
        rows.forEach(row => {
            const check = row.querySelector('.item-check');
            const qty = row.querySelector('.item-qty');
            qty.disabled = ! check.checked;
            if (check.checked) {
                row.classList.add('selected');
                anyChecked = true;
                const price = Number(row.getAttribute('data-price') || 0);
                const q = Math.max(1, Math.min(Number(qty.value || 1), Number(qty.getAttribute('data-max') || 1)));
                qty.value = q;
                total += q * price;
            } else {
                row.classList.remove('selected');
            }
        });
        preview.textContent = money(total);
        submitBtn.disabled = ! anyChecked;
    }

    rows.forEach(row => {
        row.querySelector('.item-check').addEventListener('change', recalc);
        row.querySelector('.item-qty').addEventListener('input', recalc);
        // Toggle checkbox on row click (but not when clicking the qty input)
        row.addEventListener('click', (e) => {
            if (e.target.tagName === 'INPUT') return;
            const c = row.querySelector('.item-check');
            c.checked = ! c.checked;
            recalc();
        });
    });

    recalc();
})();
</script>
@endsection
