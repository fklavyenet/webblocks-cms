@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('shared_slots.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('title');
  $siteContext = $activeSite?->name ?? $adminText('all_sites');
  $siteDomain = $activeSite?->canonicalDomain();
    $siteContextDescription = $showAllSites
      ? $adminText('index_all_context')
      : $adminText('index_site_context', ['site' => $activeSite->name, 'domain' => $siteDomain ? ' ('.$siteDomain.')' : '']);
  $sharedSlotsReady = $sharedSlotsReady ?? true;
  $newSharedSlotUrl = $activeSite ? route('admin.shared-slots.create', ['site' => $activeSite->id]) : route('admin.shared-slots.create');
  $clearUrl = route('admin.shared-slots.index', $showAllSites ? ['site' => 'all'] : ['site' => $activeSite?->id]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $localizedPageTitle,
        'context' => '<span>'.e($siteContextDescription).'</span>',
        'count' => $sharedSlotsReady ? $totalCount : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $sharedSlotsReady)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('not_ready_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('not_ready_help') }}</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-filters', [
                    'action' => route('admin.shared-slots.index'),
                    'search' => [
                        'id' => 'shared_slots_search',
                        'name' => 'search',
                        'label' => $adminText('search'),
                        'value' => $filters['search'],
                        'placeholder' => $adminText('search_placeholder'),
                    ],
                    'selects' => [
                        [
                            'id' => 'shared_slots_site_context',
                            'name' => 'site',
                            'label' => $adminText('site'),
                            'selected' => $filters['site'],
                            'placeholder' => null,
                            'options' => collect($sites)->mapWithKeys(fn ($site) => [$site->id => $site->name])->all() + ['all' => $adminText('all_sites')],
                        ],
                        [
                            'id' => 'shared_slots_status',
                            'name' => 'status',
                            'label' => $adminText('status'),
                            'selected' => $filters['status'],
                            'placeholder' => $adminText('all_statuses'),
                            'options' => [
                                'active' => $adminText('active'),
                                'inactive' => $adminText('inactive'),
                            ],
                        ],
                        [
                            'id' => 'shared_slots_sort',
                            'name' => 'sort',
                            'label' => $adminText('sort_by'),
                            'selected' => $filters['sort'],
                            'options' => [
                                'updated_at' => $adminText('updated_at'),
                                'name' => $adminText('name'),
                                'handle' => $adminText('handle'),
                                'slot_name' => $adminText('slot'),
                                'public_shell' => $adminText('page_layout'),
                            ],
                        ],
                        [
                            'id' => 'shared_slots_direction',
                            'name' => 'direction',
                            'label' => $adminText('direction'),
                            'selected' => $filters['direction'],
                            'options' => [
                                'desc' => $adminText('descending'),
                                'asc' => $adminText('ascending'),
                            ],
                        ],
                    ],
                    'showReset' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'updated_at' || $filters['direction'] !== 'desc',
                    'resetUrl' => $clearUrl,
                    'applyLabel' => $adminText('apply'),
                ])
            </div>
        </div>

    @if ($sharedSlots->isEmpty())
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('shared_slots_for', ['site' => $siteContext]) }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                @if ($canCreateSharedSlots)
                    <a href="{{ $newSharedSlotUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('new_shared_slot') }}</a>
                @endif
            </div>

            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('no_shared_slots_found') }}</div>
                    <div class="wb-empty-text">{{ $adminText('empty_help', ['site' => strtolower($siteContext)]) }}</div>
                    @if ($canCreateSharedSlots)
                        <div class="wb-empty-action">
                            <a href="{{ $newSharedSlotUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('create_shared_slot') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('shared_slots_for', ['site' => $siteContext]) }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                @if ($canCreateSharedSlots)
                    <a href="{{ $newSharedSlotUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('new_shared_slot') }}</a>
                @endif
            </div>

            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('name') }}</th>
                                <th>{{ $adminText('handle') }}</th>
                                <th>{{ $adminText('site') }}</th>
                                <th>{{ $adminText('slot') }}</th>
                                <th>{{ $adminText('page_layout') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('updated') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sharedSlots as $sharedSlot)
                                <tr>
                                    <td><strong>{{ $sharedSlot->name }}</strong></td>
                                    <td><code>{{ $sharedSlot->handle }}</code></td>
                                    <td>{{ $sharedSlot->site?->name }}</td>
                                    <td>{{ $sharedSlot->slotLabel() }}</td>
                                    <td>{{ $sharedSlot->publicShellLabel() }}</td>
                                    <td><span class="wb-status-pill {{ $sharedSlot->statusBadgeClass() }}">{{ $sharedSlot->statusLabel() }}</span></td>
                                    <td>{{ $sharedSlot->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.shared-slots.edit', $sharedSlot) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_shared_slot') }}" aria-label="{{ $adminText('edit_shared_slot') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            <a href="{{ route('admin.shared-slots.blocks.edit', $sharedSlot) }}" class="wb-action-btn" title="{{ $adminText('edit_shared_slot_blocks') }}" aria-label="{{ $adminText('edit_shared_slot_blocks') }}"><i class="wb-icon wb-icon-layout" aria-hidden="true"></i></a>
                                            <form method="POST" action="{{ route('admin.shared-slots.destroy', $sharedSlot) }}" onsubmit="return confirm('{{ $adminText('delete_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_shared_slot') }}" aria-label="{{ $adminText('delete_shared_slot') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $sharedSlots, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
        </div>
    @endif
    @endif
@endsection
