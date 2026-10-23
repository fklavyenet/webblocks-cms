@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'updated_at', 'label' => $adminText('updated')])
@else
    <td>
        <div class="wb-stack wb-gap-1">
            <span>{{ $asset->updated_at?->format('Y-m-d') }}</span>
            <span class="wb-text-sm wb-text-muted">{{ $asset->updated_at?->format('H:i') }}</span>
        </div>
    </td>
@endif
