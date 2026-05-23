@php
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
            Page Layout Slots define how each public slot wrapper renders. Wrapper markup should stay focused on layout structure, while advanced trusted HTML stays limited to wrapper-adjacent layout markup only. Scripts and unsafe JavaScript are not allowed.
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Slot Identity</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_slot_type_id">Slot Type</label>
                    <select id="page_layout_slot_slot_type_id" name="slot_type_id" class="wb-select" @disabled($isProtectedSystemSlot)>
                        <option value="">Select Slot Type</option>
                        @foreach ($slotTypes as $slotType)
                            <option value="{{ $slotType->id }}" @selected((int) old('slot_type_id', $pageLayoutSlot->slot_type_id) === (int) $slotType->id)>{{ $slotType->name }}</option>
                        @endforeach
                    </select>
                    @if ($isProtectedSystemSlot)
                        <input type="hidden" name="slot_type_id" value="{{ $pageLayoutSlot->slot_type_id }}">
                    @endif
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_slot_name">Slot Name</label>
                    <input id="page_layout_slot_slot_name" name="slot_name" class="wb-input" type="text" value="{{ old('slot_name', $pageLayoutSlot->slot_name) }}" maxlength="100" @readonly($isProtectedSystemSlot) required>
                    <div class="wb-text-sm wb-text-muted">Stable render key such as <code>header</code>, <code>main</code>, <code>sidebar</code>, or <code>footer</code>.</div>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_label">Label</label>
                    <input id="page_layout_slot_label" name="label" class="wb-input" type="text" value="{{ old('label', $pageLayoutSlot->label) }}" maxlength="255">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="page_layout_slot_description">Description</label>
                <textarea id="page_layout_slot_description" name="description" class="wb-textarea" rows="3">{{ old('description', $pageLayoutSlot->description) }}</textarea>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Wrapper Markup</strong></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-3">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_html_element">HTML Element</label>
                    <select id="page_layout_slot_html_element" name="html_element" class="wb-select">
                        @foreach (\WebBlocks\Cms\Support\Pages\LayoutMarkup::allowedElements() as $element)
                            <option value="{{ $element }}" @selected(old('html_element', $pageLayoutSlot->html_element ?: 'div') === $element)>{{ $element }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_html_id">HTML ID</label>
                    <input id="page_layout_slot_html_id" name="html_id" class="wb-input" type="text" value="{{ old('html_id', $pageLayoutSlot->html_id) }}" maxlength="255">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_html_classes">CSS Classes</label>
                    <input id="page_layout_slot_html_classes" name="html_classes" class="wb-input" type="text" value="{{ old('html_classes', $pageLayoutSlot->html_classes) }}" maxlength="1000">
                    <div class="wb-text-sm wb-text-muted wb-stack wb-gap-1">
                        <div>CSS classes must be separated with spaces.</div>
                        <div>Use classes such as <code>wb-sidebar</code>, <code>wb-dashboard-main</code>, or <code>wb-stack</code> when they fit the selected layout.</div>
                        <div>For sticky public headers, prefer a Navbar block so the shipped <code>wb-navbar</code> primitive owns positioning without site-specific CSS.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <details class="wb-card wb-card-muted" @if ($showAdvancedTrustedHtml) open @endif>
        <summary class="wb-card-header">
            <span class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-stack wb-gap-1">
                    <strong>Advanced Trusted Layout HTML</strong>
                    <span class="wb-text-sm wb-text-muted">Contains the before, start, end, and after slot HTML fields for trusted wrapper-adjacent structure.</span>
                </span>
                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
            </span>
        </summary>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-text-sm wb-text-muted wb-stack wb-gap-1">
                <div>Before Slot HTML renders before the slot wrapper.</div>
                <div>Slot Start HTML renders inside the wrapper before blocks.</div>
                <div>Slot End HTML renders inside the wrapper after blocks.</div>
                <div>After Slot HTML renders after the slot wrapper.</div>
                <div>Scripts and unsafe JavaScript are not allowed.</div>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_before_html">Before Slot HTML</label>
                    <textarea id="page_layout_slot_before_html" name="before_html" class="wb-textarea wb-font-mono" rows="6">{{ old('before_html', $pageLayoutSlot->before_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_start_html">Slot Start HTML</label>
                    <textarea id="page_layout_slot_start_html" name="start_html" class="wb-textarea wb-font-mono" rows="6">{{ old('start_html', $pageLayoutSlot->start_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_end_html">Slot End HTML</label>
                    <textarea id="page_layout_slot_end_html" name="end_html" class="wb-textarea wb-font-mono" rows="6">{{ old('end_html', $pageLayoutSlot->end_html) }}</textarea>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_after_html">After Slot HTML</label>
                    <textarea id="page_layout_slot_after_html" name="after_html" class="wb-textarea wb-font-mono" rows="6">{{ old('after_html', $pageLayoutSlot->after_html) }}</textarea>
                </div>
            </div>
        </div>
    </details>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Status / Ordering</strong></div>
        <div class="wb-card-body">
            <div class="wb-grid wb-grid-3">
                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_is_required">Required</label>
                    <select id="page_layout_slot_is_required" name="is_required" class="wb-select">
                        <option value="0" @selected(! (bool) old('is_required', $pageLayoutSlot->is_required))>Optional</option>
                        <option value="1" @selected((bool) old('is_required', $pageLayoutSlot->is_required))>Required</option>
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_is_active">Active</label>
                    <select id="page_layout_slot_is_active" name="is_active" class="wb-select">
                        <option value="1" @selected((bool) old('is_active', $pageLayoutSlot->is_active ?? true))>Active</option>
                        <option value="0" @selected(! (bool) old('is_active', $pageLayoutSlot->is_active ?? true))>Inactive</option>
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="page_layout_slot_sort_order">Sort Order</label>
                    <input id="page_layout_slot_sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $pageLayoutSlot->sort_order ?? 0) }}" required>
                </div>
            </div>
        </div>
    </div>
</div>
