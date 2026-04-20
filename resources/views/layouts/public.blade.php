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
            :root {
                --fklavye-teal-strong: #117d79;
                --fklavye-teal-deep: #0f5c5a;
                --fklavye-teal-soft: #d7f2ee;
                --fklavye-gold: #b9892c;
                --fklavye-gold-soft: #f3ead2;
                --fklavye-ink: #203334;
            }

            .wb-public-body {
                color: var(--fklavye-ink);
                background:
                    radial-gradient(circle at top right, rgba(17, 125, 121, 0.08), transparent 24%),
                    linear-gradient(180deg, #fcfefd 0%, #f6fbfa 100%);
            }

            .wb-public-header {
                position: sticky;
                top: 0;
                z-index: 40;
                background: rgba(250, 253, 252, 0.94);
                border-bottom: 1px solid rgba(17, 125, 121, 0.16);
                backdrop-filter: blur(12px);
                box-shadow: 0 10px 30px rgba(15, 92, 90, 0.06);
            }

            .wb-public-header-bar {
                display: flex;
                align-items: center;
                min-height: 3.25rem;
                gap: 0.85rem;
            }

            .wb-public-header-brand {
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                font-size: 0.82rem;
                display: block;
            }

            .wb-public-header-context {
                display: block;
                opacity: 0.72;
                font-size: 0.74rem;
                line-height: 1.2;
            }

            .wb-public-header-identity {
                display: inline-flex;
                align-items: center;
                gap: 0.65rem;
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
                gap: 0.95rem;
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
                color: rgba(32, 51, 52, 0.78);
                text-decoration: none;
                font-weight: 700;
                font-size: 0.73rem;
                line-height: 1;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                padding: 0.2rem 0;
                border: 0;
                background: transparent;
                cursor: pointer;
                transition: color 0.2s ease;
            }

            .wb-public-nav-link:hover,
            .wb-public-nav-link.is-active,
            .wb-public-nav-item.is-active > .wb-public-nav-link {
                color: var(--fklavye-teal-deep);
            }

            .wb-public-nav-link.is-active,
            .wb-public-nav-item.is-active > .wb-public-nav-link {
                text-decoration: none;
                box-shadow: inset 0 -2px 0 var(--fklavye-gold);
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
                width: 2.35rem;
                height: 2.35rem;
                border: 1px solid rgba(17, 125, 121, 0.18);
                border-radius: 0.65rem;
                background: transparent;
                cursor: pointer;
                color: var(--fklavye-teal-deep);
            }

            .wb-public-banner {
                position: relative;
                overflow: hidden;
                color: #f9fffd;
                background:
                    linear-gradient(135deg, rgba(15, 92, 90, 0.98) 0%, rgba(17, 125, 121, 0.96) 52%, rgba(62, 151, 137, 0.9) 100%);
                border-top: 1px solid rgba(255, 255, 255, 0.16);
                border-bottom: 1px solid rgba(15, 92, 90, 0.2);
            }

            .wb-public-banner::before {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 22%),
                    linear-gradient(90deg, rgba(185, 137, 44, 0.18), transparent 30% 70%, rgba(255, 255, 255, 0.08));
                pointer-events: none;
            }

            .wb-public-banner-inner {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                min-height: 4rem;
                padding-block: 0.7rem;
            }

            .wb-public-banner-copy {
                min-width: 0;
            }

            .wb-public-banner-title {
                margin: 0;
                font-size: 1.15rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .wb-public-banner-text {
                margin: 0.2rem 0 0;
                color: rgba(244, 255, 251, 0.9);
                font-size: 0.92rem;
                line-height: 1.35;
            }

            .wb-public-banner-accent {
                width: 5.5rem;
                height: 2px;
                border-radius: 999px;
                background: linear-gradient(90deg, rgba(255, 255, 255, 0.9), rgba(243, 234, 210, 0.95), rgba(185, 137, 44, 0.7));
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1);
                flex: 0 0 auto;
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

            .wb-public-contact-pair {
                display: grid;
                gap: 1.25rem;
                align-items: start;
            }

            .wb-public-contact-card,
            .wb-public-contact-form-card {
                border: 1px solid rgba(17, 125, 121, 0.12);
                box-shadow: 0 18px 40px rgba(15, 92, 90, 0.08);
            }

            .wb-public-contact-card {
                background: linear-gradient(180deg, rgba(215, 242, 238, 0.72) 0%, rgba(255, 255, 255, 0.98) 100%);
            }

            .wb-public-contact-form-card {
                background: rgba(255, 255, 255, 0.98);
            }

            .wb-public-contact-meta strong {
                color: var(--fklavye-teal-deep);
                font-size: 0.82rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .wb-public-contact-pair .wb-btn,
            .wb-public-header-actions .wb-btn {
                background: linear-gradient(135deg, var(--fklavye-gold) 0%, #9d6f1f 100%);
                border-color: rgba(117, 78, 11, 0.32);
                color: #fffdf8;
            }

            .wb-public-main a,
            .wb-public-footer a {
                color: var(--fklavye-teal-deep);
            }

            .wb-public-main a:hover,
            .wb-public-footer a:hover {
                color: var(--fklavye-gold);
            }

            .wb-public-hero,
            .wb-public-card-grid article,
            .wb-public-showcase-item,
            .wb-gallery-item {
                border-color: rgba(17, 125, 121, 0.12);
                box-shadow: 0 16px 32px rgba(15, 92, 90, 0.08);
            }

            .wb-public-hero {
                background:
                    linear-gradient(145deg, rgba(214, 241, 236, 0.92) 0%, rgba(255, 255, 255, 0.98) 36%, rgba(243, 234, 210, 0.86) 100%);
                border: 1px solid rgba(17, 125, 121, 0.14);
            }

            .wb-public-hero h1,
            .wb-public-main h2,
            .wb-public-main h3 {
                color: var(--fklavye-teal-deep);
            }

            .wb-public-main h2,
            .wb-public-main h3 {
                letter-spacing: 0.01em;
            }

            .wb-public-card-grid article img,
            .wb-public-showcase-item img,
            .wb-gallery-media {
                border-radius: 0.6rem;
            }

            .wb-public-footer {
                border-top: 1px solid rgba(17, 125, 121, 0.12);
                margin-top: 3rem;
                background: rgba(250, 253, 252, 0.8);
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

                .wb-public-contact-pair {
                    grid-template-columns: minmax(0, 0.82fr) minmax(0, 1.18fr);
                }
            }

            @media (max-width: 959px) {
                .wb-public-banner-inner {
                    min-height: auto;
                    padding-block: 0.8rem;
                }

                .wb-public-banner-accent {
                    display: none;
                }
            }

            @media (max-width: 639px) {
                .wb-public-header-bar {
                    min-height: 3rem;
                }

                .wb-public-header-context {
                    display: none;
                }

                .wb-public-banner-title {
                    font-size: 0.98rem;
                }

                .wb-public-banner-text {
                    font-size: 0.82rem;
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
