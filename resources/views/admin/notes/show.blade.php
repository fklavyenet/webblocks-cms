@php
    $text = fn (string $key) => __('webblocks-cms::notes.'.$key);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('read_note'), 'heading' => $text('read_note')])

@section('content')
    <div class="wb-page-header">
        <div class="wb-page-header-main">
            <h1 class="wb-page-header-title wb-cms-note-heading">{{ $record->title }}</h1>
        </div>
    </div>
    @include('webblocks-cms::admin.notes.feedback')
    @if ($errors->getBag('note')->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->getBag('note')->first() }}</div>
    @endif

    <article class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                        <tr><th scope="row">{{ $text('author') }}</th><td>{{ $record->author?->name ?? $text('unavailable_account') }}</td></tr>
                        <tr><th scope="row">{{ $text('recipient') }}</th><td>{{ $record->is_private ? ($record->recipient?->name ?? $text('author_only')) : $text('everyone') }}</td></tr>
                        <tr><th scope="row">{{ $text('created') }}</th><td><time datetime="{{ $record->created_at->toIso8601String() }}">{{ $record->created_at->format('Y-m-d H:i') }}</time></td></tr>
                        <tr><th scope="row">{{ $text('status') }}</th><td>{{ $text($record->is_read ? 'read' : 'unread') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="wb-cms-note-body">{{ $record->body }}</div>
        </div>
        <div class="wb-card-footer wb-cluster wb-flex-wrap">
            <form method="POST" action="{{ route('admin.notes.read-state', ['note' => $record->id] + $filters) }}">
                @csrf @method('PATCH')
                <button type="submit" name="read" value="{{ $record->is_read ? '0' : '1' }}" class="wb-btn wb-btn-primary">
                    {{ $text($record->is_read ? 'mark_unread' : 'mark_read') }}
                </button>
            </form>
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.notes.index', $filters) }}">{{ $text('back') }}</a>
        </div>
    </article>
@endsection
