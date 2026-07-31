@php
  $wrapperPreset = $slot['wrapper']['preset'] ?? null;
@endphp

@if ($slot['blocks']->isNotEmpty())
  @if ($wrapperPreset === 'docs-navbar')
    @php
      $breadcrumbBlocks = $slot['blocks']->filter(fn ($block) => $block->typeSlug() === 'breadcrumb')->values();
      $actionBlocks = $slot['blocks']->filter(fn ($block) => $block->typeSlug() === 'header-actions')->values();
      $otherBlocks = $slot['blocks']->reject(fn ($block) => in_array($block->typeSlug(), ['breadcrumb', 'header-actions'], true))->values();
    @endphp

    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full wb-flex-wrap">
      <div class="wb-cluster wb-cluster-2 wb-items-center">
        <button
          class="wb-navbar-toggle"
          type="button"
          data-wb-toggle="sidebar"
          data-wb-target="#docsSidebar"
          aria-expanded="false"
          aria-controls="docsSidebar"
          aria-label="Toggle navigation"
        >
          <span></span><span></span><span></span>
        </button>

        @foreach ($breadcrumbBlocks as $block)
          @include('webblocks-cms::pages.partials.block', ['block' => $block])
        @endforeach
      </div>

      <div class="wb-cluster wb-cluster-2 wb-cluster-end wb-items-center">
        @foreach ($actionBlocks as $block)
          @include('webblocks-cms::pages.partials.block', ['block' => $block])
        @endforeach
      </div>
    </div>

    @foreach ($otherBlocks as $block)
      @include('webblocks-cms::pages.partials.block', ['block' => $block])
    @endforeach
  @else
    @foreach ($slot['blocks'] as $block)
      @include('webblocks-cms::pages.partials.block', ['block' => $block])
    @endforeach
  @endif
@endif
