@extends('layouts.admin', ['title' => 'Package Admin Runtime Status', 'heading' => 'Package Admin Runtime Status'])

@section('content')
  @include('admin.partials.page-header', [
      'title' => 'Package Admin Runtime Status',
      'description' => 'Guarded package-owned admin runtime slice for the package transition. Root admin routes and views remain authoritative outside this reserved path.',
  ])

  <div class="wb-stack wb-stack-4" data-webblocks-cms-package-admin-slice="status">
    <div class="wb-card">
      <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <strong>Package Admin Slice</strong>
        <span class="wb-status-pill wb-status-info">Guarded</span>
      </div>

      <div class="wb-card-body">
        <div class="wb-stack wb-gap-3">
          <p class="wb-m-0">This screen is package-owned and available only when the dedicated package admin route guard is enabled.</p>
          <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <span>Named route</span>
            <code>{{ $packageRouteName }}</code>
          </div>
          <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <span>Reserved path</span>
            <code>{{ $packageRoutePath }}</code>
          </div>
          <div class="wb-text-sm wb-text-muted">Root admin pages such as Pages, Media, Blocks, Sites, Updates, Backups, and Export / Import remain root-owned.</div>
        </div>
      </div>
    </div>
  </div>
@endsection
