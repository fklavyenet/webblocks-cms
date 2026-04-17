<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
        <style>
            .wb-public-header {
                position: sticky;
                top: 0;
                z-index: 40;
                background: rgba(255, 255, 255, 0.94);
                border-bottom: 1px solid rgba(15, 23, 42, 0.08);
                backdrop-filter: blur(12px);
            }

            .wb-public-header-bar {
                display: flex;
                align-items: center;
                min-height: 4.5rem;
                gap: 1rem;
            }

            .wb-public-header-brand {
                font-weight: 700;
                display: block;
            }

            .wb-public-header-context {
                display: block;
                opacity: 0.7;
                font-size: 0.875rem;
            }

            .wb-public-header-identity {
                display: inline-flex;
                align-items: center;
                gap: 0.75rem;
            }

            .wb-public-header-spacer {
                flex: 1 1 auto;
            }

            .wb-public-header-actions {
                display: flex;
                align-items: center;
            }

            .wb-public-header-nav {
                display: none;
            }

            .wb-public-nav-list {
                display: flex;
                align-items: center;
                gap: 1.25rem;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .wb-public-nav-item {
                display: flex;
                align-items: center;
            }

            .wb-public-nav-link {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                color: rgba(15, 23, 42, 0.78);
                text-decoration: none;
                font-weight: 500;
                padding: 0.25rem 0;
                border: 0;
                background: transparent;
                cursor: pointer;
            }

            .wb-public-nav-link:hover,
            .wb-public-nav-link.is-active,
            .wb-public-nav-item.is-active > .wb-public-nav-link {
                color: rgba(15, 23, 42, 1);
            }

            .wb-public-nav-link.is-active,
            .wb-public-nav-item.is-active > .wb-public-nav-link {
                text-decoration: underline;
                text-underline-offset: 0.35rem;
            }

            .wb-public-nav-link-trigger {
                font: inherit;
            }

            .wb-public-header-mobile {
                margin-left: auto;
            }

            .wb-public-header-menu-trigger {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.75rem;
                height: 2.75rem;
                border: 1px solid rgba(15, 23, 42, 0.12);
                border-radius: 0.75rem;
                background: transparent;
                cursor: pointer;
            }

            .wb-public-main {
                min-width: 0;
            }

            .wb-public-main-column {
                grid-column: span 3;
            }

            .wb-public-main > .wb-container,
            .wb-public-sidebar > .wb-container {
                padding-inline: 0;
            }

            .wb-public-block[data-wb-public-block-type="page-title"] + .wb-public-block[data-wb-public-block-type="rich-text"] {
                margin-top: -0.5rem;
            }

            .wb-public-footer {
                border-top: 1px solid rgba(15, 23, 42, 0.08);
                margin-top: 3rem;
            }

            .wb-public-footer .wb-prose {
                max-width: none;
            }

            .wb-slider {
                position: relative;
                overflow: hidden;
            }

            .wb-slider-track {
                display: flex;
                transition: transform 0.3s ease-in-out;
                will-change: transform;
                transform: translateX(0%);
            }

            .wb-slider-slide {
                min-width: 100%;
                flex: 0 0 100%;
            }

            .wb-slider-controls {
                display: flex;
                justify-content: space-between;
                gap: 0.75rem;
                align-items: center;
            }

            .wb-slider-dots {
                display: flex;
                gap: 0.5rem;
            }

            .wb-slider-dot {
                width: 0.75rem;
                height: 0.75rem;
                border-radius: 999px;
                border: none;
                background: rgba(15, 23, 42, 0.16);
                cursor: pointer;
            }

            .wb-slider-dot.is-active {
                background: currentColor;
            }

            @media (min-width: 960px) {
                .wb-public-header-nav {
                    display: block;
                }

                .wb-public-header-mobile {
                    display: none;
                }
            }
        </style>
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
