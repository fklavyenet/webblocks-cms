@extends('webblocks-cms::layouts.public', ['title' => 'Package Public Runtime Status', 'metaDescription' => 'Guarded package public runtime status page'])

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
            <p class="wb-text-lg wb-m-0">This guarded status page still lives on a reserved path, but the main public layout, page shell, and search views now render from the package namespace too.</p>
            <p class="wb-text-muted wb-m-0">Root compatibility wrappers remain in place while active runtime asset URLs still use root public/cms compatibility paths and install, auth, migration, and update authority stay in the root application.</p>
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
              <div class="wb-text-sm wb-text-muted">Normal CMS public URLs are package-routed, and the active public page or search shells are now package-owned through the `webblocks-cms::` namespace.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
