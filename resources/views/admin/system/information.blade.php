@extends('webblocks-cms::layouts.admin')

@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $text = fn (string $key, array $replace = []) => $adminTranslator->get('admin.system_information.'.$key, $adminLocale, $replace);
@endphp

@section('title', $text('title'))

@section('content')
  <div class="wb-page-header">
    <div>
      <h1>{{ $text('title') }}</h1>
      <p class="wb-text-muted">{{ $text('description') }}</p>
    </div>
  </div>

  <div class="wb-card">
    <div class="wb-card-body">
      <div class="wb-table-wrap">
        <table class="wb-table wb-table-striped">
        <thead>
          <tr>
            <th scope="col">{{ $text('property') }}</th>
            <th scope="col">{{ $text('value') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($information as $key => $value)
            <tr>
              <th scope="row" class="wb-table-key">{{ $text($key) }}</th>
              <td>
                @if ($key === 'debug_mode')
                  <span class="wb-status-pill {{ $value ? 'wb-status-pending' : 'wb-status-active' }}">
                    {{ $text($value ? 'enabled' : 'disabled') }}
                  </span>
                @else
                  {{ $value }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
