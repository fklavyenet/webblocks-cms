@php
    $drawerId = $drawerId ?? 'pageDetailsDrawer';
    $drawerTitleId = $drawerId.'Title';
    $publishedAt = data_get($page, 'published_at');
    $slotCount = $page->slots_count ?? ($page->relationLoaded('slots') ? $page->slots->count() : $page->slots()->count());
    $blockCount = $page->blocks_count ?? ($page->relationLoaded('blocks') ? $page->blocks->count() : $page->blocks()->count());
    $firstSlot = $page->relationLoaded('slots') ? $page->slots->first() : $page->slots()->orderBy('sort_order')->first();
    $publishedLabel = $publishedAt instanceof \Illuminate\Support\Carbon
        ? $publishedAt->format('Y-m-d H:i')
        : ($publishedAt ?: 'Not recorded');
@endphp

<div class="wb-drawer wb-drawer-right wb-drawer-sm" id="{{ $drawerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $drawerTitleId }}">
    <div class="wb-drawer-header">
        <h2 class="wb-drawer-title" id="{{ $drawerTitleId }}">Page Details</h2>
        <button class="wb-drawer-close" data-wb-dismiss="drawer" aria-label="Close details panel">
            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
        </button>
    </div>

    <div class="wb-drawer-body">
        <div class="wb-stack wb-stack-4">
            <div class="wb-list wb-list-sm">
                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">ID</span>
                        <span class="wb-list-item-sub">{{ $page->id }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Name</span>
                        <span class="wb-list-item-sub">{{ $page->title }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Path</span>
                        <span class="wb-list-item-sub">{{ $page->publicPath() }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Site</span>
                        <span class="wb-list-item-sub">{{ $page->site?->name }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Default URL</span>
                        <span class="wb-list-item-sub">{{ $page->publicUrl() }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Slug</span>
                        <span class="wb-list-item-sub">{{ $page->slug }}</span>
                    </div>
                </div>

                @foreach ($page->translationStatusForSite() as $translationStatus)
                    <div class="wb-list-item">
                        <div class="wb-list-item-text">
                            <span class="wb-list-item-title">{{ strtoupper($translationStatus['locale']->code) }}</span>
                            <span class="wb-list-item-sub">{{ $translationStatus['translation']?->slug ?? 'Missing' }}{{ $translationStatus['public_path'] ? ' | '.$translationStatus['public_path'] : '' }}</span>
                        </div>
                    </div>
                @endforeach

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Status</span>
                        <span class="wb-list-item-sub"><span class="wb-status-pill {{ $page->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $page->status }}</span></span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Published</span>
                        <span class="wb-list-item-sub">{{ $publishedLabel }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Slot count</span>
                        <span class="wb-list-item-sub">{{ $slotCount }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Block count</span>
                        <span class="wb-list-item-sub">{{ $blockCount }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Created</span>
                        <span class="wb-list-item-sub">{{ $page->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Updated</span>
                        <span class="wb-list-item-sub">{{ $page->updated_at?->format('Y-m-d H:i') }}</span>
                    </div>
                </div>
            </div>

            @if ($page->status === 'published')
                <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener noreferrer" class="wb-btn wb-btn-secondary wb-w-full">Open Public Page</a>
            @endif
        </div>
    </div>

    <div class="wb-drawer-footer">
        <div class="wb-cluster wb-cluster-2">
            <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">Edit Slots</a>

            @if ($firstSlot)
                <a href="{{ route('admin.pages.slots.blocks', [$page, $firstSlot]) }}" class="wb-btn wb-btn-secondary">Edit Blocks</a>
            @endif
        </div>
    </div>
</div>
