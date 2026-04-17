@extends('layouts.admin', ['title' => 'Edit Block Type', 'heading' => 'Edit Block Type'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Block Type',
        'description' => 'Update the selected block type record.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.block-types.update', $blockType) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.block-types._form')
            </form>
        </div>
    </div>
@endsection
