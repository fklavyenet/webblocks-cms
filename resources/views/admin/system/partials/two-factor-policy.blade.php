<form id="two-factor-policy" method="POST" action="{{ route('webblocks.two-factor.policy') }}" class="wb-card wb-card-muted">
    @csrf @method('PUT')
    <div class="wb-card-header"><h2 class="wb-card-title">{{ __('webblocks-cms::two-factor.policy') }}</h2></div>
    <div class="wb-card-body wb-stack-4">
        <p>{{ __('webblocks-cms::two-factor.policy_help') }}</p>
        <input type="hidden" name="require_super_admin" value="0">
        <label class="wb-check" for="two-factor-root"><input id="two-factor-root" type="checkbox" name="require_super_admin" value="1" @checked(old('require_super_admin', $twoFactorPolicy['require_super_admin']))><span>{{ __('webblocks-cms::two-factor.super_admin') }}</span></label>
        <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::two-factor.policy_removal_help') }}</p>
        @if ($twoFactorEnabled)
            <a class="wb-action-link" href="{{ route('webblocks.two-factor.show') }}">{{ __('webblocks-cms::two-factor.manage') }}</a>
        @endif
        <section role="group" aria-labelledby="two-factor-roles-title" class="wb-stack-2">
            <h3 class="wb-text-base" id="two-factor-roles-title">{{ __('webblocks-cms::two-factor.roles') }}</h3>
            @foreach ($twoFactorRoles as $twoFactorRole)
                <label class="wb-check" for="two-factor-role-{{ $twoFactorRole->id }}"><input id="two-factor-role-{{ $twoFactorRole->id }}" type="checkbox" name="role_ids[]" value="{{ $twoFactorRole->id }}" @checked(in_array($twoFactorRole->id, old('role_ids', $twoFactorPolicy['role_ids'])))><span>{{ $twoFactorRole->name }}</span></label>
            @endforeach
        </section>
        <section role="group" aria-labelledby="two-factor-policy-confirm-title" class="wb-stack-4">
            <h3 class="wb-text-base" id="two-factor-policy-confirm-title">{{ __('webblocks-cms::two-factor.policy_confirm') }}</h3>
            <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::two-factor.policy_confirm_help') }}</p>
            @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'two-factor-policy', 'password' => true, 'factor' => $twoFactorEnabled, 'inlineSend' => true])
        </section>
    </div>
    <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::two-factor.save_policy') }}</button></div>
</form>
@if ($twoFactorEnabled)
    @push('scripts')
        @php($policyScript = public_path('cms/js/admin/two-factor-policy.js'))
        @if (is_file($policyScript))
            <script src="{{ asset('cms/js/admin/two-factor-policy.js') }}?v={{ filemtime($policyScript) }}" defer></script>
        @endif
    @endpush
@endif
