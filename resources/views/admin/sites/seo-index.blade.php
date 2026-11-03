@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::jobs.seo')])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => __('webblocks-cms::jobs.seo')])
    <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th scope="col">{{ __('webblocks-cms::admin.navigation.sites') }}</th><th scope="col">{{ __('webblocks-cms::jobs.seo') }}</th></tr></thead><tbody>
    @foreach ($jobSites as $jobSite)<tr><th scope="row">{{ $jobSite->name }}</th><td><a class="wb-btn wb-btn-secondary" href="{{ route('admin.sites.seo-audits.index', $jobSite) }}">{{ __('webblocks-cms::seo.audit_title') }}</a></td></tr>@endforeach
    </tbody></table></div>
@endsection
