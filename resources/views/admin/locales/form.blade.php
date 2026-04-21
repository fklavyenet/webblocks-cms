@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Keep locale setup small and safe. The default locale always remains enabled.',
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
                            <label for="locale_code">Code</label>
                            <input id="locale_code" name="code" class="wb-input" type="text" value="{{ old('code', $locale->code) }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="locale_name">Name</label>
                            <input id="locale_name" name="name" class="wb-input" type="text" value="{{ old('name', $locale->name) }}" required>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <label class="wb-nowrap">
                                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $locale->is_default))>
                                <span>Default</span>
                            </label>

                            <label class="wb-nowrap">
                                <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $locale->exists ? $locale->is_enabled : true)) @disabled($locale->is_default)>
                                <span>Enabled</span>
                            </label>

                            @if ($locale->is_default)
                                <div class="wb-text-sm wb-text-muted">The default locale remains enabled automatically.</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
                    <a href="{{ route('admin.locales.index') }}" class="wb-btn wb-btn-secondary">Back</a>
                    <button type="submit" class="wb-btn wb-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection
