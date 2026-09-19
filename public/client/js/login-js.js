
// The auth endpoints answer a failure with a status this file used to ignore.
// LoginUserController returns 401 for bad credentials and 403 for a blocked
// account; the routes are throttle:5,1 so a sixth attempt returns 429. Only
// 422 was ever rendered — every other failure fell through to console.log and
// the form just sat there, with the reason visible nowhere but devtools.
//
// 422 itself is two different shapes: a FormRequest sends {errors: {field: []}},
// but verifyOtp/phoneOtpVerify send a bare {error, message}. Reading .errors
// off the second one threw a TypeError, which swallowed those failures too.
function reportAuthError(xhr, form) {
    var body = xhr.responseJSON || {};

    if (xhr.status === 422 && body.errors) {
        Object.keys(body.errors).forEach(function (key) {
            form.find('#error-' + key).text([].concat(body.errors[key]).join(' '));
        });
        return;
    }

    var message = body.message;
    if (xhr.status === 429) {
        // Laravel's throttle message is "Too Many Attempts." — tell them how
        // long, since the whole point is that they should stop retrying.
        var wait = parseInt(xhr.getResponseHeader('Retry-After'), 10);
        message = 'Too many attempts. Please wait '
            + (wait > 0 ? wait + ' seconds' : 'a minute')
            + ' and try again.';
    } else if (!xhr.status) {
        message = 'Could not reach the server. Check your connection and try again.';
    } else if (!message) {
        message = 'Something went wrong. Please try again.';
    }

    showFormError(form, message);
}

// A form-level slot, because "Invalid credentials." belongs to the pair of
// fields rather than to either one. Deliberately not a SweetAlert: a failed
// sign-in is not worth a modal the shopper has to dismiss before they can
// reach the field they need to correct. Carries the shared .error class so the
// reset at the top of each handler clears it like every other message.
// .text(), never .html() — the string comes off the wire.
function showFormError(form, message) {
    var box = form.find('.auth-form-error');
    if (!box.length) {
        box = $('<div class="auth-form-error error" role="alert" aria-live="polite"></div>');
        form.prepend(box);
    }
    box.text(message);
}

$(document).ready(function() {
    $(document).on('click', '.login-form-button', function(e) {
        e.preventDefault();
        let form = $(this).closest('form');
        let action = form.attr('action');
        let data = form.serialize();
        let button = $(this);
        $(document).find('.error').html('');

        // These routes are throttle:5,1 — an unguarded button turns an
        // impatient double-click into two of those five attempts.
        if (button.prop('disabled')) return;
        button.prop('disabled', true);

        $.ajax({
            url: action,
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: data,
            dataType : 'json',
            success: function(response) {
                if (response.success) {
                    const currentUrl = window.location.href;
                    const lastSegment = currentUrl.split('/').pop();
                    if (lastSegment.toLowerCase() === 'login') {
                        window.location.href = '/';
                        showSweetAlert('success', response.message);
                    } else {
                        window.location.reload();
                    }
                } else if (response.token) {
                    // #co-login-form is the checkout panel's copy — without it
                    // the OTP box appeared *under* a still-live login form.
                    $('#view-login, #view-register, #co-login-form').hide();
                    $('#otp-form').removeClass('d-none');
                    $('#otp-form').find('input[name="token"]').val(response.token);
                    if (response.identifier) $('#otp-target').text(response.identifier);
                    else if (response.channel === 'phone') $('#otp-target').text('your phone');
                    else $('#otp-target').text('your email');
                    showSweetAlert('info', response.message);
                } else if(response.info) {
                    showSweetAlert('info', response.message);
                }
            },
            error: function(xhr) {
                reportAuthError(xhr, form);
            },
            complete: function() {
                button.prop('disabled', false);
            },
        });
    })

    $(document).on('click', '.otp-form-button', function(e) {
        e.preventDefault();
        let form = $(this).closest('form');
        let action = form.attr('action');
        let data = form.serialize();
        let button = $(this);
        $(document).find('.error').html('');

        // These routes are throttle:5,1 — an unguarded button turns an
        // impatient double-click into two of those five attempts.
        if (button.prop('disabled')) return;
        button.prop('disabled', true);

        $.ajax({
            url: action,
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: data,
            dataType : 'json',
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else if (response.error) {
                    // verifyOtp answers 200 with {error:true,message} rather
                    // than a 4xx, so this never reaches reportAuthError.
                    showFormError(form, response.message);
                }
            },
            error: function(xhr) {
                reportAuthError(xhr, form);
            },
            complete: function() {
                button.prop('disabled', false);
            },
        });
    })

    $(document).on('click', '.register-form-button', function(e) {
        e.preventDefault();
        let form = $(this).closest('form');
        let action = form.attr('action');
        let data = form.serialize();
        let button = $(this);
        $(document).find('.error').html('');

        // These routes are throttle:5,1 — an unguarded button turns an
        // impatient double-click into two of those five attempts.
        if (button.prop('disabled')) return;
        button.prop('disabled', true);

        $.ajax({
            url: action,
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: data,
            dataType : 'json',
            success: function(response) {
                if (response.token)  {
                    $('#view-login, #view-register, #register-user').hide();
                    $('#otp-form').find('input[name="token"]').val(response.token);
                    $('#otp-form').removeClass('d-none');
                    if (response.identifier) $('#otp-target').text(response.identifier);
                    else if (response.channel === 'phone') $('#otp-target').text('your phone');
                    else $('#otp-target').text('your email');
                    showSweetAlert('info', response.message);
                } else if (response.error) {
                    // This endpoint puts the text in .error, not .message.
                    showFormError(form, response.error);
                }
            },
            error: function(xhr) {
                reportAuthError(xhr, form);
            },
            complete: function() {
                button.prop('disabled', false);
            },
        });
    })
});

function displayEditAddressButton() {
    let checkedInput = $('input[name="address"]:checked');
    $(document).find('.edit-address').addClass('d-none');
    checkedInput.siblings('.edit-address').removeClass('d-none');
    
    if(checkedInput.val() == '') {
        let form = $('#address-form').find('form')[0];
        form.reset();
        $(form).find('input[name="id"]').val('');
        $('#address-form').addClass('show');
    } else {
        $('#address-form').removeClass('show');
    }
}