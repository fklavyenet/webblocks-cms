<x-guest-layout title="Install Core" meta-description="Run the core WebBlocks CMS setup steps.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Install core CMS"
        description="Generate the app key if needed, run migrations, seed the CMS foundation, and prepare storage."
    >
        <x-auth-feedback />

        @include('install.partials.steps', ['steps' => $steps])

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Core setup actions</strong>
                <span class="wb-status-pill {{ $coreInstalled ? 'wb-status-active' : 'wb-status-pending' }}">
                    {{ $coreInstalled ? 'Ready' : 'Pending' }}
                </span>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($coreResults)
                    @foreach ($coreResults as $step)
                        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-border-b wb-pb-2">
                            <div class="wb-stack wb-gap-1">
                                <strong>{{ $step['label'] }}</strong>
                                <div class="wb-text-sm wb-text-muted">{{ $step['message'] }}</div>
                            </div>
                            <span class="wb-status-pill {{ $step['badge_class'] }}">{{ $step['status'] === 'pass' ? 'Pass' : 'Failed' }}</span>
                        </div>
                    @endforeach
                @else
                    <div class="wb-text-sm wb-text-muted">No install actions have run yet.</div>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('install.core.store') }}" class="wb-row wb-row-gap-2">
            @csrf
            <a href="{{ route('install.database') }}" class="wb-btn wb-btn-secondary">Back</a>
            <x-primary-button>Run core install</x-primary-button>
        </form>
    </x-auth-shell>
</x-guest-layout>
