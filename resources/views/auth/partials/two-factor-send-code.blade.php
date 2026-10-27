<form method="POST" action="{{ route('webblocks.two-factor.send') }}" class="wb-stack-2">
    @csrf
    <input type="hidden" name="purpose" value="{{ $purpose }}">
    <input type="hidden" name="tab" value="{{ $sendTab }}">
    @if (isset($returnUser))<input type="hidden" name="target_user_id" value="{{ $returnUser }}">@endif
    <p class="wb-text-muted">{{ __('webblocks-cms::two-factor.send_help') }}</p>
    <div><button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.send_code') }}</button></div>
</form>
