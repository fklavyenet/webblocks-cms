@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);

    $selectedType = old('type', 'Issue');
    $typeLabel = static fn (string $type): string => $adminTranslator->admin('support.types.'.$type, $adminLocaleCode) === 'support.types.'.$type
        ? $type
        : $adminTranslator->admin('support.types.'.$type, $adminLocaleCode);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('support.new_title'), 'heading' => $adminText('support.new_title')])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('support.new_title'),
            'description' => $adminText('support.new_intro'),
        ])

        @include('webblocks-cms::admin.partials.flash')

        <div>
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.support.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i>{{ $adminText('support.back') }}
            </a>
        </div>

        @if (! $configured)
            <div class="wb-alert wb-alert-warning">
                <div>{{ $adminText('support.not_configured') }}</div>
            </div>
        @else
            <section class="wb-card">
                <form method="POST" action="{{ route('admin.support.store') }}">
                    @csrf

                    <div class="wb-card-body wb-stack wb-gap-4">
                        <div class="wb-field">
                            <label class="wb-label" for="supportType">{{ $adminText('support.col_type') }}</label>
                            <select id="supportType" name="type" class="wb-select">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ $typeLabel($type) }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="supportTitle">{{ $adminText('support.col_subject') }}</label>
                            <input id="supportTitle" class="wb-input" name="title" maxlength="255" value="{{ old('title') }}" required>
                            @error('title')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="supportBody">{{ $adminText('support.body_label') }}</label>
                            <textarea id="supportBody" class="wb-textarea" name="body" rows="8" maxlength="20000" required>{{ old('body') }}</textarea>
                            <p class="wb-text-muted wb-text-sm">{{ $adminText('support.body_help') }}</p>
                            @error('body')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="wb-card-footer">
                        <button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.submit') }}</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
