@extends('layouts.public', ['title' => 'Package Public Runtime Status', 'metaDescription' => 'Guarded package public runtime status page'])

@section('content')
  <section class="wb-section wb-section-muted" data-webblocks-cms-package-public-slice="status">
    <div class="wb-container wb-container-lg">
      <div class="wb-grid wb-grid-2 wb-gap-6">
        <div class="wb-stack wb-gap-4">
          <div class="wb-cluster wb-cluster-2 wb-items-center">
            <span class="wb-badge wb-badge-primary">Package slice</span>
            <span class="wb-text-sm wb-text-muted">Guarded public runtime pilot</span>
          </div>

          <div class="wb-stack wb-gap-3">
            <h1>Package Public Runtime Status</h1>
            <p class="wb-text-lg wb-m-0">This is the first guarded package-owned public route and view slice.</p>
            <p class="wb-text-muted wb-m-0">It stays isolated on a reserved path so root public page rendering, multisite routing, search, and block rendering remain authoritative.</p>
          </div>
        </div>

        <div class="wb-card wb-card-muted">
          <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Runtime Boundary</strong>
            <span class="wb-status-pill wb-status-info">Guarded</span>
          </div>

          <div class="wb-card-body">
            <div class="wb-stack wb-gap-3">
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span>Named route</span>
                <code>{{ $packageRouteName }}</code>
              </div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span>Reserved path</span>
                <code>{{ $packageRoutePath }}</code>
              </div>
              <div class="wb-text-sm wb-text-muted">Normal CMS public routes and shells remain root-owned outside this reserved path.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
