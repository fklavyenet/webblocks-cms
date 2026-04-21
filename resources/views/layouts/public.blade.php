<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $siteCssPath = public_path('site/css/site.css');
        $siteJsPath = public_path('site/js/site.js');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($siteCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/site.css') }}?v={{ filemtime($siteCssPath) }}">
        @endif
    </head>
    <body class="wb-public-body">
        @yield('content')

        <div id="wb-overlay-root" class="wb-overlay-root">
            <div class="wb-modal wb-modal-xl" id="wb-gallery-viewer" role="dialog" aria-modal="true" aria-label="Gallery viewer">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-body">
                        <div class="wb-gallery-viewer">
                            <div class="wb-gallery-viewer-toolbar">
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-prev" aria-label="Previous image">Previous</button>
                                <div class="wb-gallery-viewer-counter" aria-live="polite"></div>
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-next" aria-label="Next image">Next</button>
                            </div>
                            <figure class="wb-gallery-viewer-media">
                                <img src="" alt="" class="wb-gallery-viewer-image">
                                <figcaption id="wb-gallery-viewer-caption" class="wb-gallery-viewer-caption" hidden></figcaption>
                                <p class="wb-gallery-viewer-meta wb-text-sm wb-text-muted" hidden></p>
                            </figure>
                            <div class="wb-cluster wb-cluster-end">
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-close" data-wb-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($siteJsPath))
            <script src="{{ asset('site/js/site.js') }}?v={{ filemtime($siteJsPath) }}"></script>
        @endif
        <script>
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
        </script>
    </body>
</html>
