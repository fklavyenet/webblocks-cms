@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@php
    $selectedLocaleIds = collect(old('locale_ids', $site->exists ? $site->locales->pluck('id') : $locales->where('is_default', true)->pluck('id')))
        ->map(fn ($id) => (int) $id)
        ->values();
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Keep site setup compact. Each site can map one canonical host and its enabled public locales.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="site_name">Name</label>
                            <input id="site_name" name="name" class="wb-input" type="text" value="{{ old('name', $site->name) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="site_handle">Handle</label>
                            <input id="site_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $site->handle) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="site_domain">Domain</label>
                            <input id="site_domain" name="domain" class="wb-input" type="text" value="{{ old('domain', $site->domain) }}">
                            <div class="wb-text-sm wb-text-muted">Store the host only, for example <code>www.example.com</code> or <code>campaign.ddev.site</code>.</div>
                        </div>

                        <label class="wb-nowrap">
                            <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $site->is_primary))>
                            <span>Primary</span>
                        </label>
                    </div>

                        <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong>Locales</strong>
                            <div class="wb-text-sm wb-text-muted">Each site must keep at least one locale enabled. The system default locale is always forced on.</div>
                            @foreach ($locales as $locale)
                                <label class="wb-nowrap">
                                    <input
                                        type="checkbox"
                                        name="locale_ids[]"
                                        value="{{ $locale->id }}"
                                        @checked($selectedLocaleIds->contains($locale->id))
                                        @disabled($locale->is_default)
                                    >
                                    <span>{{ strtoupper($locale->code) }} - {{ $locale->name }}@if ($locale->is_default) (Default) @endif</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">Back</a>
                        @if ($site->exists && isset($siteDeleteReport))
                            <a href="{{ route('admin.sites.delete', $site) }}" class="wb-btn wb-btn-danger" @if (! $siteDeleteReport->canDelete) aria-disabled="true" @endif>Delete Site</a>
                        @endif
                    </div>
                    <button type="submit" class="wb-btn wb-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection
