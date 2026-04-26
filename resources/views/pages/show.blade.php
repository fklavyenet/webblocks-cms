@extends('layouts.public', [
    'title' => $page->title,
    'metaDescription' => $metaDescription,
    'hasFooterSlot' => $footerSlot !== null,
])

@php
    $secondarySlots = $slots->reject(function ($slot) {
        return in_array($slot['slug'], ['header', 'main', 'sidebar', 'footer'], true);
    });
@endphp

@section('content')
    @if ($headerSlot)
        @include('pages.partials.slot', ['slot' => $headerSlot, 'page' => $page])
    @endif

    @switch($layoutMode)
        @case(App\Support\Pages\PublicLayoutMode::SIDEBAR)
            @if ($mainSlot || $sidebarSlot)
                <main class="wb-public-main" id="main-content">
                    <div class="wb-container wb-container-lg">
                        <div class="{{ $mainSlot && $sidebarSlot ? 'wb-grid wb-grid-4 wb-gap-6' : 'wb-stack wb-gap-6' }}">
                            @if ($mainSlot)
                                <div class="{{ $sidebarSlot ? 'wb-public-main-column' : '' }}">
                                    <div class="wb-stack wb-gap-6">
                                        @include('pages.partials.slots.main', ['slot' => $mainSlot, 'page' => $page, 'renderShell' => false])
                                    </div>
                                </div>
                            @endif

                            @if ($sidebarSlot)
                                <aside class="wb-public-sidebar" aria-label="{{ $sidebarSlot['name'] }}">
                                    <div class="wb-stack wb-gap-4">
                                        @include('pages.partials.slots.sidebar', ['slot' => $sidebarSlot, 'page' => $page, 'renderShell' => false])
                                    </div>
                                </aside>
                            @endif
                        </div>
                    </div>
                </main>
            @endif
            @break

        @case(App\Support\Pages\PublicLayoutMode::CONTENT)
            @if ($mainSlot)
                <main class="wb-public-main" id="main-content">
                    <div class="wb-container wb-container-lg">
                        <div class="wb-content-shell">
                            <div class="wb-content-body">
                                <div class="wb-stack wb-gap-6">
                                    @include('pages.partials.slots.main', ['slot' => $mainSlot, 'page' => $page, 'renderShell' => false])
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            @endif
            @break

        @default
            @if ($mainSlot)
                @include('pages.partials.slot', ['slot' => $mainSlot, 'page' => $page])
            @endif
    @endswitch

    @foreach ($secondarySlots as $slot)
        @include('pages.partials.slot', ['slot' => $slot, 'page' => $page])
    @endforeach

    @if ($footerSlot)
        @include('pages.partials.slot', ['slot' => $footerSlot, 'page' => $page])
    @endif

    @if ($slots->isEmpty())
        <section class="wb-section">
            <div class="wb-container wb-container-lg">
                <div class="wb-empty">
                    <div class="wb-empty-title">This page has no published content yet</div>
                </div>
            </div>
        </section>
    @endif
@endsection
