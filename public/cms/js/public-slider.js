document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-wb-slider]').forEach(function (slider) {
        var track = slider.querySelector('[data-wb-slider-track]');
        var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-wb-slider-slide]'));
        var previous = slider.querySelector('[data-wb-slider-prev]');
        var next = slider.querySelector('[data-wb-slider-next]');
        var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-wb-slider-dot]'));

        if (!track || slides.length < 2) {
            return;
        }

        var activeIndex = 0;

        function render(index) {
            activeIndex = (index + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (activeIndex * -100) + '%)';

            dots.forEach(function (dot, dotIndex) {
                dot.classList.toggle('is-active', dotIndex === activeIndex);
                dot.setAttribute('aria-selected', dotIndex === activeIndex ? 'true' : 'false');
            });
        }

        if (previous) {
            previous.addEventListener('click', function () {
                render(activeIndex - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                render(activeIndex + 1);
            });
        }

        dots.forEach(function (dot, dotIndex) {
            dot.addEventListener('click', function () {
                render(dotIndex);
            });
        });

        render(0);
    });
});
