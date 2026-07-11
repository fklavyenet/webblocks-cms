@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('slot_types.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('title');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $localizedPageTitle,
        'description' => $adminText('description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $adminText('name') }}</th>
                            <th>{{ $adminText('slug') }}</th>
                            <th>{{ $adminText('axis') }}</th>
                            <th>{{ $adminText('description_column') }}</th>
                            <th>{{ $adminText('blocks') }}</th>
                            <th>{{ $adminText('sort_order') }}</th>
                            <th>{{ $adminText('status') }}</th>
                            <th>{{ $adminText('system') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($slotTypes as $slotType)
                            <tr>
                                <td class="wb-nowrap"><strong>{{ $slotType->name }}</strong></td>
                                <td class="wb-nowrap"><code>{{ $slotType->slug }}</code></td>
                                <td class="wb-nowrap">{{ $slotType->axis ?: '-' }}</td>
                                <td class="wb-text-muted">{{ $slotType->description ?: '-' }}</td>
                                <td class="wb-nowrap">{{ $slotType->blocks_count }}</td>
                                <td class="wb-nowrap">{{ $slotType->sort_order }}</td>
                                <td><span class="wb-status-pill {{ $slotType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $adminText('status_'.$slotType->status) }}</span></td>
                                <td><span class="wb-status-pill {{ $slotType->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $slotType->is_system ? $adminText('system_status') : $adminText('user_status') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wb-card-footer wb-text-sm wb-text-muted">
            {{ $adminText('fixed_slots_help') }}
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $slotTypes])
    </div>
@endsection
