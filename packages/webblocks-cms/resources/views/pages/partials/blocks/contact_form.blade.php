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
            <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">
            <input type="hidden" name="submitted_at" value="{{ now()->timestamp }}">

            <div class="wb-public-contact-honeypot" aria-hidden="true">
                <label for="contact-website-{{ $block->id }}">Website</label>
                <input id="contact-website-{{ $block->id }}" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="contact-name-{{ $block->id }}" class="wb-label">Name</label>
                    <input id="contact-name-{{ $block->id }}" name="name" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('name') : '' }}" required>
                    @if ((int) old('block_id') === $block->id)
                        @foreach ($errors->get('name') as $message)
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @endforeach
                    @endif
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="contact-email-{{ $block->id }}" class="wb-label">Email</label>
                    <input id="contact-email-{{ $block->id }}" name="email" type="email" class="wb-input" value="{{ old('block_id') == $block->id ? old('email') : '' }}" required>
                    @if ((int) old('block_id') === $block->id)
                        @foreach ($errors->get('email') as $message)
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="contact-subject-{{ $block->id }}" class="wb-label">Subject</label>
                <input id="contact-subject-{{ $block->id }}" name="subject" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('subject') : '' }}">
                @if ((int) old('block_id') === $block->id)
                    @foreach ($errors->get('subject') as $message)
                        <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                    @endforeach
                @endif
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="contact-message-{{ $block->id }}" class="wb-label">Message</label>
                <textarea id="contact-message-{{ $block->id }}" name="message" class="wb-textarea" rows="7" required>{{ old('block_id') == $block->id ? old('message') : '' }}</textarea>
                @if ((int) old('block_id') === $block->id)
                    @foreach ($errors->get('message') as $message)
                        <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                    @endforeach
                @endif
            </div>

            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-text-sm wb-text-muted">Your message is stored first, then email notification is attempted.</span>
                <button type="submit" class="wb-btn wb-btn-primary">{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</section>
