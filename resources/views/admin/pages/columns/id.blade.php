@if ($columnHeading)
    <th class="wb-admin-pages-id-cell">{{ $adminText('pages.columns.id') }}</th>
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-id-cell wb-text-sm wb-text-muted">{{ $page->id }}</td>
@endif
