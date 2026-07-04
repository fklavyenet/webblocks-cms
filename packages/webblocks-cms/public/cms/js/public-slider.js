document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-wb-slider]').forEach(function (slider) {
        var track = slider.querySelector(':scope > [data-wb-slider-track]');
        var slides = track ? Array.prototype.slice.call(track.children).filter(function (child) {
            return child.matches('[data-wb-slider-slide]');
        }) : [];
        var previous = slider.querySelector(':scope > .wb-cms-slider-arrows [data-wb-slider-prev]');
        var next = slider.querySelector(':scope > .wb-cms-slider-arrows [data-wb-slider-next]');
        var dots = Array.prototype.slice.call(slider.querySelectorAll(':scope > .wb-cms-slider-dots [data-wb-slider-dot]'));
        var activeIndex = 0;
        var transition = slider.getAttribute('data-wb-slider-transition') === 'fade' ? 'fade' : 'slide';
        var loop = slider.getAttribute('data-wb-slider-loop') !== 'false';
        var autoplay = slider.getAttribute('data-wb-slider-autoplay') === 'true';
        var pauseOnHover = slider.getAttribute('data-wb-slider-pause-on-hover') !== 'false';
        var swipe = slider.getAttribute('data-wb-slider-swipe') !== 'false';
        var keyboard = slider.getAttribute('data-wb-slider-keyboard') !== 'false';
        var interval = Math.min(Math.max(parseInt(slider.getAttribute('data-wb-slider-interval') || '6000', 10), 1000), 30000);
        var timer = null;
        var pointerStart = null;

        if (!track || slides.length === 0) {
            return;
        }

        function nextIndex(index) {
            if (loop) {
                return (index + slides.length) % slides.length;
            }

            return Math.min(Math.max(index, 0), slides.length - 1);
        }

        function stopAutoplay() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();

            if (!autoplay || slides.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            timer = window.setInterval(function () {
                render(activeIndex + 1);
            }, interval);
        }

        function render(index) {
            activeIndex = nextIndex(index);

            if (transition === 'slide') {
                track.style.transform = 'translateX(' + (activeIndex * -100) + '%)';
            }

            slides.forEach(function (slide, slideIndex) {
                var isActive = slideIndex === activeIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach(function (dot, dotIndex) {
                var isActive = dotIndex === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            if (!loop) {
                if (previous) {
                    previous.disabled = activeIndex === 0;
                }

                if (next) {
                    next.disabled = activeIndex === slides.length - 1;
                }
            }
        }

        if (keyboard && !slider.hasAttribute('tabindex')) {
            slider.setAttribute('tabindex', '0');
        }

        if (previous) {
            previous.addEventListener('click', function () {
                render(activeIndex - 1);
                startAutoplay();
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                render(activeIndex + 1);
                startAutoplay();
            });
        }

        dots.forEach(function (dot, dotIndex) {
            dot.addEventListener('click', function () {
                render(dotIndex);
                startAutoplay();
            });
        });

        if (pauseOnHover) {
            slider.addEventListener('mouseenter', stopAutoplay);
            slider.addEventListener('mouseleave', startAutoplay);
            slider.addEventListener('focusin', stopAutoplay);
            slider.addEventListener('focusout', startAutoplay);
        }

        if (swipe && window.PointerEvent) {
            slider.addEventListener('pointerdown', function (event) {
                pointerStart = {
                    x: event.clientX,
                    y: event.clientY,
                };
            });

            slider.addEventListener('pointerup', function (event) {
                if (!pointerStart) {
                    return;
                }

                var deltaX = event.clientX - pointerStart.x;
                var deltaY = event.clientY - pointerStart.y;
                pointerStart = null;

                if (Math.abs(deltaX) < 40 || Math.abs(deltaX) < Math.abs(deltaY)) {
                    return;
                }

                render(activeIndex + (deltaX < 0 ? 1 : -1));
                startAutoplay();
            });
        }

        if (keyboard) {
            slider.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    render(activeIndex - 1);
                    startAutoplay();
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    render(activeIndex + 1);
                    startAutoplay();
                }
            });
        }

        render(0);
        startAutoplay();
    });
});
