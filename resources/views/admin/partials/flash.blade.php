@if (session('status'))
    <div class="wb-alert wb-alert-success">
        <div>
            <div class="wb-alert-title">Success</div>
            <div>
                {{ session('status') }}

                @if (session('status_action'))
                    @php($statusAction = session('status_action'))
                    <a href="{{ $statusAction['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $statusAction['label'] ?? 'View page' }}</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($errors->has('system_update'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Update Failed</div>
            <div>{{ $errors->first('system_update') }}</div>
        </div>
    </div>
@endif

@if ($errors->has('system_backup'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Backup Failed</div>
            <div>{{ $errors->first('system_backup') }}</div>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Validation Error</div>
            <div>{{ $errors->getBag('default')->first() }}</div>
        </div>
    </div>
@endif
