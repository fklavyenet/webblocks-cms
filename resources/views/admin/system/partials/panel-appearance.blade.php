<div class="wb-card wb-card-muted">
    <div class="wb-card-header"><strong>{{ $systemText('panel_appearance') }}</strong></div>
    <div class="wb-card-body wb-stack wb-gap-3" data-wb-admin-panel-appearance>
        <p class="wb-text-sm wb-text-muted" id="panel-appearance-help">{{ $systemText('panel_appearance_help') }}</p>
        <div class="wb-grid wb-grid-2 wb-gap-4">
            <div class="wb-field">
                <label for="panel-appearance-preset">{{ $adminText('topbar.presets') }}</label>
                <select class="wb-select" id="panel-appearance-preset" data-wb-admin-panel-preset aria-describedby="panel-appearance-help" disabled>
                    <option value="" disabled>{{ $adminText('common.select') }}</option>
                    @foreach (['modern', 'minimal', 'editorial', 'playful', 'corporate'] as $preset)
                        <option value="{{ $preset }}">{{ $adminText('topbar.'.$preset) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wb-field">
                <label for="panel-appearance-accent">{{ $adminText('topbar.accent') }}</label>
                <select class="wb-select" id="panel-appearance-accent" data-wb-admin-panel-accent aria-describedby="panel-appearance-help" disabled>
                    @foreach (['ocean', 'forest', 'sunset', 'royal', 'mint', 'amber', 'rose', 'slate-fire'] as $accent)
                        <option value="{{ $accent }}">{{ $adminText('topbar.'.str_replace('-', '_', $accent)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
