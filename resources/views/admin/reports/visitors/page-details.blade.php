@foreach ($report['top_pages'] as $row)
    <div id="visitor-page-{{ $loop->index }}" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="visitor-page-title-{{ $loop->index }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 id="visitor-page-title-{{ $loop->index }}" class="wb-modal-title">{{ $insightText('details') }}</h2>
                <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $insightText('close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <div class="wb-modal-body wb-stack wb-gap-3">
                <p><code>{{ $row['path'] }}</code> · {{ $row['site_name'] }} · {{ strtoupper($row['locale_code']) }}</p>
                <p>{{ $insightText('views') }}: <strong>{{ number_format($row['page_views']) }}</strong></p>
                <p class="wb-text-muted">{{ $insightText('detail_help') }}</p>
                @foreach ($row['details'] as $dimension => $groups)
                    <h3>{{ $insightText($dimension) }}</h3>
                    @if ($groups === [])
                        <p class="wb-text-muted">{{ $insightText('small_groups') }}</p>
                    @else
                        <div class="wb-table-wrap"><table class="wb-table">
                            <thead><tr><th scope="col">{{ $insightText($dimension) }}</th><th scope="col">{{ $insightText('views') }}</th></tr></thead>
                            <tbody>@foreach ($groups as $group)
                                <tr><td>{{ $dimension === 'devices' ? $insightText($group['label']) : (match ($group['label']) { 'Direct / Unknown' => $insightText('direct'), 'Internal' => $insightText('internal'), default => $group['label'] }) }}</td><td>{{ number_format($group['views']) }}</td></tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endforeach
