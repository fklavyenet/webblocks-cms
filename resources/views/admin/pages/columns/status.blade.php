@if ($columnHeading)
    <th>{{ $adminText('common.status') }}</th>
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-status-cell">
        <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">
            {{ $page->workflowLabel() }}
        </span>
    </td>
@endif
