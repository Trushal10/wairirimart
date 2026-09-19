{{--
    Handler for any `.js-cancel-order` button (data-order-no, data-action):
    a SweetAlert asking for an optional reason, then a real POST so the
    redirect + flash path is the one every other order action uses.
    Shared by the order confirmation and tracking pages.
--}}
<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.js-cancel-order') : null;
        if (!btn) return;
        e.preventDefault();

        var orderNo = btn.getAttribute('data-order-no');
        var action = btn.getAttribute('data-action');

        var submit = function (reason) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            form.appendChild(token);
            var why = document.createElement('input');
            why.type = 'hidden';
            why.name = 'reason';
            why.value = reason || '';
            form.appendChild(why);
            document.body.appendChild(form);
            form.submit();
        };

        if (typeof Swal === 'undefined') {
            // SweetAlert is loaded with defer; if a shopper clicks before it
            // lands, fall back rather than silently doing nothing.
            if (window.confirm('Cancel order #' + orderNo + '? This cannot be undone.')) submit('');
            return;
        }

        Swal.fire({
            title: 'Cancel order #' + orderNo + '?',
            text: 'This cannot be undone. Anything you paid for it will be refunded.',
            icon: 'warning',
            input: 'text',
            inputLabel: 'Reason (optional)',
            inputPlaceholder: 'e.g. ordered by mistake',
            inputAttributes: { maxlength: 380 },
            showCancelButton: true,
            confirmButtonColor: '#a83b32',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'Keep my order'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            btn.disabled = true;
            submit(result.value);
        });
    });
})();
</script>
