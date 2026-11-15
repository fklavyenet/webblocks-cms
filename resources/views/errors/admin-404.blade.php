@extends('webblocks-cms::layouts.guest')

@section('content')
    <div class="wb-auth-shell">
        <section class="wb-auth-card" aria-labelledby="panel-not-found-title">
            <header class="wb-auth-header">
                <h1 id="panel-not-found-title" class="wb-auth-header-title">{{ $title }}</h1>
                <p class="wb-auth-header-subtitle">{{ __('webblocks-cms::admin-feedback.not_found.body') }}</p>
            </header>
            <div class="wb-auth-body">
                <a class="wb-btn wb-btn-primary" href="{{ route('admin.dashboard') }}">{{ __('webblocks-cms::admin-feedback.not_found.back') }}</a>
            </div>
        </section>
    </div>
@endsection
