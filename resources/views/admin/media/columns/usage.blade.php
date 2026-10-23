@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'usage', 'label' => $adminText('usage')])
@else
    <td>
        @if ($assetUsages->isNotEmpty())
            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_media' => $asset->id])) }}" class="wb-status-pill wb-status-pending">{{ $mediaUsageCount($assetUsages->count()) }}</a>
        @else
            <span class="wb-status-pill wb-status-info">{{ $adminText('unused_media') }}</span>
        @endif
    </td>
@endif
