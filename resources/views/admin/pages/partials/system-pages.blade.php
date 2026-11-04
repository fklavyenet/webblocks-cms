@if ($activeSite && $canCreatePage)
<div class="wb-card wb-accordion" data-wb-accordion>
    <details class="wb-accordion-item">
        <summary class="wb-accordion-trigger" id="system-pages-trigger">
            <span>{{ __('webblocks-cms::system_pages.title') }}</span>
        </summary>
        <div class="wb-accordion-body">
        <div id="system-pages-panel" role="region" aria-labelledby="system-pages-trigger">
            <div class="wb-stack wb-gap-3">
                <p>{{ __('webblocks-cms::system_pages.help') }}</p>
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead><tr><th scope="col">{{ __('webblocks-cms::system_pages.screen') }}</th><th scope="col">{{ __('webblocks-cms::system_pages.state') }}</th><th scope="col">{{ __('webblocks-cms::admin.common.actions') }}</th></tr></thead>
                        <tbody>
                        @foreach ($systemPageDefinitions as $definition)
                            @php($design = $systemPageBindings->get($definition->key)?->page)
                            <tr>
                                <th scope="row">{{ $definition->label() }}</th>
                                <td>{{ $design ? $design->workflowLabel() : __('webblocks-cms::system_pages.default') }}</td>
                                <td>
                                    @if ($design)
                                        <a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ route('admin.pages.edit', $design) }}">{{ __('webblocks-cms::system_pages.edit') }}</a>
                                    @else
                                        <form method="POST" action="{{ route('admin.pages.system.store') }}">
                                            @csrf
                                            <input type="hidden" name="site_id" value="{{ $activeSite->id }}">
                                            <input type="hidden" name="key" value="{{ $definition->key }}">
                                            <button class="wb-btn wb-btn-secondary wb-btn-sm" type="submit">{{ __('webblocks-cms::system_pages.prepare') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::system_pages.maintenance_help') }}</p>
            </div>
        </div>
    </div>
    </details>
</div>
@endif
