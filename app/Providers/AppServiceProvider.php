<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.axcess');
        Paginator::defaultSimpleView('vendor.pagination.axcess');

        Gate::define('export-data', fn (User $user): bool => $user->isAdmin() || $user->isManager());
        Gate::define('manage-operations', fn (User $user): bool => $user->isAdmin() || $user->isManager());
        Gate::define('view-reports', fn (User $user): bool => $user->isAdmin() || $user->isManager());
        Gate::define('view-staff-directory', fn (User $user): bool => $user->isAdmin() || $user->isManager());
    }
}
