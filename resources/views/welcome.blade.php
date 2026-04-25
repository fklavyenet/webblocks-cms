@extends('layouts.public', [
    'title' => config('app.name'),
    'metaDescription' => config('app.slogan'),
])

@section('content')
    <section class="wb-section wb-section-muted">
        <div class="wb-container wb-container-lg">
            <div class="wb-grid wb-grid-2 wb-gap-6">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-cluster wb-cluster-2 wb-items-center">
                        <span class="wb-badge wb-badge-primary">Fresh install</span>
                        <span class="wb-text-sm wb-text-muted">WebBlocks CMS v{{ config('app.version') }}</span>
                    </div>

                    <div class="wb-stack wb-gap-3">
                        <h1>{{ config('app.name') }}</h1>
                        <p class="wb-text-lg">A DB-first, block-based CMS for structured websites.</p>
                        <p class="wb-text-muted">Create pages from reusable blocks, manage multilingual sites, and publish with a clean editorial workflow.</p>
                    </div>

                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('login') }}" class="wb-btn wb-btn-primary">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="wb-btn wb-btn-secondary">Create account</a>
                        @endif
                    </div>
                </div>

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-items-center">
                        <strong>Built for structured publishing</strong>
                        <span class="wb-status-pill wb-status-info">Ready to use</span>
                    </div>
                    <div class="wb-card-body">
                        <div class="wb-stack wb-gap-3">
                            <p class="wb-text-muted wb-m-0">This install is ready for production-shaped content operations, not just a starter placeholder. Model pages in the database, assemble them from reusable blocks, and manage publishing from one admin workspace.</p>

                            <div class="wb-grid wb-grid-2 wb-gap-3">
                                <div class="wb-card">
                                    <div class="wb-card-body wb-stack wb-gap-2">
                                        <div class="wb-cluster wb-cluster-2 wb-items-center">
                                            <i class="wb-icon wb-icon-layers" aria-hidden="true"></i>
                                            <strong>Block-based pages</strong>
                                        </div>
                                        <p class="wb-text-sm wb-text-muted wb-m-0">Build pages from reusable blocks, slot regions, and shared content structures.</p>
                                    </div>
                                </div>

                                <div class="wb-card">
                                    <div class="wb-card-body wb-stack wb-gap-2">
                                        <div class="wb-cluster wb-cluster-2 wb-items-center">
                                            <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                            <strong>Multisite + multilingual</strong>
                                        </div>
                                        <p class="wb-text-sm wb-text-muted wb-m-0">Manage localized content and multiple site contexts without duplicating the whole system.</p>
                                    </div>
                                </div>

                                <div class="wb-card">
                                    <div class="wb-card-body wb-stack wb-gap-2">
                                        <div class="wb-cluster wb-cluster-2 wb-items-center">
                                            <i class="wb-icon wb-icon-file-text" aria-hidden="true"></i>
                                            <strong>Editorial workflow</strong>
                                        </div>
                                        <p class="wb-text-sm wb-text-muted wb-m-0">Move content from draft to review to published with a calmer, more accountable publishing flow.</p>
                                    </div>
                                </div>

                                <div class="wb-card">
                                    <div class="wb-card-body wb-stack wb-gap-2">
                                        <div class="wb-cluster wb-cluster-2 wb-items-center">
                                            <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                                            <strong>Revisions + recovery</strong>
                                        </div>
                                        <p class="wb-text-sm wb-text-muted wb-m-0">Track revisions, manage media and navigation, and recover confidently with backups and updates.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="wb-section">
        <div class="wb-container wb-container-lg">
            <div class="wb-grid wb-grid-2 wb-gap-6">
                <div class="wb-card">
                    <div class="wb-card-header">
                        <strong>Start with the admin panel</strong>
                    </div>
                    <div class="wb-card-body">
                        <div class="wb-stack wb-gap-3">
                            <p class="wb-m-0">Your install is ready for pages, media, navigation, multisite setup, editorial workflow, revisions, backups, and updates. Sign in to start shaping the first real content model.</p>
                            <div class="wb-cluster wb-cluster-2">
                                <a href="{{ route('login') }}" class="wb-btn wb-btn-primary">Open sign in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="wb-btn wb-btn-secondary">Create account</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header">
                        <strong>What this workspace includes</strong>
                    </div>
                    <div class="wb-card-body">
                        <div class="wb-stack wb-gap-3">
                            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                <span>Structured page records</span>
                                <span class="wb-status-pill wb-status-info">DB-first</span>
                            </div>
                            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                <span>Reusable content blocks</span>
                                <span class="wb-status-pill wb-status-info">Composable</span>
                            </div>
                            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                <span>Media and navigation management</span>
                                <span class="wb-status-pill wb-status-pending">Included</span>
                            </div>
                            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                <span>Backups and system updates</span>
                                <span class="wb-status-pill wb-status-success">Operational</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
