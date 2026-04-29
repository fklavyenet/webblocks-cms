<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Card title, subtitle, description, and action label are translated per locale. URL and target stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">Title</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Description</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="action_label">Action label</label>
        <input id="action_label" name="action_label" class="wb-input" type="text" value="{{ old('action_label', $block->meta) }}">
    </div>
</div>
