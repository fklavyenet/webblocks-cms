@if ($columnHeading)
    <th>{{ $adminText('pages.last_edited') }}</th>
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-last-edited-cell">
        <div class="wb-admin-pages-last-edited wb-text-sm">
            <span>{{ $page->updated_at?->format('Y-m-d H:i') ?? '-' }}</span>
            @if ($page->updatedByUser?->name)
                <span class="wb-text-muted">{{ $page->updatedByUser->name }}</span>
            @endif
        </div>
    </td>
@endif
