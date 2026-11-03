@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::jobs.http')])
@section('content')
    <h1>{{ __('webblocks-cms::jobs.http') }}</h1>
    <p class="wb-alert wb-alert-warning">{{ __('webblocks-cms::jobs.key_once') }}</p>
    <label for="job-http-key">{{ __('webblocks-cms::jobs.key') }}</label><input class="wb-input" id="job-http-key" type="text" readonly autocomplete="off" value="{{ $token }}">
    <p>{{ __('webblocks-cms::jobs.http_help') }}</p>
    <a class="wb-btn wb-btn-secondary" href="{{ route('admin.jobs.index', ['tab' => 'automation']) }}">{{ __('webblocks-cms::jobs.automation') }}</a>
@endsection
