<div class="wb-table-wrap">
    <table class="wb-table wb-table-striped wb-table-hover">
        <thead>
            <tr>
                <th scope="col">{{ $text('language') }}</th>
                <th scope="col">{{ $text('source_version') }}</th>
                <th scope="col">{{ $text('status') }}</th>
                <th scope="col">{{ $text('actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($catalogs as $catalog)
                @php
                    $active = (int) $catalog->active_catalog_id === (int) $catalog->id;
                    $toggle = $active
                        ? (in_array($catalog->language_code, $packaged, true) ? 'use_package' : 'deactivate')
                        : 'activate';
                    $catalogLabel = $catalog->name.' ('.$catalog->language_code.')';
                @endphp
                <tr>
                    <th scope="row" class="wb-table-break"><span lang="{{ $catalog->language_code }}">{{ $catalog->name }}</span> <code>{{ $catalog->language_code }}</code></th>
                    <td>{{ $catalog->source_version }}</td>
                    <td><span class="wb-status-pill {{ $active ? 'wb-status-active' : 'wb-status-inactive' }}">{{ $text($active ? 'active' : 'inactive') }}</span></td>
                    <td class="wb-table-actions">
                        <div class="wb-action-group">
                            <a class="wb-action-btn wb-action-btn-view" href="{{ route('admin.system.panel-languages.show', $catalog->id) }}"
                                title="{{ $text('preview') }}" aria-label="{{ $text('preview') }} · {{ $catalogLabel }}">
                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ $active ? route('admin.system.panel-languages.deactivate', $catalog->language_code) : route('admin.system.panel-languages.activate', $catalog->id) }}">
                                @csrf
                                <input type="hidden" name="panel_language_tab" value="catalogs">
                                <button type="submit" class="wb-action-btn" title="{{ $text($toggle) }}" aria-label="{{ $text($toggle) }} · {{ $catalogLabel }}">
                                    <i class="wb-icon {{ $active ? 'wb-icon-eye-off' : 'wb-icon-check' }}" aria-hidden="true"></i>
                                </button>
                            </form>
                            @unless ($active)
                                <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-panel-catalog-{{ $catalog->id }}"
                                    aria-haspopup="dialog" title="{{ $text('delete') }}" aria-label="{{ $text('delete') }} · {{ $catalogLabel }}">
                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                </button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
