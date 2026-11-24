@if ($columnHeading)
    <th>{{ $adminText('preview') }}</th>
@else
    <td>
        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-preview-box wb-no-decoration"
           data-wb-tooltip="{{ $adminText('preview_media') }}" aria-label="{{ $adminText('preview_media') }} · {{ $asset->thumbnailLabel() }}">
            @if ($asset->canPreview() && $asset->url())
                <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}">
            @else
                <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-xl" aria-hidden="true"></i>
            @endif
        </a>
    </td>
@endif
