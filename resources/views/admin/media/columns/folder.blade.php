@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'folder', 'label' => $adminText('folder')])
@else
    <td>
        <div class="wb-stack wb-gap-1">
            <span>{{ $asset->folder?->name ?? $adminText('no_folder') }}</span>
            <span class="wb-text-sm wb-text-muted">{{ $mediaKindLabel($asset->kind) }}</span>
        </div>
    </td>
@endif
