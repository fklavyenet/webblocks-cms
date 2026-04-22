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

@if ($errors->has('site_delete'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Delete Failed</div>
            <div>{{ $errors->first('site_delete') }}</div>
        </div>
    </div>
@endif

@if ($errors->has('locale_lifecycle'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Locale Action Blocked</div>
            <div>{{ $errors->first('locale_lifecycle') }}</div>
        </div>
    </div>
@endif

@if ($errors->has('system_restore'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">Restore Failed</div>
            <div>{{ $errors->first('system_restore') }}</div>
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
