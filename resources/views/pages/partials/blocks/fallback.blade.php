@php
    $slug = $block->typeSlug() ?? 'block';
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $items = collect($settings['items'] ?? $settings['cards'] ?? $settings['entries'] ?? []);
    $rows = collect($settings['rows'] ?? []);
    $options = collect($settings['options'] ?? []);
    $asset = $block->downloadAsset() ?? $block->attachmentAsset() ?? $block->asset;
    $assetUrl = $asset?->url();
    $page = $block->page;
    $publishedPages = \App\Models\Page::query()
        ->where('status', 'published')
        ->with(['translations', 'site'])
        ->orderBy('title')
        ->get();
    $pageIndex = $publishedPages->search(fn ($candidate) => $candidate->id === $page?->id);
    $previousPage = $pageIndex !== false && $pageIndex > 0 ? $publishedPages[$pageIndex - 1] : null;
    $nextPage = $pageIndex !== false && $pageIndex < ($publishedPages->count() - 1) ? $publishedPages[$pageIndex + 1] : null;
    $relatedPages = $publishedPages
        ->reject(fn ($candidate) => $candidate->id === $page?->id)
        ->filter(fn ($candidate) => $candidate->page_type === $page?->page_type || in_array($candidate->slug, $settings['related_slugs'] ?? [], true))
        ->take(3)
        ->values();
    $listItems = $items->isNotEmpty()
        ? $items->map(fn ($item) => is_array($item) ? ($item['label'] ?? $item['title'] ?? $item['content'] ?? null) : $item)->filter()->values()
        : collect(preg_split('/\r\n|\r|\n/', (string) $block->content))->map(fn ($item) => trim((string) $item))->filter()->values();
@endphp

@switch($slug)
    @case('list')
        <div class="wb-stack wb-gap-2">
            @if ($block->title)
                <h3>{{ $block->title }}</h3>
            @endif
            <ul class="wb-stack wb-gap-1">
                @foreach ($listItems as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        @break

    @case('table')
    @case('comparison')
        <div class="wb-stack wb-gap-2">
            @if ($block->title)
                <h3>{{ $block->title }}</h3>
            @endif
            @if ($rows->isNotEmpty())
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        @foreach ($rows as $rowIndex => $row)
                            @if ($rowIndex === 0)
                                <thead>
                                    <tr>
                                        @foreach (($row['columns'] ?? $row) as $column)
                                            <th>{{ is_array($column) ? ($column['label'] ?? '') : $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                            @else
                                <tr>
                                    @foreach (($row['columns'] ?? $row) as $column)
                                        <td>{{ is_array($column) ? ($column['label'] ?? '') : $column }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                                </tbody>
                    </table>
                </div>
            @endif
        </div>
        @break

    @case('accordion')
    @case('faq-list')
    @case('comments')
    @case('stats')
    @case('logo-cloud')
    @case('timeline')
    @case('feature-grid')
    @case('team')
    @case('product-grid')
    @case('pricing')
        <section class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($block->title || $block->content)
                    <div class="wb-stack wb-gap-1">
                        @if ($block->title)
                            <h3>{{ $block->title }}</h3>
                        @endif
                        @if ($block->content)
                            <p>{{ $block->content }}</p>
                        @endif
                    </div>
                @endif

                @if ($slug === 'accordion' && $items->isNotEmpty())
                    <div class="wb-stack wb-gap-2">
                        @foreach ($items as $item)
                            <details class="wb-card">
                                <summary class="wb-card-header"><strong>{{ $item['title'] ?? 'Item' }}</strong></summary>
                                <div class="wb-card-body">{{ $item['content'] ?? '' }}</div>
                            </details>
                        @endforeach
                    </div>
                @elseif ($slug === 'timeline' && $items->isNotEmpty())
                    <div class="wb-stack wb-gap-2">
                        @foreach ($items as $item)
                            <div class="wb-card">
                                <div class="wb-card-body wb-stack wb-gap-1">
                                    <strong>{{ $item['title'] ?? 'Milestone' }}</strong>
                                    @if (! empty($item['subtitle']))
                                        <span class="wb-text-sm wb-text-muted">{{ $item['subtitle'] }}</span>
                                    @endif
                                    <p>{{ $item['content'] ?? '' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($items->isNotEmpty())
                    <div class="{{ in_array($slug, ['team', 'feature-grid', 'product-grid', 'pricing', 'logo-cloud'], true) ? 'wb-grid wb-grid-3' : 'wb-grid wb-grid-2' }}">
                        @foreach ($items as $item)
                            <div class="wb-card">
                                <div class="wb-card-body wb-stack wb-gap-1">
                                    @if (! empty($item['media_url']))
                                        <img src="{{ $item['media_url'] }}" alt="{{ $item['title'] ?? 'Media item' }}">
                                    @endif
                                    <strong>{{ $item['title'] ?? 'Item' }}</strong>
                                    @if (! empty($item['subtitle']))
                                        <span class="wb-text-sm wb-text-muted">{{ $item['subtitle'] }}</span>
                                    @endif
                                    @if (! empty($item['content']))
                                        <p>{{ $item['content'] }}</p>
                                    @endif
                                    @if (! empty($item['url']) && ! empty($item['url_label']))
                                        <a href="{{ $item['url'] }}" class="wb-link">{{ $item['url_label'] }}</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
        @break

    @case('product-card')
    @case('metric-card')
    @case('testimonial')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                @if ($assetUrl)
                    <img src="{{ $assetUrl }}" alt="{{ $block->title ?: 'Block media' }}">
                @endif
                @if ($block->title)
                    <strong>{{ $block->title }}</strong>
                @endif
                @if ($block->subtitle)
                    <span class="wb-text-sm wb-text-muted">{{ $block->subtitle }}</span>
                @endif
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
            </div>
        </div>
        @break

    @case('container')
    @case('stack')
    @case('grid')
    @case('card-group')
    @case('split')
    @case('form')
        <section class="wb-card {{ in_array($slug, ['container', 'stack', 'form'], true) ? 'wb-card-muted' : '' }}">
            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($block->title || $block->content)
                    <div class="wb-stack wb-gap-1">
                        @if ($block->title)
                            <h3>{{ $block->title }}</h3>
                        @endif
                        @if ($block->content)
                            <p>{{ $block->content }}</p>
                        @endif
                    </div>
                @endif

                @if ($slug === 'form')
                    <form class="wb-stack wb-gap-3">
                        @foreach ($block->children as $child)
                            @include('pages.partials.block', ['block' => $child])
                        @endforeach
                    </form>
                @else
                    <div class="{{ $slug === 'split' ? 'wb-grid wb-grid-2' : (in_array($slug, ['grid', 'card-group'], true) ? 'wb-grid wb-grid-3' : 'wb-stack wb-gap-3') }}">
                        @foreach ($block->children as $child)
                            @if (in_array($slug, ['grid', 'card-group'], true))
                                <div class="wb-card">
                                    <div class="wb-card-body">
                                        @include('pages.partials.block', ['block' => $child])
                                    </div>
                                </div>
                            @else
                                @include('pages.partials.block', ['block' => $child])
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
        @break

    @case('slider')
        @php
            $sliderAssets = $block->galleryAssets();
        @endphp
        <section class="wb-stack wb-gap-3">
            @if ($block->title)
                <h3>{{ $block->title }}</h3>
            @endif
            <div class="wb-slider wb-card wb-card-muted" data-wb-slider>
                <div class="wb-slider-track" data-wb-slider-track>
                    @foreach ($sliderAssets as $sliderAsset)
                        @if ($sliderAsset->url())
                            <article class="wb-slider-slide" data-wb-slider-slide>
                                <div class="wb-card-body wb-stack wb-gap-3">
                                    <img src="{{ $sliderAsset->url() }}" alt="{{ $sliderAsset->alt_text ?: $sliderAsset->title ?: 'Slider image' }}">
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $sliderAsset->title ?: $sliderAsset->filename }}</strong>
                                        @if ($sliderAsset->caption)
                                            <p>{{ $sliderAsset->caption }}</p>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach
                </div>
                @if ($sliderAssets->count() > 1)
                    <div class="wb-card-body wb-slider-controls">
                        <button type="button" class="wb-btn wb-btn-secondary" data-wb-slider-prev>Previous</button>
                        <div class="wb-slider-dots" role="tablist" aria-label="{{ $block->title ?: 'Slider' }} slides">
                            @foreach ($sliderAssets as $sliderAsset)
                                @if ($sliderAsset->url())
                                    <button type="button" class="wb-slider-dot {{ $loop->first ? 'is-active' : '' }}" data-wb-slider-dot aria-label="Go to slide {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"></button>
                                @endif
                            @endforeach
                        </div>
                        <button type="button" class="wb-btn wb-btn-secondary" data-wb-slider-next>Next</button>
                    </div>
                @endif
            </div>
            @if ($block->subtitle)
                <p class="wb-text-sm wb-text-muted">{{ $block->subtitle }}</p>
            @endif
        </section>
        @break

    @case('video')
    @case('audio')
    @case('file')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <strong>{{ $block->title ?: str($slug)->headline() }}</strong>
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
                @if ($assetUrl || $block->url)
                    <a href="{{ $assetUrl ?: $block->url }}" class="wb-btn wb-btn-secondary">Open asset</a>
                @endif
                @if ($asset)
                    <span class="wb-text-sm wb-text-muted">{{ $asset->filename }}{{ $asset->mime_type ? ' | '.$asset->mime_type : '' }}</span>
                @endif
            </div>
        </div>
        @break

    @case('map')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <strong>{{ $block->title ?: 'Map' }}</strong>
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
                @if ($block->url)
                    <a href="{{ $block->url }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Open map</a>
                @endif
            </div>
        </div>
        @break

    @case('breadcrumb')
        <nav aria-label="Breadcrumb">
            <ol class="wb-cluster wb-cluster-2 wb-text-sm">
                <li><a href="{{ $homePath }}" class="wb-link">Home</a></li>
                <li>/</li>
                <li>{{ $page?->title }}</li>
            </ol>
        </nav>
        @break

    @case('pagination')
        <nav class="wb-cluster wb-cluster-between wb-cluster-2" aria-label="Pagination">
            <div>
                @if ($previousPage)
                    <a href="{{ $previousPage->publicPath() }}" class="wb-btn wb-btn-secondary">Previous: {{ $previousPage->title }}</a>
                @endif
            </div>
            <div>
                @if ($nextPage)
                    <a href="{{ $nextPage->publicPath() }}" class="wb-btn wb-btn-secondary">Next: {{ $nextPage->title }}</a>
                @endif
            </div>
        </nav>
        @break

    @case('input')
    @case('search')
        <div class="wb-stack wb-gap-1">
            @if ($block->title)
                <label>{{ $block->title }}</label>
            @endif
            <input type="{{ $slug === 'search' ? 'search' : 'text' }}" class="wb-input" placeholder="{{ $block->subtitle ?: $block->content }}">
        </div>
        @break

    @case('textarea')
        <div class="wb-stack wb-gap-1">
            @if ($block->title)
                <label>{{ $block->title }}</label>
            @endif
            <textarea class="wb-textarea" rows="4" placeholder="{{ $block->subtitle ?: $block->content }}"></textarea>
        </div>
        @break

    @case('select')
        <div class="wb-stack wb-gap-1">
            @if ($block->title)
                <label>{{ $block->title }}</label>
            @endif
            <select class="wb-select">
                @foreach ($options as $option)
                    <option>{{ is_array($option) ? ($option['label'] ?? '') : $option }}</option>
                @endforeach
            </select>
        </div>
        @break

    @case('checkbox-group')
    @case('radio-group')
        <fieldset class="wb-stack wb-gap-1">
            @if ($block->title)
                <legend>{{ $block->title }}</legend>
            @endif
            @foreach ($options as $index => $option)
                <label>
                    <input type="{{ $slug === 'checkbox-group' ? 'checkbox' : 'radio' }}" @if ($slug === 'radio-group') name="{{ $block->id }}" @endif>
                    {{ is_array($option) ? ($option['label'] ?? '') : $option }}
                </label>
            @endforeach
        </fieldset>
        @break

    @case('submit')
        <button type="submit" class="wb-btn wb-btn-primary">{{ $block->title ?: 'Submit' }}</button>
        @break

    @case('cart-summary')
    @case('checkout-summary')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $block->title ?: str($slug)->headline() }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                @foreach ($items as $item)
                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <span>{{ $item['title'] ?? 'Line item' }}</span>
                        <strong>{{ $item['subtitle'] ?? '' }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
        @break

    @case('social-links')
        <div class="wb-cluster wb-cluster-2">
            @foreach ($items as $item)
                <a href="{{ $item['url'] ?? '#' }}" class="wb-link" target="_blank" rel="noopener noreferrer">{{ $item['title'] ?? 'Link' }}</a>
            @endforeach
        </div>
        @break

    @case('share-buttons')
        <div class="wb-cluster wb-cluster-2">
            @foreach ($items as $item)
                <a href="{{ $item['url'] ?? '#' }}" class="wb-btn wb-btn-secondary">{{ $item['title'] ?? 'Share' }}</a>
            @endforeach
        </div>
        @break

    @case('page-title')
        <h1>{{ $page?->title }}</h1>
        @break

    @case('page-content')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $block->title ?: 'Page Summary' }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-1">
                <div><strong>Title:</strong> {{ $page?->title }}</div>
                <div><strong>Path:</strong> {{ $page?->publicPath() }}</div>
                <div><strong>Type:</strong> {{ $page?->page_type ?: 'page' }}</div>
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
            </div>
        </div>
        @break

    @case('page-meta')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $block->title ?: 'Page Meta' }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-1">
                <div><strong>Title:</strong> {{ $page?->title }}</div>
                <div><strong>Slug:</strong> {{ $page?->slug }}</div>
                <div><strong>Path:</strong> {{ $page?->publicPath() }}</div>
                <div><strong>Status:</strong> {{ $page?->status }}</div>
                <div><strong>Page Type:</strong> {{ $page?->page_type }}</div>
            </div>
        </div>
        @break

    @case('auth-form')
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <strong>{{ $block->title ?: 'Sign in' }}</strong>
                <p>{{ $block->content ?: 'Use the existing authentication screens to continue.' }}</p>
                <div class="wb-cluster wb-cluster-2">
                    <a href="{{ route('login') }}" class="wb-btn wb-btn-primary">Sign In</a>
                    <a href="{{ route('register') }}" class="wb-btn wb-btn-secondary">Create Account</a>
                </div>
            </div>
        </div>
        @break

    @case('cookie-notice')
        <div class="wb-alert wb-alert-info">
            <div>
                <div class="wb-alert-title">{{ $block->title ?: 'Cookie notice' }}</div>
                <div>{{ $block->content ?: 'This site uses essential cookies for core functionality such as session handling and sign-in.' }}</div>
            </div>
        </div>
        @break

    @default
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $block->typeName() }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                @if ($block->title)
                    <strong>{{ $block->title }}</strong>
                @endif
                @if ($block->subtitle)
                    <span class="wb-text-sm wb-text-muted">{{ $block->subtitle }}</span>
                @endif
                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
                @if ($block->url)
                    <a href="{{ $block->url }}" class="wb-link">{{ $block->url }}</a>
                @endif
                @if ($assetUrl)
                    <a href="{{ $assetUrl }}" class="wb-link">{{ $asset->title ?: $asset->filename }}</a>
                @endif
            </div>
        </div>
@endswitch

@if ($block->children->isNotEmpty() && ! in_array($slug, ['container', 'stack', 'grid', 'card-group', 'split', 'form'], true))
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
