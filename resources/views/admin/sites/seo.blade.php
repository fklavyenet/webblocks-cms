@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $seoText = static fn (string $key, array $replace = []) => $adminTranslator->get('seo.'.$key, $adminLocale, $replace);
  $workspaceText = static fn (string $key) => $adminTranslator->get('seo_workspace.'.$key, $adminLocale);
  $seoTab = $filters['tab'] ?? 'findings';
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $seoText('audit_title').' · '.$site->name, 'heading' => $seoText('audit_title')])
@section('content')
  @include('webblocks-cms::admin.partials.page-header', [
    'title' => $seoText('audit_title').' · '.$site->name,
    'description' => $seoText('nonblocking'),
    'actions' => '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.sites.edit', $site)).'">'.e($workspaceText('back_to_site')).'</a>',
  ])
  @include('webblocks-cms::admin.partials.flash')
  <div class="wb-stack wb-gap-4" data-wb-seo-workspace>
    <section class="wb-card" aria-labelledby="seo-audit-scope">
      <div class="wb-card-header"><h2 id="seo-audit-scope" class="wb-m-0 wb-text-base">{{ $seoText('scope') }}</h2></div>
      <div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ $seoText('saved_scope') }}</p>
        <p>{{ $seoText('audit_scope') }}</p>
        <p class="wb-text-sm wb-text-muted">{{ $workspaceText('worker_help') }}</p>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
          <form method="POST" action="{{ route('admin.sites.seo-audits.store', $site) }}">@csrf<input type="hidden" name="tab" value="{{ $seoTab }}" data-wb-seo-tab><button class="wb-btn wb-btn-primary" type="submit">{{ $workspaceText('start') }}</button></form>
          <a class="wb-btn wb-btn-secondary" data-wb-seo-refresh href="{{ route('admin.sites.seo-audits.index', ['site' => $site, ...$filters]) }}">{{ $workspaceText('refresh') }}</a>
          @if ($audit)
            @foreach (match ($audit['state']) { 'queued', 'running' => ['pause'], 'paused' => ['resume'], 'partial', 'failed' => ['retry'], default => [] } as $seoAction)
              <form method="POST" action="{{ route('admin.sites.seo-audits.transition', ['site' => $site, 'seoAudit' => $audit['id'], 'action' => $seoAction]) }}">@csrf<input type="hidden" name="tab" value="{{ $seoTab }}" data-wb-seo-tab><button class="wb-btn wb-btn-secondary" type="submit">{{ $workspaceText($seoAction) }}</button></form>
            @endforeach
          @endif
        </div>
      </div>
    </section>
    @if (! $audit)
      <p class="wb-alert wb-alert-info">{{ $workspaceText('idle') }}</p>
    @else
      @if ($audit['stale'] || $audit['state'] === 'stale')<div class="wb-alert wb-alert-warning" role="status">{{ $seoText('stale_report') }}</div>@endif
      @if (! $audit['complete'])<div class="wb-alert wb-alert-info" role="status">{{ $workspaceText('incomplete') }}</div>@endif
      <section class="wb-card" aria-labelledby="seo-audit-progress">
        <div class="wb-card-header"><h2 id="seo-audit-progress" class="wb-m-0 wb-text-base">{{ $workspaceText('progress') }} #{{ $audit['id'] }}</h2></div>
        <div class="wb-card-body wb-stack wb-gap-2">
          <p>{{ $workspaceText('states.'.$audit['state']) }} · {{ $workspaceText('phases.'.$audit['phase']) }} · {{ $audit['processed'] }} / {{ $audit['total'] }}</p>
          <p class="wb-text-sm wb-text-muted">{{ $workspaceText('task_scope') }}</p>
          <dl class="wb-stack wb-gap-1">
            <dt>{{ $workspaceText('started_at') }}</dt><dd>{{ $audit['created_at'] }}</dd>
            <dt>{{ $workspaceText('completed_at') }}</dt><dd>{{ $audit['completed_at'] ?? '—' }}</dd>
            <dt>{{ $workspaceText('version') }}</dt><dd>{{ $audit['cms_version'] }} · <code>{{ $audit['source_version'] }}</code></dd>
            <dt>{{ $seoText('language') }}</dt><dd>{{ $audit['locales']->implode(', ') }}</dd>
          </dl>
        </div>
      </section>
      <form method="GET" action="{{ route('admin.sites.seo-audits.index', $site) }}" class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
          <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <div><label for="seo-audit-history">{{ $workspaceText('history') }}</label><select class="wb-select" id="seo-audit-history" name="audit">@foreach ($history as $previous)<option value="{{ $previous->id }}" @selected($audit['id'] === $previous->id)>#{{ $previous->id }} · {{ $workspaceText('states.'.$previous->state) }}</option>@endforeach</select></div>
            <div><label for="seo-filter-page">{{ $workspaceText('page_id') }}</label><input class="wb-input" id="seo-filter-page" name="page_id" type="number" min="0" value="{{ $filters['page_id'] ?? '' }}"></div>
            <div><label for="seo-filter-locale">{{ $seoText('language') }}</label><select class="wb-select" id="seo-filter-locale" name="locale"><option value="">{{ $workspaceText('all') }}</option>@foreach ($site->enabledLocales as $locale)<option value="{{ $locale->code }}" @selected(($filters['locale'] ?? '') === $locale->code)>{{ $locale->name }}</option>@endforeach</select></div>
            <div><label for="seo-filter-status">{{ $seoText('page_status') }}</label><select class="wb-select" id="seo-filter-status" name="status"><option value="">{{ $workspaceText('all') }}</option>@foreach (['draft', 'in_review', 'published', 'archived', 'navigation'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $workspaceText('page_states.'.$status) }}</option>@endforeach</select></div>
            <div><label for="seo-filter-category">{{ $workspaceText('category') }}</label><select class="wb-select" id="seo-filter-category" name="category"><option value="">{{ $workspaceText('all') }}</option>@foreach (['technical', 'editorial', 'not_checked'] as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $seoText($category === 'not_checked' ? 'not_checked_title' : $category) }}</option>@endforeach</select></div>
            <div><label for="seo-filter-code">{{ $workspaceText('code') }}</label><input class="wb-input" id="seo-filter-code" name="code" value="{{ $filters['code'] ?? '' }}" maxlength="64" pattern="[a-z_]*"></div>
            <div><label for="seo-filter-link">{{ $workspaceText('link_state') }}</label><select class="wb-select" id="seo-filter-link" name="link_state"><option value="">{{ $workspaceText('all') }}</option>@foreach (['ok','external','outside_scope','automatic_not_checked','missing_page','unpublished_target','protected_target','wrong_locale','old_url','missing_target_translation'] as $linkState)<option value="{{ $linkState }}" @selected(($filters['link_state'] ?? '') === $linkState)>{{ $seoText('link_states.'.$linkState) }}</option>@endforeach</select></div>
          </div>
          <input type="hidden" name="tab" value="{{ $seoTab }}" data-wb-seo-tab>
          <div><button class="wb-btn wb-btn-secondary" type="submit">{{ $workspaceText('filter') }}</button></div>
          @if ($errors->any())<div class="wb-alert wb-alert-danger">{{ $errors->first() }}</div>@endif
        </div>
      </form>
      <div class="wb-tabs" data-wb-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="{{ $seoText('audit_title') }}">
          @foreach (['findings', 'pages', 'links'] as $panel)<button class="wb-tabs-btn {{ $seoTab === $panel ? 'is-active' : '' }}" type="button" data-wb-tab="seo-{{ $panel }}" role="tab" aria-selected="{{ $seoTab === $panel ? 'true' : 'false' }}">{{ $workspaceText($panel) }} ({{ ${$panel}->total() }})</button>@endforeach
        </div>
        <div class="wb-tabs-panels">
          <section class="wb-tabs-panel {{ $seoTab === 'findings' ? 'is-active' : '' }}" id="seo-findings" role="tabpanel" @if ($seoTab !== 'findings') hidden @endif>
            <div class="wb-stack wb-gap-3">
              @forelse ($findings as $finding)
                <article class="wb-card"><div class="wb-card-body wb-stack wb-gap-2">
                  <p>{{ $finding->message }}</p>
                  <p class="wb-text-sm wb-text-muted">#{{ $finding->page_id }} · {{ $finding->locale }} · {{ $workspaceText('page_states.'.$finding->page_status) }} · <code>{{ $finding->code }}</code> · {{ $seoText('count') }}: {{ $finding->occurrences }}</p>
                  @if ($finding->target)<code>{{ $finding->target }}</code>@endif
                  @if ($finding->example)<p>{{ $seoText('example') }}: {{ $finding->example }}</p>@endif
                  @if ($finding->action)<a class="wb-link" href="{{ $finding->action['href'] }}">{{ $seoText('open_target') }}</a>@endif
                </div></article>
              @empty<p>{{ $workspaceText('empty') }}</p>@endforelse
              @include('webblocks-cms::admin.partials.pagination', ['paginator' => $findings->appends(['tab' => 'findings'])])
            </div>
          </section>
          <section class="wb-tabs-panel {{ $seoTab === 'pages' ? 'is-active' : '' }}" id="seo-pages" role="tabpanel" @if ($seoTab !== 'pages') hidden @endif>
            <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>{{ $workspaceText('page_id') }}</th><th>{{ $seoText('language') }}</th><th>{{ $seoText('page_status') }}</th><th>{{ $workspaceText('result') }}</th><th>{{ $seoText('checked_at') }}</th><th>{{ $seoText('metadata_title') }}</th></tr></thead><tbody>
              @foreach ($pages as $row)<tr><td>@if ($row->page_id)<a href="{{ route('admin.pages.edit', ['page' => $row->page_id, 'tab' => 'seo', 'seo_locale' => $row->locale]) }}">#{{ $row->page_id }}</a>@else{{ $workspaceText('navigation_definition') }}@endif</td><td>{{ $row->locale }}</td><td>{{ $workspaceText('page_states.'.$row->page_status) }}</td><td>{{ $workspaceText('results.'.$row->state) }}</td><td>{{ $row->checked_at ?? '—' }}</td><td>{{ $row->title ?? '—' }}</td></tr>@endforeach
            </tbody></table></div>
            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $pages->appends(['tab' => 'pages'])])
          </section>
          <section class="wb-tabs-panel {{ $seoTab === 'links' ? 'is-active' : '' }}" id="seo-links" role="tabpanel" @if ($seoTab !== 'links') hidden @endif>
            <p class="wb-alert wb-alert-info">{{ $workspaceText('links_scope') }}</p>
            <div class="wb-stack wb-gap-3">
              @forelse ($links as $link)<article class="wb-card"><div class="wb-card-body wb-stack wb-gap-2">
                <p>{{ $link->message }}</p><p>#{{ $link->page_id }} · {{ $link->locale }} · {{ $workspaceText($link->source === 'rendered' ? 'rendered' : 'navigation_definition') }}</p>
                @if (! $link->is_public_source)<p class="wb-text-sm wb-text-muted">{{ $workspaceText('saved_only') }}</p>@endif<p>{{ $link->text }}</p><code>{{ $link->href }}</code>
                @if ($link->target_page_id)<p>{{ $workspaceText('target_page') }}: #{{ $link->target_page_id }} · {{ $link->target_locale }}</p>@endif
                @if ($link->suggested_url)<p>{{ $workspaceText('suggested_url') }}: <code>{{ $link->suggested_url }}</code></p>@endif
                @if ($link->location)<code>{{ $link->location }}</code>@endif
                @if ($link->source_href)<a class="wb-link" href="{{ $link->source_href }}">{{ $seoText('open_target') }}</a>@endif
              </div></article>@empty<p>{{ $workspaceText('empty') }}</p>@endforelse
              @include('webblocks-cms::admin.partials.pagination', ['paginator' => $links->appends(['tab' => 'links'])])
            </div>
          </section>
        </div>
      </div>
    @endif
  </div>
@endsection
