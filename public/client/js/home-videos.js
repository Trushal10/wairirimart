/**
 * "What Our Makers Say" video reel — see resources/views/client/partials/home-videos.blade.php.
 *
 * A centred coverflow showing three cards: the active one in the middle at full
 * size and one either side, scaled down by --hv-side-scale. The active clip
 * plays, muted unless the shopper turns sound on.
 *
 * Auto-scroll: every data-interval ms (default 6000) the next card slides into
 * the centre, wrapping to the first after the last; a clip that ends sooner
 * moves the reel on straight away. The timer holds while the mouse is over the
 * reel, and for the rest of a clip once the shopper turns sound on or clicks
 * the centre card to watch it. Clicking a side card, arrow keys and a
 * horizontal swipe also move the reel.
 *
 * Playback and auto-scroll only run while the reel is on screen and the tab is
 * visible. Video URLs are held in data-src until the section is within ~one
 * screen, so nothing is downloaded for shoppers who never scroll this far.
 */
(function () {
    'use strict';

    var LEAVE_MS = 420;

    function initReel(section) {
        var stage = section.querySelector('[data-hv-stage]');
        var cards = Array.prototype.slice.call(section.querySelectorAll('[data-hv-card]'));
        if (!stage || !cards.length) return;

        var n = cards.length;
        var interval = parseInt(section.dataset.interval, 10) || 6000;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var active = -1;
        var offsets = cards.map(function () { return null; });
        var inView = false;
        var loaded = false;
        var muted = true;
        // Reduced motion: nothing starts or scrolls on its own, but once the
        // shopper presses play the clips behave normally.
        var userStarted = !reduceMotion;
        var hovering = false;
        var watching = false; // shopper is deliberately watching the centre clip
        var timer = null;

        function videoOf(i) { return cards[i].querySelector('video'); }

        function loadSources() {
            if (loaded) return;
            loaded = true;
            cards.forEach(function (card, i) {
                var v = videoOf(i);
                if (v.dataset.src && !v.getAttribute('src')) {
                    v.setAttribute('src', v.dataset.src);
                    // Enough to paint the first frame on cards without a poster.
                    v.preload = 'metadata';
                }
            });
        }

        // ---- layout ----

        /** Signed distance of card i from the active card, wrapped around. */
        function offsetOf(i) {
            var d = ((i - active) % n + n) % n;
            return d > n / 2 ? d - n : d;
        }

        function metrics() {
            var cs = getComputedStyle(stage);
            return {
                w: cards[0].offsetWidth,
                gap: parseFloat(cs.getPropertyValue('--hv-gap')) || 24,
                s: parseFloat(cs.getPropertyValue('--hv-side-scale')) || 0.74
            };
        }

        function transformFor(d, m) {
            var ad = Math.abs(d);
            var x = 0;
            if (d !== 0) {
                x = (d > 0 ? 1 : -1) * (m.w / 2 + m.gap + (m.w * m.s) / 2 + (ad - 1) * (m.w * m.s + m.gap));
            }
            return 'translate(-50%, -50%) translateX(' + x.toFixed(1) + 'px) scale(' + (d === 0 ? 1 : m.s) + ')';
        }

        function layout() {
            var m = metrics();
            cards.forEach(function (card, i) {
                var d = offsetOf(i);
                var prev = offsets[i];
                offsets[i] = d;
                card.style.zIndex = String(10 - Math.abs(d));
                // Three cards on screen: the active one and one either side.
                card.classList.toggle('is-far', Math.abs(d) > 1);

                clearTimeout(card._hvTimer);

                // A card wrapping from one end to the other would fly across
                // the reel. Instead it carries on outward while fading, then
                // jumps to its new slot unseen and fades back in there.
                if (prev !== null && Math.abs(prev - d) > 1 && !reduceMotion) {
                    card.classList.add('is-leaving');
                    card.style.transform = transformFor(prev + (prev > 0 ? 1 : -1), m);
                    card._hvTimer = setTimeout(function () {
                        card.classList.add('hv-no-anim');
                        card.style.transform = transformFor(offsets[i], metrics());
                        void card.offsetWidth;
                        card.classList.remove('hv-no-anim');
                        card.classList.remove('is-leaving');
                    }, LEAVE_MS);
                    return;
                }

                card.classList.remove('is-leaving');
                card.style.transform = transformFor(d, m);
            });
        }

        // ---- auto-scroll ----

        function stopTimer() {
            clearTimeout(timer);
            timer = null;
        }

        function startTimer() {
            stopTimer();
            if (n < 2 || reduceMotion || !inView || document.hidden || hovering || watching) return;
            timer = setTimeout(function () { setActive(active + 1); }, interval);
        }

        // ---- playback ----

        function playActive() {
            if (active < 0 || !inView || document.hidden || !userStarted) return;
            loadSources();
            var v = videoOf(active);
            v.muted = muted;
            var p = v.play();
            if (p && typeof p.catch === 'function') {
                p.catch(function () {
                    // Autoplay refused (e.g. data saver): leave the play button up.
                    cards[active].classList.remove('is-playing');
                });
            }
        }

        function setActive(i, opts) {
            opts = opts || {};
            i = ((i % n) + n) % n;
            var changed = i !== active;

            if (changed && active >= 0) {
                videoOf(active).pause();
                cards[active].classList.remove('is-active', 'is-playing');
            }

            active = i;
            cards.forEach(function (card, idx) {
                card.setAttribute('aria-current', idx === i ? 'true' : 'false');
                if (idx !== i && !videoOf(idx).paused) videoOf(idx).pause();
            });
            cards[i].classList.add('is-active');

            if (changed) {
                // With sound on, the shopper has chosen to listen: let each
                // clip finish instead of cutting it off on the timer.
                watching = !muted;
                try { videoOf(i).currentTime = 0; } catch (e) { /* not loaded yet */ }
                layout();
            }
            if (opts.play !== false) playActive();
            startTimer();
        }

        function go(step) {
            userStarted = true;
            setActive(active + step);
        }

        // ---- per-card events ----
        cards.forEach(function (card, i) {
            var v = videoOf(i);

            v.addEventListener('playing', function () { if (i === active) card.classList.add('is-playing'); });
            v.addEventListener('pause', function () { card.classList.remove('is-playing'); });
            v.addEventListener('ended', function () {
                if (i !== active) return;
                if (n === 1) {
                    v.currentTime = 0;
                    playActive();
                } else {
                    setActive(active + 1);
                }
            });
            // A broken file should not stall the reel.
            v.addEventListener('error', function () {
                if (i === active && n > 1 && inView) setActive(active + 1);
            });

            card.addEventListener('click', function (e) {
                if (swiped || e.target.closest('a, [data-hv-sound]')) return;
                userStarted = true;
                if (i !== active) {
                    setActive(i);
                } else if (v.paused) {
                    watching = true;
                    stopTimer();
                    playActive();
                } else {
                    // Paused by hand: hold here until the shopper moves on.
                    watching = true;
                    stopTimer();
                    v.pause();
                }
            });
        });

        // ---- sound ----
        Array.prototype.forEach.call(section.querySelectorAll('[data-hv-sound]'), function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                muted = !muted;
                section.classList.toggle('is-unmuted', !muted);
                Array.prototype.forEach.call(section.querySelectorAll('[data-hv-sound]'), function (b) {
                    b.setAttribute('aria-pressed', muted ? 'false' : 'true');
                    b.setAttribute('aria-label', muted ? 'Turn sound on' : 'Turn sound off');
                });
                if (active >= 0) videoOf(active).muted = muted;
                watching = !muted;
                startTimer();
            });
        });

        stage.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
        });

        // Hold the auto-scroll while a mouse is over the reel (not on touch,
        // where there is no "leave" to resume it).
        stage.addEventListener('pointerenter', function (e) {
            if (e.pointerType !== 'mouse') return;
            hovering = true;
            stopTimer();
        });
        stage.addEventListener('pointerleave', function (e) {
            if (e.pointerType !== 'mouse') return;
            hovering = false;
            startTimer();
        });

        // ---- swipe ----
        var startX = null, startY = 0, swiped = false;
        stage.addEventListener('pointerdown', function (e) {
            startX = e.clientX;
            startY = e.clientY;
            swiped = false;
        });
        stage.addEventListener('pointerup', function (e) {
            if (startX === null) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            startX = null;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                swiped = true;
                go(dx < 0 ? 1 : -1);
                // The click that follows this pointerup must not also select a card.
                setTimeout(function () { swiped = false; }, 0);
            }
        });
        stage.addEventListener('pointercancel', function () { startX = null; });

        var resizeFrame = null;
        window.addEventListener('resize', function () {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(layout);
        });

        // ---- visibility ----
        setActive(0, { play: false });

        function onVisible() {
            playActive();
            startTimer();
        }
        function onHidden() {
            videoOf(active).pause();
            stopTimer();
        }

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) loadSources();
            }, { rootMargin: '100% 0px' }).observe(section);

            new IntersectionObserver(function (entries) {
                inView = entries[0].isIntersecting;
                if (inView) onVisible();
                else onHidden();
            }, { threshold: 0.4 }).observe(stage);
        } else {
            inView = true;
            loadSources();
            onVisible();
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) onHidden();
            else onVisible();
        });
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-home-videos]'), initReel);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
