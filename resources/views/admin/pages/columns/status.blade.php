@if ($columnHeading)
    @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'status', 'label' => $adminText('common.status')])
@else
    <td class="wb-admin-pages-table-cell wb-admin-pages-status-cell">
        <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">
            {{ $page->workflowLabel() }}
        </span>
    </td>
@endif
