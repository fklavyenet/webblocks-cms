@extends('layouts.admin', ['title' => 'Profile', 'heading' => 'Profile'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Profile',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Profile Information</strong></div>
            <div class="wb-card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Password</strong></div>
            <div class="wb-card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-accent">
        <div class="wb-card-header"><strong>Danger Zone</strong></div>
        <div class="wb-card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
@endsection
