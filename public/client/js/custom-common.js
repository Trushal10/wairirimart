function showSweetAlert(type, message, opts) {
    if (!message) return;
    opts = opts || {};
    var isToastType = type === 'success' || type === 'info';
    var toast = opts.toast != null ? !!opts.toast : isToastType;

    var base = {
        text: message,
        icon: type,
        buttonsStyling: false,
        showClass: { popup: 'swal-fade-in' },
        hideClass: { popup: 'swal-fade-out' },
        customClass: {
            popup: 'swal-modern' + (toast ? ' swal-modern-toast' : ''),
            title: 'swal-modern-title',
            htmlContainer: 'swal-modern-text',
            icon: 'swal-modern-icon swal-modern-icon-' + type,
            confirmButton: 'swal-modern-confirm swal-modern-confirm-' + type,
            cancelButton: 'swal-modern-cancel',
            actions: 'swal-modern-actions',
        },
    };

    if (toast) {
        base.toast = true;
        base.position = opts.position || 'top-end';
        base.showConfirmButton = false;
        base.timer = opts.timer || 2800;
        base.timerProgressBar = true;
        // Explicitly kill the backdrop for toast mode. Without this the page
        // gets a translucent + blurred overlay because the container inherits
        // the modal backdrop rules.
        base.backdrop = false;
        base.allowOutsideClick = true;
        base.allowEscapeKey = true;
        base.iconColor =
            type === 'success' ? '#12b76a' :
            type === 'info'    ? '#465fff' :
            type === 'warning' ? '#f79009' :
            type === 'error'   ? '#f04438' : undefined;
    } else {
        base.confirmButtonText = opts.confirmButtonText || 'OK';
        if (opts.showCancelButton) {
            base.showCancelButton = true;
            base.cancelButtonText = opts.cancelButtonText || 'Cancel';
        }
    }

    if (opts.title) base.title = opts.title;
    if (opts.onConfirm) base.preConfirm = opts.onConfirm;

    return Swal.fire(base);
}

$(document).ready(function() {
    $(document).off('click', '.add-to-cart').on('click', '.add-to-cart', function(e) {
        e.preventDefault();

        const button = $(this);
        if (button.is('[disabled]') || button.prop('disabled')) return;

        // Scope: prefer the button's own form/modal context so quick-add and
        // product-detail don't fight over the first .quantity-product on page.
        const scope = button.closest('.tf-product-info-choose-option, .quick-add-modal, .tf-product-info-list').first();
        const $scope = scope.length ? scope : $(document);

        const url        = button.data('url');
        const productId  = button.data('product-id');
        // attr(), NOT data(): the variant picker rewrites this attribute with
        // setAttribute on every option change, and jQuery's .data() cache is
        // populated on first read and never refreshed from the DOM — so .data()
        // would keep posting whichever variant was selected on the first click.
        const variantId  = button.attr('data-variant-id') || null;

        // Legacy (pre-variant) products still expose colour/size as raw radios.
        // Variant products carry the whole selection in variantId and must not
        // also send one option group's value as a bogus "size".
        const color = variantId ? null : ($scope.find('input.color-product:checked').val() ?? null);
        const size  = variantId ? null : ($scope.find('input.size-product:checked').val() ?? null);

        const qtyRaw     = parseInt($scope.find('.quantity-product').first().val(), 10);
        const quantity   = Number.isFinite(qtyRaw) && qtyRaw > 0 ? qtyRaw : 1;
        const isQuickAdd = button.closest('.quick-add-modal').length > 0;

        const data = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            productId: productId,
            variantId: variantId,
            quantity: quantity,
            color: color,
            size: size,
        };

        // Custom name fields (personalisation) — only if the form has any.
        const names = {};
        let i = 1;
        $scope.find('input[name^="names["]').each(function () {
            names['name_' + i] = $(this).val();
            i++;
        });
        if (Object.keys(names).length > 0) data.names = names;

        button.attr('disabled', 'disabled');

        $.ajax({
            url: url,
            method: 'post',
            dataType: 'json',
            data: data,
            success: function (response) {
                button.removeAttr('disabled');
                if (!response || !response.success) return;

                if (typeof updateCartModal === 'function') updateCartModal(response.cart);
                if (typeof updateCartCount === 'function') updateCartCount(response.cart);

                if (isQuickAdd) {
                    // Close the quick-add modal before opening the cart drawer so
                    // Bootstrap's backdrop stacking doesn't leave a stuck overlay.
                    const $qa = $('#quickAdd');
                    if ($qa.length) {
                        $qa.one('hidden.bs.modal', function () {
                            $('#shoppingCart').modal('show');
                        });
                        $qa.modal('hide');
                    } else {
                        $('#shoppingCart').modal('show');
                    }
                } else {
                    $('#shoppingCart').modal('show');
                }
            },
            error: function (xhr) {
                button.removeAttr('disabled');
                let message = 'Something went wrong. Please try again.';
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) message = xhr.responseJSON.message;
                    else if (xhr.responseJSON.error && typeof xhr.responseJSON.error === 'string') message = xhr.responseJSON.error;
                }
                showSweetAlert('error', message);
            }
        });
    });


    $(document).off('click', '.get-cart-details').on('click', '.get-cart-details', function(e) {
        e.preventDefault();
        let url = $(this).data('url');
        $.ajax({
            url: url,
            method: 'get',
            dataType: 'json',
            success: function(response) {
                updateCartModal(response.cart);
                updateCartCount(response.cart)
                if (response.count > 0) {
                    $('.count-box').text(response.count).show();
                } else {
                    $('.count-box').hide();
                }
                $('#shoppingCart').modal('show');
            },
            error: function(error) {
                console.log(error);
            }
        });
    });

    $(document).off('click', '.quick-add').on('click', '.quick-add', function (e) {
        e.preventDefault();
        const button = $(this);
        const url = button.data('url');
        const productId = button.data('product-id');
        button.attr('disabled', 'disabled');
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                productId: productId,
            },
            success: function (result) {
                $('#quick-product-info').html(result);
                $('#quickAdd').modal('show');
                button.removeAttr('disabled');
            },
            error: function (xhr) {
                button.removeAttr('disabled');
                let message = 'Could not load product. Please try again.';
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) message = xhr.responseJSON.message;
                    else if (xhr.responseJSON.errors) {
                        const first = Object.values(xhr.responseJSON.errors)[0];
                        message = Array.isArray(first) ? first[0] : String(first);
                    }
                }
                showSweetAlert('error', message);
            }
        });
    });

    $(document).off('click', '.remove-cart-item').on('click', '.remove-cart-item', function(e) {
        e.preventDefault();
        let element = $(this);
        let productId = element.attr('data-product-id');
        let url = element.attr('data-url');
        // Variant products keep one cart line per variant, all sharing a product
        // id — without the key the server cannot tell which line to drop and
        // the removal silently 404s.
        let variantId = element.attr('data-variant-id') || null;
        let cartKey = element.attr('data-cart-key') || null;

        $.ajax({
            url: url,
            type: 'post',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                productId: productId,
                variantId: variantId,
                cartKey: cartKey,
            },
            success: function(response) {
                if(response.success) {
                    element.closest('.tf-mini-cart-item').remove();
                    showSweetAlert('success', response.message);
                    updateCartModal(response.cart);
                    updateCartCount(response.cart);
                }
            },
            error: function(xhr) {
                let message = 'Could not remove item.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                showSweetAlert('error', message);
            },
        });
    })
});

function updateCartModal(cart) {
    let subTotal = 0;
    let modal = $('#shoppingCart');
    let cartBody = modal.find('.tf-mini-cart-items');
    cartBody.html('');
    cartBody.empty();
    
    Object.values(cart).forEach(item => {
        subTotal += (parseFloat(item['quantity']) * parseFloat(item['price']));

        let row = $($('#cart-row').html().replace(/{(\w+)}/g, (_, key) => item[key] ?? ''));
        if(!item['image']) {
            row.find('img').attr('src', '/client/images/home/defaultImage.webp').attr('data-src', '/client/images/home/defaultImage.webp');
        }
        if(Object.keys(item['names']).length > 0) {
            Object.keys(item['names']).forEach((key, index) => {
                row.find('.names').append(`<p>Name ${index+1}: ${item['names'][key]}</p>`);
            })
        }
        cartBody.append(row);
    });

    modal.find('.tf-totals-total-value').text(`₹${subTotal.toFixed(2)}`);
}

function updateCartCount(carts) {
    $('.count-box').text(carts.length)
}