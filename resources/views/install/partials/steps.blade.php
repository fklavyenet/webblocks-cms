<div class="wb-card wb-card-muted">
    <div class="wb-card-body">
        <div class="wb-grid wb-grid-5">
            @foreach ($steps as $step)
                <div class="wb-stack wb-gap-1">
                    <div class="wb-text-xs wb-text-muted">Step {{ $loop->iteration }}</div>
                    <div><strong>{{ $step['label'] }}</strong></div>
                    <div>
                        <span class="wb-status-pill {{ match ($step['state']) { 'complete' => 'wb-status-active', 'current' => 'wb-status-info', default => 'wb-status-pending' } }}">
                            {{ ucfirst($step['state']) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
