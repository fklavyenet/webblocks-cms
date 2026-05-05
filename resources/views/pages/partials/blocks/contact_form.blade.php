@php
    $isSubmittedBlock = (int) session('contact_form_success_block_id') === $block->id;
    $hasTargetedErrors = $errors->any() && (int) old('block_id') === $block->id;
    $submitLabel = $block->submit_label ?? 'Send message';
    $successMessage = $block->success_message ?? config('contact.success_message');
@endphp

<section class="wb-card wb-public-contact-form-card" id="contact-form-{{ $block->id }}">
    <div class="wb-card-body wb-stack wb-gap-4">
        @if ($block->title)
            <div class="wb-stack wb-gap-2">
                <h2>{{ $block->title }}</h2>

                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
            </div>
        @elseif ($block->content)
            <p>{{ $block->content }}</p>
        @endif

        @if ($isSubmittedBlock)
            <div class="wb-alert wb-alert-success">
                <div>
                    <div class="wb-alert-title">Message sent</div>
                    <div>{{ $successMessage }}</div>
                </div>
            </div>
        @endif

        @if ($hasTargetedErrors)
            <div class="wb-alert wb-alert-danger">
                <div>
                    <div class="wb-alert-title">Please review the form</div>
                    <div>{{ $errors->first() }}</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('contact-messages.store') }}" class="wb-stack wb-gap-3">
            @csrf
            <input type="hidden" name="block_id" value="{{ $block->id }}">
            <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
            <input type="hidden" name="source_url" value="{{ request()->fullUrl() }}">
            <input type="hidden" name="submitted_at" value="{{ now()->timestamp }}">

            <div class="wb-public-contact-honeypot" aria-hidden="true">
                <label for="contact-website-{{ $block->id }}">Website</label>
                <input id="contact-website-{{ $block->id }}" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <x-input-label for="contact-name-{{ $block->id }}" value="Name" />
                    <x-text-input id="contact-name-{{ $block->id }}" name="name" type="text" :value="old('block_id') == $block->id ? old('name') : ''" required />
                    @if ((int) old('block_id') === $block->id)
                        <x-input-error :messages="$errors->get('name')" />
                    @endif
                </div>

                <div class="wb-stack wb-gap-1">
                    <x-input-label for="contact-email-{{ $block->id }}" value="Email" />
                    <x-text-input id="contact-email-{{ $block->id }}" name="email" type="email" :value="old('block_id') == $block->id ? old('email') : ''" required />
                    @if ((int) old('block_id') === $block->id)
                        <x-input-error :messages="$errors->get('email')" />
                    @endif
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <x-input-label for="contact-subject-{{ $block->id }}" value="Subject" />
                <x-text-input id="contact-subject-{{ $block->id }}" name="subject" type="text" :value="old('block_id') == $block->id ? old('subject') : ''" />
                @if ((int) old('block_id') === $block->id)
                    <x-input-error :messages="$errors->get('subject')" />
                @endif
            </div>

            <div class="wb-stack wb-gap-1">
                <x-input-label for="contact-message-{{ $block->id }}" value="Message" />
                <textarea id="contact-message-{{ $block->id }}" name="message" class="wb-textarea" rows="7" required>{{ old('block_id') == $block->id ? old('message') : '' }}</textarea>
                @if ((int) old('block_id') === $block->id)
                    <x-input-error :messages="$errors->get('message')" />
                @endif
            </div>

            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-text-sm wb-text-muted">Your message is stored first, then email notification is attempted.</span>
                <x-primary-button>{{ $submitLabel }}</x-primary-button>
            </div>
        </form>
    </div>
</section>
