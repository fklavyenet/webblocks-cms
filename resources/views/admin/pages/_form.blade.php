@php
	$formSiteId = old('site_id', $page->site_id ?: ($selectedSiteId ?? $sites->first()?->id));
	$selectedSite = $sites->firstWhere('id', $formSiteId);
	$canEditContent = $canEditContent ?? true;
	$submittedSlots = old('slots');
	$pageSlots = $submittedSlots
		? collect($submittedSlots)->map(function ($slot) use ($slotTypes, $page) {
			$pageSlot = new \App\Models\PageSlot($slot);
			$pageSlot->page_id = $page->id;
			$pageSlot->slot_type_id = $slot['slot_type_id'] ?? null;
			$pageSlot->setRelation('slotType', $slotTypes->firstWhere('id', $pageSlot->slot_type_id));

			return $pageSlot;
		})
		: ($page->exists ? $page->slots()->with('slotType')->orderBy('sort_order')->get() : collect());
	$availableSlotTypes = $slotTypes->reject(fn ($slotType) => $pageSlots->pluck('slot_type_id')->contains($slotType->id));
	$slotBlockPreviews = $slotBlockPreviews ?? collect();
@endphp

<div class="wb-stack wb-gap-4">
	<div class="wb-grid wb-grid-2">
		<div class="wb-stack-4 wb-gap-1">
			<div class="wb-stack-2 wb-field">
				<label for="site_id">Site</label>
				<select id="site_id" name="site_id" class="wb-select" required>
					@foreach ($sites as $site)
						<option value="{{ $site->id }}" @selected((string) $formSiteId === (string) $site->id)>{{ $site->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="wb-stack-2 wb-field">
				<label for="title">Title</label>
				<input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $page->title) }}" required>
			</div>
			<div class="wb-stack-2 wb-field">
				<label for="slug">Slug</label>
				<input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $page->slug) }}">
			</div>
		</div>
		<div class="wb-stack wb-gap-2">
			<div class="wb-stack-2 wb-field">
				<label>Site Context</label>
				<input class="wb-input" type="text" value="{{ ($selectedSite?->name ?? 'Site') }}{{ $selectedSite?->domain ? ' | '.$selectedSite->domain : '' }}" disabled>
			</div>
			<div class="wb-stack-2 wb-field">
				<label for="public_shell">Public Shell</label>
				<select id="public_shell" name="public_shell" class="wb-select">
					<option value="default" @selected(old('public_shell', $page->publicShellPreset()) === 'default')>Default</option>
					<option value="dashboard" @selected(old('public_shell', $page->publicShellPreset()) === 'dashboard')>Dashboard</option>
				</select>
			</div>
			<div class="wb-stack-2 wb-field">
				<label>Locale</label>
				<input class="wb-input" type="text" value="English (default)" disabled>
			</div>
			@if ($page->exists)
				<div class="wb-stack-2 wb-field">
					<label>Workflow</label>
					<input class="wb-input" type="text" value="{{ $page->workflowLabel() }}" disabled>
				</div>
			@endif
		</div>
	</div>

	@if (! $canEditContent)
		<div class="wb-alert wb-alert-info">
			Content editing is locked while this page is {{ strtolower($page->workflowLabel()) }}. Move it back to draft to continue editing.
		</div>
	@endif

	<div class="wb-card wb-card-accent" data-wb-slot-builder>
		<div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
			<strong>Slots</strong>

			<div class="wb-dropdown wb-dropdown-end">
				<button class="wb-btn wb-btn-primary" type="button" data-wb-toggle="dropdown" data-wb-target="#page-slot-menu" aria-expanded="false" @disabled(! $canEditContent || $availableSlotTypes->isEmpty())>Add Slot</button>
				<div class="wb-dropdown-menu" id="page-slot-menu">
					@forelse ($availableSlotTypes as $slotType)
					@php
					$slotPayload = json_encode([
					'id' => $slotType->id,
					'name' => $slotType->name,
					'slug' => $slotType->slug,
					'blocks_url' => $page->exists ? route('admin.pages.slots.blocks', [$page, 'slot' => '__SLOT_ID__']) : null,
					], JSON_HEX_APOS | JSON_HEX_QUOT);
					@endphp
					<button type="button" class="wb-dropdown-item" data-wb-slot-add data-slot-type='{{ $slotPayload }}'>{{ $slotType->name }}</button>
					@empty
					<div class="wb-dropdown-label">All slot types already added</div>
					@endforelse
				</div>
			</div>
		</div>

		<div class="wb-card-body">
			@if ($pageSlots->isEmpty())
			<div class="wb-empty" data-wb-slot-empty>
				<div class="wb-empty-title">No slots yet</div>
				<div class="wb-empty-text">Add Header, Main, Sidebar, or Footer to start defining the page structure.</div>
			</div>
			@endif

			<div class="wb-table-wrap" @if($pageSlots->isEmpty()) hidden @endif data-wb-slot-table-wrap>
				<table class="wb-table wb-table-striped wb-table-hover">
					<thead>
						<tr>
							<th>Name</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody data-wb-slot-list>
						@foreach ($pageSlots as $index => $pageSlot)
						@php
						$preview = $slotBlockPreviews->get($pageSlot->id, [
							'items' => collect(),
							'remaining' => 0,
							'is_empty' => true,
						]);
						@endphp
						<tr data-wb-slot-item>
							<td>
								<div class="wb-stack wb-gap-1">
									<strong>{{ $pageSlot->slotType?->name ?? 'Slot' }}</strong>
									@if ($preview['is_empty'])
										<span class="wb-text-sm wb-text-muted">No blocks yet</span>
									@else
										<div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
											@foreach ($preview['items'] as $item)
												<span class="wb-status-pill wb-status-info">{{ $item }}</span>
											@endforeach
											@if ($preview['remaining'] > 0)
												<span class="wb-text-sm wb-text-muted">+{{ $preview['remaining'] }} more</span>
											@endif
										</div>
									@endif
								</div>
								<input type="hidden" name="slots[{{ $index }}][id]" value="{{ $pageSlot->id }}" data-wb-slot-id>
								<input type="hidden" name="slots[{{ $index }}][slot_type_id]" value="{{ $pageSlot->slot_type_id }}" data-wb-slot-type-id>
								<input type="hidden" name="slots[{{ $index }}][sort_order]" value="{{ $index }}" data-wb-slot-sort>
								<input type="hidden" name="slots[{{ $index }}][_delete]" value="0" data-wb-slot-delete>
								<input type="hidden" value="{{ $pageSlot->slotType?->slug ?? 'main' }}" data-wb-slot-slug>
								<input type="hidden" value="{{ $pageSlot->slotType?->name ?? 'Slot' }}" data-wb-slot-name>
							</td>
							<td>
								<div class="wb-action-group">
									@if ($canEditContent && $page->exists && $pageSlot->id)
									<a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-action-btn wb-action-btn-view" title="Edit slot blocks" aria-label="Edit slot blocks"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></a>
									@else
									<span class="wb-action-btn" aria-disabled="true" title="Workflow locks slot editing for this page"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></span>
									@endif
									@if ($canEditContent)
										<button type="button" class="wb-action-btn" data-wb-slot-move="up" title="Move slot up" aria-label="Move slot up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
										<button type="button" class="wb-action-btn" data-wb-slot-move="down" title="Move slot down" aria-label="Move slot down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
										<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-slot-remove title="Delete slot" aria-label="Delete slot"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
									@endif
								</div>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<x-admin.form-actions :cancel-url="route('admin.pages.index', ['site' => $formSiteId])" :show-submit="$canEditContent" :submit-label="$page->exists ? 'Save Changes' : 'Save Draft'" />
</div>
