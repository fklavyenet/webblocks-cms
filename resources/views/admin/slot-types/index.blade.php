@extends('layouts.admin', ['title' => 'Slot Types', 'heading' => 'Slot Types'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Slot Types',
        'description' => 'Slot types are product-owned core catalog records for page structure and block placement.',
        'count' => $slotTypes->total(),
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Axis</th>
                            <th>Description</th>
                            <th>Blocks</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>System</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($slotTypes as $slotType)
                            <tr>
                                <td class="wb-nowrap"><strong>{{ $slotType->name }}</strong></td>
                                <td class="wb-nowrap"><code>{{ $slotType->slug }}</code></td>
                                <td class="wb-nowrap">{{ $slotType->axis ?: '-' }}</td>
                                <td class="wb-text-muted">{{ $slotType->description ?: '-' }}</td>
                                <td class="wb-nowrap">{{ $slotType->blocks_count }}</td>
                                <td class="wb-nowrap">{{ $slotType->sort_order }}</td>
                                <td><span class="wb-status-pill {{ $slotType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $slotType->status }}</span></td>
                                <td><span class="wb-status-pill {{ $slotType->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $slotType->is_system ? 'system' : 'user' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wb-card-footer wb-text-sm wb-text-muted">
            Header, Main, Sidebar, and Footer are fixed system slots managed by the CMS core. Pages choose which of these to use and in what order.
        </div>

        @include('admin.partials.pagination', ['paginator' => $slotTypes])
    </div>
@endsection
