@extends('webblocks-cms::layouts.guest', [
    'title' => 'Install WebBlocks CMS',
    'metaDescription' => 'Install WebBlocks CMS before accessing the admin and public runtime.',
])

@section('content')
    <div class="wb-auth-shell wb-auth-shell-centered">
        <div class="wb-auth-shell-panel">
            <div class="wb-stack wb-stack-4">
                <div class="wb-stack wb-gap-1">
                    <h1 class="wb-text-2xl wb-font-semibold">Install WebBlocks CMS</h1>
                    <p class="wb-text-muted">Run the package installer before opening the admin or public site.</p>
                </div>

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body">
                        <pre><code>php artisan webblocks:install</code></pre>
                    </div>
                </div>

                <p class="wb-text-sm wb-text-muted">The installer runs migrations, installs package assets, seeds the CMS catalogs, and creates the first super admin.</p>
            </div>
        </div>
    </div>
@endsection
