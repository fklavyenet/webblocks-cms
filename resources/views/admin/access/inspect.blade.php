@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::acl.inspect'), 'heading' => __('webblocks-cms::acl.inspect')])
@section('content')
    <div class="wb-stack wb-gap-4">
        <h1>{{ __('webblocks-cms::acl.inspect') }}</h1>
        <form method="GET" class="wb-stack wb-gap-2">
            <label for="acl_user">{{ __('webblocks-cms::acl.user') }}</label><select class="wb-select" id="acl_user" name="user_id" required><option value=""></option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected($selectedUser?->id === $user->id)>{{ $user->name }}</option>@endforeach</select>
            <label for="acl_site">{{ __('webblocks-cms::acl.site') }}</label><select class="wb-select" id="acl_site" name="site_id" required><option value=""></option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected($selectedSite?->id === $site->id)>{{ $site->name }}</option>@endforeach</select>
            <button class="wb-btn wb-btn-primary">{{ __('webblocks-cms::acl.show') }}</button>
        </form>
        <p>{{ __('webblocks-cms::acl.inspect_help') }}</p>
        @if ($effective)
            <p>{{ $assignedRole?->name ?? ($selectedUser->isSuperAdmin() ? __('webblocks-cms::acl.super_admin') : __('webblocks-cms::acl.none')) }}</p>
            <div class="wb-table-wrap"><table class="wb-table"><tbody>@foreach ($effective as $key => $allowed)<tr><td>{{ $permissions[$key]['label'] }}</td><td>{{ $allowed ? __('webblocks-cms::acl.allowed') : __('webblocks-cms::acl.denied') }}</td></tr>@endforeach</tbody></table></div>
        @endif
    </div>
@endsection
