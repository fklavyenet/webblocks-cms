@extends('layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Review the installed version, available update, and safe update steps before applying a system update.',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Version Status</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Installed version</div>
                            <strong>{{ $updateStatus['current_version'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Available version</div>
                            <strong>{{ $updateStatus['latest_version'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Status</div>
                            <span class="wb-status-pill {{ $updateStatus['up_to_date'] ? 'wb-status-active' : 'wb-status-pending' }}">
                                {{ $updateStatus['up_to_date'] ? 'Up to date' : 'Update available' }}
                            </span>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Maintenance mode</div>
                            <span class="wb-status-pill {{ $isMaintenanceMode ? 'wb-status-danger' : 'wb-status-info' }}">
                                {{ $isMaintenanceMode ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header">
                    <strong>Release Notes</strong>
                </div>

                <div class="wb-card-body">
                    @if ($updateStatus['release_notes'] === [])
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No release notes available</div>
                        </div>
                    @else
                        <ul class="wb-stack wb-gap-1">
                            @foreach ($updateStatus['release_notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Before You Update</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">Backup recommended</div>
                        <div>Back up your database and files before running a system update. Make sure you can restore the site if needed.</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.system.updates.run') }}" class="wb-stack wb-gap-3">
                    @csrf

                    <label class="wb-checkbox">
                        <input type="checkbox" name="confirm_backup" value="1" @checked(old('confirm_backup'))>
                        <span>I understand and have a backup or accept the risk.</span>
                    </label>

                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <div class="wb-text-sm wb-text-muted">The update will run migrations and clear framework caches in maintenance mode.</div>

                        @if ($updateStatus['up_to_date'])
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>Up to date</button>
                        @elseif ($isMaintenanceMode)
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>Maintenance mode active</button>
                        @else
                            <button type="submit" class="wb-btn wb-btn-primary">Update now</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if (session('update_output'))
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Update Output</strong>
                </div>

                <div class="wb-card-body">
                    <pre class="wb-code-block">{{ implode(PHP_EOL.PHP_EOL, session('update_output')) }}</pre>
                </div>
            </div>
        @endif

        @if (session('update_error_output'))
            <div class="wb-card wb-card-danger">
                <div class="wb-card-header">
                    <strong>Last Error Output</strong>
                </div>

                <div class="wb-card-body">
                    <pre class="wb-code-block">{{ session('update_error_output') }}</pre>
                </div>
            </div>
        @endif

        @if ($latestRun)
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Latest Update Run</strong>
                    <span class="wb-status-pill {{ $latestRun->status === 'success' ? 'wb-status-active' : 'wb-status-danger' }}">{{ $latestRun->status }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-text-sm wb-text-muted">{{ $latestRun->from_version }} to {{ $latestRun->to_version }} on {{ $latestRun->created_at?->format('Y-m-d H:i:s') }}</div>

                    @if ($latestRun->output)
                        <pre class="wb-code-block">{{ $latestRun->output }}</pre>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
