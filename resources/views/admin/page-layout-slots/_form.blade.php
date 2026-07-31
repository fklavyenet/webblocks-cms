@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $pageLayoutSlotFormLocale = app(AdminLocaleResolver::class)->locale();
    $pageLayoutSlotFormTranslator = app(CmsTranslator::class);
    $pageLayoutSlotFormText = static fn (string $key, array $replace = []) => $pageLayoutSlotFormTranslator->admin('page_layout_slot_form.'.$key, $pageLayoutSlotFormLocale, $replace);
    $isProtectedSystemSlot = (bool) ($pageLayout->is_system && $pageLayoutSlot->is_system);
    $showAdvancedTrustedHtml = $errors->hasAny(['before_html', 'start_html', 'end_html', 'after_html'])
        || filled(old('before_html', $pageLayoutSlot->before_html))
        || filled(old('start_html', $pageLayoutSlot->start_html))
        || filled(old('end_html', $pageLayoutSlot->end_html))
        || filled(old('after_html', $pageLayoutSlot->after_html));
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-text-sm wb-text-muted">
            {{ $pageLayoutSlotFormText('intro') }}
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $pageLayoutSlotFormText('slot_identity') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_slot_type_id">{{ $pageLayoutSlotFormText('slot_type') }}</label>
                    <select id="page_layout_slot_slot_type_id" name="slot_type_id" class="wb-select" @disabled($isProtectedSystemSlot)>
                        <option value="">{{ $pageLayoutSlotFormText('select_slot_type') }}</option>
                        @foreach ($slotTypes as $slotType)
                            <option value="{{ $slotType->id }}" @selected((int) old('slot_type_id', $pageLayoutSlot->slot_type_id) === (int) $slotType->id)>{{ $slotType->name }}</option>
                        @endforeach
                    </select>
                    @if ($isProtectedSystemSlot)
                        <input type="hidden" name="slot_type_id" value="{{ $pageLayoutSlot->slot_type_id }}">
                    @endif
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_slot_name">{{ $pageLayoutSlotFormText('slot_name') }}</label>
                    <input id="page_layout_slot_slot_name" name="slot_name" class="wb-input" type="text" value="{{ old('slot_name', $pageLayoutSlot->slot_name) }}" maxlength="100" @readonly($isProtectedSystemSlot) required>
                    <div class="wb-text-sm wb-text-muted">{!! $pageLayoutSlotFormText('slot_name_help') !!}</div>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_label">{{ $pageLayoutSlotFormText('label') }}</label>
                    <input id="page_layout_slot_label" name="label" class="wb-input" type="text" value="{{ old('label', $pageLayoutSlot->label) }}" maxlength="255">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="page_layout_slot_description">{{ $pageLayoutSlotFormText('description') }}</label>
                <textarea id="page_layout_slot_description" name="description" class="wb-textarea" rows="3">{{ old('description', $pageLayoutSlot->description) }}</textarea>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $pageLayoutSlotFormText('wrapper_markup') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-3">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_html_element">{{ $pageLayoutSlotFormText('html_element') }}</label>
                    <select id="page_layout_slot_html_element" name="html_element" class="wb-select">
                        @foreach (\WebBlocks\Cms\Support\Pages\LayoutMarkup::allowedElements() as $element)
                            <option value="{{ $element }}" @selected(old('html_element', $pageLayoutSlot->html_element ?: 'div') === $element)>{{ $element }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_html_id">{{ $pageLayoutSlotFormText('html_id') }}</label>
                    <input id="page_layout_slot_html_id" name="html_id" class="wb-input" type="text" value="{{ old('html_id', $pageLayoutSlot->html_id) }}" maxlength="255">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_css_classes">{{ $pageLayoutSlotFormText('css_classes') }}</label>
                    <input id="page_layout_slot_css_classes" name="css_classes" class="wb-input" type="text" value="{{ old('css_classes', $pageLayoutSlot->css_classes) }}" maxlength="1000">
                    <div class="wb-text-sm wb-text-muted wb-stack wb-gap-1">
                        <div>{{ $pageLayoutSlotFormText('css_classes_help') }}</div>
                        <div>{!! $pageLayoutSlotFormText('css_classes_examples') !!}</div>
                        <div>{!! $pageLayoutSlotFormText('sticky_headers_help') !!}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <details class="wb-card wb-card-muted" @if ($showAdvancedTrustedHtml) open @endif>
        <summary class="wb-card-header">
            <span class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-stack wb-gap-1">
                    <strong>{{ $pageLayoutSlotFormText('advanced_trusted_layout_html') }}</strong>
                    <span class="wb-text-sm wb-text-muted">{{ $pageLayoutSlotFormText('advanced_trusted_layout_html_help') }}</span>
                </span>
                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
            </span>
        </summary>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-text-sm wb-text-muted wb-stack wb-gap-1">
                <div>{{ $pageLayoutSlotFormText('before_slot_html_help') }}</div>
                <div>{{ $pageLayoutSlotFormText('slot_start_html_help') }}</div>
                <div>{{ $pageLayoutSlotFormText('slot_end_html_help') }}</div>
                <div>{{ $pageLayoutSlotFormText('after_slot_html_help') }}</div>
                <div>{{ $pageLayoutSlotFormText('unsafe_js_help') }}</div>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_before_html">{{ $pageLayoutSlotFormText('before_slot_html') }}</label>
                    <textarea id="page_layout_slot_before_html" name="before_html" class="wb-textarea wb-font-mono" rows="6">{{ old('before_html', $pageLayoutSlot->before_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_start_html">{{ $pageLayoutSlotFormText('slot_start_html') }}</label>
                    <textarea id="page_layout_slot_start_html" name="start_html" class="wb-textarea wb-font-mono" rows="6">{{ old('start_html', $pageLayoutSlot->start_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_end_html">{{ $pageLayoutSlotFormText('slot_end_html') }}</label>
                    <textarea id="page_layout_slot_end_html" name="end_html" class="wb-textarea wb-font-mono" rows="6">{{ old('end_html', $pageLayoutSlot->end_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_after_html">{{ $pageLayoutSlotFormText('after_slot_html') }}</label>
                    <textarea id="page_layout_slot_after_html" name="after_html" class="wb-textarea wb-font-mono" rows="6">{{ old('after_html', $pageLayoutSlot->after_html) }}</textarea>
                </div>
            </div>
        </div>
    </details>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $pageLayoutSlotFormText('status_ordering') }}</strong></div>
        <div class="wb-card-body">
            <div class="wb-grid wb-grid-3">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_is_required">{{ $pageLayoutSlotFormText('required') }}</label>
                    <select id="page_layout_slot_is_required" name="is_required" class="wb-select">
                        <option value="0" @selected(! (bool) old('is_required', $pageLayoutSlot->is_required))>{{ $pageLayoutSlotFormText('optional') }}</option>
                        <option value="1" @selected((bool) old('is_required', $pageLayoutSlot->is_required))>{{ $pageLayoutSlotFormText('required') }}</option>
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_is_active">{{ $pageLayoutSlotFormText('active') }}</label>
                    <select id="page_layout_slot_is_active" name="is_active" class="wb-select">
                        <option value="1" @selected((bool) old('is_active', $pageLayoutSlot->is_active ?? true))>{{ $pageLayoutSlotFormText('active') }}</option>
                        <option value="0" @selected(! (bool) old('is_active', $pageLayoutSlot->is_active ?? true))>{{ $pageLayoutSlotFormText('inactive') }}</option>
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_sort_order">{{ $pageLayoutSlotFormText('sort_order') }}</label>
                    <input id="page_layout_slot_sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $pageLayoutSlot->sort_order ?? 0) }}" required>
                </div>
            </div>
        </div>
    </div>
</div>
