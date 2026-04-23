<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());
        Gate::define('access-system', fn (User $user) => $user->isSuperAdmin());
    }
}
