<section class="wb-card">
    <div class="wb-card-header"><strong>{{ $adminText('rating_summary') }}</strong></div>
    <div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ is_null($averageRating) ? $adminText('no_ratings') : $adminText('rating_sample', ['value' => $averageRating, 'count' => $activeRatingsCount]) }}</p>
        <p class="wb-text-sm wb-text-muted">{{ $adminText('rating_summary_help') }}</p>
        <div class="wb-table-wrap"><table class="wb-table">
            <thead><tr><th>{{ $adminText('rating') }}</th><th>{{ $adminText('votes') }}</th><th>{{ $adminText('share') }}</th></tr></thead>
            <tbody>@foreach (range(5, 1) as $value)
                <tr><td>{{ $value }} / 5</td><td>{{ $distribution[$value] ?? 0 }}</td><td>{{ $activeRatingsCount ? round(($distribution[$value] ?? 0) * 100 / $activeRatingsCount, 1) : 0 }}%</td></tr>
            @endforeach</tbody>
        </table></div>
    </div>
</section>
