@extends('layouts.public', ['title' => $page->title, 'metaDescription' => $metaDescription])

@php
    $secondarySlots = $slots->reject(function ($slot) {
        return in_array($slot['slug'], ['header', 'main', 'sidebar', 'footer'], true);
    });
@endphp

@section('content')
    @if ($headerSlot)
        @include('pages.partials.slot', ['slot' => $headerSlot, 'page' => $page])
    @endif

    @php
        $usesComposedLayout = $headerSlot || $sidebarSlot || $footerSlot || $secondarySlots->isNotEmpty();
    @endphp

    @if ($usesComposedLayout && ($mainSlot || $sidebarSlot))
        <div class="wb-section">
            <div class="wb-container wb-container-lg">
                <div class="{{ $sidebarSlot ? 'wb-grid wb-grid-4' : 'wb-stack wb-gap-0' }} wb-gap-6">
                    @if ($mainSlot)
                        <div class="{{ $sidebarSlot ? 'wb-public-main-column' : '' }}">
                            @include('pages.partials.slot', ['slot' => $mainSlot, 'page' => $page])
                        </div>
                    @endif

                    @if ($sidebarSlot)
                        <div>
                            @include('pages.partials.slot', ['slot' => $sidebarSlot, 'page' => $page])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($mainSlot)
        @include('pages.partials.slot', ['slot' => $mainSlot, 'page' => $page])
    @endif

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
