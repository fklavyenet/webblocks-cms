@if ($twoFactorEnabled)
    <div class="wb-card wb-mb-4"><div class="wb-card-body">
        @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'action', 'sendTab' => 'policy'])
    </div></div>
@endif
<form method="POST" action="{{ route('webblocks.two-factor.policy') }}" class="wb-card wb-card-muted wb-mb-4">
    @csrf @method('PUT')
    <div class="wb-card-header"><h2 class="wb-card-title">{{ __('webblocks-cms::two-factor.policy') }}</h2></div>
    <div class="wb-card-body wb-stack-4">
        <p>{{ __('webblocks-cms::two-factor.policy_help') }}</p>
        <input type="hidden" name="require_super_admin" value="0">
        <label class="wb-check" for="two-factor-root"><input id="two-factor-root" type="checkbox" name="require_super_admin" value="1" @checked(old('require_super_admin', $twoFactorPolicy['require_super_admin']))><span>{{ __('webblocks-cms::two-factor.super_admin') }}</span></label>
        <section role="group" aria-labelledby="two-factor-roles-title" class="wb-stack-2">
            <h3 class="wb-text-base" id="two-factor-roles-title">{{ __('webblocks-cms::two-factor.roles') }}</h3>
            @foreach ($twoFactorRoles as $twoFactorRole)
                <label class="wb-check" for="two-factor-role-{{ $twoFactorRole->id }}"><input id="two-factor-role-{{ $twoFactorRole->id }}" type="checkbox" name="role_ids[]" value="{{ $twoFactorRole->id }}" @checked(in_array($twoFactorRole->id, old('role_ids', $twoFactorPolicy['role_ids'])))><span>{{ $twoFactorRole->name }}</span></label>
            @endforeach
        </section>
        @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'two-factor-policy', 'password' => true, 'factor' => $twoFactorEnabled])
    </div>
    <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::two-factor.save_policy') }}</button></div>
</form>
