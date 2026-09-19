/*
 * Shared variant picker.
 *
 * Used by the product detail page and the quick-add modal, which previously
 * carried two near-identical copies that had already drifted apart (the modal
 * never disabled out-of-stock options, and neither narrowed availability as the
 * shopper picked).
 *
 * Contract — the root element must contain:
 *   .tf-product-info-variant-groups[data-variants]  the JSON variant rows
 *   .variant-picker-item[data-option-name]          one per option group
 *   input.variant-option-input                      one radio per value
 *   [data-role="variant-price|variant-compare|variant-discount|variant-stock
 *              |variant-sku|variant-cta-label|variant-image|selected-label"]
 *   .add-to-cart, .quantity-product
 * Every data-role is optional; the picker updates whatever it finds, and it
 * updates EVERY match rather than the first — the detail page shows the price
 * and the add-to-cart button in two places (the buy box and the mobile sticky
 * bar) and both describe the same variant.
 *
 * Availability: a value is selectable when at least one in-stock variant matches
 * that value TOGETHER WITH the current selection in the other groups. So picking
 * "Red" immediately greys the sizes that are sold out in red, instead of the
 * static "sold out everywhere" pass the server renders for the first paint.
 */
(function (window, document) {
    'use strict';

    function formatRupees(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function initVariantPicker(root) {
        if (!root) return null;

        var container = root.querySelector('.tf-product-info-variant-groups');
        if (!container || container.dataset.variantPickerBound === '1') return null;

        var variants;
        try {
            variants = JSON.parse(container.dataset.variants || '[]');
        } catch (e) {
            console.warn('Variant payload malformed', e);
            return null;
        }
        if (!Array.isArray(variants) || !variants.length) return null;

        container.dataset.variantPickerBound = '1';

        var items = Array.prototype.slice.call(container.querySelectorAll('.variant-picker-item'));
        var groupNames = items
            .map(function (item) { return item.dataset.optionName; })
            .filter(Boolean);

        // Remember the product's own hero image so a variant with no picture of
        // its own restores it instead of keeping the previous variant's.
        var mainImg = root.querySelector('.tf-product-media-main img.tf-image-zoom');
        var baseImage = mainImg ? {
            src: mainImg.getAttribute('src'),
            dataSrc: mainImg.getAttribute('data-src'),
            zoom: mainImg.getAttribute('data-zoom'),
            href: mainImg.closest('a.item') ? mainImg.closest('a.item').getAttribute('href') : null,
        } : null;

        function roles(name) {
            return Array.prototype.slice.call(root.querySelectorAll('[data-role="' + name + '"]'));
        }
        function eachRole(name, fn) { roles(name).forEach(fn); }

        function currentSelection() {
            var selected = {};
            items.forEach(function (item) {
                var name = item.dataset.optionName;
                var checked = item.querySelector('input.variant-option-input:checked');
                if (name && checked) selected[name] = checked.value;
            });
            return selected;
        }

        // Exact match on every declared group. Guarding on the group count too
        // stops a 1-option variant from matching a 2-option selection.
        function matchingVariant(selection) {
            var keys = Object.keys(selection);
            return variants.find(function (v) {
                var opts = v.options || {};
                return keys.length === Object.keys(opts).length
                    && keys.every(function (k) { return opts[k] === selection[k]; });
            });
        }

        // Is there an in-stock variant with group=value, holding every OTHER
        // group at its current selection?
        function isCombinationAvailable(selection, name, value) {
            return variants.some(function (v) {
                var opts = v.options || {};
                if (opts[name] !== value) return false;
                if ((v.stock || 0) <= 0) return false;
                return groupNames.every(function (other) {
                    if (other === name) return true;
                    var chosen = selection[other];
                    return !chosen || opts[other] === chosen;
                });
            });
        }

        function refreshAvailability(selection) {
            items.forEach(function (item) {
                var name = item.dataset.optionName;
                item.querySelectorAll('input.variant-option-input').forEach(function (input) {
                    var label = item.querySelector('label[for="' + input.id + '"]');
                    var available = isCombinationAvailable(selection, name, input.value);

                    // Never disable the shopper's own current pick — that would
                    // trap them with no way back once a combination sells out.
                    if (input.checked) available = true;

                    input.disabled = !available;
                    if (!label) return;
                    label.classList.toggle('disabled', !available);
                    label.classList.toggle('variant-oos', !available);
                    if (available) {
                        label.removeAttribute('aria-disabled');
                        label.removeAttribute('title');
                    } else {
                        label.setAttribute('aria-disabled', 'true');
                        label.setAttribute('title', 'Unavailable with the current selection');
                    }
                    label.classList.toggle('active', input.checked);
                });

                var checked = item.querySelector('input.variant-option-input:checked');
                if (checked) {
                    item.querySelectorAll('[data-role="selected-label"]').forEach(function (labelEl) {
                        labelEl.textContent = checked.dataset.valueLabel || checked.value;
                    });
                }
            });
        }

        function setCta(enabled, text, variantId) {
            root.querySelectorAll('.add-to-cart').forEach(function (btn) {
                btn.setAttribute('data-variant-id', variantId == null ? '' : String(variantId));
                if (enabled) btn.removeAttribute('disabled');
                else btn.setAttribute('disabled', 'disabled');
            });
            eachRole('variant-cta-label', function (el) { el.textContent = text; });
        }

        function setImage(url) {
            eachRole('variant-image', function (el) { el.setAttribute('src', url || (baseImage && baseImage.src) || ''); });
            if (!mainImg) return;

            var target = url || (baseImage ? baseImage.src : null);
            if (!target) return;

            // src for what is painted, data-src so lazysizes does not restore the
            // previous image when it processes the element, data-zoom for the
            // drift hover-zoom pane.
            mainImg.setAttribute('src', target);
            mainImg.setAttribute('data-src', url || (baseImage ? baseImage.dataSrc : target) || target);
            mainImg.setAttribute('data-zoom', url || (baseImage ? baseImage.zoom : target) || target);

            // The slide's anchor is what PhotoSwipe opens. Without this the
            // lightbox kept showing the previously selected variant's photo
            // while the page showed the new one.
            var lightbox = mainImg.closest('a.item');
            if (lightbox) lightbox.setAttribute('href', url || (baseImage ? baseImage.href : target) || target);
        }

        function apply(variant) {
            var qtyInput = root.querySelector('.quantity-product');

            if (!variant) {
                eachRole('variant-stock', function (el) { el.textContent = 'Combination unavailable'; });
                setCta(false, 'Unavailable', null);
                return;
            }

            eachRole('variant-price', function (el) { el.textContent = formatRupees(variant.price); });

            var onSale = variant.compare_price && variant.compare_price > variant.price;
            eachRole('variant-compare', function (el) {
                el.style.display = onSale ? '' : 'none';
                if (onSale) el.textContent = formatRupees(variant.compare_price);
            });
            eachRole('variant-discount', function (el) {
                el.style.display = onSale ? '' : 'none';
                if (onSale) {
                    var pct = ((variant.compare_price - variant.price) / variant.compare_price) * 100;
                    el.textContent = Math.round(pct) + '% Off';
                }
            });

            if (variant.sku) {
                eachRole('variant-sku', function (el) { el.textContent = variant.sku; });
            }

            eachRole('variant-stock', function (el) {
                el.textContent = variant.stock > 0 ? 'In stock (' + variant.stock + ')' : 'Out of stock';
            });

            if (qtyInput) {
                // Both, so the +/- buttons in main.js clamp to the same ceiling.
                var cap = Math.max(1, variant.stock);
                qtyInput.max = cap;
                qtyInput.dataset.max = cap;
                var current = parseInt(qtyInput.value, 10);
                if (!Number.isFinite(current) || current < 1) {
                    qtyInput.value = 1;
                } else if (variant.stock > 0 && current > variant.stock) {
                    qtyInput.value = variant.stock;
                }

                // The stepper's availability moves with the variant: a size with
                // one left has nothing to choose, so the whole block goes away
                // rather than sitting there refusing to move.
                var block = qtyInput.closest('[data-role="quantity-block"]');
                if (block) block.setAttribute('data-stock', String(variant.stock));
                if (typeof window.syncQuantityUi === 'function') {
                    window.syncQuantityUi(block || root);
                }
            }

            setCta(variant.stock > 0, variant.stock > 0 ? 'Add to cart' : 'Out of stock', variant.id);
            setImage(variant.image_url || null);
        }

        function onChange() {
            var selection = currentSelection();
            refreshAvailability(selection);
            apply(matchingVariant(selection));
        }

        container.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('variant-option-input')) {
                onChange();
            }
        });

        // Clicking a disabled pill must not toggle its radio.
        container.addEventListener('click', function (e) {
            var lbl = e.target.closest ? e.target.closest('label.color-btn, label.size-btn') : null;
            if (lbl && (lbl.classList.contains('disabled')
                || lbl.classList.contains('variant-oos')
                || lbl.getAttribute('aria-disabled') === 'true')) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        onChange();
        return { refresh: onChange };
    }

    window.initVariantPicker = initVariantPicker;

    // The detail page boots against the whole document so the buy box and the
    // mobile sticky bar — which live in different subtrees — both follow the
    // selection. The quick-add modal injects its markup later and calls
    // initVariantPicker itself against the modal root.
    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelector('.tf-product-info-variant-groups')) {
            initVariantPicker(document.body);
        }
    });
})(window, document);
