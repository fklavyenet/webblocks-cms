@extends('layouts.admin', ['title' => 'Delete Site', 'heading' => 'Delete Site'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Delete Site',
        'description' => 'Delete this site and its site-scoped content. This action cannot be undone.',
        'actions' => '<a href="'.route('admin.sites.edit', $site).'" class="wb-btn wb-btn-secondary">Back to Site</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $site->name }}</strong>
                    <div class="wb-text-sm wb-text-muted"><code>{{ $site->handle }}</code></div>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>Domain:</strong> {{ $site->domain ?: 'Not set' }}</div>
                    <div><strong>Pages:</strong> {{ $report->count('pages') }}</div>
                    <div><strong>Blocks:</strong> {{ $report->count('blocks') }}</div>
                    <div><strong>Navigation items:</strong> {{ $report->count('navigation_items') }}</div>
                    <div><strong>Locale assignments:</strong> {{ $report->count('site_locales') }}</div>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">Warning</div>
                        <div>This permanently deletes the selected site and its site-scoped content. Shared assets and files are not blindly removed.</div>
                    </div>
                </div>

                @if ($report->hasBlockers())
                    <div class="wb-alert wb-alert-danger">
                        <div>
                            <div class="wb-alert-title">Delete Blocked</div>
                            <div>{{ $report->firstBlocker() }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.sites.destroy', $site) }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('DELETE')

                    <label class="wb-nowrap">
                        <input type="checkbox" name="confirm_delete" value="1" @checked(old('confirm_delete')) @disabled($report->hasBlockers())>
                        <span>I understand this will permanently delete this site.</span>
                    </label>

                    <x-admin.form-actions
                        :cancel-url="route('admin.sites.index')"
                        :show-submit="false"
                        :delete-submit="true"
                        :delete-disabled="$report->hasBlockers()"
                    />
                </form>
            </div>
        </div>
    </div>
@endsection
