@props(['menuId', 'label'])

<button
    type="button"
    class="wb-action-more-btn wb-w-auto wb-gap-1 wb-p-2"
    data-wb-toggle="dropdown"
    data-wb-target="#{{ $menuId }}"
    aria-controls="{{ $menuId }}"
    aria-expanded="false"
    aria-label="{{ $label }}"
    title="{{ $label }}"
>
    <span aria-hidden="true">⋯</span>
    <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
</button>
