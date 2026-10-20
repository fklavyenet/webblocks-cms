@if ($columnHeading)
    <th title="{{ $adminText('pages.total_blocks_help') }}">{{ $adminText('pages.columns.total_blocks') }}</th>
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-count-cell" title="{{ $adminText('pages.includes_nested_blocks') }}">{{ $page->blocks_count ?? $page->blocks()->count() }}</td>
@endif
