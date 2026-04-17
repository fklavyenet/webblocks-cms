@php
    $pageTitle = 'Edit Page: '.$page->title;
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.index').'">Pages</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.$page->title.'</span></li></ol></nav>',
        'title' => $pageTitle,
        'description' => 'Manage page details and slot ordering from one compact screen.',
        'actions' => '<a href="'.$page->publicUrl().'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.pages._form')
            </form>
        </div>
    </div>
@endsection
