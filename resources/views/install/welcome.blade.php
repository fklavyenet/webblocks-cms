<x-guest-layout title="Install WebBlocks CMS" meta-description="Set up WebBlocks CMS through the browser-based install wizard.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Install WebBlocks CMS"
        :description="'Version ' . config('app.version') . ' setup for a fresh install.'"
    >
        <x-auth-feedback />

        @include('install.partials.steps', ['steps' => $steps])

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Environment readiness</strong>
                <span class="wb-status-pill {{ $canContinue ? 'wb-status-active' : 'wb-status-danger' }}">
                    {{ $canContinue ? 'Ready to continue' : 'Needs attention' }}
                </span>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                @foreach ($requirements as $check)
                    <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-border-b wb-pb-2">
                        <div class="wb-stack wb-gap-1">
                            <strong>{{ $check['label'] }}</strong>
                            <div class="wb-text-sm wb-text-muted">{{ $check['message'] }}</div>
                        </div>
                        <span class="wb-status-pill {{ $check['badge_class'] }}">{{ ucfirst($check['status']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <strong>What setup will do</strong>
                <div class="wb-text-sm wb-text-muted">Configure database access, run the core CMS install, create the first super admin, and lock the installer after completion.</div>
            </div>
        </div>

        <div class="wb-row wb-row-gap-2">
            <a href="{{ $canContinue ? $continueRoute : route('install.welcome') }}" class="wb-btn wb-btn-primary {{ $canContinue ? '' : 'is-disabled' }}" @if (! $canContinue) aria-disabled="true" @endif>Continue setup</a>
        </div>
    </x-auth-shell>
</x-guest-layout>
