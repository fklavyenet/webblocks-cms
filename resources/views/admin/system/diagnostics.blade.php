@php
    $selected = (array) old('categories', $selectedCategories ?? ['system_summary', 'update_run', 'plugin_health', 'saved_health']);
    $text = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('diagnostics.'.$key, app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])
@section('content')
    @include('webblocks-cms::admin.partials.flash')
    <section class="wb-card">
        <div class="wb-card-header"><h2 class="wb-card-title">{{ $text('title') }}</h2></div>
        <div class="wb-card-body wb-stack wb-stack-4">
            <p>{{ $text('help') }}</p>
            <form method="post" action="{{ route('admin.system.diagnostics.prepare') }}" class="wb-stack wb-stack-3" data-wb-diagnostic-form>
                @csrf
                @if ($runId)<input type="hidden" name="run_id" value="{{ $runId }}">@endif
                <div role="group" aria-labelledby="diagnostic-categories">
                    <h3 id="diagnostic-categories">{{ $text('categories') }}</h3>
                    @foreach ($categories as $category)
                        <label class="wb-check"><input type="checkbox" name="categories[]" value="{{ $category }}" @checked(in_array($category, $selected, true))><span>{{ $text('category.'.$category) }}</span></label>
                    @endforeach
                </div>
                <p class="wb-text-sm wb-text-muted">{{ $text('errors_help') }}</p>
                <label class="wb-check"><input type="checkbox" name="prepare_consent" value="1" required><span>{{ $text('consent') }}</span></label>
                <div class="wb-cluster">
                    <button class="wb-btn wb-btn-primary" type="submit" disabled>{{ $text('prepare') }}</button>
                </div>
            </form>
            @isset($envelope)
                <div class="wb-alert wb-alert-info" role="status">{{ $text('review') }}</div>
                <pre><code>{{ $reportText }}</code></pre>
                <form method="post" action="{{ route('admin.system.diagnostics.download') }}">@csrf<input type="hidden" name="report" value="{{ $envelope }}"><button class="wb-btn wb-btn-secondary" type="submit">{{ $text('download') }}</button></form>
            @endisset
        </div>
    </section>
@endsection
