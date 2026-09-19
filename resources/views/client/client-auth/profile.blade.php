@extends('layouts.client')

@section('title')
    My Profile | {{ config('app.name') }}
@endsection

@section('meta_description')
    Your {{ config('app.name') }} account — orders, addresses and profile details.
@endsection

{{-- A signed-in account page has nothing to index. --}}
@section('structured_data')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('style')
<style>{!! \App\Helper\CommonHelper::inlineCss(['client/css/order-ui.css', 'client/css/account-orders.css']) !!}</style>
<style>
    /* ---- Avatar edit ----
       The theme clips this container to a circle with overflow:hidden, which
       swallowed the edit button positioned in the bottom-right corner. The
       <img> carries its own border-radius, so the container need not clip.
       Matching the theme's selector so this wins on specificity. */
    .sidebar-account .account-avatar .image { position: relative; overflow: visible; }
    .account-avatar .image { position: relative; }
    .account-avatar .image img {
        border-radius: 50%;
        width: 100%; height: 100%; object-fit: cover;
    }
    .avatar-edit-btn {
        position: absolute; right: 4px; bottom: 4px;
        width: 36px; height: 36px; padding: 0;
        border: 2px solid #fff;
        /* --main, not --ds-charcoal: the ds-* tokens live in design-system.css,
           which this layout does not load, so that var resolved to nothing and
           left the button transparent - a white ring on a light photo. */
        background: var(--main, #2A282B); color: #fff;
        border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 6px rgba(0,0,0,.25);
        transition: transform .15s ease, background-color .15s ease;
    }
    /* Always visible: it used to fade in on :hover, so on a touch screen it
       never appeared and the photo could not be changed at all. */
    .avatar-edit-btn:hover { transform: scale(1.06); }
    .avatar-edit-btn:focus-visible { outline: 2px solid var(--primary, #B0785F); outline-offset: 2px; }
    .avatar-edit-btn svg { width: 15px; height: 15px; fill: #fff; }

    /* ---- Orders empty state ---- */
    .orders-empty {
        text-align: center; padding: 48px 20px;
        border: 1px dashed #ddd; border-radius: 12px; background: #fafafa;
    }
    .orders-empty .icon { color: #bbb; margin-bottom: 12px; }
    .orders-empty h5 { margin-bottom: 6px; color: #222; }
    .orders-empty p { color: #777; font-size: 14px; margin: 0; }

</style>
@endsection

@section('content')

    @php
        use Illuminate\Support\Carbon;
    @endphp
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'My Account',
        'crumbs' => [['label' => 'My Account']],
    ])
    <!-- /page-hero -->

    <div class="btn-sidebar-account">
        <button data-bs-toggle="offcanvas" data-bs-target="#mbAccount"><i class="icon icon-squares-four"></i></button>
    </div>
    <!-- my-account -->
    <section class="flat-spacing">
        <div class="container">
            <div class="my-account-wrap">
                <div class="wrap-sidebar-account">
                    <div class="sidebar-account">
                        <div class="account-avatar">
                            <div class="image">
                                @if(!empty($user->image))
                                    <img src="{{asset('storage/customer/'.$user->image)}}" alt="User Image">
                                @else
                                    <img src="{{asset('client/images/home/user-account.webp')}}" alt="User Image">
                                @endif
                                {{-- Classes, not ids: main.js copies this whole block into the
                                     mobile offcanvas, so every id here exists twice. --}}
                                <button type="button" class="avatar-edit-btn" aria-label="Change profile photo" title="Change profile photo">
                                    <svg xmlns="http://www.w3.org/2000/svg" id="Outline" viewBox="0 0 24 24" width="24" height="24"><path d="M22.853,1.148a3.626,3.626,0,0,0-5.124,0L1.465,17.412A4.968,4.968,0,0,0,0,20.947V23a1,1,0,0,0,1,1H3.053a4.966,4.966,0,0,0,3.535-1.464L22.853,6.271A3.626,3.626,0,0,0,22.853,1.148ZM5.174,21.122A3.022,3.022,0,0,1,3.053,22H2V20.947a2.98,2.98,0,0,1,.879-2.121L15.222,6.483l2.3,2.3ZM21.438,4.857,18.932,7.364l-2.3-2.295,2.507-2.507a1.623,1.623,0,1,1,2.295,2.3Z"/></svg>
                                </button>
                            </div>
                            @error('image')
                            <div class="text-danger">
                                {{ $message }}
                            </div>
                            @enderror
                            <input type="file" class="avatar-input" name="image" accept="image/jpeg,image/png,image/webp" style="display: none;">
                            <div class="error" id="error"></div>               
                            <h6 class="mb_4">{{$user->name}}</h6>
                            <div class="body-text-1">{{$user->email}}</div>
                        </div>
                        <ul class="my-account-nav">
                            <li>
                                <span class="my-account-nav-item" href="#account-details" role="button">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Account Details
                                </span>
                            </li>
                            <li>
                                <a class="my-account-nav-item" href="#account-orders">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16.5078 10.8734V6.36686C16.5078 5.17166 16.033 4.02541 15.1879 3.18028C14.3428 2.33514 13.1965 1.86035 12.0013 1.86035C10.8061 1.86035 9.65985 2.33514 8.81472 3.18028C7.96958 4.02541 7.49479 5.17166 7.49479 6.36686V10.8734M4.11491 8.62012H19.8877L21.0143 22.1396H2.98828L4.11491 8.62012Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Your Orders
                                </a>
                            </li>
                            <li>
                                <a href="#account-address" class="my-account-nav-item">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M21 10C21 17 12 23 12 23C12 23 3 17 3 10C3 7.61305 3.94821 5.32387 5.63604 3.63604C7.32387 1.94821 9.61305 1 12 1C14.3869 1 16.6761 1.94821 18.364 3.63604C20.0518 5.32387 21 7.61305 21 10Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 13C13.6569 13 15 11.6569 15 10C15 8.34315 13.6569 7 12 7C10.3431 7 9 8.34315 9 10C9 11.6569 10.3431 13 12 13Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    My Address
                                </a>
                            </li>
                            <li>
                                <form id="logout" action="{{ route('client.logout.user') }}" method="POST">
                                    @csrf
                                    @method('delete')
                                    <a href="#" class="my-account-nav-item" onclick="event.preventDefault(); document.getElementById('logout').submit();">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M16 17L21 12L16 7" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M21 12H9" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Logout
                                    </a>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="my-account-content" id="my-account-content">
                    <div class="account-details collapse {{empty(request()->get('type')) ? 'show' : ''}}" id="account-details" data-bs-target="#my-account-content" data-bs-parent="#my-account-content">
                        <form action="{{route('client.profile.update')}}" method="POST" class="form-account-details form-has-password" style="margin-bottom:30px">
                            @csrf
                            @method('PUT')
                            <div class="account-info">
                                <h5 class="title">Information</h5>
                                <div class="cols mb_20">
                                    <fieldset class="">
                                        <input class="" type="text" placeholder="Name*" name="name" value="{{old('name') ?? $user->name}}">
                                    </fieldset>
                                    @error('name')
                                        <div class="text-danger">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                    <fieldset class="">
                                        <input class="" type="email" placeholder="Email*" name="email" value="{{old('email') ?? $user->email}}">
                                        @error('email')
                                            <div class="text-danger">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </fieldset>
                                </div>
                                <div class="cols mb_20">
                                    <fieldset class="">
                                        <input class="" type="text" placeholder="Phone*" name="phone" value="{{old('phone') ?? $user->phone}}">
                                        @error('phone')
                                            <div class="text-danger">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </fieldset>
                                    <fieldset class="">
                                        <input class="" type="text" placeholder="City*" name="city" value="{{old('city') ?? $user->city}}">
                                        @error('city')
                                        <div class="text-danger">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </fieldset>
                                </div>
                                <div class="cols mb_20">
                                    <fieldset class="">
                                        <textarea class="" placeholder="Address*" name="address" value="">{{old('address') ?? $user->address}}</textarea>
                                        @error('address')
                                        <div class="text-danger">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </fieldset>
                                </div>
                                <div class="button-submit">
                                    <button class="tf-btn btn-fill radius-4" type="submit">
                                        <span class="text text-button">Update Information</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <form action="{{route('client.profile.password')}}" method="POST" class="form-account-details form-has-password" style="margin-bottom:30px">
                            @csrf
                            @method('PUT')
                            <div class="account-password">
                                <h5 class="title">Change Password</h5>
                                <fieldset class="position-relative password-item mb_20">
                                    <input class="input-password" type="password" placeholder="Current Password*" name="current_password">
                                    <span class="toggle-password unshow">
                                        <i class="icon-eye-hide-line"></i>
                                    </span>
                                </fieldset>
                                @error ('current_password')
                                    <div class="text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                                <fieldset class="position-relative password-item mb_20">
                                    <input class="input-password" type="password" placeholder="New Password*" id="password" name="password">
                                    <span class="toggle-password unshow">
                                        <i class="icon-eye-hide-line"></i>
                                    </span>
                                </fieldset>
                                @error ('password')
                                    <div class="text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                                <fieldset class="position-relative password-item">
                                    <input class="input-password" type="password" placeholder="Confirm Password*" id="password_confirmation" name="password_confirmation">
                                    <span class="toggle-password unshow">
                                        <i class="icon-eye-hide-line"></i>
                                    </span>
                                </fieldset>
                                @error ('password_confirmation')
                                    <div class="text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="button-submit">
                                <button class="tf-btn btn-fill radius-4" type="submit">
                                    <span class="text text-button">Update Password</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="account-orders collapse {{request()->get('type') == 'orders' ? 'show' : ''}}" id="account-orders" data-bs-target="#my-account-content" data-bs-parent="#my-account-content">
                        <div class="wrap-account-order">

                            @if ($orderHistorys->isEmpty())
                                <div class="orders-empty">
                                    <div class="icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                                        </svg>
                                    </div>
                                    <h5>No orders yet</h5>
                                    <p>When you place an order, it'll show up here — with tracking and receipts.</p>
                                    <a href="{{ route('client.shop') }}" class="tf-btn btn-fill radius-4 mt-3">
                                        <span class="text">Start shopping</span>
                                    </a>
                                </div>
                            @else
                                <div class="ao-header">
                                    <h5>Your Orders</h5>
                                    <span>{{ $orderHistorys->total() }} order{{ $orderHistorys->total() === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="ao-list">
                                    {{-- Matches the rest of the storefront: with "Show track order"
                                         off the /track routes 404, so a link here would dead-end. --}}
                                    @php $__trackingOn = \App\Helper\OrderStatusHelper::publicTrackingVisible($settings ?? null); @endphp
                                    @foreach ($orderHistorys as $order)
                                        @include('client.partials.account-order-card', ['order' => $order, 'trackingOn' => $__trackingOn])
                                    @endforeach
                                </div>

                                @if (method_exists($orderHistorys, 'hasPages') && $orderHistorys->hasPages())
                                    <div class="orders-pagination">
                                        {{ $orderHistorys->appends(['type' => 'orders'])->links() }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    <!-- /my-account-address -->
                    <div class="account-address collapse {{(request()->get('type')) == 'address' ? 'show' : ''}}" id="account-address" data-bs-target="#my-account-content" data-bs-parent="#my-account-content">
                        <div class="account-address">
                            <div class="text-center widget-inner-address">
                                <button type="button" class="tf-btn btn-fill radius-4 mb_20 btn-address" data-user-id="{{$user['id']}}" data-url="{{ route('client.userAddress.save') }}">
                                    <span class="text text-caption-1">Add a new address</span>
                                </button>
                                <div id="address-form">
                                    @php
                                        use App\Models\CustomerAddress;
                                    @endphp
                                    <form id="addressForm" onsubmit="return false;" method="POST" class="show-form-address wd-form-address">
                                        @csrf
                                        <input type="hidden" name="id" value="">
                                        <div class="cols mb_20">
                                            <fieldset class="">
                                                <input class="" type="text" placeholder="Name*" name="name" tabindex="2" value="">
                                                <div class="error text-danger text-start" id="error-name"></div>
                                            </fieldset>
                                        </div>
                                        <div class="cols mb_20">
                                            <fieldset class="">
                                                <input class="" type="email" placeholder="Username or email address*" name="email" tabindex="2" value="">
                                                <div class="error text-danger text-start" id="error-email"></div>
                                            </fieldset>
                                            <fieldset class="">
                                                <input class="" type="text" placeholder="Phone*" name="phone" tabindex="2" value="">
                                                <div class="error text-danger text-start" id="error-phone"></div>
                                            </fieldset>
                                        </div>
                                        <fieldset class="mb_20">
                                            <input class="" type="text" placeholder="Address" name="address" tabindex="2" value="">
                                            <div class="error text-danger text-start" id="error-address"></div>
                                        </fieldset>
                                        <fieldset class="mb_20">
                                            <input class="" type="text" placeholder="City" name="city" tabindex="2" value="">
                                            <div class="error text-danger text-start" id="error-city"></div>
                                        </fieldset>
                                        <fieldset class="mb_20">
                                            <input class="" type="text" placeholder="State" name="state" tabindex="2" value="">
                                            <div class="error text-danger text-start" id="error-state"></div>
                                        </fieldset>
                                        <fieldset class="mb_20">
                                            <input class="" type="number" placeholder="pincode" name="pincode" tabindex="2" value="">
                                            <div class="error text-danger text-start" id="error-pincode"></div>
                                        </fieldset>
                                        <fieldset class="mb_20 text-start">
                                            @foreach (CustomerAddress::TYPES as $key => $type)
                                                <input type="radio" name="type" id="{{$type}}" value="{{$type}}">
                                                <label for="{{$type}}">{{$key}}</label>
                                            @endforeach
                                            <div class="error text-danger text-start" id="error-type"></div>
                                        </fieldset>
                                        <div class="d-flex align-items-center justify-content-center gap-20">
                                            <button type="button" data-url="{{ route('client.userAddress.save') }}" class="tf-btn btn-fill radius-4" id="submitBtn"><span class="text">Save</span></button>
                                            <span class="tf-btn btn-fill radius-4 btn-hide-address"><span class="text">Cancel</span></span>
                                        </div>
                                    </form>
                                </div>
                                <div class="list-account-address">
                                    @foreach ($addresses as $address)
                                    <div class="account-address-item">
                                        <h6 class="mb_20">{{ $address['name']}}</h6>
                                        <p>{{$address['address']}}</p>
                                        <p>{{$address['city']}}</p>
                                        <p>{{$address['state']}}</p>
                                        <p>{{$address['pincode']}}</p>
                                        <p>{{$address['email']}}</p>
                                        <p class="mb_10">{{$address['phone']}}</p>
                                        <div class="d-flex gap-10 justify-content-center">
                                            <button class="tf-btn radius-4 btn-fill justify-content-center btn-edit-address" data-url="{{ route('client.userAddress.edit', ['id' => $address['id']]) }}">
                                                <span class="text">Edit</span>
                                            </button>
                                            <button class="tf-btn radius-4 btn-outline justify-content-center" id="btn-delete-address" data-id="{{ $address['id'] }}">
                                                <span class="text">Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /my-account -->
    
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        let addressSaveUrl = '{{route("client.userAddress.save")}}';

        // Delegated, and scoped to the avatar that was actually tapped.
        // main.js copies .wrap-sidebar-account into the mobile offcanvas as an
        // HTML string, so on phones this block exists twice: the copy carries
        // no handlers, and a direct bind would only ever reach the first (the
        // desktop one, which is display:none there). That is why changing the
        // photo worked on desktop and did nothing at all on a phone.
        $(document).on('click', '.avatar-edit-btn', function () {
            $(this).closest('.account-avatar').find('.avatar-input').trigger('click');
        });

        // Keep in step with ProfileController::MAX_AVATAR_KB.
        const MAX_AVATAR_MB = 5;
        const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

        $(document).on('change', '.avatar-input', function () {
            const input = this;
            const file = input.files[0];
            if (!file) return;

            // Selecting the same file twice otherwise fires no change event.
            const reset = () => { input.value = ''; };

            if (ALLOWED_AVATAR_TYPES.indexOf(file.type) === -1) {
                showSweetAlert('error', 'Please choose a JPG, PNG or WebP image.');
                reset();
                return;
            }

            // Check the size here rather than letting the server reject it. An
            // oversized body can exceed PHP's post_max_size, and PHP then drops
            // the whole request - including the _method=put field this route is
            // matched on - so the browser gets a bare 405 with nothing to show.
            if (file.size > MAX_AVATAR_MB * 1024 * 1024) {
                showSweetAlert('error', 'That image is ' + (file.size / 1048576).toFixed(1) + ' MB. Please choose one under ' + MAX_AVATAR_MB + ' MB.');
                reset();
                return;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('_method', 'put');

            $.ajax({
                url: "{{route('client.profileImage.update')}}",
                type: 'post',
                data: formData,
                dataType : 'json',
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: async function (response) {
                    if (response.reload) {
                        await showSweetAlert('success', response.success)
                        location.reload();
                    }
                },
                error: function (xhr) {
                    reset();
                    // Every failure has to say something: only 422 was handled
                    // before, so 405/413/419/500 all failed in total silence.
                    let message = 'Could not update your photo. Please try again.';
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON?.errors?.image;
                        message = Array.isArray(errors) ? errors.join(' ') : (errors || message);
                    } else if (xhr.status === 413 || xhr.status === 405) {
                        message = 'That image is too large for the server to accept. Please choose a smaller one.';
                    } else if (xhr.status === 419) {
                        message = 'Your session expired. Please refresh the page and try again.';
                    }
                    showSweetAlert('error', message);
                }
            });
        });

        $(document).on('click', '.my-account-nav-item', function(e) {
            e.preventDefault();
            $(document).find('.my-account-nav-item').removeClass('active');
            const button = $(this);
            button.addClass('active')
            const collapse = $(this).attr('href');
            $(`${collapse}`).collapse('show');
        });

        $(document).on('click', '.btn-edit-address', function () {
            let url = $(this).data('url');

            $.ajax({
                url: url,
                type: 'get',
                dataType: 'json',
                success: function (response) {
                    console.log('Success:', response);
                    $('.show-form-address').show();
                    $('input[name="id"]').val(response.address.id || '');
                    $('input[name="name"]').val(response.address.name || '');
                    $('input[name="email"]').val(response.address.email || '');
                    $('input[name="phone"]').val(response.address.phone || '');
                    $('input[name="address"]').val(response.address.address || '');
                    $('input[name="city"]').val(response.address.city || '');
                    $('input[name="state"]').val(response.address.state || '');
                    $(`input[name="type"][value="${response.address.type}"]`).prop('checked', true);
                    $('input[name="pincode"]').val(response.address.pincode || '');
                },
                error: function (xhr) {
                    console.log('Error:', xhr);
                }
            });
        });

        $('#submitBtn').on('click', function () {
            let form = $(this).closest('form');
            var formData = form.serialize();
            let id = form.find('input[name="id"]').val();
            var actionUrl = addressSaveUrl;
            let method = 'post';
            if(id) {
                actionUrl = `{{route('client.userAddress.update', 'id')}}`.replace('id', id);
                method = 'put';
            }
            
            $.ajax({
                url: actionUrl,
                type: method,
                data: formData,
                success: function (response) {
                    if (response.success) {
                        let currentUrl = window.location.href;
                        let queryParam = '?type=address';
                        if (!currentUrl.includes(queryParam)) {
                            currentUrl += `${queryParam}`;
                        }
                        window.location.href = currentUrl;
                    } else {
                        console.log('Error: ' + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    let errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach( key => {
                        form.find(`#error-${key}`).text(errors[key][0]);
                    });
                }
            });
        });

        // Cancel an order that hasn't been confirmed yet. Posts a real form
        // rather than AJAX so the redirect + flash message path is the same one
        // every other order action already uses.
        $(document).on('click', '.order-card__cancel', function () {
            var orderNo = $(this).data('order-no');

            Swal.fire({
                title: 'Cancel order #' + orderNo + '?',
                text: 'This cannot be undone. Anything you paid for it will be refunded.',
                icon: 'warning',
                input: 'text',
                inputLabel: 'Reason (optional)',
                inputPlaceholder: 'e.g. ordered by mistake',
                inputAttributes: { maxlength: 480 },
                showCancelButton: true,
                confirmButtonColor: '#a83b32',
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'Keep my order'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                // The controller redirects back() to the referring URL; point it
                // at the orders tab so the customer lands on the cancelled order.
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('type', 'orders');
                    url.hash = '';
                    history.replaceState(null, '', url);
                } catch (e) { /* very old browser: lands on the profile tab */ }

                var $form = $('<form>', {
                    method: 'POST',
                    action: '{{ url('/orders') }}/' + encodeURIComponent(orderNo) + '/cancel'
                });
                $form.append($('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }));
                $form.append($('<input>', { type: 'hidden', name: 'reason', value: result.value || '' }));
                $form.appendTo('body').trigger('submit');
            });
        });

        $(document).on('click', '#btn-delete-address', function () {
            let id = $(this).data('id');

            Swal.fire({
                title: "Are you sure?",
                text: "You will not be able to recover this address!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/${id}/user-address-delete`,
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (response) {
                            if (response.success) {
                                let currentUrl = window.location.href;
                                let queryParam = '?type=address';
                                Swal.fire("Deleted!", response.message, "success").then(() => {
                                    if (!currentUrl.includes(queryParam)) {
                                        currentUrl += `${queryParam}`;
                                    }
                                    window.location.href = currentUrl;
                                });
                            } else {
                                Swal.fire("Error", response.message, "error");
                            }
                        },
                        error: function (xhr) {
                            console.error('Error:', xhr);
                            Swal.fire("Error", "An error occurred while trying to delete the address.", "error");
                        }
                    });
                } 
            });
        });
    });   
</script>


@endsection

