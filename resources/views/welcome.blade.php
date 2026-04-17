@extends('layouts.public', [
    'title' => config('app.name'),
    'metaDescription' => config('app.slogan'),
])

@section('content')
    <section class="wb-section">
        <div class="wb-container">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-4">
                    <span class="wb-badge wb-badge-primary">{{ config('app.name') }}</span>
                    <div class="wb-stack wb-gap-2">
                        <h1>{{ config('app.name') }}</h1>
                        <p>{{ config('app.slogan') }}</p>
                    </div>
                    <div class="wb-row wb-row-middle wb-row-gap-2">
                        <a href="{{ route('login') }}" class="wb-btn wb-btn-primary">Sign In</a>
                        <a href="{{ route('register') }}" class="wb-btn wb-btn-secondary">Create Account</a>
                    </div>
                </div>

                <div class="wb-card">
                    <div class="wb-card-body">
                        <div class="wb-stack wb-gap-3">
                            <h2>Included Features</h2>
                            <div class="wb-grid wb-grid-2">
                                <div class="wb-card"><div class="wb-card-body"><strong>Auth</strong><p>Sign in, registration, and password reset flows are ready.</p></div></div>
                                <div class="wb-card"><div class="wb-card-body"><strong>Admin</strong><p>The dashboard route and base admin shell are ready.</p></div></div>
                                <div class="wb-card"><div class="wb-card-body"><strong>Public</strong><p>The public homepage and shared layout render with the {{ config('app.name') }} brand.</p></div></div>
                                <div class="wb-card"><div class="wb-card-body"><strong>Data Model</strong><p>Page, layout, block, and navigation item migrations are included.</p></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="wb-section">
        <div class="wb-container">
            <div class="wb-card">
                <div class="wb-card-body">
                    <div class="wb-stack wb-gap-2">
                        <h2>Next Step</h2>
                        <p>You can now continue with the block editor, page rendering, and navigation management. The core system is already in place.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
