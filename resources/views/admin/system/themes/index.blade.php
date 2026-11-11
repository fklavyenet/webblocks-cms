@php
    $text = static fn (string $key) => __('webblocks-cms::themes.'.$key);
    $tab = old('theme_tab', request()->query('tab')) === 'upload' ? 'upload' : 'versions';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title')])
    @include('webblocks-cms::admin.system.themes.feedback')

    <div class="wb-tabs" data-wb-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $text('title') }}">
            @foreach (['versions', 'upload'] as $section)
                <button type="button" role="tab" id="themes-tab-{{ $section }}"
                    class="wb-tabs-btn{{ $tab === $section ? ' is-active' : '' }}"
                    data-wb-tab="themes-{{ $section }}" aria-controls="themes-{{ $section }}"
                    aria-selected="{{ $tab === $section ? 'true' : 'false' }}"
                    @if ($tab !== $section) tabindex="-1" @endif>{{ $text($section) }}</button>
            @endforeach
        </div>
        <div class="wb-tabs-panels">
            <div class="wb-tabs-panel{{ $tab === 'upload' ? ' is-active' : '' }}" id="themes-upload" role="tabpanel" aria-labelledby="themes-tab-upload">
                <form method="post" action="{{ route('admin.system.themes.store') }}" enctype="multipart/form-data" class="wb-card">
                    @csrf
                    <input type="hidden" name="theme_tab" value="upload">
                    <div class="wb-card-body wb-field">
                        <label class="wb-label" for="theme-package">{{ $text('package') }}</label>
                        <input id="theme-package" name="package" type="file" class="wb-file" accept=".zip,application/zip" aria-describedby="theme-package-help" required>
                        <p id="theme-package-help" class="wb-text-sm wb-text-muted">{{ $text('upload_help') }}</p>
                    </div>
                    <div class="wb-card-footer wb-cluster wb-cluster-end">
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $text('upload') }}</button>
                    </div>
                </form>
            </div>

            <div class="wb-tabs-panel{{ $tab === 'versions' ? ' is-active' : '' }}" id="themes-versions" role="tabpanel" aria-labelledby="themes-tab-versions">
                <div class="wb-card">
                    <div class="wb-card-body">
                        @if ($versions->isEmpty())
                            <p class="wb-text-muted">{{ $text('empty') }}</p>
                        @else
                            <div class="wb-table-wrap">
                                <table class="wb-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ $text('theme') }}</th>
                                            <th scope="col">{{ $text('compatibility') }}</th>
                                            <th scope="col">{{ $text('layouts') }}</th>
                                            <th scope="col">{{ $text('actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($versions as $version)
                                            <tr>
                                                <th scope="row">{{ $version->name }} · {{ $version->version }}</th>
                                                <td>{{ $text($version->compatible ? 'compatible' : 'incompatible') }}</td>
                                                <td>{{ implode(', ', $version->layouts) }}</td>
                                                <td>
                                                    @if ($version->uses)
                                                        {{ $text('retained') }}
                                                    @else
                                                        <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-toggle="modal" data-wb-target="#delete-theme-{{ $version->id }}">{{ $text('delete') }}</button>
                                                    @endif
                                                    <details class="wb-collapse-panel">
                                                        <summary class="wb-collapse-trigger">{{ $text('details') }}</summary>
                                                        <div class="wb-collapse-body">
                                                            <p>{{ $version->handle }} · {{ $version->license }}</p>
                                                            <p>CMS {{ $version->cms }} · WebBlocks UI {{ $version->ui }}</p>
                                                            <code class="wb-theme-checksum">{{ $version->checksum }}</code>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @include('webblocks-cms::admin.partials.pagination', ['paginator' => $versions, 'ariaLabel' => $text('versions'), 'compact' => true])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @foreach ($versions as $version)
        @if (! $version->uses)
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'delete-theme-'.$version->id,
                'title' => $text('delete'),
                'description' => $text('delete_help'),
                'action' => route('admin.system.themes.destroy', $version->id),
                'submitLabel' => $text('delete'),
                'nativeDialog' => true,
            ])
                <p>{{ $version->name }} · {{ $version->version }}</p>
            @endcomponent
        @endif
    @endforeach
@endpush
