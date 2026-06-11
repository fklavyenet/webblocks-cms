@extends('webblocks-cms::layouts.admin', ['title' => 'Import Details', 'heading' => 'Import Details'])

@section('content')
    @php
        $outputLog = (string) ($siteImport->output_log ?? '');

        if ($siteImport->failure_message) {
            $duplicateFailureLines = [
                'Import failed: '.$siteImport->failure_message,
                'Import validation failed: '.$siteImport->failure_message,
            ];

            $outputLog = collect(explode(PHP_EOL, $outputLog))
                ->reject(fn ($line) => in_array(trim((string) $line), $duplicateFailureLines, true))
                ->implode(PHP_EOL);
        }
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $siteImport->source_archive_name ?? 'Site Import #'.$siteImport->id,
        'description' => 'Review the package manifest, preview the import summary, and create a new site from the validated package.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">Back to Export / Import</a>'.($siteImport->targetSite ? '<a href="'.route('admin.sites.edit', $siteImport->targetSite).'" class="wb-btn wb-btn-secondary">Open Imported Site</a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Import Status</strong>
                    <span class="wb-status-pill {{ $siteImport->statusBadgeClass() }}">{{ $siteImport->statusLabel() }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Source package:</strong> {{ $siteImport->source_archive_name ?? '-' }}</div>
                    <div><strong>Imported site handle:</strong> {{ $siteImport->imported_site_handle ?? '-' }}</div>
                    <div><strong>Imported site domain:</strong> {{ $siteImport->imported_site_domain ?? '-' }}</div>

                    @if ($siteImport->failure_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Import failed</div>
                                <div>{{ $siteImport->failure_message }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Manifest Preview</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Product:</strong> {{ $manifest['product'] ?? '-' }}</div>
                    <div><strong>Format version:</strong> {{ $manifest['format_version'] ?? '-' }}</div>
                    <div><strong>Source site:</strong> {{ $manifest['source_site_name'] ?? '-' }}</div>
                    <div><strong>Source handle:</strong> {{ $manifest['source_site_handle'] ?? '-' }}</div>
                    <div><strong>Source domain:</strong> {{ $manifest['source_site_domain'] ?? '-' }}</div>
                    <div><strong>Locales:</strong> {{ collect($manifest['locales'] ?? [])->implode(', ') ?: '-' }}</div>
                    <div><strong>Includes media:</strong> {{ ($manifest['includes_media'] ?? false) ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>

        @if ($siteImport->isValidated())
            <div class="wb-card wb-card-accent">
                <div class="wb-card-header"><strong>Create New Site From Package</strong></div>

                <div class="wb-card-body">
                    <form method="POST" action="{{ route('admin.site-transfers.imports.run', $siteImport) }}" class="wb-stack wb-gap-4">
                        @csrf

                        <div class="wb-grid wb-grid-3">
                            <div class="wb-stack wb-gap-2">
                                <label for="site_name">New site name</label>
                                <input id="site_name" type="text" name="site_name" class="wb-input" value="{{ old('site_name', $manifest['source_site_name'] ?? '') }}" required>
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <label for="site_handle">New site handle</label>
                                <input id="site_handle" type="text" name="site_handle" class="wb-input" value="{{ old('site_handle', $manifest['source_site_handle'] ?? '') }}">
                                <div class="wb-text-sm wb-text-muted">If the handle already exists, a safe imported suffix will be generated automatically.</div>
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <label for="site_domain">Optional domain</label>
                                <input id="site_domain" type="text" name="site_domain" class="wb-input" value="{{ old('site_domain') }}">
                                <div class="wb-text-sm wb-text-muted">Leave blank to avoid reusing an active domain from the source package.</div>
                            </div>
                        </div>

                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <button type="submit" class="wb-btn wb-btn-primary">Run Import</button>
                                <a href="{{ route('admin.site-transfers.exports.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <section class="wb-card">
            <div class="wb-card-header"><strong>Package Counts</strong></div>

            <div class="wb-card-body">
                @if (count($counts) > 0)
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm">
                            <thead>
                                <tr>
                                    <th>Area</th>
                                    <th style="text-align: right;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($counts as $label => $value)
                                    <tr>
                                        <td>{{ str($label)->replace('_', ' ')->title() }}</td>
                                        <td style="text-align: right;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No count summary recorded</div>
                    </div>
                @endif
            </div>
        </section>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Output Log</strong></div>

            <div class="wb-card-body">
                @if (trim($outputLog) !== '')
                    <pre class="wb-code-block">{{ $outputLog }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No output log captured</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
