@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'blocks_count', 'label' => $adminText('pages.columns.total_blocks'), 'headerTitle' => $adminText('pages.total_blocks_help')])
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-count-cell" title="{{ $adminText('pages.includes_nested_blocks') }}">{{ $page->blocks_count ?? $page->blocks()->count() }}</td>
@endif
