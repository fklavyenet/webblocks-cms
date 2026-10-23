@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'id', 'label' => $adminText('pages.columns.id'), 'headerClass' => 'wb-admin-pages-id-cell'])
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-id-cell wb-text-sm wb-text-muted">{{ $page->id }}</td>
@endif
