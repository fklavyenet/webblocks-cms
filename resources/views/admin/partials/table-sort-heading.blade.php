<th scope="col" data-wb-sort="{{ $key }}" @if (! empty($headerClass)) class="{{ $headerClass }}" @endif @if ($tableSort->key === $key) aria-sort="{{ $tableSort->direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a class="wb-table-sort" href="{{ $tableSort->url($key) }}" aria-label="{{ str_replace('{label}', $label, __('webblocks-cms::table_sort.label_'.$tableSort->nextDirection($key))) }}">{{ $label }}</a>
</th>
