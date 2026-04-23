<x-guest-layout title="Install Complete" meta-description="WebBlocks CMS installation is complete.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Install complete"
        description="WebBlocks CMS is ready. Sign in to continue into the admin workspace."
    >
        @include('install.partials.steps', ['steps' => $steps])

        <div class="wb-card wb-card-accent">
            <div class="wb-card-body wb-stack wb-gap-2">
                <strong>Setup finished successfully</strong>
                <div class="wb-text-sm wb-text-muted">The installer is now locked for normal use. Continue to the CMS sign-in screen or open the public site.</div>
            </div>
        </div>

        <div class="wb-row wb-row-gap-2">
            <a href="{{ route('login') }}" class="wb-btn wb-btn-primary">Sign in</a>
            <a href="{{ route('home') }}" class="wb-btn wb-btn-secondary">Open site</a>
        </div>
    </x-auth-shell>
</x-guest-layout>
